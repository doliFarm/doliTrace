<?php
/* Copyright (C) 2026      SuperAdmin
 * Copyright (C) 2025       Frédéric France         <frederic.france@free.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    htdocs/dolitrace/lib/dolitrace_treatment.lib.php
 * \ingroup dolitrace
 * \brief   Library files with common functions for DoliTraceTreatment
 */

/**
 * Prepare array of tabs for DoliTraceTreatment
 *
 * @param	DoliTraceTreatment	$object					DoliTraceTreatment
 * @return 	array<array{string,string,string}>	Array of tabs
 */
function dolitracetreatmentPrepareHead($object)
{
	global $db, $langs, $conf;

	$langs->load("dolitrace@dolitrace");

	$showtabofpagenote = 1;
	$showtabofpagedocument = 1;
	$showtabofpageagenda = 1;

	$h = 0;
	$head = array();

	$head[$h][0] = dolBuildUrl(dol_buildpath("/dolitrace/dolitracetreatment_card.php", 1), ['id' => $object->id]);
	$head[$h][1] = $langs->trans("TreatmentRecord");
	$head[$h][2] = 'card';
	$h++;


	// BEGIN MODULEBUILDER TAB NOTE
	if ($showtabofpagenote) {
		if (isset($object->fields['note_public']) || isset($object->fields['note_private'])) {
			$nbNote = 0;
			if (!empty($object->note_private)) {
				$nbNote++;
			}
			if (!empty($object->note_public)) {
				$nbNote++;
			}
			$head[$h][0] = dolBuildUrl(dol_buildpath('/dolitrace/dolitracetreatment_note.php', 1), ['id' => $object->id]);
			$head[$h][1] = $langs->trans('Notes');
			if ($nbNote > 0) {
				$head[$h][1] .= (!getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER') ? '<span class="badge marginleftonlyshort">'.$nbNote.'</span>' : '');
			}
			$head[$h][2] = 'note';
			$h++;
		}
	}
	// END MODULEBUILDER TAB NOTE

	// BEGIN MODULEBUILDER TAB DOCUMENT
	if ($showtabofpagedocument) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';
		$upload_dir = $conf->dolitrace->dir_output."/dolitracetreatment/".dol_sanitizeFileName($object->ref);
		$nbFiles = count(dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
		$nbLinks = Link::count($db, $object->element, $object->id);
		$head[$h][0] = dolBuildUrl(dol_buildpath("/dolitrace/dolitracetreatment_document.php", 1), ['id' => $object->id]);
		$head[$h][1] = $langs->trans('Documents');
		if (($nbFiles + $nbLinks) > 0) {
			$head[$h][1] .= '<span class="badge marginleftonlyshort">'.($nbFiles + $nbLinks).'</span>';
		}
		$head[$h][2] = 'document';
		$h++;
	}
	// END MODULEBUILDER TAB DOCUMENT

	// BEGIN MODULEBUILDER TAB AGENDA
	if ($showtabofpageagenda) {
		$head[$h][0] = dolBuildUrl(dol_buildpath("/dolitrace/dolitracetreatment_agenda.php", 1), ['id' => $object->id]);
		$head[$h][1] = $langs->trans("Events");
		$head[$h][2] = 'agenda';
		$h++;
	}
	// END MODULEBUILDER TAB AGENDA

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@dolitrace:/dolitrace/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@dolitrace:/dolitrace/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'dolitracetreatment@dolitrace');

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'dolitracetreatment@dolitrace', 'remove');

	return $head;
}

/**
 * Rows of a query, by rowid
 *
 * @param	DoliDB		$db		Database handler
 * @param	string		$sql	Query, with a column rowid
 * @return	array<int,stdClass>	Rows (empty on error)
 */
function dolitraceTreatmentFetchRows($db, $sql)
{
	$rows = array();
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return $rows;
	}
	while ($obj = $db->fetch_object($resql)) {
		$rows[(int) $obj->rowid] = $obj;
	}
	$db->free($resql);
	return $rows;
}

/**
 * Crop plans of the farm in progress at the date of the record: they give plot and crop of the areas
 *
 * @param	DoliDB				$db		Database handler
 * @param	DoliTraceTreatment	$object	Record of the register
 * @return	array<int,stdClass>			Crop plans with plot and crop
 */
function dolitraceTreatmentCropPlans($db, $object)
{
	if (empty($object->fk_soc) || empty($object->date_start)) {
		return array();
	}
	$day = dol_print_date($object->date_start, '%Y-%m-%d');
	$sql = "SELECT cp.rowid, cp.ref, cp.fk_plot, cp.fk_crop, p.ref as plot_ref, p.label as plot_label, p.size_conducted, p.size_total,";
	$sql .= " p.iacs_parcel, p.cadastral_reference, p.zone_zvn, c.label as crop_label, c.eppo_code";
	$sql .= " FROM ".$db->prefix()."dolitrace_cropplan as cp";
	$sql .= " INNER JOIN ".$db->prefix()."dolifarm_plot as p ON p.rowid = cp.fk_plot";
	$sql .= " INNER JOIN ".$db->prefix()."dolifarm_crops as c ON c.rowid = cp.fk_crop";
	$sql .= " WHERE cp.entity IN (".getEntity('dolitracecropplan').")";
	$sql .= " AND cp.fk_soc = ".((int) $object->fk_soc);
	$sql .= " AND cp.status IN (1, 2)";
	$sql .= " AND (cp.date_start IS NULL OR cp.date_start <= '".$db->escape($day)."')";
	$sql .= " AND (cp.date_end IS NULL OR cp.date_end >= '".$db->escape($day)."')";
	$sql .= " ORDER BY p.ref, c.label";
	return dolitraceTreatmentFetchRows($db, $sql);
}

