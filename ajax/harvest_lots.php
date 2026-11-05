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
 *       ile       ajax/harvest_lots.php
 *       rief      Lot of a harvest for a product: code proposed for a new lot and open lots of the product (JSON)
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
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
}

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
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
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

dol_include_once('/dolitrace/class/dolitraceharvest.class.php');
dol_include_once('/dolitrace/lib/dolitrace_harvest.lib.php');
dol_include_once('/dolitrace/lib/dolitrace_access.lib.php');

if (!isModEnabled('dolitrace') || !$user->hasRight('dolitrace', 'dolitraceharvest', 'write')) {
	http_response_code(403);
	exit;
}

$object = new DoliTraceHarvest($db);
$id = GETPOSTINT('id');
if ($id > 0) {
	if ($object->fetch($id) <= 0) {
		http_response_code(404);
		exit;
	}
	dolitraceCheckAccess($user, $object);
}
$object->fk_cropplan = GETPOSTINT('fk_cropplan') > 0 ? GETPOSTINT('fk_cropplan') : (int) $object->fk_cropplan;
$object->fk_product_out = GETPOSTINT('fk_product_out') > 0 ? GETPOSTINT('fk_product_out') : 0;

$langs->loadLangs(array('dolitrace@dolitrace'));
$options = array();
foreach (dolitraceHarvestLotOptions($object) as $key => $text) {
	$options[] = array('id' => (string) $key, 'text' => $text);
}

top_httphead('application/json');
print json_encode(array(
	'proposal' => $object->proposeLot(),
	'options' => $options,
	'none' => $langs->transnoentities($object->fk_product_out > 0 ? 'HarvestLotNoOpen' : 'HarvestLotNoProduct'),
	'notbylot' => ($object->fk_product_out > 0 && !$object->isProductByLot()),
));
