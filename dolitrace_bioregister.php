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
 *    \file       htdocs/dolitrace/dolitrace_bioregister.php
 *    \ingroup    dolitrace
 *    \brief      Registers of organic production, read only: stock in and out register, annual mass balance by crop
 *                and status, annual summary by plot; CSV and PDF
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

dol_include_once('/dolitrace/class/dolitracebioregister.class.php');
dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_farm.lib.php');
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

$langs->loadLangs(array("dolitrace@dolitrace", "dolifarm@dolifarm", "productbatch", "stocks", "other"));

$action = GETPOST('action', 'aZ09');
$tab = GETPOST('tab', 'aZ09');
$socid = GETPOSTINT('socid');
$warehouseid = GETPOSTINT('warehouseid');
$productid = GETPOSTINT('productid');
$biostatus = GETPOST('biostatus', 'aZ09');
if (!in_array($biostatus, array('', 'BIO', 'INCONV', 'NONBIO', 'NONE'))) {
	$biostatus = '';
}
$year = GETPOSTINT('year');
if ($year < 1900 || $year > 2999) {
	$year = (int) dol_print_date(dol_now(), '%Y');
}
$datefrom = dol_mktime(0, 0, 0, GETPOSTINT('datefrommonth'), GETPOSTINT('datefromday'), GETPOSTINT('datefromyear'));
$dateto = dol_mktime(23, 59, 59, GETPOSTINT('datetomonth'), GETPOSTINT('datetoday'), GETPOSTINT('datetoyear'));
$socid = dolifarmForcedSocid($socid);
if (!GETPOSTISSET('datefromyear')) {
	$datefrom = dol_get_first_day($year, 1);
	$dateto = dol_get_last_day($year, 12);
}

// Security check: harvests for the registers, stock for the movements
if (!isModEnabled('dolitrace') || !$user->hasRight('dolitrace', 'dolitraceharvest', 'read')) {
	accessforbidden();
}
if ($socid > 0 && !dolifarmUserCanSeeSoc($user, $socid)) {
	accessforbidden();
}
$allowed = DoliTraceBioRegister::getAllowedTabs($user);
if (!in_array($tab, $allowed)) {
	$tab = reset($allowed);
}

$register = new DoliTraceBioRegister($db);

// Read only copy of the registers made at the closure of an inspection of the control body
$inspection = null;
$snapshot = null;
$closure = 0;
$snapshotyears = array();
if (GETPOSTINT('inspection') > 0) {
	dol_include_once('/dolitrace/class/dolitracebioinspection.class.php');
	$inspection = new DoliTraceBioInspection($db);
	if (!$user->hasRight('dolitrace', 'dolitracebioinspection', 'read') || $inspection->fetch(GETPOSTINT('inspection')) <= 0) {
		accessforbidden();
	}
	dolifarmCheckSocAccess($user, $inspection);
	$copies = $inspection->getSnapshots();
	$closure = GETPOSTINT('closure');
	if (!isset($copies[$closure])) {
		$closure = (count($copies) ? max(array_keys($copies)) : 0);
	}
	if (!$closure) {
		accessforbidden($langs->trans('BioInspectionNoCopy'), 0);
	}
	$snapshotyears = array_keys($copies[$closure]);
	if (!isset($copies[$closure][$year])) {
		$year = max($snapshotyears);
	}
	$socid = (int) $inspection->fk_soc;
	$allowed = array_values(array_intersect($allowed, array_keys($copies[$closure][$year])));
	if (!count($allowed)) {
		accessforbidden();
	}
	if (!in_array($tab, $allowed)) {
		$tab = reset($allowed);
	}
	$snapshot = $inspection->getSnapshot($closure, $tab, $year);
}

// Annual organic dossier: all the registers of the year in one PDF
if ($action == 'dossier') {
	if ($inspection) {
		$linesbytab = array();
		foreach ($allowed as $key) {
			$copy = $inspection->getSnapshot($closure, $key, $year);
			if ($copy) {
				$linesbytab[$key] = $copy['lines'];
			}
		}
		$content = $register->snapshotDossierPdf($year, $snapshot ? $snapshot['header'] : array(), $linesbytab, $langs);
	} else {
		$content = $register->toDossierPdf($user, $socid, $year, $langs);
	}
	if (is_string($content)) {
		$filename = 'fascicolo_bio'.($socid > 0 ? '_'.$socid : '').'_'.$year.($inspection ? '_'.$inspection->ref.'_'.$closure : '').'.pdf';
		dol_syslog('Export of the organic dossier '.$filename, LOG_INFO);
		top_httphead('application/pdf');
		header('Content-Disposition: attachment; filename="'.dol_sanitizeFileName($filename).'"');
		header('Content-Length: '.strlen($content));
		print $content;
		exit;
	}
	setEventMessages($register->error, null, 'errors');
}

