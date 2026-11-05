<?php
/* Copyright (C) 2001-2005  Rodolphe Quiedeville    <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012  Regis Houssin           <regis.houssin@inodbox.com>
 * Copyright (C) 2015       Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2026		Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
 *	\file       dolitrace/dolitraceindex.php
 *	\ingroup    dolitrace
 *	\brief      Home page of dolitrace top menu
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
/**
 * The main.inc.php has been included so the following variable are now defined:
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

// Load translation files required by the page
$langs->loadLangs(array("dolitrace@dolitrace", "dolifarm@dolifarm"));

$action = GETPOST('action', 'aZ09');

$now = dol_now();
$max = getDolGlobalInt('MAIN_SIZE_SHORTLIST_LIMIT', 5);

// Security check - Protection if external user
$socid = GETPOSTINT('socid');
if (!empty($user->socid) && $user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

// Security check
if (!isModEnabled('dolitrace')) {
	accessforbidden('Module not enabled');
}
if (!$user->hasRight('dolitrace', 'dolitracecropplan', 'read') && !$user->hasRight('dolitrace', 'dolitraceharvest', 'read') && !$user->hasRight('dolitrace', 'dolitraceoperation', 'read')) {
	accessforbidden();
}


/*
 * Actions
 */

// None


/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader("", $langs->trans("DolitraceArea"), '', '', 0, 0, '', '', '', 'mod-dolitrace page-index');

dol_include_once('/dolifarm/lib/dolifarm_icons.lib.php');
print load_fiche_titre($langs->trans("DolitraceArea"), '', dolifarm_get_fa_icon('dolitrace'));

print '<div class="fichecenter">';


dol_include_once('/dolitrace/class/dolitracecropplan.class.php');
dol_include_once('/dolitrace/class/dolitraceharvest.class.php');
dol_include_once('/dolitrace/class/dolitraceoperation.class.php');

/**
 * Print the start of a box of the home page
 *
 * @param	string	$title	Title, already translated
 * @param	string	$help	Short description, already translated
 * @param	string	$icon	Font Awesome class
 * @param	string	$color	Color of the border and of the icon
 * @return	void
 */
function dolitraceIndexBoxStart(string $title, string $help, string $icon, string $color): void
{
	print '<div class="box" style="border: 1px solid #ddd; border-radius: 5px; padding: 15px; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">';
	print '<div class="box-header" style="border-bottom: 2px solid '.$color.'; padding-bottom: 10px; margin-bottom: 15px;">';
	print '<h3 style="margin: 0; color: #222;"><i class="fa '.$icon.'" style="padding-right: 8px; color: '.$color.';"></i>'.$title.'</h3>';
	print '</div>';
	print '<div class="box-content">';
	print '<p class="opacitymedium" style="font-size: 0.9em; margin-bottom: 15px;">'.$help.'</p>';
}

/**
 * Print the end of a box of the home page, with the link to the list
 *
 * @param	string	$url	Url of the list
 * @param	string	$label	Label of the button, already translated
 * @return	void
 */
function dolitraceIndexBoxEnd(string $url, string $label): void
{
	print '<div style="margin-top: 15px; text-align: right;"><a class="butAction" href="'.$url.'">'.$label.'</a></div>';
	print '</div>';
	print '</div>';
}

$sqlsoc = ($socid > 0 ? " AND cp.fk_soc = ".((int) $socid) : "");

print '<div class="div-table-responsive-no-minify">';
print '<div class="grid-container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">';

// Production plans in progress
if ($user->hasRight('dolitrace', 'dolitracecropplan', 'read')) {
	dolitraceIndexBoxStart($langs->trans("DoliTraceCropplans"), $langs->trans('DoliTraceIndexCropPlansHelp'), 'fa-calendar-check-o', '#0073aa');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans("Ref").'</th><th>'.$langs->trans("CropPlanCrop").'</th><th class="center">'.$langs->trans("Status").'</th></tr>';
	$sql = "SELECT cp.rowid, cp.ref, cp.label, cp.status, c.label as crop_label";
	$sql .= " FROM ".$db->prefix()."dolitrace_cropplan as cp";
	$sql .= " LEFT JOIN ".$db->prefix()."dolifarm_crops as c ON c.rowid = cp.fk_crop";
	$sql .= " WHERE cp.entity IN (".getEntity('dolitracecropplan').")";
	$sql .= " AND cp.status IN (".DoliTraceCropplan::STATUS_DRAFT.", ".DoliTraceCropplan::STATUS_VALIDATED.")".$sqlsoc;
	$sql .= " ORDER BY cp.date_start DESC, cp.rowid DESC";
	$sql .= $db->plimit($max, 0);
	$resql = $db->query($sql);
	$num = 0;
	if ($resql) {
		$static = new DoliTraceCropplan($db);
		while ($obj = $db->fetch_object($resql)) {
			$num++;
			$static->id = $obj->rowid;
			$static->ref = $obj->ref;
			$static->label = $obj->label;
			$static->status = $obj->status;
			print '<tr class="oddeven"><td class="nowraponall">'.$static->getNomUrl(1).'</td>';
			print '<td class="tdoverflowmax150">'.dol_escape_htmltag($langs->trans((string) $obj->crop_label)).'</td>';
			print '<td class="center">'.$static->getLibStatut(5).'</td></tr>';
		}
		$db->free($resql);
	} else {
		dol_print_error($db);
	}
	if (!$num) {
		print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
	}
	print '</table>';
	dolitraceIndexBoxEnd(dol_buildpath('/dolitrace/dolitracecropplan_list.php', 1), $langs->trans("DoliTraceCropplans"));
}

