<?php
/* Copyright (C) 2026      Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
 *    \file       dolitraceharvest_labels.php
 *    \ingroup    dolitrace
 *    \brief      Labels with the QR code of a harvest, in PDF, on A4 sheets of adhesive labels
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
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
// Try main.inc.php using relative path
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
	die("Include of main fails");
}

dol_include_once('/dolitrace/class/dolitracetrace.class.php');

$langs->loadLangs(array("dolitrace@dolitrace", "dolifarm@dolifarm", "other"));

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$formats = DoliTraceTrace::labelFormats();
$format = GETPOST('format', 'aZ09');
if (!isset($formats[$format])) {
	$format = getDolGlobalString('DOLITRACE_LABEL_FORMAT', 'A4_3x8');
	if (!isset($formats[$format])) {
		$format = 'A4_3x8';
	}
}
$f = $formats[$format];
$number = GETPOSTISSET('number') ? GETPOSTINT('number') : $f['cols'] * $f['rows'];
$skip = GETPOSTINT('skip');

// Security check: harvests, then the farm of the harvest through the filter of the query
if (!isModEnabled('dolitrace') || !$user->hasRight('dolitrace', 'dolitraceharvest', 'read')) {
	accessforbidden();
}
$trace = new DoliTraceTrace($db);
$history = ($id > 0 ? $trace->getHistory($user, $id, $langs) : 0);
if (!is_array($history) || $history['harvest']['uuid'] === '') {
	accessforbidden($langs->trans('TraceNotFound'));
}
$h = $history['harvest'];

if ($action == 'pdf') {
	$content = $trace->labelsToPdf($history, $format, $number, $skip, $langs);
	$filename = dol_sanitizeFileName('etichette_'.$h['ref'].'.pdf');
	top_httphead('application/pdf');
	header('Content-Disposition: inline; filename="'.$filename.'"');
	header('Content-Length: '.strlen($content));
	print $content;
	exit;
}

/*
 * View
 */

$title = $langs->trans('TraceLabelsTitle', $h['ref']);
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolitrace page-harvest-labels');
$linkback = '<a href="'.dol_buildpath('/dolitrace/dolitraceharvest_card.php', 1).'?id='.$h['id'].'">'.$langs->trans('Back').'</a>';
print load_fiche_titre($title, $linkback, 'fa-qrcode');

print '<span class="opacitymedium">'.$langs->trans('TraceLabelsHelp').'</span><br><br>';
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" target="_blank" id="dolitrace-labels-form">';
print '<input type="hidden" name="id" value="'.$h['id'].'">';
print '<input type="hidden" name="action" value="pdf">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('HarvestProduct').'</td><td>'.dol_escape_htmltag($h['product'] !== '' ? $h['product'] : $history['cropplan']['crop']).'</td></tr>';
print '<tr><td>'.$langs->trans('HarvestBatch').'</td><td>'.dol_escape_htmltag($h['batch']).'</td></tr>';
print '<tr><td>'.$langs->trans('TraceQrCode').'</td><td class="wordbreak">'.dol_escape_htmltag(DoliTraceTrace::qrUrl($h['uuid'])).'</td></tr>';
print '<tr><td>'.$langs->trans('TraceLabelsFormat').'</td><td><select name="format" id="format" class="minwidth300">';
foreach ($formats as $key => $def) {
	print '<option value="'.$key.'"'.($key == $format ? ' selected' : '').'>'.$langs->trans('TraceLabelsFormat'.$key).'</option>';
}
print '</select></td></tr>';
print '<tr><td>'.$langs->trans('TraceLabelsNumber').'</td><td><input type="number" name="number" min="1" max="1000" class="width75" value="'.((int) $number).'"></td></tr>';
print '<tr><td>'.$langs->trans('TraceLabelsSkip').'</td><td><input type="number" name="skip" min="0" max="39" class="width75" value="'.((int) $skip).'"></td></tr>';
print '</table>';
print '<div class="center"><input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('TraceLabelsPrint')).'"></div>';
print '</form>';

llxFooter();
$db->close();
