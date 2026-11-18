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
 * \file    dolitrace/lib/dolitrace_pwa.lib.php
 * \ingroup dolitrace
 * \brief   Data of the field app (PWA): what the phone keeps offline, and the drafts of operations and harvests it sends.
 *          Only drafts: the office completes and validates them in Dolibarr.
 */

dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');
dol_include_once('/dolitrace/class/dolitracecropplan.class.php');
dol_include_once('/dolitrace/class/dolitraceoperation.class.php');
dol_include_once('/dolitrace/class/dolitraceharvest.class.php');
dol_include_once('/dolitrace/class/dolitracetrace.class.php');
dol_include_once('/dolitrace/lib/dolitrace_units.lib.php');

/** Version of the exchange between the app and the server: the app warns when it does not know it */
define('DOLITRACE_PWA_API', 1);
/** Records accepted in one sending */
define('DOLITRACE_PWA_MAXBATCH', 50);
/** Last operations and harvests kept by the phone for each crop plan */
define('DOLITRACE_PWA_LASTRECORDS', 10);
/** Photos of a record sent by the app */
define('DOLITRACE_PWA_MAXPHOTOS', 5);
/** Size of a photo (the app reduces them under 1 MB) */
define('DOLITRACE_PWA_PHOTOSIZE', 4194304);

/**
 * What the user can do with the field app
 *
 * The permission "use the field app" lets a worker or a contractor make drafts without the write permission of the
 * operations and harvests; who has the write permission can use the app too.
 *
 * @param	User	$user	User
 * @return	array{use:bool,operation:bool,harvest:bool,treatment:bool}
 */
function dolitracePwaRights($user)
{
	$pwa = $user->hasRight('dolitrace', 'dolitracepwa', 'use');
	$operation = ($pwa || $user->hasRight('dolitrace', 'dolitraceoperation', 'write'));
	$harvest = ($pwa || $user->hasRight('dolitrace', 'dolitraceharvest', 'write'));
	// Treatments enter the register: a permission of their own (or the right to write them in the office)
	$treatment = ($user->hasRight('dolitrace', 'dolitracepwa', 'treatment') || $user->hasRight('dolitrace', 'dolitracetreatment', 'write'));
	return array('use' => ($operation || $harvest || $treatment), 'operation' => $operation, 'harvest' => $harvest, 'treatment' => $treatment);
}

/**
 * Date of the server as text for the app: days as stored (time zone of the server, like the cards of Dolibarr),
 * hours in the time zone of the user
 *
 * @param	int|string|null	$date		Timestamp
 * @param	bool			$withtime	With hour and minute
 * @return	string|null
 */
function dolitracePwaDate($date, $withtime = false)
{
	if ($date === null || $date === '') {
		return null;
	}
	return dol_print_date($date, $withtime ? '%Y-%m-%dT%H:%M' : '%Y-%m-%d', $withtime ? 'tzuserrel' : 'tzserver');
}

/**
 * Date sent by the app: a day (2026-11-18, time zone of the server as the fields of type date) or a day and an hour
 * (2026-11-18T08:30, time zone of the user)
 *
 * @param	mixed	$text	Text
 * @return	int|null		Timestamp, null when it is not a date
 */
function dolitracePwaParseDate($text)
{
	if (!is_string($text) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/', $text, $m)) {
		return null;
	}
	if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || (isset($m[4]) && ((int) $m[4] > 23 || (int) $m[5] > 59))) {
		return null;
	}
	$date = dol_mktime(isset($m[4]) ? (int) $m[4] : 0, isset($m[5]) ? (int) $m[5] : 0, 0, (int) $m[2], (int) $m[3], (int) $m[1], isset($m[4]) ? 'tzuserrel' : 'tzserver');
	return ($date === '' ? null : (int) $date);
}

/**
 * Data that the phone keeps offline: plots, open production plans, last operations and harvests, pre-harvest
 * intervals in progress, dictionaries. Only the farms the user can see.
 *
 * @param	DoliDB		$db		Database handler
 * @param	User		$user	User
 * @param	Translate	$langs	Language
 * @return	array<string,mixed>
 */
