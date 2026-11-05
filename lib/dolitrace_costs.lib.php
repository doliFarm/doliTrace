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
 * \file    lib/dolitrace_costs.lib.php
 * \ingroup dolitrace
 * \brief   Costs of a production plan: budget (planned operations) and actual costs (operations, treatments, harvests)
 *
 * Items of cost: material (products), labour, machines and fuel (field cost_energy), services of third parties.
 * Actual costs:
 * - operations of the plan (draft and validated), with the costs written or computed from the standard costs;
 * - plant protection and fertilization records (draft and validated, not replaced nor cancelled) on areas of the
 *   plan: products at the average price, divided by the treated areas, and application at the standard cost of
 *   the type TT_Pesticide or TT_Fertiliser by the treated hectares of the plan;
 * - harvests of the plan (not cancelled), with the costs written in them.
 * The budget is made of planned operations: passes x area x costs per hectare.
 */

dol_include_once('/dolitrace/lib/dolitrace_treatment.lib.php');
dol_include_once('/dolitrace/lib/dolitrace_units.lib.php');

/**
 * Items of cost, in order: key => label
 *
 * @return	array<string,string>
 */
function dolitraceCostItems()
{
	return array('material' => 'Costmaterial', 'manpower' => 'Costmanpower', 'machine' => 'Costenergy', 'service' => 'Costservice');
}

/**
 * Empty totals by item
 *
 * @return	array<string,float>
 */
function dolitraceCostZero()
{
	return array('material' => 0.0, 'manpower' => 0.0, 'machine' => 0.0, 'service' => 0.0, 'total' => 0.0);
}

/**
 * Area of a production plan: area conducted of the plot, or its total area
 *
 * @param	stdClass|null	$cropplan	Crop plan from dolitraceTreatmentCropPlan()
 * @return	float						Hectares, 0 when unknown
 */
function dolitraceCropPlanArea($cropplan)
{
	if (!$cropplan) {
		return 0.0;
	}
	return ((float) $cropplan->size_conducted > 0 ? (float) $cropplan->size_conducted : (float) $cropplan->size_total);
}

/**
 * Standard cost of a type of operation, when doliFarm gives it
 *
 * @param	DoliDB		$db			Database handler
 * @param	string		$optype		Type of operation
 * @param	int			$socid		Farm
 * @param	?int		$date		Date
 * @return	?array<string,mixed>
 */
function dolitraceStandardCost($db, $optype, $socid, ?int $date = null)
{
	if (!dol_include_once('/dolifarm/lib/dolifarm_costs.lib.php') || !function_exists('dolifarmStandardCost')) {
		return null;
	}
	return dolifarmStandardCost($db, $optype, $socid, $date);
}

/**
 * Cost of a quantity of a product at its average price, in the unit of the product
 *
 * @param	DoliDB		$db			Database handler
 * @param	int			$productid	Product
 * @param	float		$qty		Quantity
 * @param	string		$unit		Unit of the quantity (kg, l, t...), '' when it is the unit of the product
 * @return	?float					Cost, null when the product has no price
 */
function dolitraceProductCost($db, $productid, $qty, $unit = '')
{
	if ($productid <= 0 || !dol_include_once('/dolifarm/lib/dolifarm_costs.lib.php') || !function_exists('dolifarmProductUnitCost')) {
		return null;
	}
	$unitcost = dolifarmProductUnitCost($db, (int) $productid);
	if ($unitcost === null) {
		return null;
	}
	$from = dolitraceUnitFind($db, (string) $unit);
	$to = dolitraceProductUnit($db, (int) $productid);
	if ($from !== '' && $to !== '' && $from !== $to) {
		$converted = dolitraceUnitConvert($db, (float) $qty, $from, $to);
		if ($converted !== null) {
			$qty = $converted;
		}
	}
	return (float) $qty * $unitcost;
}

/**
 * Actual costs of a production plan
 *
 * @param	DoliDB	$db				Database handler
 * @param	int		$cropplanid		Production plan
 * @return	array{area:float,operations:array<int,array<string,mixed>>,treatments:array<int,array<string,mixed>>,harvests:array<int,array<string,mixed>>,totals:array<string,float>,missing:int}
 */
