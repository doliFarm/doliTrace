<?php
/* Copyright (C) 2026      Luigi Grillo - doliFarm.com	<info@dolifarm.com>
 *
 * This program is free software; you can redistribute it and/or modify
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
 *  \file       dolitracecropplan_costs.php
 *  \ingroup    dolitrace
 *  \brief      Tab "Costs" of a production plan: budget (planned operations), actual costs and comparison
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
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
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

dol_include_once('/dolitrace/class/dolitracecropplan.class.php');
dol_include_once('/dolitrace/class/dolitracecropplanbudget.class.php');
dol_include_once('/dolitrace/class/dolitraceoperation.class.php');
dol_include_once('/dolitrace/class/dolitracetreatment.class.php');
dol_include_once('/dolitrace/class/dolitraceharvest.class.php');
dol_include_once('/dolitrace/lib/dolitrace_cropplan.lib.php');
dol_include_once('/dolitrace/lib/dolitrace_access.lib.php');
dol_include_once('/dolitrace/lib/dolitrace_costs.lib.php');

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('dolitrace@dolitrace', 'dolifarm@dolifarm', 'other'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$lineid = GETPOSTINT('lineid');

$object = new DoliTraceCropplan($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden('NotFound');
}
if (!isModEnabled('dolitrace')) {
	accessforbidden('Module not enabled');
}
$permissiontoread = $user->hasRight('dolitrace', 'dolitracecropplan', 'read');
$permissiontoadd = $user->hasRight('dolitrace', 'dolitracecropplan', 'write') && $object->status != $object::STATUS_CANCELED;
if (!$permissiontoread) {
	accessforbidden();
}
dolitraceCheckAccess($user, $object);

$operationstatic = new DoliTraceOperation($db);
$optypes = (array) $operationstatic->fields['fk_operation_type']['arrayofkeyval'];
$cropplan = dolitraceTreatmentCropPlan($db, (int) $object->id);
$planarea = dolitraceCropPlanArea($cropplan);
$plandate = $db->jdate($cropplan ? $cropplan->date_start : null);

/*
 * Actions
 */

if (in_array($action, array('addline', 'updateline', 'confirm_deleteline', 'reloadrates')) && $permissiontoadd) {
	$line = new DoliTraceCropPlanBudget($db);
	if ($action != 'addline' && ($lineid <= 0 || $line->fetch($lineid) <= 0 || (int) $line->fk_cropplan != (int) $object->id)) {
		accessforbidden('NotFound');
	}
	$error = 0;
	if ($action == 'confirm_deleteline') {
		$error = ($line->delete($user) < 0);
	} elseif ($action == 'reloadrates') {
		if (!$line->setStandardCosts((int) $object->fk_soc, $plandate)) {
			setEventMessages($langs->trans('BudgetNoStandardCost'), null, 'warnings');
		}
		$error = ($line->update($user) < 0);
	} else {
		$line->fk_cropplan = (int) $object->id;
		$line->fk_operation_type = GETPOST('fk_operation_type', 'aZ09');
		$line->label = GETPOST('label', 'alphanohtml');
		$line->passes = (GETPOST('passes', 'alpha') === '' ? 1 : price2num(GETPOST('passes', 'alpha')));
		$line->area_ha = (GETPOST('area_ha', 'alpha') === '' ? $planarea : price2num(GETPOST('area_ha', 'alpha')));
		$rates = array('cost_manpower_ha', 'cost_machine_ha', 'cost_service_ha', 'cost_material_ha');
		$written = false;
		foreach ($rates as $key) {
			$value = GETPOST($key, 'alpha');
			$written = $written || ($value !== '');
			$line->$key = ($value === '' ? null : price2num($value));
		}
		if ($line->fk_operation_type === '' && $line->label === '') {
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('OperationType')), null, 'errors');
			$error = 1;
		} elseif ($action == 'addline') {
			// Costs per hectare not written: the standard costs of the type of operation
			if (!$written && $line->fk_operation_type !== '' && !$line->setStandardCosts((int) $object->fk_soc, $plandate)) {
				setEventMessages($langs->trans('BudgetNoStandardCost'), null, 'warnings');
			}
			$line->rang = count(dolitraceCropPlanBudget($db, (int) $object->id)['lines']) + 1;
			$error = ($line->create($user) < 0);
		} else {
			$error = ($line->update($user) < 0);
		}
	}
	if ($error) {
		setEventMessages($line->error, $line->errors, 'errors');
	} else {
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id.'#budget');
		exit;
	}
}