function dolitracePwaData($db, $user, $langs)
{
	$prefix = $db->prefix();
	$now = dol_now();
	$data = array('plots' => array(), 'cropplans' => array(), 'operations' => array(), 'harvests' => array(), 'safety' => array());

	// Plots
	$sql = "SELECT p.rowid, p.ref, p.label, p.fk_soc, s.nom as farm, p.geo_lat, p.geo_lon, p.size_conducted, p.size_sau, p.size_total,";
	$sql .= " p.cadastral_comune, p.cadastral_sheet, p.cadastral_parcel";
	$sql .= " FROM ".$prefix."dolifarm_plot as p LEFT JOIN ".$prefix."societe as s ON s.rowid = p.fk_soc";
	$sql .= " WHERE p.entity IN (".getEntity('dolifarmplot').") AND (p.status IS NULL OR p.status <> 9)";
	$sql .= dolifarmSqlSocFilter($user, 'p.fk_soc');
	$sql .= " ORDER BY p.ref";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$size = (float) ($obj->size_conducted > 0 ? $obj->size_conducted : ($obj->size_sau > 0 ? $obj->size_sau : $obj->size_total));
			$data['plots'][] = array(
				'id' => (int) $obj->rowid, 'ref' => (string) $obj->ref, 'label' => (string) $obj->label,
				'farm_id' => (int) $obj->fk_soc, 'farm' => (string) $obj->farm,
				'lat' => ($obj->geo_lat !== null && (float) $obj->geo_lat != 0 ? (float) $obj->geo_lat : null),
				'lon' => ($obj->geo_lon !== null && (float) $obj->geo_lon != 0 ? (float) $obj->geo_lon : null),
				'area_ha' => ($size > 0 ? round($size, 4) : null),
				'comune' => (string) $obj->cadastral_comune, 'sheet' => (string) $obj->cadastral_sheet, 'parcel' => (string) $obj->cadastral_parcel,
			);
		}
		$db->free($resql);
	} else {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
	}

	// Production plans not closed
	$plans = array();
	$sql = "SELECT cp.rowid, cp.ref, cp.label, cp.fk_plot, cp.fk_soc, cp.season, cp.date_start, cp.date_end, cp.status, cp.fk_output_product,";
	$sql .= " c.label as crop_label, c.variety, c.eppo_code, pr.ref as product_ref, pr.label as product_label";
	$sql .= " FROM ".$prefix."dolitrace_cropplan as cp";
	$sql .= " LEFT JOIN ".$prefix."dolifarm_crops as c ON c.rowid = cp.fk_crop";
	$sql .= " LEFT JOIN ".$prefix."product as pr ON pr.rowid = cp.fk_output_product";
	$sql .= " WHERE cp.entity IN (".getEntity('dolitracecropplan').") AND cp.status IN (".DoliTraceCropplan::STATUS_DRAFT.", ".DoliTraceCropplan::STATUS_VALIDATED.")";
	$sql .= dolifarmSqlSocFilter($user, 'cp.fk_soc');
	$sql .= " ORDER BY cp.date_start DESC, cp.rowid DESC";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$plans[(int) $obj->rowid] = $obj;
			$data['cropplans'][] = array(
				'id' => (int) $obj->rowid, 'ref' => (string) $obj->ref, 'label' => (string) $obj->label,
				'plot_id' => (int) $obj->fk_plot, 'farm_id' => (int) $obj->fk_soc, 'season' => (string) $obj->season,
				'crop' => $langs->transnoentities((string) $obj->crop_label), 'variety' => (string) $obj->variety, 'eppo' => strtoupper((string) $obj->eppo_code),
				'date_start' => dolitracePwaDate($db->jdate($obj->date_start)), 'date_end' => dolitracePwaDate($db->jdate($obj->date_end)),
				'status' => (int) $obj->status,
				'product_id' => (int) $obj->fk_output_product, 'product' => trim($obj->product_ref.' - '.$obj->product_label, ' -'),
			);
		}
		$db->free($resql);
	} else {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
	}

	if (!empty($plans)) {
		$ids = implode(',', array_keys($plans));
		// Last operations of each plan
		$operation = new DoliTraceOperation($db);
		$types = (array) ($operation->fields['fk_operation_type']['arrayofkeyval'] ?? array());
		$count = array();
		$sql = "SELECT o.rowid, o.ref, o.fk_cropplan, o.fk_operation_type, o.date_op, o.date_op_end, o.label, o.status";
		$sql .= " FROM ".$prefix."dolitrace_operation as o";
		$sql .= " WHERE o.fk_cropplan IN (".$db->sanitize($ids).") AND (o.status IS NULL OR o.status <> ".DoliTraceOperation::STATUS_CANCELED.")";
		$sql .= " ORDER BY o.date_op DESC, o.rowid DESC";
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$plan = (int) $obj->fk_cropplan;
				$count[$plan] = ($count[$plan] ?? 0) + 1;
				if ($count[$plan] > DOLITRACE_PWA_LASTRECORDS) {
					continue;
				}
				$data['operations'][] = array(
					'id' => (int) $obj->rowid, 'ref' => (string) $obj->ref, 'cropplan_id' => $plan,
					'type' => (string) $obj->fk_operation_type, 'type_label' => (string) ($types[(string) $obj->fk_operation_type] ?? $obj->fk_operation_type),
					'date' => dolitracePwaDate($db->jdate($obj->date_op), true), 'date_end' => dolitracePwaDate($db->jdate($obj->date_op_end), true),
					'label' => (string) $obj->label, 'status' => (int) $obj->status,
				);
			}
			$db->free($resql);
		}
		// Last harvests of each plan
		$count = array();
		$sql = "SELECT h.rowid, h.ref, h.fk_cropplan, h.date_harvest, h.qty_harvested, h.qty_unit, h.batch_number, h.status";
		$sql .= " FROM ".$prefix."dolitrace_harvest as h";
		$sql .= " WHERE h.fk_cropplan IN (".$db->sanitize($ids).") AND (h.status IS NULL OR h.status <> ".DoliTraceHarvest::STATUS_CANCELED.")";
		$sql .= " ORDER BY h.date_harvest DESC, h.rowid DESC";
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$plan = (int) $obj->fk_cropplan;
				$count[$plan] = ($count[$plan] ?? 0) + 1;
				if ($count[$plan] > DOLITRACE_PWA_LASTRECORDS) {
					continue;
				}
				$data['harvests'][] = array(
					'id' => (int) $obj->rowid, 'ref' => (string) $obj->ref, 'cropplan_id' => $plan,
					'date' => dolitracePwaDate($db->jdate($obj->date_harvest)),
					'qty' => ($obj->qty_harvested !== null ? (float) $obj->qty_harvested : null), 'unit' => (string) $obj->qty_unit,
					'batch' => (string) $obj->batch_number, 'status' => (int) $obj->status,
				);
			}
			$db->free($resql);
		}
		// Pre-harvest intervals still running
		$trace = new DoliTraceTrace($db);
		foreach ($plans as $planid => $obj) {
			$start = $db->jdate($obj->date_start);
			foreach ($trace->getSafetyInProgress($planid, (int) $obj->fk_plot, $start ? $start : 0, strtoupper((string) $obj->eppo_code), $now) as $line) {
				$data['safety'][] = array(
					'cropplan_id' => $planid, 'treatment_id' => $line['treatment_id'], 'ref' => $line['ref'], 'product' => $line['product'],
					'date_end' => dolitracePwaDate($line['date_end']), 'safety_days' => $line['safety_days'], 'until' => dolitracePwaDate($line['until']),
				);
			}
		}
	}

	// Dictionaries: types of operation, units of the harvests, machines of the farms
	$operation = new DoliTraceOperation($db);
	$data['operation_types'] = array();
	foreach ((array) ($operation->fields['fk_operation_type']['arrayofkeyval'] ?? array()) as $code => $label) {
		$data['operation_types'][] = array('code' => (string) $code, 'label' => (string) $label);
	}
	$data['units'] = array();
	foreach (dolitraceUnitList($db) as $short => $unit) {
		$data['units'][] = array('code' => (string) $short, 'label' => html_entity_decode((string) $unit['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'type' => (string) $unit['type']);
	}
	$data['machines'] = array();
	$sql = "SELECT m.rowid, m.ref, m.label, m.fk_soc, m.date_sprayer_check_end FROM ".$prefix."dolifarm_machine as m";
	$sql .= " WHERE m.entity IN (".getEntity('dolifarmmachine').") AND m.status = 1";
	$sql .= dolifarmSqlSocFilter($user, 'm.fk_soc');
	$sql .= " ORDER BY m.label, m.ref";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$data['machines'][] = array('id' => (int) $obj->rowid, 'ref' => (string) $obj->ref, 'label' => (string) $obj->label, 'farm_id' => (int) $obj->fk_soc,
				'check_end' => dolitracePwaDate($db->jdate($obj->date_sprayer_check_end)));
		}
		$db->free($resql);
	}
	$data['weather_available'] = (dol_include_once('/dolitrace/lib/dolitrace_weather.lib.php') && function_exists('dolitraceWeatherAvailable') && dolitraceWeatherAvailable());
	// Treatments (phase P3): only for who can make their drafts
	$rights = dolitracePwaRights($user);
	if ($rights['treatment']) {
		$data['treatment'] = dolitracePwaTreatmentData($db, $user, $langs);
	}
	return $data;
}

