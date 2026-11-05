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
 * \file       dolitracebioinspection_document.php
 * \ingroup    dolitrace
 * \brief      Documents of an inspection: report of the control body, PDF dossiers of the copies of the registers
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
include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
dol_include_once('/dolitrace/class/dolitracebioinspection.class.php');
dol_include_once('/dolitrace/lib/dolitrace_inspection.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');

$langs->loadLangs(array("dolitrace@dolitrace", "companies", "other", "mails"));

$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm');
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
if (!$sortorder) {
	$sortorder = "ASC";
}
if (!$sortfield) {
	$sortfield = "name";
}

$object = new DoliTraceBioInspection($db);
$hookmanager->initHooks(array($object->element.'document', 'globalcard'));
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

$permissiontoread = $user->hasRight('dolitrace', 'dolitracebioinspection', 'read');
$permissiontoadd = $user->hasRight('dolitrace', 'dolitracebioinspection', 'write');
if (!isModEnabled('dolitrace') || !$permissiontoread || empty($object->id)) {
	accessforbidden();
}
dolifarmCheckSocAccess($user, $object);
$upload_dir = $conf->dolitrace->dir_output.'/dolitracebioinspection/'.dol_sanitizeFileName($object->ref);


/*
 * Actions
 */

include DOL_DOCUMENT_ROOT.'/core/actions_linkedfiles.inc.php';


/*
 * View
 */

$form = new Form($db);
$title = $langs->trans('BioInspection').' - '.$langs->trans('Files');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolitrace page-bioinspection-document');

$head = dolitracebioinspectionPrepareHead($object);
print dol_get_fiche_head($head, 'document', $langs->trans('BioInspection'), -1, $object->picto);

$filearray = dol_dir_list($upload_dir, "files", 0, '', '(\.meta|_preview.*\.png)$', $sortfield, (strtolower($sortorder) == 'desc' ? SORT_DESC : SORT_ASC), 1);
$totalsize = 0;
foreach ($filearray as $file) {
	$totalsize += $file['size'];
}

$linkback = '<a href="'.dol_buildpath('/dolitrace/dolitracebioinspection_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', '<div class="refidno"></div>');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans("NbOfAttachedFiles").'</td><td colspan="3">'.count($filearray).'</td></tr>';
print '<tr><td>'.$langs->trans("TotalSizeOfAttachedFiles").'</td><td colspan="3">'.$totalsize.' '.$langs->trans("bytes").'</td></tr>';
print '</table>';
print '</div>';
print '<div class="opacitymedium margintoponly">'.$langs->trans('BioInspectionDocumentsHelp').'</div>';

print dol_get_fiche_end();

$modulepart = 'dolitrace';
$param = '&id='.$object->id;
$relativepathwithnofile = 'dolitracebioinspection/'.dol_sanitizeFileName($object->ref).'/';
include DOL_DOCUMENT_ROOT.'/core/tpl/document_actions_post_headers.tpl.php';

llxFooter();
$db->close();
