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
 * \file    lib/dolitrace_bbch.lib.php
 * \ingroup dolitrace
 * \brief   Growth stages, general BBCH scale: dictionary dolitrace_c_bbch filled by the module at the activation
 *
 * The descriptions are written for doliTrace (keys BbchStageNN of the language files); scales by crop group to come.
 */

/**
 * Codes of the general BBCH scale proposed at the activation of the module
 *
 * @return	string[]
 */
function dolitraceBbchDefaultCodes()
{
	return array('00', '01', '03', '05', '07', '09', '10', '11', '12', '13', '14', '15', '19', '21', '22', '23', '25', '29',
		'30', '31', '32', '33', '37', '39', '41', '45', '49', '51', '55', '59', '60', '61', '65', '67', '69', '71', '73', '75',
		'77', '79', '81', '83', '85', '87', '89', '91', '93', '95', '97', '99');
}

/**
 * Create the dictionary of the growth stages if missing and add the codes of the general scale not there yet (the
 * codes changed or disabled by the user are kept)
 *
 * @param	DoliDB		$db		Database
 * @param	Translate	$langs	Language of the descriptions written in the dictionary
 * @return	int					<0 if KO, number of codes added
 */
function dolitraceBbchInitDictionary($db, $langs)
{
	$table = $db->prefix().'dolitrace_c_bbch';
	$sql = "CREATE TABLE IF NOT EXISTS ".$table." (";
	$sql .= " rowid integer AUTO_INCREMENT PRIMARY KEY, code varchar(3) NOT NULL, label varchar(128) NOT NULL,";
	$sql .= " active tinyint DEFAULT 1 NOT NULL, position integer DEFAULT 0, UNIQUE (code)) ENGINE=innodb";
	if (!$db->query($sql)) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return -1;
	}
	$langs->load('dolitrace@dolitrace');
	$existing = array();
	$resql = $db->query("SELECT code FROM ".$table);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$existing[(string) $obj->code] = true;
		}
		$db->free($resql);
	}
	$added = 0;
	foreach (dolitraceBbchDefaultCodes() as $i => $code) {
		if (isset($existing[$code])) {
			continue;
		}
		$label = dol_trunc($langs->transnoentitiesnoconv('BbchStage'.$code), 128, 'right', 'UTF-8', 1);
		$sql = "INSERT INTO ".$table." (code, label, active, position) VALUES ('".$db->escape($code)."', '".$db->escape($label)."', 1, ".(($i + 1) * 10).")";
		if ($db->query($sql)) {
			$added++;
		}
	}
	return $added;
}

/**
 * Active growth stages: description in the language of the user for the codes of the general scale (language files),
 * the one of the dictionary for the codes added by the user
 *
 * @param	DoliDB		$db		Database
 * @param	Translate	$langs	Language
 * @return	array<string,string>	Code => description
 */
function dolitraceBbchList($db, $langs)
{
	// Read once per request and language (the objects read it in their constructor)
	static $cache = array();
	if (isset($cache[$langs->defaultlang])) {
		return $cache[$langs->defaultlang];
	}
	$list = array();
	$cache[$langs->defaultlang] = $list;
	$resql = $db->query("SELECT code, label FROM ".$db->prefix()."dolitrace_c_bbch WHERE active = 1 ORDER BY position, code");
	if (!$resql) {
		return $list;
	}
	$langs->load('dolitrace@dolitrace');
	while ($obj = $db->fetch_object($resql)) {
		$code = (string) $obj->code;
		$key = 'BbchStage'.$code;
		$translated = $langs->transnoentitiesnoconv($key);
		$list[$code] = ($translated !== $key ? $translated : (string) $obj->label);
	}
	$db->free($resql);
	$cache[$langs->defaultlang] = $list;
	return $list;
}
