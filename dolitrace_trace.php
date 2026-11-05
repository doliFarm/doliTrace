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
 *    \file       htdocs/dolitrace/dolitrace_trace.php
 *    \ingroup    dolitrace
 *    \brief      Traceability of a harvested product: search by UUID, short code, lot or reference of the harvest, and
 *                history upstream (farm, plot, crop plan, operations, treatments) and downstream (stock, movements,
 *                customers); traceability sheet in PDF. Read only.
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

dol_include_once('/dolitrace/class/dolitracetrace.class.php');
dol_include_once('/dolitrace/class/dolitraceharvest.class.php');

$langs->loadLangs(array("dolitrace@dolitrace", "dolifarm@dolifarm", "productbatch", "stocks", "products", "companies", "other"));

$action = GETPOST('action', 'aZ09');
$search = trim(GETPOST('search', 'alphanohtml'));
$id = GETPOSTINT('id');
$uuid = GETPOST('uuid', 'alphanohtml');
if ($uuid !== '' && $search === '') {
	$search = $uuid;
}

// Security check: harvests, then the farm of each harvest through the filter of the queries
if (!isModEnabled('dolitrace') || !$user->hasRight('dolitrace', 'dolitraceharvest', 'read')) {
	accessforbidden();
}

$trace = new DoliTraceTrace($db);

// Search: one harvest found opens its history
$found = array();
if ($id <= 0 && $search !== '') {
	$found = $trace->find($user, $search);
	if (!is_array($found)) {
		setEventMessages($trace->error, null, 'errors');
		$found = array();
	} elseif (count($found) == 1) {
		$id = $found[0]['id'];
	}
}

$history = null;
if ($id > 0) {
	$history = $trace->getHistory($user, $id, $langs);
	if (is_int($history)) {
		if ($history < 0) {
			setEventMessages($trace->error, null, 'errors');
		} else {
			setEventMessages($langs->trans('TraceNotFound'), null, 'warnings');
		}
		$history = null;
	}
}

// Traceability sheet in PDF
if ($action == 'pdf' && $history) {
	$content = $trace->toPdf($history, $langs);
	$filename = 'tracciabilita_'.$history['harvest']['ref'].'.pdf';
	dol_syslog('Export of the traceability sheet '.$filename, LOG_INFO);
	top_httphead('application/pdf');
	header('Content-Disposition: attachment; filename="'.dol_sanitizeFileName($filename).'"');
	header('Content-Length: '.strlen($content));
	print $content;
	exit;
}

/*
 * View
 */

$title = $langs->trans('TraceTitle');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolitrace page-trace');
print load_fiche_titre($title, '', 'fa-route');

$day = function ($date) {
	return $date ? dol_print_date($date, 'day', 'tzserver') : '';
};
$qty = function ($value, $unit = '') {
	return $value === null ? '' : price(price2num($value, 'MS')).($unit !== '' ? ' '.$unit : '');
};
$harvestlink = function ($hid, $ref) {
	return '<a href="'.dol_buildpath('/dolitrace/dolitraceharvest_card.php', 1).'?id='.((int) $hid).'">'.img_picto('', 'fa-tractor', 'class="pictofixedwidth"').dol_escape_htmltag($ref).'</a>';
};

// Search form
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" id="dolitrace-trace-search">';
print '<span class="opacitymedium">'.$langs->trans('TraceHelp').'</span><br><br>';
print '<input type="text" name="search" id="search" class="minwidth400 maxwidth500" autofocus value="'.dol_escape_htmltag($id > 0 && $history ? '' : $search).'" placeholder="'.dol_escape_htmltag($langs->trans('TraceSearchPlaceholder')).'">';
print ' <input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Search')).'">';
print '</form><br>';