// The totals saved on the plan follow the operations, records and harvests
dolitraceCropPlanSyncCosts($db, (int) $object->id);

/*
 * View
 */

$form = new Form($db);
$items = dolitraceCostItems();
$budget = dolitraceCropPlanBudget($db, (int) $object->id);
$actual = dolitraceCropPlanActualCosts($db, (int) $object->id);

$title = $object->ref.' - '.$langs->trans('CropPlanCosts');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolitrace page-card-costs');

$head = dolitracecropplanPrepareHead($object);
print dol_get_fiche_head($head, 'costs', $langs->trans('DoliTraceCropplan'), -1, $object->picto);
$linkback = '<a href="'.dol_buildpath('/dolitrace/dolitracecropplan_list.php', 1).'">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

if ($action == 'deleteline' && $permissiontoadd) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id.'&lineid='.$lineid, $langs->trans('BudgetDeleteLine'), $langs->trans('BudgetConfirmDeleteLine'), 'confirm_deleteline', '', 0, 1);
}

$money = static function ($value) use ($langs, $conf) {
	return price($value, 0, $langs, 1, -1, 2, $conf->currency);
};

print '<div class="fichecenter"><div class="underbanner clearboth"></div>';

// Summary: budget, actual, difference by item
print load_fiche_titre($langs->trans('CropPlanCostsSummary'), '', 'fa-chart-bar');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('CostItem').'</td><td class="right">'.$langs->trans('CostBudget').'</td><td class="right">'.$langs->trans('CostActual').'</td>';
print '<td class="right">'.$langs->trans('CostDifference').'</td><td class="right">'.$langs->trans('CostActualPerHa').'</td></tr>';
foreach ($items + array('total' => 'Total') as $item => $label) {
	$diff = $actual['totals'][$item] - $budget['totals'][$item];
	$class = ($item == 'total' ? ' class="liste_total"' : ' class="oddeven"');
	print '<tr'.$class.'><td>'.($item == 'total' ? '<b>'.$langs->trans('Total').'</b>' : $langs->trans($label)).'</td>';
	print '<td class="right nowraponall">'.$money($budget['totals'][$item]).'</td>';
	print '<td class="right nowraponall">'.$money($actual['totals'][$item]).'</td>';
	print '<td class="right nowraponall'.($budget['totals'][$item] > 0 && $diff > 0 ? ' error' : '').'">'.($diff > 0 ? '+' : '').$money($diff);
	if ($budget['totals'][$item] > 0) {
		print ' <span class="opacitymedium">('.($diff > 0 ? '+' : '').price(round($diff / $budget['totals'][$item] * 100, 1)).' %)</span>';
	}
	print '</td>';
	print '<td class="right nowraponall">'.($planarea > 0 ? $money($actual['totals'][$item] / $planarea) : '').'</td></tr>';
}
print '</table></div>';

// Area, harvested quantity and cost per unit harvested
$harvested = array();
foreach ($actual['harvests'] as $row) {
	if ($row['qty'] > 0) {
		$harvested[$row['unit']] = ($harvested[$row['unit']] ?? 0) + $row['qty'];
	}
}
print '<div class="opacitymedium marginbottomonly">'.$langs->trans('CropPlanArea').': <b>'.($planarea > 0 ? price($planarea, 0, $langs, 1, -1, 4).' ha' : $langs->trans('Unknown')).'</b>';
foreach ($harvested as $unit => $qty) {
	print ' &nbsp; - &nbsp; '.$langs->trans('Harvested').': <b>'.price($qty, 0, $langs, 1, -1, 2).' '.dol_escape_htmltag($unit).'</b>';
	if (count($harvested) == 1 && $qty > 0) {
		print ' &nbsp; - &nbsp; '.$langs->trans('CostPerUnitHarvested', dol_escape_htmltag($unit)).': <b>'.price($actual['totals']['total'] / $qty, 0, $langs, 1, -1, 4, $conf->currency).'</b>';
	}
}
print '</div>';
if ($actual['missing'] > 0) {
	print info_admin($langs->trans('CostMissingInfo', $actual['missing']), 0, 0, 'warning');
}