// Lines of the tab: the stock register by period and filters, the others by year
$lines = array();
if ($snapshot) {
	$lines = $snapshot['lines'];
} elseif ($tab == 'movements') {
	$warehouses = $register->getWarehouses($user, $socid, $warehouseid);
	$lines = $register->getMovements($warehouses, $datefrom, $dateto, $biostatus, $productid);
} else {
	$lines = $register->getLines($tab, $user, $socid, $year);
}
if (!is_array($lines)) {
	setEventMessages($register->error, null, 'errors');
	$lines = array();
}

// Heading of the exports: farm, period, filters
$header = $register->getFarmHeader($socid, $langs);
if ($snapshot) {
	$header = $snapshot['header'];
	$header[] = $langs->transnoentities('Year').': '.$year;
	$header[] = $langs->transnoentities('BioDossierHelp'.ucfirst($tab));
} elseif ($tab == 'movements') {
	$header[] = $langs->transnoentities('Period').': '.dol_print_date($datefrom, 'day').' - '.dol_print_date($dateto, 'day');
	if ($warehouseid > 0) {
		$wh = new Entrepot($db);
		if ($wh->fetch($warehouseid) > 0) {
			$header[] = $langs->transnoentities('Warehouse').': '.$wh->ref;
		}
	}
	if ($biostatus !== '') {
		$header[] = $langs->transnoentities('BioRegisterStatus').': '.$langs->transnoentities('BioRegisterFilter'.$biostatus);
	}
	$header[] = $langs->transnoentities('BioRegisterMovementsNote');
} else {
	$header[] = $langs->transnoentities('Year').': '.$year;
	$header[] = $langs->transnoentities('BioDossierHelp'.ucfirst($tab));
}

// Exports, read only
if (in_array($action, array('csv', 'pdf'))) {
	$base = array(
		'movements' => 'registro_carico_scarico', 'balance' => 'bilancio_massa', 'plots' => 'riepilogo_appezzamenti', 'seeds' => 'sementi_deroghe',
		'inputs' => 'mezzi_tecnici', 'treatments' => 'trattamenti_bio', 'purchases' => 'acquisti', 'harvests' => 'raccolte', 'sales' => 'vendite',
	);
	$filename = $base[$tab].($socid > 0 ? '_'.$socid : '').'_'.($tab == 'movements' && !$snapshot ? dol_print_date($datefrom, '%Y-%m-%d').'_'.dol_print_date($dateto, '%Y-%m-%d') : $year).($inspection ? '_'.$inspection->ref.'_'.$closure : '').'.'.$action;
	if ($action == 'csv') {
		$content = $register->toCsv($tab, $lines, $langs);
		$mime = 'text/csv';
	} else {
		$content = $register->toPdf($tab, $lines, $header, $langs);
		$mime = 'application/pdf';
	}
	dol_syslog('Export of the organic register '.$filename.': '.count($lines).' lines', LOG_INFO);
	top_httphead($mime.'; charset=UTF-8');
	header('Content-Disposition: attachment; filename="'.dol_sanitizeFileName($filename).'"');
	header('Content-Length: '.strlen($content));
	print $content;
	exit;
}

/*
 * View
 */

$form = new Form($db);
$formproduct = new FormProduct($db);
$title = $langs->trans('BioRegister');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolitrace page-bioregister');

$params = array('socid' => $socid, 'year' => $year);
if ($inspection) {
	$params = array('inspection' => $inspection->id, 'closure' => $closure, 'year' => $year);
}
$head = array();
$h = 0;
foreach (DoliTraceBioRegister::getTabs() as $key => $label) {
	if (!in_array($key, $allowed)) {
		continue;
	}
	$head[$h][0] = $_SERVER['PHP_SELF'].'?'.http_build_query(array_merge($params, array('tab' => $key)));
	$head[$h][1] = $langs->trans($label);
	$head[$h][2] = $key;
	$h++;
}
print load_fiche_titre($title, '', 'fa-leaf');
print dol_get_fiche_head($head, $tab, '', -1);

