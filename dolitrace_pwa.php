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
 * \file    dolitrace/dolitrace_pwa.php
 * \ingroup dolitrace
 * \brief   Field app: what it does, how to install it on the phone, QR code of its address
 */

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
	die("Include of main fails");
}
/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */
dol_include_once('/dolitrace/lib/dolitrace_pwa.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_help.lib.php'); // Help texts by role (doliFarm >= 1.5.9)

$langs->loadLangs(array('dolitrace@dolitrace'));

$rights = dolitracePwaRights($user);
if (!isModEnabled('dolitrace') || !isModEnabled('dolifarm') || !$rights['use']) {
	accessforbidden();
}

$appurl = dol_buildpath('/dolitrace/pwa/index.html', 2);


/*
 * View
 */

llxHeader('', $langs->trans('PwaTitle'), '', '', 0, 0, '', '', '', 'mod-dolitrace page-pwa');
print load_fiche_titre($langs->trans('PwaTitle'), '', 'fa-mobile-alt');

print '<div class="fichecenter"><div class="fichehalfleft">';
print '<p>'.$langs->trans('PwaIntro').'</p>';
print '<ul>';
foreach (array('PwaFeaturePlots', 'PwaFeatureQr', 'PwaFeatureNear', 'PwaFeatureOffline', 'PwaFeatureRecords') as $key) {
	print '<li>'.$langs->trans($key).'</li>';
}
print '</ul>';
print '<h3>'.$langs->trans('PwaInstall').'</h3>';
print '<ul>';
print '<li>'.$langs->trans('PwaInstallAndroid').'</li>';
print '<li>'.$langs->trans('PwaInstalliPhone').'</li>';
print '</ul>';
print '<p class="opacitymedium">'.$langs->trans('PwaPrivacy').'</p>';
// Permissions: only for the administrators (help by role)
if (!empty($user->admin)) {
	print info_admin($langs->trans('PwaPermissionsAdmin'), 0, 0, 'info');
}
// Service worker, camera and position need https (localhost excepted)
if (!preg_match('/^https:/i', $appurl) && !preg_match('/^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?\//i', $appurl)) {
	print info_admin($langs->trans((function_exists('dolifarmHelpKey') ? dolifarmHelpKey('PwaNeedsHttps') : 'PwaNeedsHttps')), 0, 0, 'warning');
}
print '<div class="center"><a class="butAction" href="'.dol_escape_htmltag($appurl).'" target="_blank" rel="noopener">'.img_picto('', 'fa-external-link-alt', 'class="pictofixedwidth"').$langs->trans('PwaOpen').'</a></div>';
print '</div><div class="fichehalfright"><div class="center">';
require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';
$barcodeobj = new TCPDF2DBarcode($appurl, 'QRCODE,M');
$pngdata = $barcodeobj->getBarcodePngData(5, 5, array(0, 0, 0));
if ($pngdata) {
	print '<img src="data:image/png;base64,'.base64_encode($pngdata).'" alt="QR" style="border: 1px solid #eee; padding: 8px; background: white;">';
}
print '<br><span class="opacitymedium">'.$langs->trans('PwaQrHelp').'</span>';
print '<br><span class="small wordbreak">'.dol_escape_htmltag($appurl).'</span>';
print '</div></div></div>';
print '<div class="clearboth"></div>';

llxFooter();
$db->close();