function dolitraceCropPlanActualCosts($db, $cropplanid)
{
	$cropplan = dolitraceTreatmentCropPlan($db, (int) $cropplanid);
	$result = array('area' => dolitraceCropPlanArea($cropplan), 'operations' => array(), 'treatments' => array(), 'harvests' => array(), 'totals' => dolitraceCostZero(), 'missing' => 0);
	if (!$cropplan) {
		return $result;
	}
	$prefix = $db->prefix();
	$add = static function (&$totals, $row) {
		foreach (array('material', 'manpower', 'machine', 'service') as $item) {
			$totals[$item] += $row[$item];
		}
		$totals['total'] += $row['total'];
	};

	// Operations
	$sql = "SELECT rowid, ref, label, date_op, fk_operation_type, status, area_ha, cost_material, cost_manpower, cost_energy, cost_service";
	$sql .= " FROM ".$prefix."dolitrace_operation WHERE fk_cropplan = ".((int) $cropplanid)." AND entity IN (".getEntity('dolitraceoperation').")";
	$sql .= " AND (status IS NULL OR status IN (0, 1)) ORDER BY date_op, rowid";
	$resql = $db->query($sql);
	while ($resql && ($obj = $db->fetch_object($resql))) {
		$row = array('id' => (int) $obj->rowid, 'ref' => (string) $obj->ref, 'label' => (string) $obj->label, 'date' => $db->jdate($obj->date_op),
			'type' => (string) $obj->fk_operation_type, 'status' => (int) $obj->status, 'area' => ($obj->area_ha === null ? null : (float) $obj->area_ha),
			'material' => (float) $obj->cost_material, 'manpower' => (float) $obj->cost_manpower, 'machine' => (float) $obj->cost_energy, 'service' => (float) $obj->cost_service);
		$row['total'] = $row['material'] + $row['manpower'] + $row['machine'] + $row['service'];
		if ($obj->cost_material === null && $obj->cost_manpower === null && $obj->cost_energy === null && $obj->cost_service === null) {
			$row['missing'] = 1;
			$result['missing']++;
		}
		$result['operations'][] = $row;
		$add($result['totals'], $row);
	}

	// Plant protection and fertilization records on areas of the plan
	$sql = "SELECT t.rowid, t.ref, t.kind, t.status, t.date_start, t.fk_soc,";
	$sql .= " SUM(CASE WHEN tt.fk_cropplan = ".((int) $cropplanid)." AND tt.treated_unit = 'HA' THEN tt.treated_qty WHEN tt.fk_cropplan = ".((int) $cropplanid)." AND tt.treated_unit = 'M2' THEN tt.treated_qty / 10000 ELSE 0 END) as area_plan,";
	$sql .= " SUM(CASE WHEN tt.treated_unit = 'HA' THEN tt.treated_qty WHEN tt.treated_unit = 'M2' THEN tt.treated_qty / 10000 ELSE 0 END) as area_all,";
	$sql .= " SUM(CASE WHEN tt.fk_cropplan = ".((int) $cropplanid)." THEN 1 ELSE 0 END) as nb_plan, COUNT(tt.rowid) as nb_all";
	$sql .= " FROM ".$prefix."dolitrace_treatment as t INNER JOIN ".$prefix."dolitrace_treatment_target as tt ON tt.fk_treatment = t.rowid";
	$sql .= " WHERE t.entity IN (".getEntity('dolitracetreatment').") AND t.status IN (0, 1)";
	$sql .= " AND t.rowid IN (SELECT fk_treatment FROM ".$prefix."dolitrace_treatment_target WHERE fk_cropplan = ".((int) $cropplanid).")";
	$sql .= " GROUP BY t.rowid, t.ref, t.kind, t.status, t.date_start, t.fk_soc ORDER BY t.date_start, t.rowid";
	$resql = $db->query($sql);
	$treatments = array();
	while ($resql && ($obj = $db->fetch_object($resql))) {
		$treatments[] = $obj;
	}
	foreach ($treatments as $obj) {
		$areaplan = (float) $obj->area_plan;
		if ($areaplan <= 0) {
			$areaplan = $result['area'];
		}
		// Share of the record that belongs to the plan: treated hectares, or number of areas when they have no hectares
		$share = ((float) $obj->area_all > 0 ? (float) $obj->area_plan / (float) $obj->area_all : ((int) $obj->nb_all > 0 ? (int) $obj->nb_plan / (int) $obj->nb_all : 1));
		$row = array('id' => (int) $obj->rowid, 'ref' => (string) $obj->ref, 'kind' => (int) $obj->kind, 'status' => (int) $obj->status, 'date' => $db->jdate($obj->date_start),
			'area' => $areaplan, 'material' => 0.0, 'manpower' => 0.0, 'machine' => 0.0, 'service' => 0.0, 'missing' => 0);
		$resql2 = $db->query("SELECT fk_product, qty_total, qty_unit FROM ".$prefix."dolitrace_treatment_product WHERE fk_treatment = ".((int) $obj->rowid));
		while ($resql2 && ($product = $db->fetch_object($resql2))) {
			$cost = ((float) $product->qty_total > 0 ? dolitraceProductCost($db, (int) $product->fk_product, (float) $product->qty_total, (string) $product->qty_unit) : null);
			if ($cost === null) {
				$row['missing'] = 1;
				continue;
			}
			$row['material'] += $cost * $share;
		}
		$rate = dolitraceStandardCost($db, ((int) $obj->kind == 2 ? 'TT_Fertiliser' : 'TT_Pesticide'), (int) $obj->fk_soc, $db->jdate($obj->date_start));
		if ($rate && $areaplan > 0) {
			$row['manpower'] = $rate['manpower'] * $areaplan;
			$row['machine'] = $rate['machine'] * $areaplan;
			$row['service'] = $rate['service'] * $areaplan;
		} else {
			$row['missing'] = 1;
		}
		$row['total'] = $row['material'] + $row['manpower'] + $row['machine'] + $row['service'];
		$result['missing'] += $row['missing'];
		$result['treatments'][] = $row;
		$add($result['totals'], $row);
	}

	// Harvests
	$sql = "SELECT rowid, ref, date_harvest, qty_harvested, qty_unit, status, cost_material, cost_manpower, cost_energy";
	$sql .= " FROM ".$prefix."dolitrace_harvest WHERE fk_cropplan = ".((int) $cropplanid)." AND entity IN (".getEntity('dolitraceharvest').")";
	$sql .= " AND (status IS NULL OR status <> 9) ORDER BY date_harvest, rowid";
	$resql = $db->query($sql);
	while ($resql && ($obj = $db->fetch_object($resql))) {
		$row = array('id' => (int) $obj->rowid, 'ref' => (string) $obj->ref, 'date' => $db->jdate($obj->date_harvest), 'status' => (int) $obj->status,
			'qty' => (float) $obj->qty_harvested, 'unit' => (string) $obj->qty_unit,
			'material' => (float) $obj->cost_material, 'manpower' => (float) $obj->cost_manpower, 'machine' => (float) $obj->cost_energy, 'service' => 0.0);
		$row['total'] = $row['material'] + $row['manpower'] + $row['machine'];
		$result['harvests'][] = $row;
		$add($result['totals'], $row);
	}
	return $result;
}

