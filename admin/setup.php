<?php
/* Copyright (C) 2004-2017  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2026		Luigi Grillo - doliFarm.com	<info@dolifarm.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    dolitrace/admin/setup.php
 * \ingroup dolitrace
 * \brief   Dolitrace setup page.
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
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
require_once '../lib/dolitrace.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Translations
$langs->loadLangs(array("admin", "dolitrace@dolitrace"));

// Initialize a technical object to manage hooks of page. Note that conf->hooks_modules contains an array of hook context
$hookmanager->initHooks(array('dolitracesetup', 'globalsetup'));

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');	// Used by actions_setmoduleoptions.inc.php

$error = 0;

// Access control
if (!$user->admin) {
	accessforbidden();
}

$formSetup = new FormSetup($db);

// Register of treatments: limit of nitrogen of livestock origin in vulnerable zones, kg N/ha a year (0 = no warning)
$item = $formSetup->newItem('DOLITRACE_ZVN_N_LIVESTOCK_LIMIT');
$item->defaultFieldValue = '170';
$item->fieldAttr['type'] = 'number';
$item->fieldAttr['min'] = '0';
$item->helpText = $langs->transnoentities('DOLITRACE_ZVN_N_LIVESTOCK_LIMITHelp');

// Register of treatments: applications in a row with the same group of the mode of action (FRAC, IRAC, HRAC), 0 = no warning
$item = $formSetup->newItem('DOLITRACE_MOA_MAX_CONSECUTIVE');
$item->defaultFieldValue = '2';
$item->fieldAttr['type'] = 'number';
$item->fieldAttr['min'] = '0';
$item->helpText = $langs->transnoentities('DOLITRACE_MOA_MAX_CONSECUTIVEHelp');

// Public traceability page opened by the QR code on the label
dol_include_once('/dolitrace/class/dolitracetrace.class.php');
$formSetup->newItem('TracePublicSetup')->setAsTitle();

$item = $formSetup->newItem('DOLITRACE_PUBLIC_ENABLED');
$item->setAsYesNo();
$item->helpText = $langs->transnoentities('DOLITRACE_PUBLIC_ENABLEDHelp');

$item = $formSetup->newItem('DOLITRACE_PUBLIC_URL');
$item->setAsUrl();
$item->helpText = $langs->transnoentities('DOLITRACE_PUBLIC_URLHelp', dol_buildpath('/dolitrace/public/trace.php', 2));

$item = $formSetup->newItem('DOLITRACE_PUBLIC_TREATMENT_LIST');
$item->setAsYesNo();
$item->helpText = $langs->transnoentities('DOLITRACE_PUBLIC_TREATMENT_LISTHelp');

$item = $formSetup->newItem('DOLITRACE_PUBLIC_MAP');
$item->setAsYesNo();
$item->helpText = $langs->transnoentities('DOLITRACE_PUBLIC_MAPHelp');

// The objects of doliTrace have no numbering or document models to choose: no other section


/*
 * Actions
 */

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

$action = 'edit';


/*
 * View
 */

$form = new Form($db);

$help_url = '';
$title = "DolitraceSetup";

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-dolitrace page-admin');

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.img_picto($langs->trans("BackToModuleList"), 'back', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans("BackToModuleList").'</span></a>';

print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

// Configuration header
$head = dolitraceAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($title), -1, "dolitrace@dolitrace");

// Setup page goes here
echo '<span class="opacitymedium">'.$langs->trans("DolitraceSetupPage").'</span><br><br>';

if (!empty($formSetup->items)) {
	print $formSetup->generateOutput(true);
	print '<br>';
} else {
	print '<br>'.$langs->trans("NothingToSetup");
}