// Latest harvests
if ($user->hasRight('dolitrace', 'dolitraceharvest', 'read')) {
	dolitraceIndexBoxStart($langs->trans("DoliTraceHarvests"), $langs->trans('DoliTraceIndexHarvestsHelp'), 'fa-shopping-basket', '#27ae60');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans("Ref").'</th><th class="right">'.$langs->trans("Qty").'</th><th class="right">'.$langs->trans("Date").'</th></tr>';
	$sql = "SELECT h.rowid, h.ref, h.label, h.status, h.date_harvest, h.qty_harvested, h.qty_unit";
	$sql .= " FROM ".$db->prefix()."dolitrace_harvest as h";
	$sql .= " INNER JOIN ".$db->prefix()."dolitrace_cropplan as cp ON cp.rowid = h.fk_cropplan";
	$sql .= " WHERE cp.entity IN (".getEntity('dolitracecropplan').")";
	$sql .= " AND h.status <> ".DoliTraceHarvest::STATUS_CANCELED.$sqlsoc;
	$sql .= " ORDER BY h.date_harvest DESC, h.rowid DESC";
	$sql .= $db->plimit($max, 0);
	$resql = $db->query($sql);
	$num = 0;
	if ($resql) {
		$static = new DoliTraceHarvest($db);
		$units = $static->fields['qty_unit']['arrayofkeyval'] ?? array();
		while ($obj = $db->fetch_object($resql)) {
			$num++;
			$static->id = $obj->rowid;
			$static->ref = $obj->ref;
			$static->label = $obj->label;
			$static->status = $obj->status;
			$unit = (string) $obj->qty_unit;
			print '<tr class="oddeven"><td class="nowraponall">'.$static->getNomUrl(1).'</td>';
			print '<td class="right nowraponall">'.price2num((float) $obj->qty_harvested, 'MS').' '.dol_escape_htmltag($units[$unit] ?? $unit).'</td>';
			print '<td class="right nowraponall">'.dol_print_date($db->jdate($obj->date_harvest), 'day').'</td></tr>';
		}
		$db->free($resql);
	} else {
		dol_print_error($db);
	}
	if (!$num) {
		print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
	}
	print '</table>';
	dolitraceIndexBoxEnd(dol_buildpath('/dolitrace/dolitraceharvest_list.php', 1), $langs->trans("DoliTraceHarvests"));
}

// Latest crop operations
if ($user->hasRight('dolitrace', 'dolitraceoperation', 'read')) {
	dolitraceIndexBoxStart($langs->trans("DoliTraceOperations"), $langs->trans('DoliTraceIndexOperationsHelp'), 'fa-wrench', '#e67e22');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans("OperationType").'</th><th>'.$langs->trans("CropPlan").'</th><th class="right">'.$langs->trans("Date").'</th></tr>';
	$sql = "SELECT o.rowid, o.ref, o.label, o.status, o.date_op, o.fk_operation_type, cp.rowid as cp_id, cp.ref as cp_ref, cp.label as cp_label, cp.status as cp_status";
	$sql .= " FROM ".$db->prefix()."dolitrace_operation as o";
	$sql .= " LEFT JOIN ".$db->prefix()."dolitrace_cropplan as cp ON cp.rowid = o.fk_cropplan";
	$sql .= " WHERE o.entity IN (".getEntity('dolitraceoperation').")";
	$sql .= " AND o.status <> ".DoliTraceOperation::STATUS_CANCELED.$sqlsoc;
	$sql .= " ORDER BY o.date_op DESC, o.rowid DESC";
	$sql .= $db->plimit($max, 0);
	$resql = $db->query($sql);
	$num = 0;
	if ($resql) {
		$static = new DoliTraceOperation($db);
		$cpstatic = new DoliTraceCropplan($db);
		$types = $static->fields['fk_operation_type']['arrayofkeyval'] ?? array();
		while ($obj = $db->fetch_object($resql)) {
			$num++;
			$static->id = $obj->rowid;
			$static->ref = $obj->ref;
			$static->label = $obj->label;
			$static->status = $obj->status;
			$type = (string) $obj->fk_operation_type;
			$typelabel = $types[$type] ?? $type;
			print '<tr class="oddeven"><td class="tdoverflowmax200">'.$static->getNomUrl(1, '', 0, '', -1).' <span class="opacitymedium">'.dol_escape_htmltag($typelabel).'</span></td>';
			print '<td class="nowraponall">';
			if ($obj->cp_id > 0) {
				$cpstatic->id = $obj->cp_id;
				$cpstatic->ref = $obj->cp_ref;
				$cpstatic->label = $obj->cp_label;
				$cpstatic->status = $obj->cp_status;
				print $cpstatic->getNomUrl(0);
			}
			print '</td>';
			print '<td class="right nowraponall">'.dol_print_date($db->jdate($obj->date_op), 'day').'</td></tr>';
		}
		$db->free($resql);
	} else {
		dol_print_error($db);
	}
	if (!$num) {
		print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
	}
	print '</table>';
	dolitraceIndexBoxEnd(dol_buildpath('/dolitrace/dolitraceoperation_list.php', 1), $langs->trans("DoliTraceOperations"));
}

print '</div>';
print '</div>';

print '</div>';

// End of page
llxFooter();
$db->close();