/**
 * Result of a record sent by the app
 *
 * @param	string	$clientid	Identifier of the phone
 * @param	string	$error		Code of the error, empty when created
 * @param	string	$message	Message for the user
 * @param	array<string,mixed>	$more	Other data (id, ref, url, duplicate)
 * @return	array<string,mixed>
 */
function dolitracePwaResult($clientid, $error, $message = '', $more = array())
{
	return array_merge(array('client_id' => $clientid, 'ok' => ($error === ''), 'error' => $error, 'message' => $message), $more);
}

/**
 * Record already created from the same identifier of the phone
 *
 * @param	DoliDB	$db			Database handler
 * @param	string	$clientid	Identifier
 * @return	array{element:string,id:int,user:int}|null
 */
function dolitracePwaReceipt($db, $clientid)
{
	global $conf;

	$sql = "SELECT element, fk_object, fk_user FROM ".$db->prefix()."dolitrace_pwa_receipt";
	$sql .= " WHERE entity = ".((int) $conf->entity)." AND client_id = '".$db->escape($clientid)."'";
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return null;
	}
	$obj = $db->fetch_object($resql);
	$db->free($resql);
	return ($obj ? array('element' => (string) $obj->element, 'id' => (int) $obj->fk_object, 'user' => (int) $obj->fk_user) : null);
}

/**
 * Photo of a record sent by the app: added to the documents of the draft (tab Documents)
 *
 * POST multipart: record (identifier of the record on the phone), photo_id (identifier of the photo), created_at,
 * photo (JPEG). Only on a record sent from the app by the same user; the same photo sent again is not added twice.
 *
 * @param	DoliDB		$db		Database handler
 * @param	User		$user	User
 * @param	Translate	$langs	Language
 * @return	array<string,mixed>	Result (dolitracePwaResult), client_id = photo_id
 */
function dolitracePwaPhoto($db, $user, $langs)
{
	global $conf;

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	$photoid = GETPOST('photo_id', 'alphanohtml');
	$recordid = GETPOST('record', 'alphanohtml');
	if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $photoid) || !preg_match('/^[A-Za-z0-9_-]{8,64}$/', $recordid)) {
		return dolitracePwaResult('', 'client_id', $langs->transnoentities('PwaErrorClientId'));
	}

	// Sent again: already in the documents
	$done = dolitracePwaReceipt($db, $photoid);
	if ($done) {
		if (strpos($done['element'], 'photo_') !== 0) {
			return dolitracePwaResult($photoid, 'client_id', $langs->transnoentities('PwaErrorClientId'));
		}
		return dolitracePwaResult($photoid, '', '', array('duplicate' => true));
	}

	// The record: sent from the app by the same user
	$receipt = dolitracePwaReceipt($db, $recordid);
	if (!$receipt || !in_array($receipt['element'], array('operation', 'harvest', 'treatment')) || $receipt['user'] != (int) $user->id) {
		return dolitracePwaResult($photoid, 'record', $langs->transnoentities('PwaErrorPhotoRecord'));
	}
	$kind = $receipt['element'];
	$rights = dolitracePwaRights($user);
	if (empty($rights[$kind])) {
		return dolitracePwaResult($photoid, 'forbidden', $langs->transnoentities('PwaErrorForbidden'));
	}
	$class = dolitracePwaClass($kind);
	$object = new $class($db);
	if ($object->fetch($receipt['id']) <= 0 || (int) $object->status == $class::STATUS_CANCELED) {
		return dolitracePwaResult($photoid, 'record', $langs->transnoentities('PwaErrorPhotoRecord'));
	}

	// The file: a JPEG made by the app
	$file = (isset($_FILES['photo']) && is_array($_FILES['photo']) ? $_FILES['photo'] : null);
	if (!$file || is_array($file['name']) || !empty($file['error']) || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
		return dolitracePwaResult($photoid, 'file', $langs->transnoentities('PwaErrorPhotoFile'));
	}
	if ((int) $file['size'] > DOLITRACE_PWA_PHOTOSIZE) {
		return dolitracePwaResult($photoid, 'size', $langs->transnoentities('PwaErrorPhotoSize', dol_print_size(DOLITRACE_PWA_PHOTOSIZE)));
	}
	$info = @getimagesize($file['tmp_name']);
	if (!is_array($info) || $info[2] !== IMAGETYPE_JPEG) {
		return dolitracePwaResult($photoid, 'file', $langs->transnoentities('PwaErrorPhotoFile'));
	}

	// At most DOLITRACE_PWA_MAXPHOTOS photos for a record
	$sql = "SELECT COUNT(rowid) as nb FROM ".$db->prefix()."dolitrace_pwa_receipt";
	$sql .= " WHERE entity = ".((int) $conf->entity)." AND element = 'photo_".$db->escape($kind)."' AND fk_object = ".((int) $object->id);
	$resql = $db->query($sql);
	$obj = ($resql ? $db->fetch_object($resql) : null);
	if (!$obj) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return dolitracePwaResult($photoid, 'create', $langs->transnoentities('Error'));
	}
	if ((int) $obj->nb >= DOLITRACE_PWA_MAXPHOTOS) {
		return dolitracePwaResult($photoid, 'max', $langs->transnoentities('PwaErrorPhotoMax', DOLITRACE_PWA_MAXPHOTOS));
	}

	// Drafts of operations and harvests made before doliTrace 1.5.3 have no ref, the name of the directory of their
	// documents (treatments have (PROVid) since their creation)
	if (method_exists($object, 'setProvisionalRef')) {
		$object->setProvisionalRef();
	}
	$made = dolitracePwaParseDate(GETPOST('created_at', 'alphanohtml'));

	// Receipt first (unique identifier): two sendings of the same photo at the same time add it once
	$db->begin();
	$sql = "INSERT INTO ".$db->prefix()."dolitrace_pwa_receipt (entity, client_id, element, fk_object, fk_user, date_client, datec)";
	$sql .= " VALUES (".((int) $conf->entity).", '".$db->escape($photoid)."', 'photo_".$db->escape($kind)."', ".((int) $object->id).", ".((int) $user->id).",";
	$sql .= " ".($made ? "'".$db->idate($made)."'" : "NULL").", '".$db->idate(dol_now())."')";
	if (!$db->query($sql)) {
		$db->rollback();
		$done = dolitracePwaReceipt($db, $photoid);
		if ($done && strpos($done['element'], 'photo_') === 0) {
			return dolitracePwaResult($photoid, '', '', array('duplicate' => true));
		}
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return dolitracePwaResult($photoid, 'create', $langs->transnoentities('Error'));
	}
	$dir = (empty($conf->dolitrace->multidir_output[$conf->entity]) ? $conf->dolitrace->dir_output : $conf->dolitrace->multidir_output[$conf->entity]);
	$dir .= '/dolitrace'.$kind.'/'.get_exdir(0, 0, 0, 1, $object);
	$_FILES['photo']['name'] = 'photo_'.dol_print_date($made ? $made : dol_now(), '%Y%m%d_%H%M', 'tzuserrel').'_'.substr(str_replace('-', '', $photoid), 0, 8).'.jpg';
	$result = dol_add_file_process($dir, 0, 1, 'photo', '', null, '', 1, $object);
	// Messages of the page of documents: not for the app
	unset($_SESSION['dol_events']);
	if ($result <= 0) {
		$db->rollback();
		dol_syslog(__FUNCTION__.' '.$kind.' '.$object->id.' '.$dir, LOG_ERR);
		return dolitracePwaResult($photoid, 'file', $langs->transnoentities('PwaErrorPhotoFile'));
	}
	$db->commit();
	return dolitracePwaResult($photoid, '', '', array('duplicate' => false, 'file' => $_FILES['photo']['name'], 'ref' => (string) $object->ref, 'url' => dol_buildpath('/dolitrace/dolitrace'.$kind.'_document.php', 2).'?id='.((int) $object->id)));
}

