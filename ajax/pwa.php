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
 * \file    dolitrace/ajax/pwa.php
 * \ingroup dolitrace
 * \brief   Endpoint JSON of the field app (PWA), with the session of Dolibarr
 *
 * GET  ?action=session          user, rights, token for the sendings (also to check that the session is still open)
 * GET  ?action=data             data kept offline by the phone (plots, open production plans, last records, ...)
 * POST ?action=send&token=...   body JSON {"records": [{client_id, kind, created_at, data}]}: drafts of operations and
 *                               harvests, one result for each record (sent again: the record already created)
 *
 * Without a session Dolibarr answers with its login page (HTML, not JSON): the app asks to log in again.
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOBROWSERNOTIF')) {
	define('NOBROWSERNOTIF', '1');
}
// The token of the sendings is checked below, to answer in JSON instead of the HTML page of main.inc.php
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
}

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
	http_response_code(500);
	die("Include of main fails");
}
/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */
dol_include_once('/dolitrace/lib/dolitrace_pwa.lib.php');

$langs->loadLangs(array('main', 'dolitrace@dolitrace', 'dolifarm@dolifarm'));

$action = GETPOST('action', 'aZ09');

/**
 * Print the answer and stop
 *
 * @param	array<string,mixed>	$answer	Answer
 * @param	int					$code	HTTP status
 * @return	never
 */
function dolitracePwaOut($answer, $code = 200)
{
	global $db;

	http_response_code($code);
	print json_encode($answer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$db->close();
	exit;
}

top_httphead('application/json');
header('Cache-Control: no-store');

$rights = dolitracePwaRights($user);
if (!isModEnabled('dolitrace') || !isModEnabled('dolifarm')) {
	dolitracePwaOut(array('ok' => false, 'error' => 'disabled', 'message' => 'doliTrace / doliFarm'), 404);
}
if (!$rights['use']) {
	dolitracePwaOut(array('ok' => false, 'error' => 'forbidden', 'message' => $langs->transnoentities('PwaErrorForbidden')), 403);
}

if ($action == 'session') {
	$token = currentToken();
	dolitracePwaOut(array(
		'ok' => true, 'api' => DOLITRACE_PWA_API, 'server_time' => dolitracePwaDate(dol_now(), true),
		'user' => array('id' => (int) $user->id, 'login' => (string) $user->login, 'name' => $user->getFullName($langs), 'lang' => (string) $langs->defaultlang),
		'entity' => (int) $conf->entity, 'rights' => array('operation' => $rights['operation'], 'harvest' => $rights['harvest']),
		'token' => ($token !== '' ? $token : newToken()),
	));
}

if ($action == 'data') {
	dolitracePwaOut(array_merge(array('ok' => true, 'api' => DOLITRACE_PWA_API, 'server_time' => dolitracePwaDate(dol_now(), true)), dolitracePwaData($db, $user, $langs)));
}

if ($action == 'send') {
	if (empty($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
		dolitracePwaOut(array('ok' => false, 'error' => 'method', 'message' => 'POST'), 405);
	}
	$token = GETPOST('token', 'alpha');
	if ($token === '' || !hash_equals(currentToken(), $token)) {
		dolitracePwaOut(array('ok' => false, 'error' => 'token', 'message' => $langs->transnoentities('PwaErrorToken')), 403);
	}
	$body = file_get_contents('php://input', false, null, 0, 1048576);
	$input = json_decode((string) $body, true);
	$records = (is_array($input) && isset($input['records']) && is_array($input['records']) ? array_values($input['records']) : null);
	if ($records === null || count($records) > DOLITRACE_PWA_MAXBATCH) {
		dolitracePwaOut(array('ok' => false, 'error' => 'body', 'message' => $langs->transnoentities('PwaErrorValue', 'records')), 400);
	}
	$results = array();
	foreach ($records as $record) {
		$results[] = dolitracePwaCreate($db, $user, $langs, $record);
	}
	dolitracePwaOut(array('ok' => true, 'api' => DOLITRACE_PWA_API, 'results' => $results));
}

dolitracePwaOut(array('ok' => false, 'error' => 'action', 'message' => $langs->transnoentities('PwaErrorValue', 'action')), 400);
