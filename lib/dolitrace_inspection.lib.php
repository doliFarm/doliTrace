<?php
/* Copyright (C) 2026		Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
 * \file    lib/dolitrace_inspection.lib.php
 * \ingroup dolitrace
 * \brief   Inspections of the organic control body: tabs, and freeze of the registers of a farm up to the date of
 *          the last closed inspection
 *
 * Frozen: crop operations, harvests and the draft records of the treatment register dated on or before the date of the
 * inspection cannot be created, changed, validated, set back to draft or deleted. A validated treatment record is
 * changed only with its correction (a new version, traced) or its traced cancellation, as before. The periods of the
 * organic status of the plots (doliFarm) follow the same rule.
 */

/**
 * Last closed inspection of a farm: the registers are frozen up to its date (included)
 *
 * @param	DoliDB	$db			Database handler
 * @param	int		$socid		Farm
 * @param	bool	$reset		Empty the cache (after a closure or a reopening)
 * @return	?stdClass			Object with id, ref, date (timestamp of 23:59:59 of the day of the inspection), null if none
 */
function dolitraceFrozenInspection($db, $socid, $reset = false)
{
	static $cache = array();
	if ($reset) {
		$cache = array();
	}
	$socid = (int) $socid;
	if ($socid <= 0) {
		return null;
	}
	if (!array_key_exists($socid, $cache)) {
		$cache[$socid] = null;
		$sql = "SELECT rowid, ref, date_inspection FROM ".$db->prefix()."dolitrace_bioinspection";
		$sql .= " WHERE fk_soc = ".$socid." AND status = 1 AND entity IN (".getEntity('dolitracebioinspection').")";
		$sql .= " ORDER BY date_inspection DESC, rowid DESC";
		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				$day = $db->jdate($obj->date_inspection);
				$cache[$socid] = (object) array(
					'id' => (int) $obj->rowid, 'ref' => (string) $obj->ref, 'day' => $day,
					'date' => dol_mktime(23, 59, 59, (int) dol_print_date($day, '%m'), (int) dol_print_date($day, '%d'), (int) dol_print_date($day, '%Y')),
				);
			}
			$db->free($resql);
		}
	}
	return $cache[$socid];
}

/**
 * Timestamp of a date given as timestamp or string
 *
 * @param	mixed	$date	Date
 * @return	?int			Timestamp, null if empty
 */
function dolitraceFrozenTimestamp($date)
{
	if ($date === null || $date === '' || $date === false) {
		return null;
	}
	if (is_numeric($date)) {
		return (int) $date;
	}
	$ts = dol_stringtotime((string) $date);
	return $ts ? (int) $ts : null;
}

/**
 * Check that a date of a farm is not frozen by an inspection
 *
 * @param	DoliDB		$db			Database handler
 * @param	int			$socid		Farm
 * @param	mixed		$date		Date (timestamp or string)
 * @param	string		$error		Error message (translated) when frozen
 * @return	bool					True if the date can be changed
 */
function dolitraceFrozenDateAllowed($db, $socid, $date, &$error)
{
	global $langs;

	$ts = dolitraceFrozenTimestamp($date);
	if ($ts === null) {
		return true;
	}
	$inspection = dolitraceFrozenInspection($db, (int) $socid);
	if ($inspection && $ts <= $inspection->date) {
		$langs->load('dolitrace@dolitrace');
		$error = $langs->trans('ErrorDolitraceFrozen', dol_print_date($ts, 'day'), $inspection->ref, dol_print_date($inspection->day, 'day'));
		return false;
	}
	return true;
}

/**
 * Farm and date of an object of the registers, as given and as stored
 *
 * @param	CommonObject	$object		Treatment record, crop operation or harvest
 * @return	array<int,array{0:int,1:mixed}>		Pairs farm, date
 */
