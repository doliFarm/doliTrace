<?php
/* Copyright (C) 2026       Luigi Grillo - doliFarm.com	<info@dolifarm.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       dolitracecropplan_treatments.php
 * \ingroup    dolitrace
 * \brief      Tab "Treatment register" of a crop plan: its plant protection treatments and fertilizations
 *
 * The records on surfaces belong to one crop plan: they are created from here (or from the menu, choosing the crop plan),
 * and imported from a CSV limited to the crop plan.
 */

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
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
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
dol_include_once('/dolitrace/class/dolitracecropplan.class.php');
dol_include_once('/dolitrace/class/dolitracetreatment.class.php');
dol_include_once('/dolitrace/lib/dolitrace_cropplan.lib.php');
dol_include_once('/dolitrace/lib/dolitrace_access.lib.php');

$langs->loadLangs(array("dolitrace@dolitrace", "other"));

$id = GETPOSTINT('id');

$object = new DoliTraceCropplan($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden();
}
if (!isModEnabled('dolitrace') || !$user->hasRight('dolitrace', 'dolitracetreatment', 'read')) {
	accessforbidden();
}
dolitraceCheckAccess($user, $object);
$permissiontoadd = $user->hasRight('dolitrace', 'dolitracetreatment', 'write');


/*
 * View
 */

$title = $object->ref.' - '.$langs->trans('TreatmentRegisterTab');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolitrace page-cropplan-treatments');

$head = dolitracecropplanPrepareHead($object);
print dol_get_fiche_head($head, 'treatments', $langs->trans('DoliTraceCropplan'), -1, $object->picto);
$linkback = '<a href="'.dol_buildpath('/dolitrace/dolitracecropplan_list.php', 1).'">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');
print '<div class="underbanner clearboth"></div>';
print dol_get_fiche_end();

// Records of the crop plan; the older records with more crop plans are found by their areas
$prefix = $db->prefix();
$sql = "SELECT t.rowid, t.ref, t.kind, t.version, t.date_start, t.status, t.late, g.bbch, g.treated_qty, g.treated_unit";
$sql .= " FROM ".$prefix."dolitrace_treatment as t";
$sql .= " LEFT JOIN ".$prefix."dolitrace_treatment_target as g ON g.fk_treatment = t.rowid AND g.fk_cropplan = ".((int) $object->id);
$sql .= " WHERE t.entity IN (".getEntity('dolitracetreatment').")";
$sql .= " AND (t.fk_cropplan = ".((int) $object->id)." OR g.rowid IS NOT NULL)";
$sql .= " ORDER BY t.date_start DESC, t.rowid DESC";
$records = array();
$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		if (!isset($records[(int) $obj->rowid])) {
			$obj->products = array();
			$records[(int) $obj->rowid] = $obj;
		}
	}
	$db->free($resql);
} else {
	dol_print_error($db);
}
if (count($records)) {
	$sql = "SELECT fk_treatment, product_label, dose, dose_unit_label, adversity_label FROM ".$prefix."dolitrace_treatment_product";
	$sql .= " WHERE fk_treatment IN (".$db->sanitize(implode(',', array_keys($records))).") ORDER BY fk_treatment, rang, rowid";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$records[(int) $obj->fk_treatment]->products[] = $obj;
		}
		$db->free($resql);
	}
}

// New records: only on a crop plan validated or in progress (a draft crop plan has no areas yet)
$canrecord = in_array((int) $object->status, array(1, 2));
$buttons = '';
if ($permissiontoadd) {
	$backtab = dol_buildpath('/dolitrace/dolitracecropplan_treatments.php', 1).'?id='.$object->id;
	$url = dol_buildpath('/dolitrace/dolitracetreatment_card.php', 1).'?action=create&technical_type=1&fk_cropplan='.$object->id.'&backtopageforcancel='.urlencode($backtab);
	$disabled = ($canrecord ? '' : $langs->trans('TreatmentCropPlanNotOpen'));
	$buttons .= dolGetButtonAction($disabled, $langs->trans('TreatmentNewPlantProtection'), 'default', $url.'&kind='.DoliTraceTreatment::KIND_PLANTPROTECTION, '', $canrecord);
	$buttons .= dolGetButtonAction($disabled, $langs->trans('TreatmentNewFertilization'), 'default', $url.'&kind='.DoliTraceTreatment::KIND_FERTILIZATION, '', $canrecord);
	$buttons .= dolGetButtonAction($disabled, $langs->trans('TreatmentImport'), 'default', dol_buildpath('/dolitrace/dolitracetreatment_import.php', 1).'?fk_cropplan='.$object->id, '', $canrecord);
}
print load_fiche_titre($langs->trans('TreatmentRegisterTab'), $buttons, 'fa-clipboard-list');

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Ref').'</td>';
print '<td>'.$langs->trans('TreatmentDateStart').'</td>';
print '<td>'.$langs->trans('TreatmentKind').'</td>';
print '<td>'.$langs->trans('Products').'</td>';
print '<td class="center">'.$langs->trans('TreatmentBbch').'</td>';
print '<td class="right">'.$langs->trans('TreatmentTreatedQty').'</td>';
print '<td class="right">'.$langs->trans('Status').'</td>';
print '</tr>';
$record = new DoliTraceTreatment($db);
foreach ($records as $obj) {
	$record->id = (int) $obj->rowid;
	$record->ref = $obj->ref;
	$record->status = (int) $obj->status;
	$record->kind = (int) $obj->kind;
	print '<tr class="oddeven">';
	print '<td class="nowraponall">'.$record->getNomUrl(1).($obj->late ? ' '.img_warning($langs->trans('TreatmentLate')) : '').'</td>';
	print '<td class="nowraponall">'.dol_print_date($db->jdate($obj->date_start), 'dayhour').'</td>';
	print '<td>'.$langs->trans($obj->kind == DoliTraceTreatment::KIND_FERTILIZATION ? 'TreatmentKindFertilization' : 'TreatmentKindPlantProtection').'</td>';
	print '<td>';
	foreach ($obj->products as $product) {
		print '<div>'.dol_escape_htmltag($product->product_label);
		if ($product->dose > 0) {
			print ' <span class="opacitymedium">'.price($product->dose, 0, $langs, 0, -1, -1).' '.dol_escape_htmltag($product->dose_unit_label).'</span>';
		}
		if ($product->adversity_label) {
			print ' <span class="opacitymedium">- '.dol_escape_htmltag($product->adversity_label).'</span>';
		}
		print '</div>';
	}
	print '</td>';
	print '<td class="center">'.dol_escape_htmltag((string) $obj->bbch).'</td>';
	print '<td class="right nowraponall">'.($obj->treated_qty > 0 ? price($obj->treated_qty, 0, $langs, 0, -1, -1).' '.($obj->treated_unit == 'HA' ? 'ha' : dol_escape_htmltag($obj->treated_unit)) : '').'</td>';
	print '<td class="right">'.$record->getLibStatut(5).'</td>';
	print '</tr>';
}
if (!count($records)) {
	print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
}
print '</table>';
print '</div>';

llxFooter();
$db->close();