// Budget: planned operations
print '<a id="budget"></a>';
print load_fiche_titre($langs->trans('CropPlanBudget'), '', 'fa-clipboard-list');
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="'.($action == 'editline' ? 'updateline' : 'addline').'">';
if ($action == 'editline') {
	print '<input type="hidden" name="lineid" value="'.$lineid.'">';
}
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('OperationType').'</td><td>'.$langs->trans('Label').'</td>';
print '<td class="right">'.$form->textwithpicto($langs->trans('BudgetPasses'), $langs->trans('BudgetPassesHelp')).'</td><td class="right">'.$langs->trans('OperationAreaHa').'</td>';
print '<td class="right">'.$langs->trans('BudgetManpowerHa').'</td><td class="right">'.$langs->trans('BudgetMachineHa').'</td>';
print '<td class="right">'.$langs->trans('BudgetServiceHa').'</td><td class="right">'.$form->textwithpicto($langs->trans('BudgetMaterialHa'), $langs->trans('BudgetMaterialHaHelp')).'</td>';
print '<td class="right">'.$langs->trans('Total').'</td><td></td></tr>';

$inputs = static function ($line) use ($form, $optypes, $langs) {
	$out = '<td>'.$form->selectarray('fk_operation_type', $optypes, $line ? $line['type'] : GETPOST('fk_operation_type', 'aZ09'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth150 maxwidth200', 1).'</td>';
	$out .= '<td><input type="text" name="label" class="minwidth100" value="'.dol_escape_htmltag($line ? $line['label'] : '').'"></td>';
	$out .= '<td class="right"><input type="text" name="passes" class="width40 right" value="'.($line ? price($line['passes']) : '1').'"></td>';
	$out .= '<td class="right"><input type="text" name="area_ha" class="width50 right" value="'.($line ? price($line['area']) : '').'" placeholder="'.dol_escape_htmltag($langs->trans('BudgetAreaAuto')).'"></td>';
	foreach (array('cost_manpower_ha' => 'rate_manpower', 'cost_machine_ha' => 'rate_machine', 'cost_service_ha' => 'rate_service', 'cost_material_ha' => 'rate_material') as $name => $key) {
		$out .= '<td class="right"><input type="text" name="'.$name.'" class="width50 right" value="'.($line ? price($line[$key]) : '').'"'.($line ? '' : ' placeholder="'.dol_escape_htmltag($langs->trans('BudgetRateAuto')).'"').'></td>';
	}
	return $out;
};

foreach ($budget['lines'] as $line) {
	if ($action == 'editline' && $lineid == $line['id']) {
		print '<tr class="oddeven">'.$inputs($line).'<td></td>';
		print '<td class="center nowraponall"><input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Save')).'"> <a class="button button-cancel smallpaddingimp" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'#budget">'.$langs->trans('Cancel').'</a></td></tr>';
		continue;
	}
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($optypes[$line['type']] ?? $line['type']).'</td><td>'.dol_escape_htmltag($line['label']).'</td>';
	print '<td class="right">'.price($line['passes']).'</td><td class="right">'.price($line['area'], 0, $langs, 1, -1, 4).'</td>';
	foreach (array('rate_manpower', 'rate_machine', 'rate_service', 'rate_material') as $key) {
		print '<td class="right nowraponall">'.price($line[$key], 0, $langs, 1, -1, 2).'</td>';
	}
	print '<td class="right nowraponall"><b>'.$money($line['total']).'</b></td>';
	print '<td class="center nowraponall">';
	if ($permissiontoadd && $action != 'editline') {
		print '<a class="editfielda reposition marginrightonly" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=editline&lineid='.$line['id'].'#budget">'.img_edit().'</a>';
		print '<a class="reposition marginrightonly" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=reloadrates&lineid='.$line['id'].'&token='.newToken().'#budget" title="'.dol_escape_htmltag($langs->trans('BudgetReloadRates')).'">'.img_picto('', 'refresh').'</a>';
		print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=deleteline&lineid='.$line['id'].'&token='.newToken().'">'.img_delete().'</a>';
	}
	print '</td></tr>';
}
if (empty($budget['lines']) && !$permissiontoadd) {
	print '<tr class="oddeven"><td colspan="10" class="opacitymedium">'.$langs->trans('None').'</td></tr>';
}
if ($permissiontoadd && $action != 'editline') {
	print '<tr class="oddeven nohover">'.$inputs(null).'<td></td><td class="center"><input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Add')).'"></td></tr>';
}
print '<tr class="liste_total"><td colspan="8">'.$langs->trans('Total').'</td><td class="right nowraponall">'.$money($budget['totals']['total']).'</td><td></td></tr>';
print '</table></div></form>';

