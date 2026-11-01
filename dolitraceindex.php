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
$langs->loadLangs(array("dolitrace@dolitrace"));

$action = GETPOST('action', 'aZ09');

$now = dol_now();
$max = getDolGlobalInt('MAIN_SIZE_SHORTLIST_LIMIT', 5);

// Security check - Protection if external user
$socid = GETPOSTINT('socid');
if (!empty($user->socid) && $user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

// Initialize a technical object to manage hooks. Note that conf->hooks_modules contains array
//$hookmanager->initHooks(array($object->element.'index'));

// Security check (enable the most restrictive one)
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
//if (!isModEnabled('dolitrace')) {
//	accessforbidden('Module not enabled');
//}
//if (! $user->hasRight('dolitrace', 'myobject', 'read')) {
//	accessforbidden();
//}
//restrictedArea($user, 'dolitrace', 0, 'dolitrace_myobject', 'myobject', '', 'rowid');
//if (empty($user->admin)) {
//	accessforbidden('Must be admin');
//}


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


/* BEGIN MODULEBUILDER DRAFT MYOBJECT
// Draft MyObject
if (isModEnabled('dolitrace') && $user->hasRight('dolitrace', 'read')) {
	$langs->load("orders");

	$sql = "SELECT c.rowid, c.ref, c.ref_client, c.total_ht, c.tva as total_tva, c.total_ttc, s.rowid as socid, s.nom as name, s.client, s.canvas";
	$sql.= ", s.code_client";
	$sql.= " FROM ".$db->prefix()."commande as c";
	$sql.= ", ".$db->prefix()."societe as s";
	$sql.= " WHERE c.fk_soc = s.rowid";
	$sql.= " AND c.fk_statut = 0";
	$sql.= " AND c.entity IN (".getEntity('commande').")";
	if ($socid)	$sql.= " AND c.fk_soc = ".((int) $socid);

	$resql = $db->query($sql);
	if ($resql)
	{
		$total = 0;
		$num = $db->num_rows($resql);

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<th colspan="3">'.$langs->trans("DraftMyObjects").($num?'<span class="badge marginleftonlyshort">'.$num.'</span>':'').'</th></tr>';

		$var = true;
		if ($num > 0)
		{
			$i = 0;
			while ($i < $num)
			{

				$obj = $db->fetch_object($resql);
				print '<tr class="oddeven"><td class="nowrap">';

				$myobjectstatic->id=$obj->rowid;
				$myobjectstatic->ref=$obj->ref;
				$myobjectstatic->ref_client=$obj->ref_client;
				$myobjectstatic->total_ht = $obj->total_ht;
				$myobjectstatic->total_tva = $obj->total_tva;
				$myobjectstatic->total_ttc = $obj->total_ttc;

				print $myobjectstatic->getNomUrl(1);
				print '</td>';
				print '<td class="nowrap">';
				print '</td>';
				print '<td class="right" class="nowrap">'.price($obj->total_ttc).'</td></tr>';
				$i++;
				$total += $obj->total_ttc;
			}
			if ($total>0)
			{

				print '<tr class="liste_total"><td>'.$langs->trans("Total").'</td><td colspan="2" class="right">'.price($total)."</td></tr>";
			}
		}
		else
		{

			print '<tr class="oddeven"><td colspan="3" class="opacitymedium">'.$langs->trans("NoOrder").'</td></tr>';
		}
		print "</table><br>";

		$db->free($resql);
	}
	else
	{
		dol_print_error($db);
	}
}
END MODULEBUILDER DRAFT MYOBJECT */




/* BEGIN MODULEBUILDER LASTMODIFIED MYOBJECT
// Last modified myobject
if (isModEnabled('dolitrace') && $user->hasRight('dolitrace', 'read')) {
	$sql = "SELECT s.rowid, s.ref, s.label, s.date_creation, s.tms";
	$sql.= " FROM ".$db->prefix()."dolitrace_myobject as s";
	$sql.= " WHERE s.entity IN (".getEntity($myobjectstatic->element).")";
	//if ($socid)	$sql.= " AND s.rowid = $socid";
	$sql .= " ORDER BY s.tms DESC";
	$sql .= $db->plimit($max, 0);

	$resql = $db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);
		$i = 0;

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<th colspan="2">';
		print $langs->trans("BoxTitleLatestModifiedMyObjects", $max);
		print '</th>';
		print '<th class="right">'.$langs->trans("DateModificationShort").'</th>';
		print '</tr>';
		if ($num)
		{
			while ($i < $num)
			{
				$objp = $db->fetch_object($resql);

				$myobjectstatic->id=$objp->rowid;
				$myobjectstatic->ref=$objp->ref;
				$myobjectstatic->label=$objp->label;
				$myobjectstatic->status = $objp->status;

				print '<tr class="oddeven">';
				print '<td class="nowrap">'.$myobjectstatic->getNomUrl(1).'</td>';
				print '<td class="right nowrap">';
				print "</td>";
				print '<td class="right nowrap">'.dol_print_date($db->jdate($objp->tms), 'day')."</td>";
				print '</tr>';
				$i++;
			}

			$db->free($resql);
		} else {
			print '<tr class="oddeven"><td colspan="3" class="opacitymedium">'.$langs->trans("None").'</td></tr>';
		}
		print "</table><br>";
	}
}
*/




// Inizio della griglia responsive per i tre riquadri principali
print '<div class="div-table-responsive-no-minify">';
print '<div class="grid-container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">';

// --------------------------------------------------------
// RIQUADRO 1: PIANI DI PRODUZIONE
// --------------------------------------------------------
print '<div class="box" style="border: 1px solid #ddd; border-radius: 5px; padding: 15px; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">';
print '<div class="box-header" style="border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin-bottom: 15px;">';
print '<h3 style="margin: 0; color: #222;"><i class="fa fa-calendar-check-o" style="padding-right: 8px; color: #0073aa;"></i>' . $langs->trans("DoliTraceCropplans") . '</h3>';
print '</div>';
print '<div class="box-content">';
print '<p style="color: #666; font-size: 0.9em; margin-bottom: 15px;">' . $langs->trans("Panoramica delle pianificazioni colturali attive.") . '</p>';

// Tabella dimostrativa con dati consolidati
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>' . $langs->trans("Cultura") . '</th><th class="right">' . $langs->trans("Superficie") . '</th><th class="center">' . $langs->trans("Status") . '</th></tr>';

print '<tr class="oddeven"><td>Pomodoro Ciliegino</td><td class="right">1.2 Ha</td><td class="center"><span class="badge badge-status" style="background-color: #4bca52; color: white; padding: 2px 6px; border-radius: 3px;">In Corso</span></td></tr>';
print '<tr class="oddeven"><td>Zucchina Chiara</td><td class="right">0.8 Ha</td><td class="center"><span class="badge badge-status" style="background-color: #4bca52; color: white; padding: 2px 6px; border-radius: 3px;">In Corso</span></td></tr>';
print '<tr class="oddeven"><td>Patata Novella</td><td class="right">2.5 Ha</td><td class="center"><span class="badge badge-status" style="background-color: #f39c12; color: white; padding: 2px 6px; border-radius: 3px;">Pianificato</span></td></tr>';

print '</table>';
print '<div style="margin-top: 15px; text-align: right;"><a class="butAction" href="dolitracecropplan_list.php">' . $langs->trans("DoliTraceCropplans") . '</a></div>';
print '</div>';
print '</div>';


// --------------------------------------------------------
// RIQUADRO 2: RACCOLTA
// --------------------------------------------------------
print '<div class="box" style="border: 1px solid #ddd; border-radius: 5px; padding: 15px; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">';
print '<div class="box-header" style="border-bottom: 2px solid #27ae60; padding-bottom: 10px; margin-bottom: 15px;">';
print '<h3 style="margin: 0; color: #222;"><i class="fa fa-shopping-basket" style="padding-right: 8px; color: #27ae60;"></i>' . $langs->trans("DoliTraceHarvests") . '</h3>';
print '</div>';
print '<div class="box-content">';
print '<p style="color: #666; font-size: 0.9em; margin-bottom: 15px;">' . $langs->trans("Ultimi volumi raccolti e stime correnti.") . '</p>';

// Tabella dimostrativa dei volumi di raccolta
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>' . $langs->trans("Prodotto") . '</th><th class="right">' . $langs->trans("Quantità") . '</th><th class="right">' . $langs->trans("Data") . '</th></tr>';

print '<tr class="oddeven"><td>Fragole</td><td class="right">120 Kg</td><td class="right">Oggi</td></tr>';
print '<tr class="oddeven"><td>Insalata Gentile</td><td class="right">450 Cestini</td><td class="right">Ieri</td></tr>';
print '<tr class="oddeven"><td>Melanzane</td><td class="right">80 Kg</td><td class="right">10/06/2026</td></tr>';

print '</table>';
print '<div style="margin-top: 15px; text-align: right;"><a class="butAction" href="dolitraceharvest_list.php">' . $langs->trans("DoliTraceHarvests") . '</a></div>';
print '</div>';
print '</div>';


// --------------------------------------------------------
// RIQUADRO 3: OPERAZIONI COLTURALI
// --------------------------------------------------------
print '<div class="box" style="border: 1px solid #ddd; border-radius: 5px; padding: 15px; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">';
print '<div class="box-header" style="border-bottom: 2px solid #e67e22; padding-bottom: 10px; margin-bottom: 15px;">';
print '<h3 style="margin: 0; color: #222;"><i class="fa fa-wrench" style="padding-right: 8px; color: #e67e22;"></i>' . $langs->trans("DoliTraceOperations") . '</h3>';
print '</div>';
print '<div class="box-content">';
print '<p style="color: #666; font-size: 0.9em; margin-bottom: 15px;">' . $langs->trans("Attività sul campo, trattamenti e irrigazione.") . '</p>';

// Tabella dimostrativa con attività programmate ed effettuate
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>' . $langs->trans("Attività") . '</th><th>' . $langs->trans("Zona") . '</th><th class="center">' . $langs->trans("Priorità") . '</th></tr>';

print '<tr class="oddeven"><td>Irrigazione Turno A</td><td>Appezzamento Nord</td><td class="center"><span style="color: #e74c3c; font-weight: bold;">Alta</span></td></tr>';
print '<tr class="oddeven"><td>Concimazione Organica</td><td>Serra 2</td><td class="center"><span style="color: #f1c40f; font-weight: bold;">Media</span></td></tr>';
print '<tr class="oddeven"><td>Trattamento Biologico</td><td>Uliveto Scarpata</td><td class="center"><span style="color: #7f8c8d;">Bassa</span></td></tr>';

print '</table>';
print '<div style="margin-top: 15px; text-align: right;"><a class="butAction" href="dolitraceoperation_list.php">' . $langs->trans("DoliTraceOperations") . '</a></div>';
print '</div>';
print '</div>';

print '</div>'; // Fine grid-container




print '</div>';

// End of page
llxFooter();
$db->close();
