<?php
/* Copyright (C) 2026       Luigi Grillo - doliFarm.com	<info@dolifarm.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       dolitracebioinspection_card.php
 * \ingroup    dolitrace
 * \brief      Card of an inspection of the organic control body: data, closure (freeze of the registers and read only
 *             copy), reopening with reason, copies of the registers
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}
/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
dol_include_once('/dolitrace/class/dolitracebioinspection.class.php');
dol_include_once('/dolitrace/class/dolitracebioregister.class.php');
dol_include_once('/dolitrace/lib/dolitrace_inspection.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_farm.lib.php');

$langs->loadLangs(array("dolitrace@dolitrace", "dolifarm@dolifarm", "other", "companies"));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');

$object = new DoliTraceBioInspection($db);
$hookmanager->initHooks(array($object->element.'card', 'globalcard'));
if (empty($action) && empty($id) && empty($ref)) {
	$action = 'view';
}
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

$permissiontoread = $user->hasRight('dolitrace', 'dolitracebioinspection', 'read');
$permissiontoadd = $user->hasRight('dolitrace', 'dolitracebioinspection', 'write');
$permissiontoreopen = $user->hasRight('dolitrace', 'dolitracebioinspection', 'reopen');
$isdraft = (empty($object->id) || (int) $object->status == DoliTraceBioInspection::STATUS_DRAFT);
$permissiontodelete = $permissiontoadd && $isdraft;
$permissionnote = $permissiontoadd;

if (!isModEnabled('dolitrace') || !$permissiontoread) {
	accessforbidden();
}
if (!empty($object->id)) {
	dolifarmCheckSocAccess($user, $object);
}
// A closed inspection is read only: only reopening and notes
if (!$isdraft && in_array($action, array('edit', 'update', 'delete', 'confirm_delete'))) {
	$action = 'view';
}

// Prefill of the creation: farm of the configuration, control body of the current dossier of the farm
if ($action == 'create' && !GETPOSTISSET('fk_soc')) {
	$singlesoc = dolifarmSingleFarmSocid();
	if ($singlesoc > 0) {
		$_GET['fk_soc'] = $singlesoc;
	}
}
if ($action == 'create' && GETPOSTINT('fk_soc') > 0 && !GETPOSTISSET('fk_soc_body')) {
	$sql = "SELECT fk_soc_bio_body FROM ".$db->prefix()."dolifarm_farmdossier WHERE fk_soc = ".GETPOSTINT('fk_soc')." AND fk_soc_bio_body > 0 ORDER BY rowid DESC";
	$resql = $db->query($sql);
	if ($resql && ($obj = $db->fetch_object($resql))) {
		$_GET['fk_soc_body'] = (int) $obj->fk_soc_bio_body;
	}
}


/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}
if (empty($reshook)) {
	$backurlforlist = dol_buildpath('/dolitrace/dolitracebioinspection_list.php', 1);
	if (empty($backtopage) || ($cancel && empty($id))) {
		if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
			$backtopage = $backurlforlist;
		} else {
			$backtopage = dol_buildpath('/dolitrace/dolitracebioinspection_card.php', 1).'?id='.((!empty($id) && $id > 0) ? $id : '__ID__');
		}
	}
	$triggermodname = 'DOLITRACE_BIOINSPECTION_MODIFY';
	// Organic control body chosen from the official list (doliFarm): its third party, created when needed
	if (in_array($action, array('add', 'update')) && $permissiontoadd && !$cancel && dol_include_once('/dolifarm/lib/dolifarm_biobody.lib.php')) {
		if (dolifarmBioBodyHandlePost($db, $user, 'fk_soc_body') < 0) {
			$action = ($action == 'add' ? 'create' : 'edit');
		}
	}
	include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';

	if ($action == 'confirm_freeze' && $confirm == 'yes' && $permissiontoadd && $isdraft && $object->id > 0) {
		if ($object->close($user, $langs) > 0) {
			setEventMessages($langs->trans('BioInspectionClosedMessage', dol_print_date($object->date_inspection, 'day')), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
			exit;
		}
		setEventMessages($object->error, $object->errors, 'errors');
		$action = '';
	}
	if ($action == 'confirm_unfreeze' && $confirm == 'yes' && $permissiontoreopen && !$isdraft) {
		if ($object->reopenWithReason($user, GETPOST('reason', 'restricthtml')) > 0) {
			setEventMessages($langs->trans('BioInspectionReopenedMessage'), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
			exit;
		}
		setEventMessages($object->error, $object->errors, 'errors');
		$action = 'unfreeze';
	}
}


/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);
$title = $langs->trans('BioInspection');
if ($action == 'create') {
	$title = $langs->trans('NewBioInspection');
}
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolitrace page-bioinspection-card');

if ($action == 'create') {
	if (!$permissiontoadd) {
		accessforbidden('NotEnoughPermissions', 0, 1);
	}
	print load_fiche_titre($title, '', $object->picto);
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="'.dol_escape_htmltag($backtopageforcancel).'">';
	}
	print dol_get_fiche_head(array(), '');
	print '<div class="opacitymedium marginbottomonly">'.$langs->trans('BioInspectionHelp').'</div>';
	print '<table class="border centpercent tableforfieldcreate">'."\n";
	if (dol_include_once('/dolifarm/lib/dolifarm_biobody.lib.php')) {
		dolifarmBioBodyPrepareField($object, 'fk_soc_body');
	}
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_add.tpl.php';
	print '</table>'."\n";
	print dol_get_fiche_end();
	print $form->buttonsSaveCancel("Create");
	print '</form>';
}

if (($id || $ref) && $action == 'edit') {
	print load_fiche_titre($langs->trans('BioInspection'), '', $object->picto);
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';
	print dol_get_fiche_head();
	print '<table class="border centpercent tableforfieldedit">'."\n";
	if (dol_include_once('/dolifarm/lib/dolifarm_biobody.lib.php')) {
		dolifarmBioBodyPrepareField($object, 'fk_soc_body');
	}
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_edit.tpl.php';
	print '</table>';
	print dol_get_fiche_end();
	print $form->buttonsSaveCancel();
	print '</form>';
}

if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
	$head = dolitracebioinspectionPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans('BioInspection'), -1, $object->picto);

	$formconfirm = '';
	if ($action == 'delete') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('Delete'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 0, 1);
	}
	if ($action == 'freeze') {
		$drafts = $object->countDraftsToFreeze();
		$text = $langs->trans('BioInspectionConfirmClose', dol_print_date($object->date_inspection, 'day'), implode(', ', $object->getSnapshotYears()));
		if (array_sum($drafts) > 0) {
			$text .= '<br><br><span class="warning">'.$langs->trans('BioInspectionDraftsWarning', $drafts['treatments'], $drafts['operations'], $drafts['harvests']).'</span>';
		}
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('BioInspectionClose'), $text, 'confirm_freeze', '', 0, 1, 260, 600);
	}
	if ($action == 'unfreeze') {
		$questions = array(array('type' => 'text', 'name' => 'reason', 'label' => $langs->trans('TreatmentReason'), 'value' => '', 'morecss' => 'minwidth400'));
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('BioInspectionReopen'), $langs->trans('BioInspectionConfirmReopen'), 'confirm_unfreeze', $questions, 0, 1, 250, 600);
	}
	print $formconfirm;

	$linkback = '<a href="'.dol_buildpath('/dolitrace/dolitracebioinspection_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', '<div class="refidno"></div>');

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">'."\n";
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_view.tpl.php';
	if (!$isdraft) {
		$closer = new User($db);
		$closer->fetch((int) $object->fk_user_close);
		print '<tr><td class="titlefield">'.$langs->trans('BioInspectionDateClose').'</td><td>'.dol_print_date($object->date_close, 'dayhour').' - '.$closer->getNomUrl(-1).'</td></tr>';
	}
	print '</table>';
	print '</div>';
	print '<div class="clearboth"></div>';

	if (!$isdraft) {
		print '<div class="info"><span class="fa fa-lock paddingright"></span>'.$langs->trans('BioInspectionFrozenInfo', dol_print_date($object->date_inspection, 'day')).'</div>';
	}
	print dol_get_fiche_end();

	// Buttons
	print '<div class="tabsAction">'."\n";
	$parameters = array();
	$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action);
	if (empty($reshook)) {
		if ($isdraft) {
			print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit&token='.newToken(), '', $permissiontoadd);
			print dolGetButtonAction($langs->trans('BioInspectionCloseHelp'), $langs->trans('BioInspectionClose'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=freeze&token='.newToken(), '', $permissiontoadd);
			print dolGetButtonAction('', $langs->trans('Delete'), 'delete', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken(), '', $permissiontodelete && $object->getClosureCount() == 0);
		} else {
			print dolGetButtonAction($langs->trans('BioInspectionReopenHelp'), $langs->trans('BioInspectionReopen'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=unfreeze&token='.newToken(), '', $permissiontoreopen);
		}
	}
	print '</div>'."\n";

	// Copies of the registers
	$copies = $object->getSnapshots();
	$tabs = DoliTraceBioRegister::getTabs();
	print load_fiche_titre($langs->trans('BioInspectionCopies'), '', 'fa-lock');
	print '<div class="opacitymedium marginbottomonly">'.$langs->trans('BioInspectionCopiesHelp').'</div>';
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('BioInspectionClosure').'</td><td>'.$langs->trans('Year').'</td><td>'.$langs->trans('BioRegister').'</td></tr>';
	krsort($copies);
	foreach ($copies as $closure => $years) {
		foreach ($years as $year => $tabhashes) {
			print '<tr class="oddeven"><td class="nowraponall">'.((int) $closure).'</td><td>'.((int) $year).'</td><td>';
			$links = array();
			foreach (array_keys($tabhashes) as $tab) {
				$url = dol_buildpath('/dolitrace/dolitrace_bioregister.php', 1).'?inspection='.$object->id.'&closure='.((int) $closure).'&year='.((int) $year).'&tab='.urlencode($tab);
				$links[] = '<a href="'.$url.'">'.dol_escape_htmltag($langs->trans($tabs[$tab] ?? $tab)).'</a>';
			}
			print implode(' &middot; ', $links);
			print '</td></tr>';
		}
	}
	if (!count($copies)) {
		print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans('BioInspectionNoCopy').'</span></td></tr>';
	}
	print '</table>';
	print '</div>';

	// Log of the closures and reopenings
	$log = $object->getLog();
	if (count($log)) {
		print load_fiche_titre($langs->trans('BioInspectionLog'), '', 'fa-history');
		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><td>'.$langs->trans('Date').'</td><td>'.$langs->trans('Action').'</td><td>'.$langs->trans('User').'</td><td>'.$langs->trans('TreatmentReason').'</td></tr>';
		$tmpuser = new User($db);
		foreach (array_reverse($log) as $row) {
			$tmpuser->fetch((int) $row->fk_user);
			print '<tr class="oddeven"><td class="nowraponall">'.dol_print_date($row->datec, 'dayhour').'</td>';
			print '<td>'.$langs->trans($row->action == 'CLOSE' ? 'BioInspectionLogClose' : 'BioInspectionLogReopen', (int) $row->closure).'</td>';
			print '<td>'.$tmpuser->getNomUrl(-1).'</td><td>'.dol_escape_htmltag((string) $row->reason).'</td></tr>';
		}
		print '</table>';
		print '</div>';
	}
}

llxFooter();
$db->close();