/**
 * Budget of a production plan: planned operations
 *
 * @param	DoliDB	$db				Database handler
 * @param	int		$cropplanid		Production plan
 * @return	array{lines:array<int,array<string,mixed>>,totals:array<string,float>}
 */
function dolitraceCropPlanBudget($db, $cropplanid)
{
	$result = array('lines' => array(), 'totals' => dolitraceCostZero());
	$sql = "SELECT rowid, rang, fk_operation_type, label, passes, area_ha, cost_manpower_ha, cost_machine_ha, cost_service_ha, cost_material_ha";
	$sql .= " FROM ".$db->prefix()."dolitrace_cropplan_budget WHERE fk_cropplan = ".((int) $cropplanid)." AND entity IN (".getEntity('dolitracecropplan').")";
	$sql .= " ORDER BY rang, rowid";
	$resql = $db->query($sql);
	while ($resql && ($obj = $db->fetch_object($resql))) {
		$factor = (float) $obj->passes * (float) $obj->area_ha;
		$row = array('id' => (int) $obj->rowid, 'type' => (string) $obj->fk_operation_type, 'label' => (string) $obj->label, 'passes' => (float) $obj->passes, 'area' => (float) $obj->area_ha,
			'rate_material' => (float) $obj->cost_material_ha, 'rate_manpower' => (float) $obj->cost_manpower_ha, 'rate_machine' => (float) $obj->cost_machine_ha, 'rate_service' => (float) $obj->cost_service_ha,
			'material' => $factor * (float) $obj->cost_material_ha, 'manpower' => $factor * (float) $obj->cost_manpower_ha,
			'machine' => $factor * (float) $obj->cost_machine_ha, 'service' => $factor * (float) $obj->cost_service_ha);
		$row['total'] = $row['material'] + $row['manpower'] + $row['machine'] + $row['service'];
		$result['lines'][] = $row;
		foreach (array('material', 'manpower', 'machine', 'service', 'total') as $item) {
			$result['totals'][$item] += $row[$item];
		}
	}
	return $result;
}

/**
 * Save the actual costs on the production plan (fields cost_material, cost_manpower, cost_energy, cost_service,
 * actual_cost), used by the lists and by the history of the plots
 *
 * @param	DoliDB	$db				Database handler
 * @param	int		$cropplanid		Production plan
 * @return	int						>0 if OK, <0 if KO
 */
function dolitraceCropPlanSyncCosts($db, $cropplanid)
{
	if ($cropplanid <= 0) {
		return 0;
	}
	$totals = dolitraceCropPlanActualCosts($db, (int) $cropplanid)['totals'];
	$sql = "UPDATE ".$db->prefix()."dolitrace_cropplan SET";
	$sql .= " cost_material = ".((float) price2num($totals['material'], 'MT')).", cost_manpower = ".((float) price2num($totals['manpower'], 'MT')).",";
	$sql .= " cost_energy = ".((float) price2num($totals['machine'], 'MT')).", cost_service = ".((float) price2num($totals['service'], 'MT')).",";
	$sql .= " actual_cost = ".((float) price2num($totals['total'], 'MT'));
	$sql .= " WHERE rowid = ".((int) $cropplanid);
	if (!$db->query($sql)) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return -1;
	}
	return 1;
}