// Form for the website of the farm: HTML code to copy, it opens the public page from the code on the label
print load_fiche_titre($langs->trans('TracePublicEmbed'), '', 'fa-code');
if (!getDolGlobalInt('DOLITRACE_PUBLIC_ENABLED')) {
	print '<span class="opacitymedium">'.$langs->trans('TracePublicEmbedDisabled').'</span><br><br>';
} else {
	// Farms with production plans (more farms on the same Dolibarr: the form can be limited to one of them)
	$farms = array();
	$sql = "SELECT DISTINCT s.rowid, s.nom FROM ".$db->prefix()."dolitrace_cropplan as cp";
	$sql .= " INNER JOIN ".$db->prefix()."societe as s ON s.rowid = cp.fk_soc";
	$sql .= " WHERE cp.entity IN (".getEntity('dolitracecropplan').") ORDER BY s.nom";
	$resql = $db->query($sql);
	while ($resql && ($obj = $db->fetch_object($resql))) {
		$farms[(int) $obj->rowid] = (string) $obj->nom;
	}
	$snippet = function ($farmid) use ($langs, $conf) {
		$html = '<form action="'.dol_escape_htmltag(DoliTraceTrace::publicPageUrl()).'" method="get" target="_blank" class="dolitrace-form">'."\n";
		$html .= '  <label for="dolitrace-code">'.dol_escape_htmltag($langs->transnoentities('TracePublicTitle')).'</label>'."\n";
		$html .= '  <input type="text" id="dolitrace-code" name="c" required maxlength="64" placeholder="'.dol_escape_htmltag($langs->transnoentities('TracePublicFormPlaceholder')).'">'."\n";
		if ($farmid > 0) {
			$html .= '  <input type="hidden" name="farm" value="'.((int) $farmid).'">'."\n";
		}
		if ((int) $conf->entity > 1) {
			$html .= '  <input type="hidden" name="entity" value="'.((int) $conf->entity).'">'."\n";
		}
		$html .= '  <button type="submit">'.dol_escape_htmltag($langs->transnoentities('TracePublicFormButton')).'</button>'."\n";
		$html .= '</form>';
		return $html;
	};
	$snippets = array(0 => $snippet(0));
	foreach ($farms as $farmid => $name) {
		$snippets[$farmid] = $snippet($farmid);
	}

	print '<span class="opacitymedium">'.$langs->trans('TracePublicEmbedHelp').'</span><br><br>';
	print '<table class="noborder centpercent" id="dolitrace-embed">';
	if (count($farms) > 1) {
		print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('TracePublicEmbedFarm').'</td><td><select id="dolitrace-embed-farm" class="minwidth300">';
		print '<option value="0">'.$langs->trans('TracePublicEmbedAllFarms').'</option>';
		foreach ($farms as $farmid => $name) {
			print '<option value="'.$farmid.'">'.dol_escape_htmltag($name).'</option>';
		}
		print '</select></td></tr>';
	}
	print '<tr class="oddeven"><td class="titlefield tdtop">'.$langs->trans('TracePublicEmbedCode').'</td><td>';
	print '<textarea id="dolitrace-embed-code" class="quatrevingtpercent" rows="8" readonly style="font-family: monospace;">'.htmlspecialchars($snippets[0], ENT_QUOTES, 'UTF-8').'</textarea><br>';
	print '<button type="button" class="button smallpaddingimp" id="dolitrace-embed-copy">'.$langs->trans('TracePublicEmbedCopy').'</button>';
	print ' <span id="dolitrace-embed-copied" class="opacitymedium" style="display:none">'.$langs->trans('TracePublicEmbedCopied').'</span>';
	print '</td></tr>';
	print '<tr class="oddeven"><td class="tdtop">'.$langs->trans('TracePublicEmbedPreview').'</td><td><div id="dolitrace-embed-preview">'.$snippets[0].'</div></td></tr>';
	print '</table>';
	print '<script>
$(function () {
	var snippets = '.json_encode($snippets, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT).';
	$("#dolitrace-embed-farm").on("change", function () {
		var code = snippets[$(this).val()] || snippets[0];
		$("#dolitrace-embed-code").val(code);
		$("#dolitrace-embed-preview").html(code);
	});
	$("#dolitrace-embed-copy").on("click", function () {
		var area = document.getElementById("dolitrace-embed-code");
		// The code stays selected: Ctrl+C works also when the browser refuses the clipboard
		area.focus();
		area.select();
		var done = function () { $("#dolitrace-embed-copied").stop(true, true).show().delay(2000).fadeOut(); };
		var fallback = function () { area.focus(); area.select(); if (document.execCommand("copy")) { done(); } };
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(area.value).then(done, fallback);
		} else {
			fallback();
		}
	});
});
</script>';
}

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