function dolitraceFrozenPairs($object)
{
	$db = $object->db;
	$prefix = $db->prefix();
	$pairs = array();
	$cropplanfarm = function ($cropplanid) use ($db, $prefix) {
		if ((int) $cropplanid <= 0) {
			return 0;
		}
		$resql = $db->query("SELECT fk_soc FROM ".$prefix."dolitrace_cropplan WHERE rowid = ".((int) $cropplanid));
		$obj = $resql ? $db->fetch_object($resql) : null;
		return $obj ? (int) $obj->fk_soc : 0;
	};
	switch ($object->element) {
		case 'dolitracetreatment':
			$pairs[] = array((int) $object->fk_soc, $object->date_start);
			if ($object->id > 0) {
				$resql = $db->query("SELECT fk_soc, date_start FROM ".$prefix."dolitrace_treatment WHERE rowid = ".((int) $object->id));
				if ($resql && ($obj = $db->fetch_object($resql))) {
					$pairs[] = array((int) $obj->fk_soc, $db->jdate($obj->date_start));
				}
			}
			break;
		case 'dolitraceoperation':
			$pairs[] = array($cropplanfarm($object->fk_cropplan), $object->date_op);
			if ($object->id > 0) {
				$resql = $db->query("SELECT fk_cropplan, date_op FROM ".$prefix."dolitrace_operation WHERE rowid = ".((int) $object->id));
				if ($resql && ($obj = $db->fetch_object($resql))) {
					$pairs[] = array($cropplanfarm($obj->fk_cropplan), $db->jdate($obj->date_op));
				}
			}
			break;
		case 'dolitraceharvest':
			$pairs[] = array($cropplanfarm($object->fk_cropplan), $object->date_harvest);
			if ($object->id > 0) {
				$resql = $db->query("SELECT fk_cropplan, date_harvest FROM ".$prefix."dolitrace_harvest WHERE rowid = ".((int) $object->id));
				if ($resql && ($obj = $db->fetch_object($resql))) {
					$pairs[] = array($cropplanfarm($obj->fk_cropplan), $db->jdate($obj->date_harvest));
				}
			}
			break;
	}
	return $pairs;
}

/**
 * Check that an object of the registers can be changed: its date (given and stored) is not frozen by an inspection
 *
 * The corrections of the treatment register (versions after the first) are allowed: they are the traced way to change
 * a frozen record.
 *
 * @param	CommonObject	$object		Treatment record, crop operation or harvest
 * @return	int							0 if allowed, -1 if frozen ($object->error is set)
 */
function dolitraceFrozenCheck($object)
{
	if (!empty($object->context['frozenbypass'])) {
		return 0;
	}
	if ($object->element == 'dolitracetreatment' && (int) $object->fk_previous > 0) {
		return 0;
	}
	foreach (dolitraceFrozenPairs($object) as $pair) {
		$error = '';
		if (!dolitraceFrozenDateAllowed($object->db, $pair[0], $pair[1], $error)) {
			$object->error = $error;
			$object->errors = array($error);
			return -1;
		}
	}
	return 0;
}

/**
 * Inspection that freezes an object, for the cards (read only view)
 *
 * @param	CommonObject	$object		Treatment record, crop operation or harvest
 * @return	?stdClass					Inspection (see dolitraceFrozenInspection), null if the object is not frozen
 */
function dolitraceFrozenBy($object)
{
	if (empty($object->id) || ($object->element == 'dolitracetreatment' && (int) $object->fk_previous > 0)) {
		return null;
	}
	foreach (dolitraceFrozenPairs($object) as $pair) {
		$ts = dolitraceFrozenTimestamp($pair[1]);
		$inspection = dolitraceFrozenInspection($object->db, $pair[0]);
		if ($ts !== null && $inspection && $ts <= $inspection->date) {
			return $inspection;
		}
	}
	return null;
}

/**
 * Banner of a frozen object, with the link to the inspection
 *
 * @param	stdClass	$inspection		Inspection (see dolitraceFrozenInspection)
 * @return	string						HTML
 */
function dolitraceFrozenBanner($inspection)
{
	global $langs;

	$langs->load('dolitrace@dolitrace');
	$link = '<a href="'.dol_buildpath('/dolitrace/dolitracebioinspection_card.php', 1).'?id='.((int) $inspection->id).'">'.dol_escape_htmltag($inspection->ref).'</a>';
	return '<div class="info"><span class="fa fa-lock paddingright"></span>'.$langs->trans('DolitraceFrozenBanner', $link, dol_print_date($inspection->day, 'day')).'</div>';
}

/**
 * Prepare the tabs of an inspection
 *
 * @param	DoliTraceBioInspection	$object		Inspection
 * @return	array<array{string,string,string}>	Tabs
 */
function dolitracebioinspectionPrepareHead($object)
{
	global $db, $langs, $conf;

	$langs->load('dolitrace@dolitrace');
	$h = 0;
	$head = array();
	$head[$h][0] = dol_buildpath('/dolitrace/dolitracebioinspection_card.php', 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('BioInspection');
	$head[$h][2] = 'card';
	$h++;

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';
	$upload_dir = $conf->dolitrace->dir_output.'/dolitracebioinspection/'.dol_sanitizeFileName($object->ref);
	$nbFiles = count(dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
	$nbLinks = Link::count($db, $object->element, $object->id);
	$head[$h][0] = dol_buildpath('/dolitrace/dolitracebioinspection_document.php', 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('Documents');
	if (($nbFiles + $nbLinks) > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">'.($nbFiles + $nbLinks).'</span>';
	}
	$head[$h][2] = 'document';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'dolitracebioinspection@dolitrace');
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'dolitracebioinspection@dolitrace', 'remove');

	return $head;
}
