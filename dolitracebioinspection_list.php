<?php
/* Copyright (C) 2026       Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       dolitracebioinspection_list.php
 * \ingroup    dolitrace
 * \brief      List of the inspections of the organic control body, by farm
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
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/dolitrace/class/dolitracebioinspection.class.php');
dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');

$langs->loadLangs(array("dolitrace@dolitrace", "companies", "other"));

if (!isModEnabled('dolitrace') || !$user->hasRight('dolitrace', 'dolitracebioinspection', 'read')) {
	accessforbidden();
}
$socid = GETPOSTINT('socid');
$year = GETPOSTINT('year');

$sql = "SELECT t.rowid, t.ref, t.fk_soc, t.date_inspection, t.fk_soc_body, t.inspection_type, t.outcome, t.report_ref, t.status, t.date_close";
$sql .= " FROM ".$db->prefix()."dolitrace_bioinspection as t";
$sql .= " WHERE t.entity IN (".getEntity('dolitracebioinspection').")";
$sql .= dolifarmSqlSocFilter($user, 't.fk_soc');
if ($socid > 0) {
	$sql .= " AND t.fk_soc = ".((int) $socid);
}
if ($year > 0) {
	$sql .= " AND t.date_inspection BETWEEN '".$db->idate(dol_get_first_day($year, 1))."' AND '".$db->idate(dol_get_last_day($year, 12))."'";
}
$sql .= " ORDER BY t.date_inspection DESC, t.rowid DESC";
$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}

$form = new Form($db);
$title = $langs->trans('BioInspections');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolitrace page-bioinspection-list');

$button = dolGetButtonTitle($langs->trans('NewBioInspection'), '', 'fa fa-plus-circle', dol_buildpath('/dolitrace/dolitracebioinspection_card.php', 1).'?action=create', '', $user->hasRight('dolitrace', 'dolitracebioinspection', 'write'));
print load_fiche_titre($title, $button, 'fa-clipboard-check');
print '<div class="opacitymedium marginbottomonly">'.$langs->trans('BioInspectionHelp').'</div>';

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<div class="divsearchfield">'.$langs->trans('TreatmentFarm').' '.$form->select_company($socid, 'socid', '', 1, 0, 0, array(), 0, 'minwidth200').'</div>';
print '<div class="divsearchfield">'.$langs->trans('Year').' <input type="number" name="year" class="width75" min="1900" max="2999" value="'.($year > 0 ? $year : '').'"></div>';
print '<div class="divsearchfield"><input type="submit" class="button small" value="'.dol_escape_htmltag($langs->trans('Search')).'"></div>';
print '</form><br>';

$object = new DoliTraceBioInspection($db);
$soc = new Societe($db);
print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Ref').'</td><td>'.$langs->trans('TreatmentFarm').'</td><td class="center">'.$langs->trans('BioInspectionDate').'</td>';
print '<td>'.$langs->trans('BioInspectionBody').'</td><td>'.$langs->trans('BioInspectionType').'</td><td>'.$langs->trans('BioInspectionOutcome').'</td>';
print '<td>'.$langs->trans('BioInspectionReport').'</td><td class="right">'.$langs->trans('Status').'</td>';
print '</tr>';
$num = 0;
while ($obj = $db->fetch_object($resql)) {
	$num++;
	$object->id = (int) $obj->rowid;
	$object->ref = $obj->ref;
	$object->status = (int) $obj->status;
	print '<tr class="oddeven">';
	print '<td class="nowraponall">'.$object->getNomUrl(1).'</td>';
	$soc->fetch((int) $obj->fk_soc);
	print '<td class="tdoverflowmax200">'.$soc->getNomUrl(1).'</td>';
	print '<td class="center">'.dol_print_date($db->jdate($obj->date_inspection), 'day').'</td>';
	print '<td class="tdoverflowmax200">';
	if ((int) $obj->fk_soc_body > 0 && $soc->fetch((int) $obj->fk_soc_body) > 0) {
		print $soc->getNomUrl(1);
	}
	print '</td>';
	print '<td>'.($obj->inspection_type ? $langs->trans('BioInspectionType'.$obj->inspection_type) : '').'</td>';
	print '<td>'.($obj->outcome ? $langs->trans('BioInspectionOutcome'.$obj->outcome) : '').'</td>';
	print '<td>'.dol_escape_htmltag((string) $obj->report_ref).'</td>';
	print '<td class="right">'.$object->getLibStatut(5).'</td>';
	print '</tr>';
}
if (!$num) {
	print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
}
print '</table>';
print '</div>';

llxFooter();
$db->close();
