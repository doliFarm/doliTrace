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
 *    \file       htdocs/dolitrace/dolitracetreatment_export.php
 *    \ingroup    dolitrace
 *    \brief      Export of the register of treatments and fertilizations: JSON, CSV and PDF, by farm and period
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
dol_include_once('/dolitrace/class/dolitracetreatmentexport.class.php');
dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_farm.lib.php');

$langs->loadLangs(array("dolitrace@dolitrace", "other"));

$action = GETPOST('action', 'aZ09');
$socid = GETPOSTINT('socid');
$format = GETPOST('format', 'aZ09');
$withcanceled = GETPOSTINT('withcanceled');
$datefrom = dol_mktime(0, 0, 0, GETPOSTINT('datefrommonth'), GETPOSTINT('datefromday'), GETPOSTINT('datefromyear'));
$dateto = dol_mktime(23, 59, 59, GETPOSTINT('datetomonth'), GETPOSTINT('datetoday'), GETPOSTINT('datetoyear'));
if (!GETPOSTISSET('socid')) {
	$socid = dolifarmSingleFarmSocid();
}
if (!GETPOSTISSET('datefromyear')) {
	$datefrom = dol_get_first_day((int) dol_print_date(dol_now(), '%Y'), 1);
	$dateto = dol_get_last_day((int) dol_print_date(dol_now(), '%Y'), 12);
}

if (!isModEnabled('dolitrace') || !$user->hasRight('dolitrace', 'dolitracetreatment', 'read')) {
	accessforbidden();
}
if ($socid > 0 && !dolifarmUserCanSeeSoc($user, $socid)) {
	accessforbidden();
}


/*
 * Actions
 */

if ($action == 'export' && in_array($format, array('json', 'csv', 'pdf'))) {
	$export = new DoliTraceTreatmentExport($db);
	$statuses = array(DoliTraceTreatment::STATUS_VALIDATED);
	if ($withcanceled) {
		$statuses[] = DoliTraceTreatment::STATUS_REPLACED;
		$statuses[] = DoliTraceTreatment::STATUS_CANCELED;
	}
	$records = $export->getRecords($user, $socid, $datefrom, $dateto, $statuses);
	if (!is_array($records)) {
		setEventMessages($export->error, null, 'errors');
	} else {
		$filters = array(
			'farm_id' => (string) $socid,
			'date_from' => $datefrom ? dol_print_date($datefrom, '%Y-%m-%d') : '',
			'date_to' => $dateto ? dol_print_date($dateto, '%Y-%m-%d') : '',
			'statuses' => $withcanceled ? 'validated,replaced,canceled' : 'validated',
		);
		$filename = 'registro_trattamenti'.($socid > 0 ? '_'.$socid : '').'_'.$filters['date_from'].'_'.$filters['date_to'].'.'.$format;
		if ($format == 'json') {
			// Crop events and irrigations of the field notebook, for the users who can read the crop operations
			$operations = array();
			if ($user->hasRight('dolitrace', 'dolitraceoperation', 'read')) {
				$operations = $export->getOperations($user, $socid, $datefrom, $dateto);
				if (!is_array($operations)) {
					dol_syslog('Export of the crop operations: '.$export->error, LOG_ERR);
					$operations = array();
				}
			}
			// Storage sites with the stock of the products at the start and at the end of the period
			$storage = array();
			if (isModEnabled('stock') && $user->hasRight('stock', 'lire')) {
				$storage = $export->getStorage($user, $socid, $datefrom, $dateto);
				if (!is_array($storage)) {
					dol_syslog('Export of the storage sites: '.$export->error, LOG_ERR);
					$storage = array();
				}
			}
			$content = $export->toJson($records, $filters, $operations, $storage);
			$mime = 'application/json';
		} elseif ($format == 'csv') {
			$content = $export->toCsv($records);
			$mime = 'text/csv';
		} else {
			$content = $export->toPdf($records, $filters, $langs);
			$mime = 'application/pdf';
		}
		dol_syslog('Export of the register '.$filename.': '.count($records).' records', LOG_INFO);
		top_httphead($mime.'; charset=UTF-8');
		header('Content-Disposition: attachment; filename="'.dol_sanitizeFileName($filename).'"');
		header('Content-Length: '.strlen($content));
		print $content;
		exit;
	}
}


/*
 * View
 */

$form = new Form($db);
$title = $langs->trans('TreatmentExport');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolitrace page-export');

print load_fiche_titre($title, '', 'fa-file-export');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="export">';
print '<span class="opacitymedium">'.$langs->trans('TreatmentExportHelp').'</span><br><br>';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefieldcreate">'.$langs->trans('TreatmentFarm').'</td><td>';
print $form->select_company($socid, 'socid', '', $langs->trans('All'), 0, 0, array(), 0, 'minwidth300');
print '</td></tr>';
print '<tr><td>'.$langs->trans('Period').'</td><td>';
print $form->selectDate($datefrom, 'datefrom', 0, 0, 1, '', 1, 0).' - '.$form->selectDate($dateto, 'dateto', 0, 0, 1, '', 1, 0);
print '</td></tr>';
print '<tr><td>'.$langs->trans('TreatmentExportWithCanceled').'</td><td><input type="checkbox" name="withcanceled" value="1"'.($withcanceled ? ' checked' : '').'></td></tr>';
print '<tr><td>'.$langs->trans('TreatmentExportFormat').'</td><td>';
foreach (array('pdf' => 'TreatmentExportPdf', 'csv' => 'TreatmentExportCsv', 'json' => 'TreatmentExportJson') as $key => $label) {
	print '<label class="marginrightonly"><input type="radio" name="format" value="'.$key.'"'.(($format ? $format : 'pdf') == $key ? ' checked' : '').'> '.$langs->trans($label).'</label> ';
}
print '</td></tr>';
print '</table>';
print '<div class="center"><input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('Export')).'"></div>';
print '</form>';

llxFooter();
$db->close();