/**
 * Address of the card of a record
 *
 * @param	string	$element	operation or harvest
 * @param	int		$id			Id
 * @return	string
 */
function dolitracePwaCardUrl($element, $id)
{
	return dol_buildpath('/dolitrace/dolitrace'.(in_array($element, array('harvest', 'treatment')) ? $element : 'operation').'_card.php', 2).'?id='.((int) $id);
}

/**
 * Class of a kind of record of the app
 *
 * @param	string	$kind	operation, harvest or treatment
 * @return	string			Class, loaded
 */
function dolitracePwaClass($kind)
{
	if ($kind == 'treatment') {
		dol_include_once('/dolitrace/class/dolitracetreatment.class.php');
		return 'DoliTraceTreatment';
	}
	return ($kind == 'harvest' ? 'DoliTraceHarvest' : 'DoliTraceOperation');
}

/**
 * Create the draft of an operation or of a harvest sent by the app
 *
 * The same identifier sent again (the phone did not get the answer) returns the record already created.
 *
 * @param	DoliDB		$db		Database handler
 * @param	User		$user	User
 * @param	Translate	$langs	Language
 * @param	mixed		$record	{client_id, kind: operation|harvest, created_at, data: {...}}
 * @return	array<string,mixed>	Result (dolitracePwaResult)
 */