// More harvests found
if (!$history && $search !== '') {
	print '<div class="div-table-responsive"><table class="noborder centpercent" id="dolitrace-trace-found">';
	print '<tr class="liste_titre"><th>'.$langs->trans('Ref').'</th><th>'.$langs->trans('HarvestDate').'</th><th>'.$langs->trans('HarvestProduct').'</th>';
	print '<th>'.$langs->trans('HarvestBatch').'</th><th>'.$langs->trans('BioRegisterPlot').'</th><th>'.$langs->trans('TreatmentFarm').'</th><th class="right">'.$langs->trans('HarvestQty').'</th><th></th></tr>';
	if (empty($found)) {
		print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('TraceNothingFound').'</span></td></tr>';
	}
	foreach ($found as $line) {
		print '<tr class="oddeven"><td>'.$harvestlink($line['id'], $line['ref']).'</td><td>'.$day($line['date']).'</td><td>'.dol_escape_htmltag($line['product']).'</td>';
		print '<td>'.dol_escape_htmltag($line['batch']).'</td><td>'.dol_escape_htmltag($line['plot']).'</td><td>'.dol_escape_htmltag($line['farm']).'</td>';
		print '<td class="right nowraponall">'.$qty($line['qty'], $line['unit']).'</td>';
		print '<td class="right"><a class="button smallpaddingimp" href="'.$_SERVER['PHP_SELF'].'?id='.$line['id'].'">'.$langs->trans('TraceOpen').'</a></td></tr>';
	}
	print '</table></div>';
}