/**
 * Plant protection products in use
 *
 * @param	DoliDB		$db		Database handler
 * @return	array<int,stdClass>	Products
 */
function dolitraceTreatmentAgroDrugs($db)
{
	$sql = "SELECT t.rowid, t.ref, t.label, t.reg_number, t.fk_product, t.max_dose_ha";
	$sql .= " FROM ".$db->prefix()."dolifarm_agrodrug as t";
	$sql .= " WHERE t.entity IN (".getEntity('dolifarmagrodrug').") AND t.status = 1";
	$sql .= " ORDER BY t.label";
	return dolitraceTreatmentFetchRows($db, $sql);
}

/**
 * Fertilizers in use
 *
 * @param	DoliDB		$db		Database handler
 * @return	array<int,stdClass>	Fertilizers
 */
function dolitraceTreatmentFertilizers($db)
{
	$sql = "SELECT t.rowid, t.ref, t.label, t.registration_number, t.fk_product, t.n_content, t.p2o5_content, t.k2o_content";
	$sql .= " FROM ".$db->prefix()."dolifarm_fertilizer as t";
	$sql .= " WHERE t.entity IN (".getEntity('dolifarmfertilizer').") AND t.status = 1";
	$sql .= " ORDER BY t.label";
	return dolitraceTreatmentFetchRows($db, $sql);
}

/**
 * Adversities of the dictionary
 *
 * @param	DoliDB		$db		Database handler
 * @return	array<int,stdClass>	Adversities
 */
function dolitraceTreatmentAdversities($db)
{
	$sql = "SELECT t.rowid, t.code, t.label, t.category";
	$sql .= " FROM ".$db->prefix()."dolitrace_c_adversity as t";
	$sql .= " WHERE t.active = 1";
	$sql .= " ORDER BY t.label";
	return dolitraceTreatmentFetchRows($db, $sql);
}

/**
 * Units of dose for a kind and a technical type of record
 *
 * @param	DoliDB		$db					Database handler
 * @param	int			$kind				1 plant protection, 2 fertilization
 * @param	int			$technical_type		1 surfaces, 2 closed spaces, 3 seeds
 * @return	array<int,stdClass>				Units
 */
function dolitraceTreatmentDoseUnits($db, $kind, $technical_type)
{
	$sql = "SELECT t.rowid, t.code, t.label, t.basis, t.qty_unit, t.factor";
	$sql .= " FROM ".$db->prefix()."dolitrace_c_dose_unit as t";
	$sql .= " WHERE t.active = 1";
	$sql .= " AND t.kind IN (0, ".((int) $kind).")";
	$sql .= " AND t.technical_type IN (0, ".((int) $technical_type).")";
	$sql .= " ORDER BY t.position, t.label";
	return dolitraceTreatmentFetchRows($db, $sql);
}

/**
 * Buildings of the farm
 *
 * @param	DoliDB		$db		Database handler
 * @param	int			$socid	Third party of the farm
 * @return	array<int,stdClass>	Buildings
 */
function dolitraceTreatmentBuildings($db, $socid)
{
	$sql = "SELECT t.rowid, t.ref, t.label, t.surface_covered, t.volume, t.cadastral_comune, t.cadastral_sheet, t.cadastral_parcel";
	$sql .= " FROM ".$db->prefix()."dolifarm_building as t";
	$sql .= " WHERE t.entity IN (".getEntity('dolifarmbuilding').") AND t.status = 1";
	$sql .= " AND t.fk_soc = ".((int) $socid);
	$sql .= " ORDER BY t.ref";
	return dolitraceTreatmentFetchRows($db, $sql);
}

/**
 * Treated quantity of the record in the basis of a unit of dose
 *
 * The areas are converted to the basis (ha and m², kg and t). For the basis HL the quantity is the volume of water (l/ha) by the treated hectares.
 *
 * @param	DoliTraceTreatment	$object		Record with its areas
 * @param	string				$basis		HA, M2, M3, HL, KG or T
 * @return	float|null						Quantity, null when no area is in a compatible unit
 */
function dolitraceTreatmentQtyInBasis($object, $basis)
{
	if ($basis == 'HL') {
		// Volume of water in l/ha: hectolitres = l/ha / 100 x treated hectares
		$hectares = dolitraceTreatmentQtyInBasis($object, 'HA');
		return ($object->water_volume > 0 && $hectares > 0) ? $object->water_volume / 100 * $hectares : null;
	}
	$conversions = array(
		'HA' => array('HA' => 1, 'M2' => 0.0001),
		'M2' => array('M2' => 1, 'HA' => 10000),
		'M3' => array('M3' => 1),
		'KG' => array('KG' => 1, 'T' => 1000),
		'T' => array('T' => 1, 'KG' => 0.001),
		'KSEED' => array('KSEED' => 1),
	);
	if (empty($conversions[$basis])) {
		return null;
	}
	$qty = null;
	foreach ($object->targets as $target) {
		if ($target->treated_qty === null || $target->treated_qty === '' || !isset($conversions[$basis][$target->treated_unit])) {
			continue;
		}
		$qty = (float) $qty + (float) $target->treated_qty * $conversions[$basis][$target->treated_unit];
	}
	return $qty;
}
