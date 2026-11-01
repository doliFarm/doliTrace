<?php
/* Copyright (C) 2026      Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *    \file       htdocs/dolitrace/dolitracetreatment_import.php
 *    \ingroup    dolitrace
 *    \brief      Import of records of the register from CSV: check first, then creation of drafts
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
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
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}
/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/dolitrace/class/dolitracetreatmentimport.class.php');
dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_farm.lib.php');

$langs->loadLangs(array("dolitrace@dolitrace", "other"));

$action = GETPOST('action', 'aZ09');
$socid = GETPOSTISSET('socid') ? GETPOSTINT('socid') : dolifarmSingleFarmSocid();

if (!isModEnabled('dolitrace') || !$user->hasRight('dolitrace', 'dolitracetreatment', 'write')) {
	accessforbidden();
}
if ($socid > 0 && !dolifarmUserCanSeeSoc($user, $socid)) {
	accessforbidden();
}

// The file checked is kept in the temporary directory of the module until the import, one per user
$tempfile = $conf->dolitrace->dir_temp.'/treatment_import_'.((int) $user->id).'.csv';


/*
 * Actions
 */

if ($action == 'template') {
	$content = DoliTraceTreatmentImport::getTemplate();
	top_httphead('text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="registro_trattamenti_modello.csv"');
	header('Content-Length: '.strlen($content));
	print $content;
	exit;
}

$import = new DoliTraceTreatmentImport($db);
$checked = false;
if (in_array($action, array('check', 'import'))) {
	$error = 0;
	if ($socid <= 0) {
		$error++;
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('TreatmentFarm')), null, 'errors');
	}
	if (!$error && $action == 'check') {
		if (empty($_FILES['importfile']['tmp_name']) || !is_uploaded_file($_FILES['importfile']['tmp_name'])) {
			$error++;
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('File')), null, 'errors');
		} else {
			dol_mkdir($conf->dolitrace->dir_temp);
			if (dol_move_uploaded_file($_FILES['importfile']['tmp_name'], $tempfile, 1, 0, $_FILES['importfile']['error']) <= 0) {
				$error++;
				setEventMessages($langs->trans('ErrorFailedToSaveFile'), null, 'errors');
			}
		}
	}
	if (!$error && !dol_is_file($tempfile)) {
		$error++;
		setEventMessages($langs->trans('ErrorTreatmentImportNoFile'), null, 'errors');
	}
	if (!$error && $import->parse((string) file_get_contents($tempfile)) < 0) {
		$error++;
		setEventMessages($import->error, null, 'errors');
	}
	if (!$error) {
		$nb = $import->import($user, $socid, $action == 'check');
		if ($nb < 0) {
			setEventMessages($import->error, null, 'errors');
		} elseif ($action == 'import') {
			dol_delete_file($tempfile, 0, 0, 0, null, false, 0);
			setEventMessages($langs->trans('TreatmentImportDone', $nb), null, $nb ? 'mesgs' : 'warnings');
		} else {
			$checked = true;
		}
	}
}


/*
 * View
 */

$form = new Form($db);
$title = $langs->trans('TreatmentImport');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolitrace page-import');

print load_fiche_titre($title, '', 'fa-file-import');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" enctype="multipart/form-data">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="check">';
print '<span class="opacitymedium">'.$langs->trans('TreatmentImportHelp').'</span> ';
print '<a href="'.$_SERVER['PHP_SELF'].'?action=template&token='.newToken().'">'.img_picto('', 'download', 'class="pictofixedwidth"').$langs->trans('TreatmentImportTemplate').'</a><br><br>';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('TreatmentFarm').'</td><td>';
print $form->select_company($socid, 'socid', '', 1, 0, 0, array(), 0, 'minwidth300');
print '</td></tr>';
print '<tr><td class="fieldrequired">'.$langs->trans('File').'</td><td><input type="file" name="importfile" accept=".csv,text/csv"></td></tr>';
print '</table>';
print '<div class="center"><input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('TreatmentImportCheck')).'"></div>';
print '</form>';

// Result of the check or of the import, one line per record of the file
if (count($import->results)) {
	print '<br>';
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('TreatmentImportRecord').'</th><th>'.$langs->trans('Line').'</th><th>'.$langs->trans('Result').'</th><th>'.$langs->trans('TreatmentImportMessages').'</th></tr>';
	$nbok = 0;
	foreach ($import->results as $result) {
		$ok = empty($result['errors']);
		$nbok += ($ok ? 1 : 0);
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($result['record']).'</td>';
		print '<td>'.((int) $result['line']).'</td>';
		print '<td class="nowraponall">';
		if (!$ok) {
			print dolGetBadge($langs->trans('TreatmentImportRejected'), '', 'danger');
		} elseif ($checked) {
			print dolGetBadge($langs->trans('TreatmentImportReady'), '', 'info');
		} else {
			$object = new DoliTraceTreatment($db);
			$object->fetch($result['id']);
			print $object->getNomUrl(1);
		}
		print '</td>';
		print '<td>';
		foreach ($result['errors'] as $message) {
			print '<div class="error">'.dol_escape_htmltag(dol_string_nohtmltag($message, 0)).'</div>';
		}
		foreach ($result['warnings'] as $message) {
			print '<div class="warning">'.dol_escape_htmltag(dol_string_nohtmltag($message, 0)).'</div>';
		}
		print '</td>';
		print '</tr>';
	}
	print '</table></div>';

	if ($checked) {
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="import">';
		print '<input type="hidden" name="socid" value="'.((int) $socid).'">';
		print '<div class="center">';
		print '<span class="opacitymedium">'.$langs->trans('TreatmentImportSummary', $nbok, count($import->results)).'</span><br>';
		if ($nbok) {
			print '<input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('TreatmentImportConfirm', $nbok)).'">';
		}
		print '</div>';
		print '</form>';
	}
}

llxFooter();
$db->close();
