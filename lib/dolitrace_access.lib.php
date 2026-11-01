<?php
/* Copyright (C) 2026      SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    lib/dolitrace_access.lib.php
 * \ingroup dolitrace
 * \brief   Visibility of crop plans, harvests and operations for the sales representatives
 *
 * Same rules as doliFarm (lib/dolifarm_access.lib.php): a user without the right to see all the third parties
 * sees only the farms he is sales representative of. Harvests and operations follow the farm of their crop plan.
 */

dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');

/**
 * SQL filter of a list of harvests or operations on the farms the user can see, through the crop plan
 *
 * @param	User		$user		User
 * @param	string		$field		Field of the crop plan, with the alias of the table (t.fk_cropplan)
 * @return	string					SQL to add to the WHERE (" AND ..."), empty if the user sees all the third parties
 */
function dolitraceSqlCropplanSocFilter($user, $field = 't.fk_cropplan')
{
	global $db;

	$filter = dolifarmSqlSocFilter($user, 'cpacc.fk_soc');
	if ($filter === '') {
		return '';
	}
	return " AND (".$field." IS NULL OR EXISTS (SELECT cpacc.rowid FROM ".$db->prefix()."dolitrace_cropplan as cpacc WHERE cpacc.rowid = ".$field.$filter."))";
}

/**
 * Stop the page when the user cannot see the farm of a crop plan, a harvest or an operation
 *
 * @param	User			$user		User
 * @param	CommonObject	$object		Object loaded by the page (nothing is checked when it has no id)
 * @return	void
 */
function dolitraceCheckAccess($user, $object)
{
	global $db;

	if (!is_object($object) || empty($object->id)) {
		return;
	}
	// Entity, and farm of the crop plan
	dolifarmCheckSocAccess($user, $object);
	if (isset($object->fields['fk_soc']) || empty($object->fk_cropplan)) {
		return;
	}
	$sql = "SELECT fk_soc FROM ".$db->prefix()."dolitrace_cropplan WHERE rowid = ".((int) $object->fk_cropplan);
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		accessforbidden();
	}
	$obj = $db->fetch_object($resql);
	$db->free($resql);
	if ($obj && !dolifarmUserCanSeeSoc($user, (int) $obj->fk_soc)) {
		accessforbidden();
	}
}