function dolitracePwaCreate($db, $user, $langs, $record)
{
	global $conf;

	$clientid = (is_array($record) && is_string($record['client_id'] ?? null) ? $record['client_id'] : '');
	if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $clientid)) {
		return dolitracePwaResult('', 'client_id', $langs->transnoentities('PwaErrorClientId'));
	}
	$kind = (string) ($record['kind'] ?? '');
	$data = (is_array($record['data'] ?? null) ? $record['data'] : array());
	$rights = dolitracePwaRights($user);
	if (!in_array($kind, array('operation', 'harvest', 'treatment'))) {
		return dolitracePwaResult($clientid, 'kind', $langs->transnoentities('PwaErrorValue', 'kind'));
	}
	if (empty($rights[$kind])) {
		return dolitracePwaResult($clientid, 'forbidden', $langs->transnoentities('PwaErrorForbidden'));
	}

	// Sent again: the record is already there
	$receipt = dolitracePwaReceipt($db, $clientid);
	if ($receipt) {
		if ($receipt['element'] != $kind) {
			return dolitracePwaResult($clientid, 'client_id', $langs->transnoentities('PwaErrorClientId'));
		}
		$class = dolitracePwaClass($kind);
		$object = new $class($db);
		$object->fetch($receipt['id']);
		return dolitracePwaResult($clientid, '', '', array('duplicate' => true, 'id' => $receipt['id'], 'ref' => (string) $object->ref, 'url' => dolitracePwaCardUrl($kind, $receipt['id'])));
	}

	// Production plan: same entity, not closed, farm visible to the user (the entity is read from the table: without
	// multicompany the field is disabled and fetch() does not load it)
	$plan = new DoliTraceCropplan($db);
	$planid = (int) ($data['cropplan_id'] ?? 0);
	$entity = 0;
	if ($planid > 0) {
		$resql = $db->query("SELECT entity FROM ".$db->prefix()."dolitrace_cropplan WHERE rowid = ".$planid);
		$obj = ($resql ? $db->fetch_object($resql) : null);
		$entity = ($obj ? (int) $obj->entity : 0);
	}
	if ($planid <= 0 || $plan->fetch($planid) <= 0
		|| !in_array($entity, array_map('intval', explode(',', getEntity('dolitracecropplan', 1))))
		|| !in_array((int) $plan->status, array(DoliTraceCropplan::STATUS_DRAFT, DoliTraceCropplan::STATUS_VALIDATED))
		|| !dolifarmUserCanSeeSoc($user, (int) $plan->fk_soc)) {
		return dolitracePwaResult($clientid, 'cropplan', $langs->transnoentities('PwaErrorCropPlan'));
	}

	$text = function ($key, $max) use ($data) {
		$value = trim((string) (is_scalar($data[$key] ?? null) ? $data[$key] : ''));
		return dol_trunc(dol_string_nohtmltag($value), $max, 'right', 'UTF-8', 1);
	};
	$made = dolitracePwaParseDate($record['created_at'] ?? null);
	$note = $text('note', 2000);
	$note .= ($note !== '' ? "\n" : '').$langs->transnoentities('PwaNoteFromApp', $user->getFullName($langs), dol_print_date($made ? $made : dol_now(), 'dayhour', 'tzuserrel'));
	$maxdate = dol_now() + 86400;

	if ($kind == 'treatment') {
		// Treatment or fertilization on the plot of the plan
		$object = dolitracePwaTreatmentObject($db, $user, $langs, $clientid, $data, $plan, $entity, $note);
		if (is_array($object)) {
			return $object;
		}
	} elseif ($kind == 'operation') {
		$object = new DoliTraceOperation($db);
		$types = (array) ($object->fields['fk_operation_type']['arrayofkeyval'] ?? array());
		$type = (string) ($data['type'] ?? '');
		if ($type === '' || !isset($types[$type])) {
			return dolitracePwaResult($clientid, 'type', $langs->transnoentities('PwaErrorValue', $langs->transnoentities('OperationType')));
		}
		$start = dolitracePwaParseDate($data['date'] ?? null);
		if ($start === null || $start > $maxdate) {
			return dolitracePwaResult($clientid, 'date', $langs->transnoentities('PwaErrorValue', $langs->transnoentities('Dateop')));
		}
		$end = null;
		if (!empty($data['date_end'])) {
			$end = dolitracePwaParseDate($data['date_end']);
			if ($end === null || $end < $start || $end > $maxdate) {
				return dolitracePwaResult($clientid, 'date_end', $langs->transnoentities('PwaErrorValue', $langs->transnoentities('OperationDateEnd')));
			}
		}
		$machine = (int) ($data['machine_id'] ?? 0);
		if ($machine > 0) {
			$resql = $db->query("SELECT fk_soc FROM ".$db->prefix()."dolifarm_machine WHERE rowid = ".$machine." AND entity IN (".getEntity('dolifarmmachine').")");
			$obj = ($resql ? $db->fetch_object($resql) : null);
			if (!$obj || ((int) $obj->fk_soc > 0 && (int) $obj->fk_soc != (int) $plan->fk_soc)) {
				return dolitracePwaResult($clientid, 'machine', $langs->transnoentities('PwaErrorValue', $langs->transnoentities('Fkmachine')));
			}
		}
		$object->entity = $entity;
		$object->fk_cropplan = $planid;
		$object->fk_operation_type = $type;
		$object->date_op = $start;
		$object->date_op_end = $end;
		$object->label = $text('label', 255);
		$object->fk_machine = ($machine > 0 ? $machine : null);
		$duration = (int) ($data['duration_min'] ?? 0);
		$object->duration_min = ($duration > 0 && $duration < 100000 ? $duration : null);
		$area = (float) ($data['area_ha'] ?? 0);
		$object->area_ha = ($area > 0 && $area < 100000 ? $area : null);
		$bbch = $text('bbch', 2);
		$object->fk_pheno_stage = ($bbch !== '' ? $bbch : null);
		$object->fk_user_operator = (int) $user->id;
		$object->status = DoliTraceOperation::STATUS_DRAFT;
	} else {
		$object = new DoliTraceHarvest($db);
		$date = dolitracePwaParseDate($data['date'] ?? null);
		if ($date === null || $date > $maxdate) {
			return dolitracePwaResult($clientid, 'date', $langs->transnoentities('PwaErrorValue', $langs->transnoentities('HarvestDate')));
		}
		$qty = (float) ($data['qty'] ?? 0);
		if ($qty <= 0 || $qty >= 1e9) {
			return dolitracePwaResult($clientid, 'qty', $langs->transnoentities('PwaErrorValue', $langs->transnoentities('HarvestQty')));
		}
		$unit = (string) ($data['unit'] ?? '');
		if (!isset(dolitraceUnitList($db)[$unit])) {
			return dolitracePwaResult($clientid, 'unit', $langs->transnoentities('PwaErrorValue', $langs->transnoentities('HarvestUnit')));
		}
		$object->entity = $entity;
		$object->fk_cropplan = $planid;
		$object->date_harvest = $date;
		$object->qty_harvested = $qty;
		$object->qty_unit = $unit;
		$object->fk_product_out = ($plan->fk_output_product > 0 ? (int) $plan->fk_output_product : null);
		$object->label = $text('label', 255);
		$object->status = DoliTraceHarvest::STATUS_DRAFT;
	}
	$object->note_private = $note;

	$db->begin();
	$id = $object->create($user);
	if ($id > 0 && $kind == 'treatment' && dolitracePwaTreatmentLines($user, $object) < 0) {
		$id = -1;
	}
	if ($id <= 0) {
		$db->rollback();
		$error = trim($object->error.' '.implode(' ', (array) $object->errors));
		dol_syslog(__FUNCTION__.' '.$kind.' '.$error, LOG_ERR);
		return dolitracePwaResult($clientid, 'create', $error !== '' ? $error : $langs->transnoentities('Error'));
	}
	$sql = "INSERT INTO ".$db->prefix()."dolitrace_pwa_receipt (entity, client_id, element, fk_object, fk_user, date_client, datec)";
	$sql .= " VALUES (".((int) $conf->entity).", '".$db->escape($clientid)."', '".$db->escape($kind)."', ".((int) $id).", ".((int) $user->id).",";
	$sql .= " ".($made ? "'".$db->idate($made)."'" : "NULL").", '".$db->idate(dol_now())."')";
	if (!$db->query($sql)) {
		// Same identifier sent twice at the same time: keep the first one
		$db->rollback();
		$receipt = dolitracePwaReceipt($db, $clientid);
		if ($receipt && $receipt['element'] == $kind) {
			return dolitracePwaResult($clientid, '', '', array('duplicate' => true, 'id' => $receipt['id'], 'ref' => '', 'url' => dolitracePwaCardUrl($kind, $receipt['id'])));
		}
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return dolitracePwaResult($clientid, 'create', $langs->transnoentities('Error'));
	}
	$db->commit();
	$more = array('duplicate' => false, 'id' => (int) $id, 'ref' => (string) $object->ref, 'url' => dolitracePwaCardUrl($kind, $id));
	if ($kind == 'treatment') {
		// Warnings of the register (uses, doses, FRAC groups, pre-harvest interval, organic, nitrogen...): shown by the app
		$more['warnings'] = array_values(array_unique($object->getWarnings()));
	}
	return dolitracePwaResult($clientid, '', '', $more);
}

/**
 * Data of the treatments kept by the phone (phase P3): products with their authorised uses on the crops of the open
 * production plans, adversities, units of dose, operators, lots of the warehouse of technical inputs of the farms,
 * organic plots. Only for the users who can make drafts of treatments.
 *
 * Checks needing the whole register (FRAC groups, pre-harvest interval, nitrogen) are done by the server after the
 * sending: the app shows them on the record sent.
 *
 * @param	DoliDB		$db		Database handler
 * @param	User		$user	User
 * @param	Translate	$langs	Language
 * @return	array<string,mixed>
 */