// Actual costs: operations, records, harvests
print load_fiche_titre($langs->trans('CropPlanActualCosts'), '', 'fa-receipt');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Date').'</td><td>'.$langs->trans('Ref').'</td><td>'.$langs->trans('Type').'</td><td class="right">'.$langs->trans('OperationAreaHa').'</td>';
foreach ($items as $label) {
	print '<td class="right">'.$langs->trans($label).'</td>';
}
print '<td class="right">'.$langs->trans('Total').'</td></tr>';

$rows = array();
$treatmentstatic = new DoliTraceTreatment($db);
$harveststatic = new DoliTraceHarvest($db);
foreach ($actual['operations'] as $row) {
	$operationstatic->id = $row['id'];
	$operationstatic->ref = ($row['ref'] !== '' ? $row['ref'] : $row['label']);
	$operationstatic->status = $row['status'];
	$row['link'] = $operationstatic->getNomUrl(1);
	$row['typelabel'] = $optypes[$row['type']] ?? $row['type'];
	$rows[] = $row;
}
foreach ($actual['treatments'] as $row) {
	$treatmentstatic->id = $row['id'];
	$treatmentstatic->ref = $row['ref'];
	$treatmentstatic->status = $row['status'];
	$treatmentstatic->kind = $row['kind'];
	$row['link'] = $treatmentstatic->getNomUrl(1);
	$row['typelabel'] = $langs->trans($row['kind'] == 2 ? 'CostFertilizationRecord' : 'CostPlantProtectionRecord');
	$rows[] = $row;
}
foreach ($actual['harvests'] as $row) {
	$harveststatic->id = $row['id'];
	$harveststatic->ref = $row['ref'];
	$harveststatic->status = $row['status'];
	$row['link'] = $harveststatic->getNomUrl(1);
	$row['typelabel'] = $langs->trans('CostHarvestRecord');
	$row['area'] = null;
	$rows[] = $row;
}
usort($rows, static function ($a, $b) {
	return ((int) $a['date'] <=> (int) $b['date']);
});
foreach ($rows as $row) {
	print '<tr class="oddeven"><td class="nowraponall">'.dol_print_date($row['date'], 'day').'</td><td class="nowraponall">'.$row['link'];
	if (!empty($row['missing'])) {
		print ' '.img_warning($langs->trans('CostMissingRow'));
	}
	if (isset($row['status']) && $row['status'] == 0) {
		print ' <span class="opacitymedium small">('.$langs->trans('Draft').')</span>';
	}
	print '</td><td>'.dol_escape_htmltag((string) $row['typelabel']).'</td>';
	print '<td class="right">'.($row['area'] !== null ? price($row['area'], 0, $langs, 1, -1, 4) : '').'</td>';
	foreach (array_keys($items) as $item) {
		print '<td class="right nowraponall">'.($row[$item] != 0 ? price($row[$item], 0, $langs, 1, -1, 2) : '').'</td>';
	}
	print '<td class="right nowraponall"><b>'.$money($row['total']).'</b></td></tr>';
}
if (empty($rows)) {
	print '<tr class="oddeven"><td colspan="9" class="opacitymedium">'.$langs->trans('None').'</td></tr>';
}
print '<tr class="liste_total"><td colspan="4">'.$langs->trans('Total').'</td>';
foreach (array_keys($items) as $item) {
	print '<td class="right nowraponall">'.$money($actual['totals'][$item]).'</td>';
}
print '<td class="right nowraponall">'.$money($actual['totals']['total']).'</td></tr>';
print '</table></div>';
print '<div class="opacitymedium small">'.$langs->trans('CropPlanCostsHelp').'</div>';

print '</div>';
print dol_get_fiche_end();

llxFooter();
$db->close();
