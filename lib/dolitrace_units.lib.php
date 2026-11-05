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
 * \file    lib/dolitrace_units.lib.php
 * \ingroup dolitrace
 * \brief   Units of the harvested quantities: the units dictionary of Dolibarr (llx_c_units)
 *
 * The list is made of the active units of weight, volume and quantity (pieces, crates, bins...) of the dictionary
 * "Units" of Dolibarr, which the administrator can complete; doliTrace adds quintal, hectolitre, crate and bin
 * (sql/data_dolitrace_units.sql). The imperial units are left out. A harvest keeps the short label of the unit
 * (kg, q, L, hl, cass., ...), readable in registers and documents as it is. Weights and volumes are converted
 * between them for the stock movement, in the unit of the product.
 */

/**
 * Units that can be chosen for a quantity, keyed by short label
 *
 * @param	DoliDB	$db		Database handler
 * @return	array<string,array{code:string,label:string,type:string,scale:int}>
 */
function dolitraceUnitList($db)
{
	global $langs;
	static $cache = null;

	if ($cache !== null) {
		return $cache;
	}
	$cache = array();
	// Labels of the units of Dolibarr: other.lang (weights, volumes) and products.lang (pieces, sets)
	$langs->loadLangs(array('other', 'products'));
	$sql = "SELECT code, label, short_label, unit_type, scale FROM ".$db->prefix()."c_units";
	$sql .= " WHERE active = 1 AND unit_type IN ('weight', 'volume', 'qty') AND short_label IS NOT NULL AND short_label <> ''";
	// Imperial units (scale 88 and more) left out, except the litre (scale 98 in Dolibarr)
	$sql .= " AND (scale IS NULL OR scale < 88 OR code = 'L')";
	$sql .= " ORDER BY CASE unit_type WHEN 'weight' THEN 1 WHEN 'volume' THEN 2 ELSE 3 END, scale, sortorder, label";
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return $cache;
	}
	while ($obj = $db->fetch_object($resql)) {
		$short = (string) $obj->short_label;
		if (isset($cache[$short])) {
			continue;
		}
		// The litre is 1 dm3 for the conversions
		$scale = ($obj->code == 'L' ? -3 : (int) $obj->scale);
		$cache[$short] = array('code' => (string) $obj->code, 'label' => $langs->trans((string) $obj->label), 'type' => (string) $obj->unit_type, 'scale' => $scale);
	}
	$db->free($resql);
	return $cache;
}

/**
 * Options of a drop-down list of units: short label => label (short label)
 *
 * @param	DoliDB	$db		Database handler
 * @return	array<string,string>
 */
function dolitraceUnitOptions($db)
{
	$options = array();
	foreach (dolitraceUnitList($db) as $short => $unit) {
		$options[$short] = ($unit['label'] != $short ? $unit['label'].' ('.$short.')' : $short);
	}
	return $options;
}

/**
 * Unit of the list from a text: short label (also with other case), code or label
 *
 * @param	DoliDB	$db		Database handler
 * @param	string	$text	Text
 * @return	string			Short label of the unit, '' if not found
 */
function dolitraceUnitFind($db, $text)
{
	$text = trim((string) $text);
	if ($text === '') {
		return '';
	}
	$units = dolitraceUnitList($db);
	if (isset($units[$text])) {
		return $text;
	}
	foreach ($units as $short => $unit) {
		if (strcasecmp($short, $text) == 0 || strcasecmp($unit['code'], $text) == 0 || strcasecmp($unit['label'], $text) == 0) {
			return $short;
		}
	}
	return '';
}

/**
 * Unit of a product (field "Unit" of the product), as short label of the list
 *
 * @param	DoliDB	$db			Database handler
 * @param	int		$productid	Id of the product
 * @return	string				Short label, '' when the product has no unit or one not in the list
 */
function dolitraceProductUnit($db, $productid)
{
	if ($productid <= 0) {
		return '';
	}
	$sql = "SELECT u.code FROM ".$db->prefix()."product as p INNER JOIN ".$db->prefix()."c_units as u ON u.rowid = p.fk_unit";
	$sql .= " WHERE p.rowid = ".((int) $productid);
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return '';
	}
	$obj = $db->fetch_object($resql);
	$db->free($resql);
	return $obj ? dolitraceUnitFind($db, (string) $obj->code) : '';
}

/**
 * Convert a quantity between two units of the list: same unit, or weights, or volumes
 *
 * @param	DoliDB	$db		Database handler
 * @param	float	$qty	Quantity
 * @param	string	$from	Unit of the quantity (short label)
 * @param	string	$to		Unit wanted (short label)
 * @return	float|null		Quantity in the unit wanted, null when the units cannot be converted
 */
function dolitraceUnitConvert($db, $qty, $from, $to)
{
	if ($from === $to) {
		return (float) $qty;
	}
	$units = dolitraceUnitList($db);
	if (!isset($units[$from]) || !isset($units[$to]) || $units[$from]['type'] != $units[$to]['type'] || $units[$from]['type'] == 'qty') {
		return null;
	}
	return (float) $qty * pow(10, $units[$from]['scale'] - $units[$to]['scale']);
}