function dolitracePwaTreatmentData($db, $user, $langs)
{
	$prefix = $db->prefix();
	$now = dol_now();
	$langs->loadLangs(array('dolitrace@dolitrace', 'dolifarm@dolifarm'));
	dol_include_once('/dolitrace/class/dolitracetreatment.class.php');
	dol_include_once('/dolitrace/lib/dolitrace_treatment.lib.php');
	dol_include_once('/dolifarm/class/dolifarmagrodruguse.class.php');
	$hasorganic = (dol_include_once('/dolifarm/lib/dolifarm_organic.lib.php') && function_exists('dolifarmOrganicAgroDrugCheck'));
	$hastaxonomy = (dol_include_once('/dolifarm/lib/dolifarm_eppo.lib.php') && function_exists('dolifarmEppoCovers'));
	$data = array('agrodrugs' => array(), 'uses' => array(), 'fertilizers' => array(), 'adversities' => array(), 'dose_units' => array(),
		'operators' => array(), 'lots' => array(), 'organic_plots' => array(), 'stock_date' => dolitracePwaDate($now, true));

	// Plans not closed of the farms of the user: crops, farms and plots
	$crops = array();
	$farms = array();
	$plots = array();
	$sql = "SELECT cp.rowid, cp.fk_plot, cp.fk_soc, c.eppo_code FROM ".$prefix."dolitrace_cropplan as cp";
	$sql .= " LEFT JOIN ".$prefix."dolifarm_crops as c ON c.rowid = cp.fk_crop";
	$sql .= " WHERE cp.entity IN (".getEntity('dolitracecropplan').") AND cp.status IN (".DoliTraceCropplan::STATUS_DRAFT.", ".DoliTraceCropplan::STATUS_VALIDATED.")";
	$sql .= dolifarmSqlSocFilter($user, 'cp.fk_soc');
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$code = strtoupper(trim((string) $obj->eppo_code));
			if ($code !== '') {
				$crops[$code] = true;
			}
			$farms[(int) $obj->fk_soc] = true;
			$plots[(int) $obj->fk_plot] = true;
		}
		$db->free($resql);
	} else {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
	}

	// Plant protection products, with the status of the catalogue and the organic check of today
	$products = array();
	foreach (dolitraceTreatmentAgroDrugs($db) as $agrodrug) {
		$rows = dolitraceTreatmentFetchRows($db, "SELECT rowid, auth_expiration_date, admin_status, date_revocation FROM ".$prefix."dolifarm_agrodrug WHERE rowid = ".((int) $agrodrug->rowid));
		$row = reset($rows);
		$warning = '';
		$authend = ($row ? $db->jdate($row->auth_expiration_date) : null);
		$status = ($row ? strtolower(trim((string) $row->admin_status)) : '');
		$revoked = ($row ? $db->jdate($row->date_revocation) : null);
		if (str_starts_with($status, 'revocat') && (empty($revoked) || $revoked <= $now)) {
			$warning = $langs->transnoentities('WarningTreatmentRevoked', $agrodrug->label, $revoked ? dol_print_date($revoked, 'day') : '-');
		} elseif (str_starts_with($status, 'sospes')) {
			$warning = $langs->transnoentities('WarningTreatmentSuspended', $agrodrug->label);
		} elseif (!empty($authend) && $authend < $now) {
			$warning = $langs->transnoentities('WarningTreatmentAuthExpired', $agrodrug->label, dol_print_date($authend, 'day'));
		}
		$check = ($hasorganic ? dolifarmOrganicAgroDrugCheck($db, (int) $agrodrug->rowid) : null);
		$uses = (class_exists('DoliFarmAgroDrugUse') ? DoliFarmAgroDrugUse::fetchAllForAgroDrug($db, (int) $agrodrug->rowid) : array());
		$data['agrodrugs'][] = array(
			'id' => (int) $agrodrug->rowid, 'label' => (string) $agrodrug->label, 'reg' => (string) $agrodrug->reg_number,
			'product_id' => (int) $agrodrug->fk_product, 'max_dose_ha' => ($agrodrug->max_dose_ha > 0 ? (float) $agrodrug->max_dose_ha : null),
			'organic' => ($check ? (string) $check['result'] : ''), 'warning' => $warning,
			// Without uses on the card the register does not check crop, adversity and dose
			'has_uses' => (count($uses) > 0),
		);
		if ($agrodrug->fk_product > 0) {
			$products[(int) $agrodrug->fk_product] = true;
		}
		// Uses on the crops of the plans (a use on a group of the EPPO taxonomy covers its species)
		foreach ($uses as $use) {
			$usecode = strtoupper((string) $use->crop_eppo);
			foreach (array_keys($crops) as $code) {
				if ($usecode !== $code && !($hastaxonomy && dolifarmEppoCovers($db, $usecode, $code))) {
					continue;
				}
				$data['uses'][] = array(
					'agrodrug_id' => (int) $agrodrug->rowid, 'crop' => $code,
					'adversity' => strtoupper((string) $use->adversity_code), 'adversity_label' => (string) $use->adversity_label,
					'dose_min' => ($use->dose_min !== null ? (float) $use->dose_min : null), 'dose_max' => ($use->dose_max !== null ? (float) $use->dose_max : null),
					'dose_unit' => (string) $use->dose_unit, 'safety_days' => (int) $use->safety_days,
				);
			}
		}
	}

	// Fertilizers, with their organic check
	foreach (dolitraceTreatmentFertilizers($db) as $fertilizer) {
		$check = ($hasorganic && function_exists('dolifarmOrganicFertilizerCheck') ? dolifarmOrganicFertilizerCheck($db, (int) $fertilizer->rowid) : null);
		$data['fertilizers'][] = array(
			'id' => (int) $fertilizer->rowid, 'label' => (string) $fertilizer->label, 'product_id' => (int) $fertilizer->fk_product,
			'organic' => ($check ? (string) $check['result'] : ''),
		);
		if ($fertilizer->fk_product > 0) {
			$products[(int) $fertilizer->fk_product] = true;
		}
	}

	foreach (dolitraceTreatmentAdversities($db) as $adversity) {
		$data['adversities'][] = array('code' => (string) $adversity->code, 'label' => (string) $adversity->label);
	}
	foreach (array(DoliTraceTreatment::KIND_PLANTPROTECTION, DoliTraceTreatment::KIND_FERTILIZATION) as $kind) {
		foreach (dolitraceTreatmentDoseUnits($db, $kind, DoliTraceTreatmentTarget::TYPE_PLOT) as $unit) {
			$data['dose_units'][] = array(
				'id' => (int) $unit->rowid, 'kind' => $kind, 'code' => (string) $unit->code,
				'label' => html_entity_decode((string) $unit->label, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				'basis' => (string) $unit->basis, 'qty_unit' => (string) $unit->qty_unit, 'factor' => (float) $unit->factor,
			);
		}
	}

	// Operators of the farms, and the operator linked to the user (a contractor works on other farms)
	$sql = "SELECT o.rowid, o.label, o.fk_soc, o.fk_user, o.license_end FROM ".$prefix."dolifarm_operator as o";
	$sql .= " WHERE o.entity IN (".getEntity('dolifarmoperator').") AND o.status = 1";
	$sql .= " AND (o.fk_user = ".((int) $user->id).(count($farms) ? " OR o.fk_soc IN (".$db->sanitize(implode(',', array_keys($farms))).")" : '').")";
	$sql .= " ORDER BY o.label";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$data['operators'][] = array(
				'id' => (int) $obj->rowid, 'label' => (string) $obj->label, 'farm_id' => (int) $obj->fk_soc, 'user_id' => (int) $obj->fk_user,
				'license_end' => dolitracePwaDate($db->jdate($obj->license_end)),
			);
		}
		$db->free($resql);
	} else {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
	}

	// Lots in the warehouse of technical inputs of each farm, as of now (the validation checks the real stock)
	if (isModEnabled('stock') && isModEnabled('productbatch') && count($products) && dol_include_once('/dolifarm/lib/dolifarm_warehouse.lib.php') && function_exists('dolifarmGetFarmWarehouse')) {
		foreach (array_keys($farms) as $farm) {
			$warehouse = dolifarmGetFarmWarehouse($db, $farm, 'inputs');
			if ($warehouse <= 0) {
				continue;
			}
			$sql = "SELECT ps.fk_product, pb.batch, pb.qty, pl.eatby FROM ".$prefix."product_batch as pb";
			$sql .= " INNER JOIN ".$prefix."product_stock as ps ON ps.rowid = pb.fk_product_stock";
			$sql .= " LEFT JOIN ".$prefix."product_lot as pl ON pl.fk_product = ps.fk_product AND pl.batch = pb.batch";
			$sql .= " WHERE ps.fk_entrepot = ".((int) $warehouse)." AND pb.qty > 0";
			$sql .= " AND ps.fk_product IN (".$db->sanitize(implode(',', array_keys($products))).")";
			$sql .= " ORDER BY pl.eatby, pb.batch";
			$resql = $db->query($sql);
			if ($resql) {
				while ($obj = $db->fetch_object($resql)) {
					$data['lots'][] = array(
						'farm_id' => $farm, 'product_id' => (int) $obj->fk_product, 'batch' => (string) $obj->batch,
						'qty' => (float) $obj->qty, 'eatby' => dolitracePwaDate($db->jdate($obj->eatby)),
					);
				}
				$db->free($resql);
			} else {
				dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
			}
		}
	}

	// Plots in conversion or organic today
	if (count($plots) && dol_include_once('/dolifarm/lib/dolifarm_bio.lib.php') && function_exists('dolifarmBioStatusAt')) {
		foreach (array_keys($plots) as $plot) {
			if (dolifarmBioIsOrganic(dolifarmBioStatusAt($db, $plot, $now))) {
				$data['organic_plots'][] = $plot;
			}
		}
	}
	return $data;
}