if ($history) {
	$h = $history['harvest'];
	$p = $history['plot'];
	$cp = $history['cropplan'];
	$statuslabels = array('BIO' => 'BioRegisterFilterBIO', 'INCONV' => 'BioRegisterFilterINCONV', 'NONBIO' => 'BioRegisterFilterNONBIO');

	if (count($history['warnings'])) {
		print '<div class="warning" id="dolitrace-trace-warnings">'.implode('<br>', array_map('dol_escape_htmltag', $history['warnings'])).'</div>';
	}

	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$h['id'].'&action=pdf&token='.newToken().'">'.$langs->trans('TraceExportPdf').'</a>';
	print '</div>';

	// Harvest and lot, with the QR code of the harvest
	print '<div class="fichecenter"><div class="fichehalfleft">';
	print load_fiche_titre($langs->trans('TraceSectionHarvest'), '', 'fa-tractor');
	print '<table class="border centpercent tableforfield" id="dolitrace-trace-harvest">';
	print '<tr><td class="titlefield">'.$langs->trans('Ref').'</td><td>'.$harvestlink($h['id'], $h['ref']).($h['label'] !== '' ? ' - '.dol_escape_htmltag($h['label']) : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('HarvestProduct').'</td><td>'.dol_escape_htmltag($h['product']).'</td></tr>';
	print '<tr><td>'.$langs->trans('HarvestBatch').'</td><td>'.dol_escape_htmltag($h['batch']).'</td></tr>';
	print '<tr><td>'.$langs->trans('HarvestDate').'</td><td>'.$day($h['date']).'</td></tr>';
	print '<tr><td>'.$langs->trans('HarvestQty').'</td><td>'.$qty($h['qty'], $h['unit']).'</td></tr>';
	print '<tr><td>'.$langs->trans('HarvestBioStatus').'</td><td>'.($h['bio_status'] !== '' ? $langs->trans($statuslabels[$h['bio_status']] ?? $h['bio_status']) : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('TraceUuid').'</td><td class="wordbreak"><span class="fa fa-fingerprint opacitymedium paddingright"></span>'.dol_escape_htmltag($h['uuid']).'</td></tr>';
	print '<tr><td>'.$langs->trans('TraceShortCode').'</td><td><strong>'.dol_escape_htmltag($h['short']).'</strong></td></tr>';
	if ($h['note_public'] !== '') {
		print '<tr><td>'.$langs->trans('NotePublic').'</td><td>'.dol_htmlentitiesbr($h['note_public']).'</td></tr>';
	}
	print '</table>';
	print '</div><div class="fichehalfright">';
	print load_fiche_titre($langs->trans('TraceSectionOrigin'), '', 'fa-map-marked-alt');
	print '<table class="border centpercent tableforfield" id="dolitrace-trace-origin">';
	foreach ($history['farm']['header'] as $i => $line) {
		print '<tr><td class="titlefield">'.($i == 0 ? $langs->trans('TreatmentFarm') : '').'</td><td>'.dol_escape_htmltag($line).'</td></tr>';
	}
	print '<tr><td>'.$langs->trans('TraceDossier').'</td><td>'.dol_escape_htmltag($history['farm']['dossier']).'</td></tr>';
	print '<tr><td>'.$langs->trans('BioRegisterPlot').'</td><td>';
	if ($p['id'] > 0) {
		print '<a href="'.dol_buildpath('/dolifarm/dolifarmplot_card.php', 1).'?id='.$p['id'].'">'.dol_escape_htmltag(trim($p['ref'].' - '.$p['label'], ' -')).'</a>';
	}
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('TraceCadastral').'</td><td>'.dol_escape_htmltag(trim($p['comune'].' '.($p['sheet'] !== '' ? $langs->trans('TraceSheetParcel', $p['sheet'], $p['parcel']) : ''))).'</td></tr>';
	print '<tr><td>'.$langs->trans('BioRegisterSurface').'</td><td>'.$qty($p['surface'], 'ha').'</td></tr>';
	print '<tr><td>'.$langs->trans('BioRegisterPlotStatus').'</td><td>'.dol_escape_htmltag($p['bio_label']).'</td></tr>';
	print '<tr><td>'.$langs->trans('BioRegisterCropPlan').'</td><td><a href="'.dol_buildpath('/dolitrace/dolitracecropplan_card.php', 1).'?id='.$cp['id'].'">'.dol_escape_htmltag(trim($cp['ref'].' - '.$cp['label'], ' -')).'</a>';
	// The traceability follows the whole history of the crop plan
	print ' &nbsp; <a href="'.dol_buildpath('/dolitrace/dolitracecropplan_history.php', 1).'?id='.$cp['id'].'">'.img_picto('', 'fa-route', 'class="pictofixedwidth"').$langs->trans('CropPlanHistoryOpen').'</a></td></tr>';
	print '<tr><td>'.$langs->trans('BioRegisterCrop').'</td><td>'.dol_escape_htmltag($cp['crop']).($cp['crop_eppo'] !== '' ? ' <span class="opacitymedium">('.dol_escape_htmltag($cp['crop_eppo']).')</span>' : '').'</td></tr>';
	print '<tr><td>'.$langs->trans('TraceCropPeriod').'</td><td>'.trim($day($cp['date_start']).' - '.$day($cp['date_end']), ' -').'</td></tr>';
	print '</table>';
	print '</div></div><div class="clearboth"></div>';

	// Other harvests of the lot
	if (count($history['lot'])) {
		print load_fiche_titre($langs->trans('TraceSectionLot'), '', 'fa-boxes');
		print '<div class="div-table-responsive"><table class="noborder centpercent" id="dolitrace-trace-lot">';
		print '<tr class="liste_titre"><th>'.$langs->trans('Ref').'</th><th>'.$langs->trans('HarvestDate').'</th><th>'.$langs->trans('BioRegisterPlot').'</th><th class="right">'.$langs->trans('HarvestQty').'</th><th>'.$langs->trans('BioRegisterStatus').'</th><th></th></tr>';
		foreach ($history['lot'] as $line) {
			print '<tr class="oddeven"><td>'.$harvestlink($line['id'], $line['ref']).'</td><td>'.$day($line['date']).'</td><td>'.dol_escape_htmltag($line['plot']).'</td>';
			print '<td class="right nowraponall">'.$qty($line['qty'], $line['unit']).'</td><td>'.($line['bio_status'] !== '' ? $langs->trans($statuslabels[$line['bio_status']] ?? $line['bio_status']) : '').'</td>';
			print '<td class="right"><a class="button smallpaddingimp" href="'.$_SERVER['PHP_SELF'].'?id='.$line['id'].'">'.$langs->trans('TraceOpen').'</a></td></tr>';
		}
		print '</table></div>';
	}

	// Operations of the crop plan
	if (isset($history['operations'])) {
		print load_fiche_titre($langs->trans('TraceSectionOperations'), '', 'fa-seedling');
		print '<div class="div-table-responsive"><table class="noborder centpercent" id="dolitrace-trace-operations">';
		print '<tr class="liste_titre"><th>'.$langs->trans('Date').'</th><th>'.$langs->trans('Ref').'</th><th>'.$langs->trans('Type').'</th><th>'.$langs->trans('Label').'</th>';
		print '<th>'.$langs->trans('Product').'</th><th>'.$langs->trans('Batch').'</th><th class="right">'.$langs->trans('Qty').'</th><th>'.$langs->trans('BioRegisterStatus').'</th></tr>';
		if (empty($history['operations'])) {
			print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
		}
		foreach ($history['operations'] as $line) {
			print '<tr class="oddeven"><td class="nowraponall">'.$day($line['date']).'</td>';
			print '<td><a href="'.dol_buildpath('/dolitrace/dolitraceoperation_card.php', 1).'?id='.$line['id'].'">'.dol_escape_htmltag($line['ref']).'</a></td>';
			print '<td>'.($line['seeding'] ? '<span class="fa fa-seedling paddingright"></span>' : '').dol_escape_htmltag($line['type_label']).'</td><td>'.dol_escape_htmltag($line['label']).'</td>';
			print '<td>'.dol_escape_htmltag($line['product']).'</td><td>'.dol_escape_htmltag($line['batch']).'</td><td class="right nowraponall">'.$qty($line['qty']).'</td>';
			print '<td>'.($line['seed_status'] !== '' ? $langs->trans($statuslabels[$line['seed_status']] ?? $line['seed_status']) : '').($line['bdsb_ref'] !== '' ? ' <span class="opacitymedium">'.dol_escape_htmltag($line['bdsb_ref']).'</span>' : '').'</td></tr>';
		}
		print '</table></div>';
	}

	// Treatments and fertilizations up to the harvest
	if (isset($history['treatments'])) {
		$s = $history['treatment_summary'];
		print load_fiche_titre($langs->trans('TraceSectionTreatments'), '', 'fa-spray-can');
		print '<div class="opacitymedium" id="dolitrace-trace-summary">'.$langs->trans('TraceSummary', $s['protection'], $s['fertilization']);
		if ($s['last_protection']) {
			print ' '.$langs->trans('TraceSummaryLast', $day($s['last_protection']), $s['last_days']);
		}
		if ($s['all_organic']) {
			print ' '.$langs->trans('TraceSummaryOrganic');
		}
		print '</div>';
		print '<div class="div-table-responsive"><table class="noborder centpercent" id="dolitrace-trace-treatments">';
		print '<tr class="liste_titre"><th>'.$langs->trans('Date').'</th><th>'.$langs->trans('Ref').'</th><th>'.$langs->trans('Product').'</th><th>'.$langs->trans('TraceSubstance').'</th>';
		print '<th>'.$langs->trans('TraceAdversity').'</th><th class="right">'.$langs->trans('TraceDose').'</th><th class="right">'.$langs->trans('Qty').'</th><th>'.$langs->trans('Batch').'</th>';
		print '<th class="right">'.$langs->trans('TraceDaysSafety').'</th></tr>';
		if (empty($history['treatments'])) {
			print '<tr class="oddeven"><td colspan="9"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
		}
		foreach ($history['treatments'] as $line) {
			print '<tr class="oddeven"><td class="nowraponall">'.$day($line['date']).'</td>';
			print '<td><a href="'.dol_buildpath('/dolitrace/dolitracetreatment_card.php', 1).'?id='.$line['id'].'">'.dol_escape_htmltag($line['ref']).'</a></td>';
			print '<td><span class="fa '.($line['kind'] == 2 ? 'fa-flask' : 'fa-spray-can').' opacitymedium paddingright"></span>'.dol_escape_htmltag($line['product_label']).($line['reg_number'] !== '' ? ' <span class="opacitymedium">'.dol_escape_htmltag($line['reg_number']).'</span>' : '').'</td>';
			print '<td class="tdoverflowmax200" title="'.dol_escape_htmltag($line['substance']).'">'.dol_escape_htmltag($line['substance']).'</td><td>'.dol_escape_htmltag($line['adversity']).'</td>';
			print '<td class="right nowraponall">'.$qty($line['dose'], $line['dose_unit']).'</td><td class="right nowraponall">'.$qty($line['qty'], $line['unit']).'</td><td>'.dol_escape_htmltag($line['batch']).'</td>';
			$days = (string) $line['days_before'].($line['safety_days'] !== null ? ' / '.$line['safety_days'] : '');
			print '<td class="right nowraponall">'.($line['safety_ok'] === false ? '<span class="warning" title="'.dol_escape_htmltag($langs->trans('TraceSafetyNotKept')).'">'.$days.'</span>' : $days).'</td></tr>';
		}
		print '</table></div>';
	}

	// Downstream: stock and movements of the lot, customers
	if (isset($history['movements'])) {
		print load_fiche_titre($langs->trans('TraceSectionDownstream'), '', 'fa-truck');
		$stock = array();
		foreach ($history['stock'] as $line) {
			$stock[] = dol_escape_htmltag($line['warehouse']).': '.$qty($line['qty']);
		}
		print '<div id="dolitrace-trace-stock">'.$langs->trans('TraceStockNow').': <strong>'.(count($stock) ? implode(', ', $stock) : '0').'</strong></div>';
		if (count($history['customers'])) {
			$customers = array();
			foreach ($history['customers'] as $name => $value) {
				$customers[] = dol_escape_htmltag($name).' ('.$qty($value).')';
			}
			print '<div>'.$langs->trans('TraceCustomers').': '.implode(', ', $customers).'</div>';
		}
		print '<br><div class="div-table-responsive"><table class="noborder centpercent" id="dolitrace-trace-movements">';
		print '<tr class="liste_titre"><th>'.$langs->trans('Date').'</th><th>'.$langs->trans('BioRegisterOrigin').'</th><th>'.$langs->trans('BioRegisterDocument').'</th><th>'.$langs->trans('ThirdParty').'</th>';
		print '<th>'.$langs->trans('Warehouse').'</th><th class="right">'.$langs->trans('BioRegisterIn').'</th><th class="right">'.$langs->trans('BioRegisterOut').'</th><th class="right">'.$langs->trans('BioRegisterBalanceQty').'</th></tr>';
		if (empty($history['movements'])) {
			print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
		}
		foreach ($history['movements'] as $line) {
			print '<tr class="oddeven"><td class="nowraponall">'.$day($line['date']).'</td><td>'.$langs->trans('BioRegisterOrigin'.ucfirst(strtolower($line['origin']))).'</td>';
			print '<td>'.dol_escape_htmltag($line['document']).'</td><td>'.dol_escape_htmltag($line['thirdparty']).'</td><td>'.dol_escape_htmltag($line['warehouse']).'</td>';
			print '<td class="right nowraponall">'.$qty($line['qty_in']).'</td><td class="right nowraponall">'.$qty($line['qty_out']).'</td><td class="right nowraponall">'.$qty($line['balance']).'</td></tr>';
		}
		print '</table></div>';
	}
}

llxFooter();
$db->close();
