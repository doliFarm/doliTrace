<?php
/* Copyright (C) 2026		SuperAdmin
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
 * \file    dolitrace/lib/dolitrace.lib.php
 * \ingroup dolitrace
 * \brief   Library files with common functions for Dolitrace
 */

/**
 * Prepare admin pages header
 *
 * @return array<array{string,string,string}>
 */
function dolitraceAdminPrepareHead()
{
	global $langs, $conf;

	// global $db;
	// $extrafields = new ExtraFields($db);
	// $extrafields->fetch_name_optionals_label('myobject');

	$langs->load("dolitrace@dolitrace");

	$h = 0;
	$head = array();

	$head[$h][0] = dolBuildUrl(dol_buildpath("/dolitrace/admin/setup.php", 1));
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	/*
	$head[$h][0] = dolBuildUrl(dol_buildpath("/dolitrace/admin/myobject_extrafields.php", 1));
	$head[$h][1] = $langs->trans("ExtraFields");
	$nbExtrafields = (isset($extrafields->attributes['myobject']['label']) && is_countable($extrafields->attributes['myobject']['label'])) ? count($extrafields->attributes['myobject']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'myobject_extrafields';
	$h++;

	$head[$h][0] = dolBuildUrl(dol_buildpath("/dolitrace/admin/myobjectline_extrafields.php", 1));
	$head[$h][1] = $langs->trans("ExtraFieldsLines");
	$nbExtrafields = (isset($extrafields->attributes['myobjectline']['label']) && is_countable($extrafields->attributes['myobjectline']['label'])) ? count($extrafields->attributes['myobject']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'myobject_extrafieldsline';
	$h++;
	*/

	$head[$h][0] = dolBuildUrl(dol_buildpath("/dolitrace/admin/about.php", 1));
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@dolitrace:/dolitrace/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@dolitrace:/dolitrace/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, null, $head, $h, 'dolitrace@dolitrace');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'dolitrace@dolitrace', 'remove');

	return $head;
}


/** dolitrace_calculate_harvest_expiry(obj)
 * Calcola la data di scadenza (Eat-by / Sell-by date) stimata per un raccolto
 * basandosi sul piano colturale.
 *
 * @param   Object  $cropplan_obj   L'oggetto CropPlan
 * @return  int                     Timestamp della data di scadenza
 */
function dolitrace_calculate_harvest_expiry($cropplan_obj)
{
    // LOGICA DI CALCOLO:
    // 1. Partiamo dalla data di fine piano (raccolta stimata)
    // 2. Aggiungiamo una "Shelf Life" standard (es. 6 mesi)
    // TODO FUTURE: Recuperare la shelf_life specifica dalla tabella llx_dolifarm_crops
    
    $base_date = !empty($cropplan_obj->date_end) ? $cropplan_obj->date_end : dol_now();
    
    // Default: 6 mesi (shelf life media conservativa per prodotti secchi/trasformati)
    // Se fosse fresco sarebbe molto meno, ma qui parliamo di lotti di produzione agricola
    $expiry_date = strtotime('+6 months', $base_date);
    
    return $expiry_date;
}