// Copy of an inspection: no filters, the years of the copy
if ($inspection) {
	print '<div class="info"><span class="fa fa-lock paddingright"></span>'.$langs->trans('BioInspectionCopyBanner', $closure, $inspection->getNomUrl(1), dol_print_date($inspection->date_inspection, 'day'));
	if ($snapshot && !$snapshot['hash_ok']) {
		print '<br><span class="error">'.$langs->trans('BioInspectionCopyHashKo').'</span>';
	}
	print '</div>';
	print '<div class="marginbottomonly">'.$langs->trans('Year').': ';
	foreach ($snapshotyears as $y) {
		print ($y == $year ? '<strong>'.$y.'</strong>' : '<a href="'.$_SERVER['PHP_SELF'].'?'.dol_escape_htmltag(http_build_query(array_merge($params, array('tab' => $tab, 'year' => $y)))).'">'.$y.'</a>').' ';
	}
	print '</div>';
} else {
	if ($socid > 0) {
		dol_include_once('/dolitrace/lib/dolitrace_inspection.lib.php');
		$frozen = dolitraceFrozenInspection($db, $socid);
		if ($frozen) {
			print '<div class="info"><span class="fa fa-lock paddingright"></span>'.$langs->trans('BioRegisterFrozenInfo', dol_print_date($frozen->day, 'day'), '<a href="'.dol_buildpath('/dolitrace/dolitracebioinspection_card.php', 1).'?id='.$frozen->id.'">'.dol_escape_htmltag($frozen->ref).'</a>').'</div>';
		}
	}
// Filters
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="tab" value="'.$tab.'">';
$helps = array('movements' => 'BioRegisterMovementsHelp', 'balance' => 'BioRegisterBalanceHelp', 'plots' => 'BioRegisterPlotsHelp');
print '<span class="opacitymedium">'.$langs->trans($helps[$tab] ?? 'BioDossierHelp'.ucfirst($tab)).'</span><br><br>';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefieldcreate">'.$langs->trans('TreatmentFarm').'</td><td>';
print dolifarmFarmSelectHtml($form, $socid, 'socid', $langs->trans('All'));
print '</td></tr>';
if ($tab == 'movements') {
	print '<tr><td>'.$langs->trans('Period').'</td><td>';
	print $form->selectDate($datefrom, 'datefrom', 0, 0, 1, '', 1, 0).' - '.$form->selectDate($dateto, 'dateto', 0, 0, 1, '', 1, 0);
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('Warehouse').'</td><td>';
	print $formproduct->selectWarehouses($warehouseid, 'warehouseid', '', 1, 0, 0, $langs->trans('BioRegisterFarmWarehouses'), 0, 0, array(), 'minwidth300');
	print ' <span class="opacitymedium">'.$langs->trans('BioRegisterWithSubWarehouses').'</span>';
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('BioRegisterStatus').'</td><td><select name="biostatus" id="biostatus" class="flat minwidth150">';
	foreach (array('' => 'All', 'BIO' => 'BioRegisterFilterBIO', 'INCONV' => 'BioRegisterFilterINCONV', 'NONBIO' => 'BioRegisterFilterNONBIO', 'NONE' => 'BioRegisterFilterNONE') as $key => $label) {
		print '<option value="'.$key.'"'.($biostatus === $key ? ' selected' : '').'>'.dol_escape_htmltag($langs->trans($label)).'</option>';
	}
	print '</select>'.ajax_combobox('biostatus').'</td></tr>';
	print '<tr><td>'.$langs->trans('Product').'</td><td>';
	$form->select_produits($productid, 'productid', '', 0, 0, -1, 2, '', 0, array(), 0, '1', 0, 'minwidth300');
	print '</td></tr>';
} else {
	print '<tr><td>'.$langs->trans('Year').'</td><td><input type="number" name="year" class="width75" min="1900" max="2999" value="'.$year.'"></td></tr>';
}
print '</table>';
print '<div class="center"><input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('Refresh')).'"></div>';
print '</form>';
}

// Exports with the same filters
$query = array_merge($_GET, array('tab' => $tab, 'socid' => $socid, 'year' => $year, 'token' => newToken()));
unset($query['action']);
print '<div class="tabsAction">';
print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?'.dol_escape_htmltag(http_build_query(array_merge($query, array('action' => 'pdf')))).'">'.$langs->trans('BioRegisterExportPdf').'</a>';
print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?'.dol_escape_htmltag(http_build_query(array_merge($query, array('action' => 'csv')))).'">'.$langs->trans('BioRegisterExportCsv').'</a>';
print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?'.dol_escape_htmltag(http_build_query(array_merge($inspection ? $params : array(), array('tab' => $tab, 'socid' => $socid, 'year' => $tab == 'movements' && !$inspection ? (int) dol_print_date($dateto, '%Y') : $year, 'action' => 'dossier', 'token' => newToken())))).'" title="'.dol_escape_htmltag($langs->trans('BioDossierButtonHelp')).'">'.$langs->trans('BioDossierButton').'</a>';
print '</div>';

// Register
$columns = $register->getColumns($tab);
print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
foreach ($columns as $column) {
	print '<th'.($column[3] ? ' class="right"' : '').'>'.$langs->trans($column[1]).'</th>';
}
print '</tr>';
if (empty($lines)) {
	print '<tr class="oddeven"><td colspan="'.count($columns).'"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
}
foreach ($lines as $line) {
	print '<tr class="oddeven'.(($line['origin'] ?? '') == 'OPENING' ? ' opacitymedium' : '').'">';
	foreach ($columns as $column) {
		$value = $register->formatValue($column[0], $line, $langs, false);
		if (($column[0] == 'losses' && (float) $line['losses'] < 0) || ($column[0] == 'check' && $value !== '') || ($column[0] == 'organic_result' && in_array($line['organic_result'], array('no', 'verify')) && $line['on_organic'])) {
			$value = '<span class="warning" title="'.dol_escape_htmltag($langs->trans($column[0] == 'losses' ? 'BioRegisterNegativeLosses' : 'BioRegisterCheckHelp')).'">'.dol_escape_htmltag($value).'</span>';
		} else {
			$value = dol_escape_htmltag($value);
		}
		print '<td'.($column[3] ? ' class="right nowraponall"' : '').'>'.$value.'</td>';
	}
	print '</tr>';
}
print '</table>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
