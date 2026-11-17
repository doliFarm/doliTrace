<?php
/* Copyright (C) 2026 Luigi Grillo - doliFarm.com <info@dolifarm.com>
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
 *    \file       public/trace.php
 *    \ingroup    dolitrace
 *    \brief      Public traceability page of a harvest, opened by the QR code on the label (no login): the story of the
 *                farm and of the product, the harvest, the place and the treatments, only as the farm publishes them
 *                (setup of doliTrace). Same answer for a UUID that does not exist and for a harvest not published.
 */

if (!defined('NOLOGIN')) {
	define('NOLOGIN', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
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
if (!defined('NOIPCHECK')) {
	define('NOIPCHECK', '1');
}
if (!defined('NOBROWSERNOTIF')) {
	define('NOBROWSERNOTIF', '1');
}
if (!defined('NOSESSION')) {
	define('NOSESSION', '1');
}
// Entity of the harvest (multicompany): in the address of the QR code
$entity = (!empty($_GET['entity']) ? (int) $_GET['entity'] : 0);
if ($entity > 0) {
	define('DOLENTITY', $entity);
}

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 */

// Language of the browser, or the one asked
$lang = GETPOST('lang', 'aZ09');
$langs->setDefaultLang($lang !== '' ? $lang : 'auto');
$langs->loadLangs(array('main', 'dolitrace@dolitrace'));

$history = null;
if (isModEnabled('dolitrace')) {
	dol_include_once('/dolitrace/class/dolitracetrace.class.php');
	$trace = new DoliTraceTrace($db);
	$history = $trace->getPublicHistory(GETPOST('u', 'aZ09'), $langs);
}

$esc = function ($text) {
	return dol_escape_htmltag((string) $text);
};
$day = function ($date) use ($langs) {
	return $date ? dol_print_date($date, 'day', 'tzserver', $langs) : '';
};

if ($history === null) {
	http_response_code(404);
}
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-cache');

$title = ($history ? $history['product']['label'].' - '.$history['farm']['name'] : $langs->transnoentities('TracePublicTitle'));
print '<!DOCTYPE html>'."\n";
print '<html lang="'.$esc(substr((string) $langs->defaultlang, 0, 2)).'"><head><meta charset="utf-8">';
print '<meta name="viewport" content="width=device-width, initial-scale=1">';
print '<meta name="robots" content="noindex, nofollow">';
print '<title>'.$esc($title).'</title>';
print '<style>
:root { --ink: #1f2a1f; --muted: #5d6b5d; --line: #dfe6dc; --bg: #f6f8f3; --card: #fff; --accent: #3f7d3a; }
* { box-sizing: border-box; }
body { margin: 0; font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--ink); background: var(--bg); }
main { max-width: 680px; margin: 0 auto; padding: 16px; }
header.farm { display: flex; align-items: center; gap: 14px; margin: 8px 0 16px; }
header.farm img { width: 64px; height: 64px; object-fit: contain; border-radius: 8px; background: #fff; }
h1 { font-size: 1.5rem; margin: 0 0 4px; line-height: 1.2; }
h2 { font-size: 1.1rem; margin: 0 0 8px; color: var(--accent); }
.muted { color: var(--muted); font-size: .92rem; }
section { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 16px; margin: 0 0 14px; }
.photo { width: 100%; max-height: 320px; object-fit: cover; border-radius: 12px; margin: 0 0 14px; display: block; }
dl { display: grid; grid-template-columns: max-content 1fr; gap: 4px 14px; margin: 0; }
dt { color: var(--muted); } dd { margin: 0; }
.badge { display: inline-block; padding: 2px 10px; border-radius: 999px; background: #e3f0df; color: var(--accent); font-weight: 600; font-size: .9rem; }
ul { margin: 6px 0 0; padding-left: 20px; }
.story { white-space: normal; }
iframe { width: 100%; height: 240px; border: 0; border-radius: 8px; margin-top: 10px; }
footer { text-align: center; color: var(--muted); font-size: .85rem; margin: 18px 0; }
a { color: var(--accent); }
</style>';
print '</head><body><main>';

if ($history === null) {
	print '<section><h1>'.$esc($langs->transnoentities('TracePublicTitle')).'</h1>';
	print '<p>'.$esc($langs->transnoentities('TracePublicNotFound')).'</p></section>';
	print '</main></body></html>';
	$db->close();
	exit;
}

$farm = $history['farm'];
$product = $history['product'];

// Farm
print '<header class="farm">';
if ($farm['logo'] !== '') {
	print '<img src="'.$esc($farm['logo']).'" alt="">';
}
print '<div><div class="muted">'.$esc($langs->transnoentities('TracePublicTitle')).'</div>';
print '<h1>'.$esc($product['label']).'</h1>';
print '<div>'.$esc($farm['name']).($farm['town'] !== '' ? ' &middot; '.$esc($farm['town']) : '').'</div></div>';
print '</header>';

if ($product['photo'] !== '') {
	print '<img class="photo" src="'.$esc($product['photo']).'" alt="'.$esc($product['label']).'">';
}

// Harvests of the lot (the one of the QR code first)
$several = (count($history['harvests']) > 1);
foreach ($history['harvests'] as $i => $h) {
	print '<section>';
	print '<h2>'.$esc($langs->transnoentities($i == 0 ? 'TracePublicHarvest' : 'TracePublicOtherHarvest')).'</h2>';
	print '<dl>';
	print '<dt>'.$esc($langs->transnoentities('TracePublicHarvestDate')).'</dt><dd>'.$esc($day($h['date'])).'</dd>';
	if ($i == 0) {
		if ($history['batch'] !== '') {
			print '<dt>'.$esc($langs->transnoentities('TracePublicLot')).'</dt><dd>'.$esc($history['batch']).'</dd>';
		}
		print '<dt>'.$esc($langs->transnoentities('TracePublicCode')).'</dt><dd>'.$esc($history['short']).'</dd>';
	}
	if ($h['crop'] !== '') {
		print '<dt>'.$esc($langs->transnoentities('TracePublicCrop')).'</dt><dd>'.$esc($h['crop']).'</dd>';
	}
	$place = trim($h['plot'].($h['plot'] !== '' && $h['comune'] !== '' ? ', ' : '').$h['comune']);
	if ($place !== '') {
		print '<dt>'.$esc($langs->transnoentities('TracePublicPlace')).'</dt><dd>'.$esc($place).'</dd>';
	}
	if ($h['bio_label'] !== '') {
		print '<dt>'.$esc($langs->transnoentities('TracePublicBio')).'</dt><dd>'.($h['bio_status'] === 'BIO' ? '<span class="badge">'.$esc($h['bio_label']).'</span>' : $esc($h['bio_label'])).'</dd>';
	}
	print '</dl>';

	// Treatments: summary, and the list when the farm publishes it
	$s = $h['summary'];
	print '<h2 style="margin-top:14px">'.$esc($langs->transnoentities('TracePublicTreatments')).'</h2>';
	if ($s['protection'] == 0 && $s['fertilization'] == 0) {
		print '<div>'.$esc($langs->transnoentities('TracePublicNoTreatments')).'</div>';
	} else {
		print '<ul>';
		print '<li>'.$esc($langs->transnoentities('TracePublicProtection', $s['protection'])).'</li>';
		print '<li>'.$esc($langs->transnoentities('TracePublicFertilization', $s['fertilization'])).'</li>';
		if ($s['protection'] > 0 && $s['last_days'] !== null) {
			print '<li>'.$esc($langs->transnoentities('TracePublicLastDays', $s['last_days'])).'</li>';
		}
		if ($s['all_organic']) {
			print '<li>'.$esc($langs->transnoentities('TracePublicAllOrganic')).'</li>';
		}
		print '</ul>';
		if ($history['list'] && count($h['treatments'])) {
			print '<dl style="margin-top:10px">';
			foreach ($h['treatments'] as $line) {
				print '<dt>'.$esc($day($line['date'])).'</dt><dd>'.$esc($langs->transnoentities($line['kind'] == 2 ? 'TracePublicKindFertilization' : 'TracePublicKindProtection'));
				if (count($line['items'])) {
					print ': '.$esc(implode(', ', $line['items']));
				}
				print '</dd>';
			}
			print '</dl>';
		}
	}

	if ($h['lat'] !== null && $h['lon'] !== null) {
		$d = 0.01;
		$bbox = implode(',', array_map(function ($v) {
			return number_format($v, 5, '.', '');
		}, array($h['lon'] - $d, $h['lat'] - $d, $h['lon'] + $d, $h['lat'] + $d)));
		$marker = number_format($h['lat'], 5, '.', '').','.number_format($h['lon'], 5, '.', '');
		print '<iframe loading="lazy" referrerpolicy="no-referrer" title="'.$esc($langs->transnoentities('TracePublicPlace')).'" src="https://www.openstreetmap.org/export/embed.html?bbox='.$esc($bbox).'&amp;layer=mapnik&amp;marker='.$esc($marker).'"></iframe>';
		print '<div class="muted">'.$esc($langs->transnoentities('TracePublicMapAttribution')).'</div>';
	}

	if ($h['note_public'] !== '') {
		print '<h2 style="margin-top:14px">'.$esc($langs->transnoentities('TracePublicHarvestNote')).'</h2>';
		print '<div class="story">'.dolPrintHTML($h['note_public']).'</div>';
	}
	print '</section>';
}
if ($several) {
	print '<p class="muted">'.$esc($langs->transnoentities('TracePublicLotHelp', count($history['harvests']))).'</p>';
}

// Product and farm stories
if ($product['story'] !== '') {
	print '<section><h2>'.$esc($langs->transnoentities('TracePublicProduct')).'</h2><div class="story">'.dolPrintHTML($product['story']).'</div></section>';
}
if ($farm['story'] !== '' || $farm['bio_cert'] !== '' || $farm['url'] !== '') {
	print '<section><h2>'.$esc($langs->transnoentities('TracePublicFarm')).'</h2>';
	if ($farm['story'] !== '') {
		print '<div class="story">'.dolPrintHTML($farm['story']).'</div>';
	}
	if ($farm['bio_cert'] !== '') {
		print '<p class="muted">'.$esc($langs->transnoentities('TracePublicCertificate', $farm['bio_cert'], $farm['bio_body'])).'</p>';
	}
	if ($farm['url'] !== '') {
		print '<p><a href="'.$esc($farm['url']).'" rel="noopener nofollow">'.$esc(preg_replace('/^https?:\/\//i', '', $farm['url'])).'</a></p>';
	}
	print '</section>';
}

print '<footer>'.$esc($langs->transnoentities('TracePublicFooter')).'</footer>';
print '</main></body></html>';

$db->close();
