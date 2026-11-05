<?php
/* Copyright (C) 2026       Luigi Grillo - doliFarm.com	<info@dolifarm.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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
 * \file       dolitracecropplan_history.php
 * \ingroup    dolitrace
 * \brief      Tab "History" of a crop plan: operations, treatments, harvests and their lots, in PDF and CSV
 *
 * The traceability of a product follows the history of its crop plan: the history of a harvest links here.
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
 * @var Translate $langs
 * @var User $user
 */
dol_include_once('/dolitrace/class/dolitracecropplan.class.php');
dol_include_once('/dolitrace/class/dolitracetrace.class.php');
dol_include_once('/dolitrace/lib/dolitrace_cropplan.lib.php');
dol_include_once('/dolitrace/lib/dolitrace_access.lib.php');

$langs->loadLangs(array("dolitrace@dolitrace", "dolifarm@dolifarm", "other", "products", "productbatch", "stocks"));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

$object = new DoliTraceCropplan($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden();
}
if (!isModEnabled('dolitrace') || !$user->hasRight('dolitrace', 'dolitracecropplan', 'read')) {
	accessforbidden();
}
dolitraceCheckAccess($user, $object);

$trace = new DoliTraceTrace($db);
$history = $trace->getCropPlanHistory($user, $object->id, $langs);
if (is_int($history)) {
	if ($history < 0) {
		setEventMessages($trace->error, null, 'errors');
	}
	$history = null;
}


/*
 * Actions
 */

if (in_array($action, array('pdf', 'csv')) && $history) {
	if ($action == 'pdf') {
		$content = $trace->cropPlanToPdf($history, $langs);
		$mime = 'application/pdf';
	} else {
		$content = $trace->cropPlanToCsv($history, $langs);
		$mime = 'text/csv; charset=UTF-8';
	}
	$filename = dol_sanitizeFileName('storia_'.$object->ref.'.'.$action);
	dol_syslog('Export of the history of the crop plan '.$filename, LOG_INFO);
	top_httphead($mime);
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	header('Content-Length: '.strlen($content));
	print $content;
	exit;
}


/*
 * View
 */

$title = $object->ref.' - '.$langs->trans('CropPlanHistory');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolitrace page-cropplan-history');

$head = dolitracecropplanPrepareHead($object);
print dol_get_fiche_head($head, 'history', $langs->trans('DoliTraceCropplan'), -1, $object->picto);
$linkback = '<a href="'.dol_buildpath('/dolitrace/dolitracecropplan_list.php', 1).'">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');
print '<div class="underbanner clearboth"></div>';
print dol_get_fiche_end();

if ($history) {
	$buttons = dolGetButtonAction('', $langs->trans('CropPlanHistoryPdf'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=pdf&token='.newToken(), '', 1);
	$buttons .= dolGetButtonAction('', $langs->trans('CropPlanHistoryCsv'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=csv&token='.newToken(), '', 1);
	print load_fiche_titre($langs->trans('CropPlanHistory'), $buttons, 'fa-route');
	print '<span class="opacitymedium">'.$langs->trans('CropPlanHistoryHelp').'</span><br>';
	print $trace->cropPlanHistoryHtml($history, $langs, false);
}

llxFooter();
$db->close();