/**
 * Draft of a treatment or of a fertilization sent by the app, on the plot of a production plan (not saved yet)
 *
 * data: {cropplan_id, kind: 1|2, date, date_end, area_ha, bbch, operator_id, machine_id, water_volume, note,
 * products: [{item_id, adversity, dose, unit_id, qty_total, batch}]}. The office completes and validates it (stock,
 * hash, weather).
 *
 * @param	DoliDB		$db			Database handler
 * @param	User		$user		User
 * @param	Translate	$langs		Language
 * @param	string		$clientid	Identifier of the phone
 * @param	array<string,mixed>	$data	Data of the record
 * @param	DoliTraceCropplan	$plan	Production plan, already checked
 * @param	int			$entity		Entity of the plan
 * @param	string		$note		Note of the record, with the origin
 * @return	DoliTraceTreatment|array<string,mixed>	Object to create, or the result of an error
 */
function dolitracePwaTreatmentObject($db, $user, $langs, $clientid, $data, $plan, $entity, $note)
{
	dol_include_once('/dolitrace/class/dolitracetreatment.class.php');
	dol_include_once('/dolitrace/lib/dolitrace_treatment.lib.php');
	$langs->loadLangs(array('dolitrace@dolitrace', 'dolifarm@dolifarm'));
	$maxdate = dol_now() + 86400;

	$kind = (int) ($data['kind'] ?? 0);
	if (!in_array($kind, array(DoliTraceTreatment::KIND_PLANTPROTECTION, DoliTraceTreatment::KIND_FERTILIZATION))) {
		return dolitracePwaResult($clientid, 'kind', $langs->transnoentities('PwaErrorValue', $langs->transnoentities('TreatmentKind')));
	}
	$start = dolitracePwaParseDate($data['date'] ?? null);
	if ($start === null || $start > $maxdate) {
		return dolitracePwaResult($clientid, 'date', $langs->transnoentities('PwaErrorValue', $langs->transnoentities('TreatmentDateStart')));
	}
	$end = null;
	if (!empty($data['date_end'])) {
		$end = dolitracePwaParseDate($data['date_end']);
		if ($end === null || $end < $start || $end > $maxdate) {
			return dolitracePwaResult($clientid, 'date_end', $langs->transnoentities('PwaErrorValue', $langs->transnoentities('TreatmentDateEnd')));
		}
	}
	// Operator: of the farm, or the one linked to the user
	$operator = (int) ($data['operator_id'] ?? 0);
	if ($operator > 0) {
		$resql = $db->query("SELECT fk_soc, fk_user FROM ".$db->prefix()."dolifarm_operator WHERE rowid = ".$operator." AND status = 1 AND entity IN (".getEntity('dolifarmoperator').")");
		$obj = ($resql ? $db->fetch_object($resql) : null);
		if (!$obj || ((int) $obj->fk_soc > 0 && (int) $obj->fk_soc != (int) $plan->fk_soc && (int) $obj->fk_user != (int) $user->id)) {
			return dolitracePwaResult($clientid, 'operator', $langs->transnoentities('PwaErrorValue', $langs->transnoentities('TreatmentOperator')));
		}
	}
	$machine = (int) ($data['machine_id'] ?? 0);
	if ($machine > 0) {
		$resql = $db->query("SELECT fk_soc FROM ".$db->prefix()."dolifarm_machine WHERE rowid = ".$machine." AND entity IN (".getEntity('dolifarmmachine').")");
		$obj = ($resql ? $db->fetch_object($resql) : null);
		if (!$obj || ((int) $obj->fk_soc > 0 && (int) $obj->fk_soc != (int) $plan->fk_soc)) {
			return dolitracePwaResult($clientid, 'machine', $langs->transnoentities('PwaErrorValue', $langs->transnoentities('TreatmentMachine')));
		}
	}
	$area = (float) ($data['area_ha'] ?? 0);
	if ($area < 0 || $area >= 100000) {
		return dolitracePwaResult($clientid, 'area_ha', $langs->transnoentities('PwaErrorValue', $langs->transnoentities('TreatmentTreatedQty')));
	}
	$bbch = trim((string) (is_scalar($data['bbch'] ?? null) ? $data['bbch'] : ''));
	if ($bbch !== '' && !preg_match('/^[0-9]{1,3}$/', $bbch)) {
		return dolitracePwaResult($clientid, 'bbch', $langs->transnoentities('ErrorTreatmentBbch'));
	}
	$water = (float) ($data['water_volume'] ?? 0);
	if ($water < 0 || $water >= 100000) {
		return dolitracePwaResult($clientid, 'water_volume', $langs->transnoentities('PwaErrorValue', $langs->transnoentities('TreatmentWaterVolume')));
	}

	// Products: at least one, from the registries (the lines are created after the record)
	$itemlabel = $langs->transnoentities($kind == DoliTraceTreatment::KIND_PLANTPROTECTION ? 'TreatmentAgroDrug' : 'TreatmentFertilizer');
	$lines = (is_array($data['products'] ?? null) ? array_values($data['products']) : array());
	if (!count($lines) || count($lines) > 20) {
		return dolitracePwaResult($clientid, 'products', $langs->transnoentities('PwaErrorValue', $itemlabel));
	}
	$items = ($kind == DoliTraceTreatment::KIND_PLANTPROTECTION ? dolitraceTreatmentAgroDrugs($db) : dolitraceTreatmentFertilizers($db));
	$units = dolitraceTreatmentDoseUnits($db, $kind, DoliTraceTreatmentTarget::TYPE_PLOT);
	$products = array();
	foreach ($lines as $i => $line) {
		$line = (is_array($line) ? $line : array());
		$item = $items[(int) ($line['item_id'] ?? 0)] ?? null;
		if (!$item) {
			return dolitracePwaResult($clientid, 'product', $langs->transnoentities('PwaErrorValue', $itemlabel).' ('.($i + 1).')');
		}
		$product = new DoliTraceTreatmentProduct($db);
		$product->rang = $i + 1;
		$product->fk_product = ($item->fk_product > 0 ? (int) $item->fk_product : null);
		$product->product_label = $item->label;
		if ($kind == DoliTraceTreatment::KIND_PLANTPROTECTION) {
			$product->fk_agrodrug = (int) $item->rowid;
			$product->reg_number = $item->reg_number;
			// Adversity by EPPO code: from the uses of the label, or any code of the EPPO data (added to the dictionary)
			$error = '';
			$adversity = dolitraceTreatmentAdversityByCode($db, (string) (is_scalar($line['adversity'] ?? null) ? $line['adversity'] : ''), $error);
			if (!$adversity) {
				return dolitracePwaResult($clientid, 'adversity', ($error !== '' ? $error : $langs->transnoentities('ErrorFieldRequired', $langs->transnoentities('TreatmentAdversity'))).' ('.$item->label.')');
			}
			$product->fk_adversity = (int) $adversity->rowid;
			$product->adversity_code = $adversity->code;
			$product->adversity_label = $adversity->label;
		} else {
			$product->fk_fertilizer = (int) $item->rowid;
			$product->reg_number = $item->registration_number;
			$product->n_content = $item->n_content;
			$product->p2o5_content = $item->p2o5_content;
			$product->k2o_content = $item->k2o_content;
		}
		$unit = $units[(int) ($line['unit_id'] ?? 0)] ?? null;
		$dose = (float) ($line['dose'] ?? 0);
		if (!$unit || $dose <= 0 || $dose >= 1e9) {
			return dolitracePwaResult($clientid, 'dose', $langs->transnoentities('PwaErrorValue', $langs->transnoentities('TreatmentDose')).' ('.$item->label.')');
		}
		$product->dose = $dose;
		$product->fk_dose_unit = (int) $unit->rowid;
		$product->dose_unit_label = $unit->label;
		$product->qty_unit = $unit->qty_unit;
		// Total quantity: given on the phone, otherwise computed from the dose and the treated surface
		$qty = (float) ($line['qty_total'] ?? 0);
		$product->qty_auto = ($qty > 0 && $qty < 1e9 ? 0 : 1);
		$product->qty_total = ($product->qty_auto ? null : $qty);
		$batch = trim((string) (is_scalar($line['batch'] ?? null) ? $line['batch'] : ''));
		$product->batch = dol_trunc(dol_string_nohtmltag($batch), 128, 'right', 'UTF-8', 1);
		$product->stock_status = DoliTraceTreatmentProduct::STOCK_NONE;
		$products[] = $product;
	}

	$object = new DoliTraceTreatment($db);
	$object->entity = $entity;
	$object->kind = $kind;
	$object->technical_type = DoliTraceTreatmentTarget::TYPE_PLOT;
	$object->fk_soc = (int) $plan->fk_soc;
	$object->fk_cropplan = (int) $plan->id;
	$object->date_start = $start;
	$object->date_end = $end;
	$object->fk_operator = ($operator > 0 ? $operator : null);
	$object->fk_machine = ($machine > 0 ? $machine : null);
	$object->water_volume = ($water > 0 ? $water : null);
	$object->note_private = $note;
	$object->status = DoliTraceTreatment::STATUS_DRAFT;
	$object->context['pwa'] = array('products' => $products, 'area_ha' => ($area > 0 ? $area : null), 'bbch' => $bbch);
	return $object;
}

/**
 * Lines of a draft of a treatment made by the app: surface of the plan (with the area and the BBCH of the phone) and
 * products, then the computed quantities. In the transaction of the creation.
 *
 * @param	User				$user	User
 * @param	DoliTraceTreatment	$object	Record just created
 * @return	int							<0 if KO, >0 if OK
 */
function dolitracePwaTreatmentLines($user, $object)
{
	$pwa = $object->context['pwa'];
	if ($object->fetchLines() < 0) {
		return -1;
	}
	foreach ($object->targets as $target) {
		if ($pwa['area_ha'] !== null) {
			$target->treated_qty = $pwa['area_ha'];
		}
		if ($pwa['bbch'] !== '') {
			$target->bbch = $pwa['bbch'];
		}
		if ($target->update($user) < 0) {
			$object->error = $target->error;
			$object->errors = (array) $target->errors;
			return -1;
		}
	}
	foreach ($pwa['products'] as $product) {
		$product->fk_treatment = $object->id;
		if ($product->create($user) < 0) {
			$object->error = $product->error;
			$object->errors = (array) $product->errors;
			return -1;
		}
	}
	if ($object->fetchLines() < 0 || $object->updateAutoQuantities($user) < 0) {
		return -1;
	}
	return 1;
}
