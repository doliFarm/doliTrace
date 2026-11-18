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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    lib/dolitrace_period.lib.php
 * \ingroup dolitrace
 * \brief   Period of a production plan: operations, treatments and harvests must be dated inside it
 *
 * Days are compared as YYYY-MM-DD: the plan has dates without time, the records date and time (time zone of the user)
 * or a date (harvests).
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

/**
 * Day YYYY-MM-DD of a date of a record
 *
 * @param	int|string|null	$date		Timestamp, or text understood by dol_stringtotime()
 * @param	bool			$withtime	true for the fields date and time (time zone of the user), false for dates
 * @return	string						Day, '' when empty
 */
function dolitracePeriodDay($date, $withtime = true)
{
	if ($date === null || $date === '') {
		return '';
	}
	if (!is_numeric($date)) {
		$date = dol_stringtotime($date);
	}
	if (empty($date)) {
		return '';
	}
	return dol_print_date((int) $date, '%Y-%m-%d', $withtime ? 'tzuserrel' : 'tzserver');
}

/**
 * Period of a production plan
 *
 * @param	DoliDB	$db		Database
 * @param	int		$planid	Id of the plan
 * @return	array{ref:string,start:string,end:string}|null	Days YYYY-MM-DD ('' when not set), null if not found
 */
function dolitracePlanPeriod($db, $planid)
{
	static $cache = array();

	$planid = (int) $planid;
	if ($planid <= 0) {
		return null;
	}
	if (!array_key_exists($planid, $cache)) {
		$cache[$planid] = null;
		$resql = $db->query("SELECT ref, date_start, date_end FROM ".$db->prefix()."dolitrace_cropplan WHERE rowid = ".$planid);
		$obj = ($resql ? $db->fetch_object($resql) : null);
		if ($obj) {
			$cache[$planid] = array(
				'ref' => (string) $obj->ref,
				'start' => substr((string) $obj->date_start, 0, 10),
				'end' => substr((string) $obj->date_end, 0, 10),
			);
		}
	}
	return $cache[$planid];
}

/**
 * Error when a record is dated outside the period of its production plan
 *
 * @param	DoliDB				$db			Database
 * @param	int					$planid		Id of the plan (nothing checked when empty)
 * @param	int|string|null		$start		Date of the record (or its start)
 * @param	int|string|null		$end		End of the record, if any
 * @param	bool				$withtime	true for the fields date and time, false for dates
 * @param	array{ref:string,start:string,end:string}|null	$period	Period to use instead of the one read from the plan
 * @return	string							Translated error, '' when inside the period
 */
function dolitraceRecordPeriodError($db, $planid, $start, $end = null, $withtime = true, $period = null)
{
	global $langs;

	if ($period === null) {
		$period = dolitracePlanPeriod($db, $planid);
	}
	if ($period === null || ($period['start'] === '' && $period['end'] === '')) {
		return '';
	}
	$first = dolitracePeriodDay($start, $withtime);
	$last = dolitracePeriodDay($end, $withtime);
	if ($last === '') {
		$last = $first;
	}
	if ($first === '') {
		return '';
	}
	if (($period['start'] !== '' && $first < $period['start']) || ($period['end'] !== '' && $last > $period['end'])) {
		$langs->load('dolitrace@dolitrace');
		$fmt = static function ($day) use ($langs) {
			return ($day === '' ? '...' : dol_print_date(dol_stringtotime($day.' 12:00:00'), 'day'));
		};
		return $langs->transnoentities('ErrorRecordOutsideCropPlan', $fmt($first < $period['start'] ? $first : $last), $period['ref'], $fmt($period['start']), $fmt($period['end']));
	}
	return '';
}

/**
 * Error when the new period of a plan leaves out some of its operations, treatments or harvests
 *
 * @param	DoliDB	$db		Database
 * @param	int		$planid	Id of the plan
 * @param	string	$start	New start YYYY-MM-DD ('' = none)
 * @param	string	$end	New end YYYY-MM-DD ('' = none)
 * @return	string			Translated error, '' when all the records are inside
 */
function dolitracePlanRecordsOutsideError($db, $planid, $start, $end)
{
	global $langs;

	$planid = (int) $planid;
	if ($planid <= 0 || ($start === '' && $end === '')) {
		return '';
	}
	$prefix = $db->prefix();
	$queries = array(
		array("SELECT date_op as d1, date_op_end as d2 FROM ".$prefix."dolitrace_operation WHERE fk_cropplan = ".$planid." AND (status IS NULL OR status <> 9)", true),
		array("SELECT date_start as d1, date_end as d2 FROM ".$prefix."dolitrace_treatment WHERE fk_cropplan = ".$planid." AND status IN (0, 1)", true),
		array("SELECT date_harvest as d1, NULL as d2 FROM ".$prefix."dolitrace_harvest WHERE fk_cropplan = ".$planid." AND (status IS NULL OR status <> 9)", false),
	);
	$first = '';
	$last = '';
	$count = 0;
	foreach ($queries as $query) {
		$resql = $db->query($query[0]);
		if (!$resql) {
			continue;
		}
		while ($obj = $db->fetch_object($resql)) {
			$d1 = dolitracePeriodDay($db->jdate($obj->d1), $query[1]);
			$d2 = dolitracePeriodDay($db->jdate($obj->d2), $query[1]);
			if ($d1 === '') {
				continue;
			}
			$d2 = ($d2 === '' ? $d1 : $d2);
			if (($start !== '' && $d1 < $start) || ($end !== '' && $d2 > $end)) {
				$count++;
				$first = ($first === '' || $d1 < $first ? $d1 : $first);
				$last = ($last === '' || $d2 > $last ? $d2 : $last);
			}
		}
		$db->free($resql);
	}
	if ($count) {
		$langs->load('dolitrace@dolitrace');
		return $langs->transnoentities('ErrorCropPlanRecordsOutside', $count, dol_print_date(dol_stringtotime($first.' 12:00:00'), 'day'), dol_print_date(dol_stringtotime($last.' 12:00:00'), 'day'));
	}
	return '';
}
