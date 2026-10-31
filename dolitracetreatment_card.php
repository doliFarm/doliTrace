<?php
/* Copyright (C) 2017       Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024-2025  Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2025		Anthony Berton 			<anthony.berton@bb2a.fr>
 * Copyright (C) 2026      SuperAdmin
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
 *    \file       htdocs/dolitrace/dolitracetreatment_card.php
 *    \ingroup    dolitrace
 *    \brief      Page to create/edit/view dolitracetreatment
 */


// General defined Options
//if (! defined('CSRFCHECK_WITH_TOKEN'))     define('CSRFCHECK_WITH_TOKEN', '1');					// Force use of CSRF protection with tokens even for GET
//if (! defined('MAIN_AUTHENTICATION_MODE')) define('MAIN_AUTHENTICATION_MODE', 'aloginmodule');	// Force authentication handler
//if (! defined('MAIN_LANG_DEFAULT'))        define('MAIN_LANG_DEFAULT', 'auto');					// Force LANG (language) to a particular value
//if (! defined('MAIN_SECURITY_FORCECSP'))   define('MAIN_SECURITY_FORCECSP', 'none');				// Disable all Content Security Policies
//if (! defined('NOBROWSERNOTIF'))     		 define('NOBROWSERNOTIF', '1');					// Disable browser notification
//if (! defined('NOIPCHECK'))                define('NOIPCHECK', '1');						// Do not check IP defined into conf $dolibarr_main_restrict_ip
//if (! defined('NOLOGIN'))                  define('NOLOGIN', '1');						// Do not use login - if this page is public (can be called outside logged session). This includes the NOIPCHECK too.
//if (! defined('NOREQUIREAJAX'))            define('NOREQUIREAJAX', '1');       	  		// Do not load ajax.lib.php library
//if (! defined('NOREQUIREDB'))              define('NOREQUIREDB', '1');					// Do not create database handler $db
//if (! defined('NOREQUIREHTML'))            define('NOREQUIREHTML', '1');					// Do not load html.form.class.php
//if (! defined('NOREQUIREMENU'))            define('NOREQUIREMENU', '1');					// Do not load and show top and left menu
//if (! defined('NOREQUIRESOC'))             define('NOREQUIRESOC', '1');					// Do not load object $mysoc
//if (! defined('NOREQUIRETRAN'))            define('NOREQUIRETRAN', '1');					// Do not load object $langs
//if (! defined('NOREQUIREUSER'))            define('NOREQUIREUSER', '1');					// Do not load object $user
//if (! defined('NOSCANGETFORINJECTION'))    define('NOSCANGETFORINJECTION', '1');			// Do not check injection attack on GET parameters
//if (! defined('NOSCANPOSTFORINJECTION'))   define('NOSCANPOSTFORINJECTION', '1');			// Do not check injection attack on POST parameters
//if (! defined('NOSESSION'))                define('NOSESSION', '1');						// On CLI mode, no need to use web sessions
//if (! defined('NOSTYLECHECK'))             define('NOSTYLECHECK', '1');					// Do not check style html tag into posted data
//if (! defined('NOTOKENRENEWAL'))           define('NOTOKENRENEWAL', '1');					// Do not roll the Anti CSRF token (used if MAIN_SECURITY_CSRF_WITH_TOKEN is on)


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
 * @var Societe $mysoc
 */
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
dol_include_once('/dolitrace/class/dolitracetreatment.class.php');
dol_include_once('/dolitrace/lib/dolitrace_treatment.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_farm.lib.php');

// Load translation files required by the page
$langs->loadLangs(array("dolitrace@dolitrace", "dolifarm@dolifarm", "other"));

// Get parameters
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$lineid   = GETPOSTINT('lineid');
//$socid = GETPOSTINT('socid');

$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : getDolDefaultContextPage(__FILE__); // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha');					// if not set, a default page will be used
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');	// if not set, $backtopage will be used
$optioncss = GETPOST('optioncss', 'aZ'); // Option for the css output (always '' except when 'print')
$dol_openinpopup = GETPOST('dol_openinpopup', 'aZ09');

// Initialize a technical objects
$object = new DoliTraceTreatment($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->dolitrace->dir_output.'/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array($object->element.'card', 'globalcard')); // Note that conf->hooks_modules contains array
$soc = null;

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);


$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Initialize array of search criteria
$search_all = trim(GETPOST("search_all", 'alpha'));
$search = array();
foreach ($object->fields as $key => $val) {
	if (GETPOST('search_'.$key, 'alpha')) {
		$search[$key] = GETPOST('search_'.$key, 'alpha');
	}
}

if (empty($action) && empty($id) && empty($ref)) {
	$action = 'view';
}

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be 'include', not 'include_once'.

$permissiontoread = $user->hasRight('dolitrace', 'dolitracetreatment', 'read');
$permissiontoadd = $user->hasRight('dolitrace', 'dolitracetreatment', 'write'); // Used by the include of actions_addupdatedelete.inc.php
$permissiontovalidate = $user->hasRight('dolitrace', 'dolitracetreatment', 'validate');
$permissiontodelete = $user->hasRight('dolitrace', 'dolitracetreatment', 'delete') && isset($object->status) && $object->status == $object::STATUS_DRAFT;
$permissionnote = $permissiontoadd; // Used by the include of actions_setnotes.inc.php
$permissiondellink = $permissiontoadd; // Used by the include of actions_dellink.inc.php
$isdraft = (empty($object->id) || $object->status == $object::STATUS_DRAFT);

$upload_dir = $conf->dolitrace->multidir_output[isset($object->entity) ? $object->entity : 1].'/dolitracetreatment';

// Security check (enable at least one, the most restrictive one)
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
//$isdraft = (isset($object->status) && ($object->status == $object::STATUS_DRAFT) ? 1 : 0);
//restrictedArea($user, $object->module, $object, $object->table_element, $object->element, 'fk_soc', 'rowid', $isdraft);
if (!isModEnabled($object->module)) {
	accessforbidden("Module ".$object->module." not enabled");
}
if (!$permissiontoread) {
	accessforbidden();
}
dolifarmCheckSocAccess($user, $object);
dolifarmPrefillFarm($db, $object, $action);

$error = 0;


/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	$backurlforlist = dol_buildpath('/dolitrace/dolitracetreatment_list.php', 1);

	if (empty($backtopage) || ($cancel && empty($id))) {
		if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
			if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
				$backtopage = $backurlforlist;
			} else {
				$backtopage = dol_buildpath('/dolitrace/dolitracetreatment_card.php', 1).'?id='.((!empty($id) && $id > 0) ? $id : '__ID__');
			}
		}
	}

	$triggermodname = $object->TRIGGER_PREFIX.'_MODIFY'; // Name of trigger action code to execute when we modify record. Used in actions_addupdatedelete.inc.php

	// A validated record of the register is never modified: it is corrected with a new version
	if (!$isdraft && in_array($action, array('edit', 'update', 'confirm_delete', 'confirm_setdraft', 'confirm_reopen', 'confirm_clone', 'confirm_close', 'addtarget', 'addproduct', 'confirm_deletetarget', 'confirm_deleteproduct'))) {
		$action = '';
	}
	if (in_array($action, array('confirm_validate', 'confirm_correct', 'confirm_cancelrecord')) && !$permissiontovalidate) {
		$action = '';
	}

	// Correction: a new draft version of a validated record
	if ($action == 'confirm_correct' && $confirm == 'yes') {
		$newid = $object->createCorrection($user, GETPOST('reason', 'alphanohtml'));
		if ($newid > 0) {
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$newid);
			exit;
		}
		setEventMessages($object->error, $object->errors, 'errors');
		$action = '';
	}

	// Cancellation of a validated record, with its reason
	if ($action == 'confirm_cancelrecord' && $confirm == 'yes') {
		if ($object->cancelRecord($user, GETPOST('reason', 'alphanohtml')) > 0) {
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
			exit;
		}
		setEventMessages($object->error, $object->errors, 'errors');
		$action = '';
	}

	// Areas of the record: the plot and the crop come from the crop plan
	if ($action == 'addtarget' && $permissiontoadd) {
		$target = new DoliTraceTreatmentTarget($db);
		$target->fk_treatment = $object->id;
		$target->rang = count($object->targets) + 1;
		$target->target_type = (int) $object->technical_type;
		$target->treated_qty = (GETPOST('treated_qty', 'alpha') !== '' ? price2num(GETPOST('treated_qty', 'alpha')) : null);
		$target->bbch = GETPOST('bbch', 'alphanohtml');
		if ($target->bbch !== '' && !preg_match('/^[0-9]{1,3}$/', $target->bbch)) {
			$error++;
			setEventMessages($langs->trans('ErrorTreatmentBbch'), null, 'errors');
		}
		if ($target->target_type == DoliTraceTreatmentTarget::TYPE_PLOT) {
			$cropplans = dolitraceTreatmentCropPlans($db, $object);
			$cropplan = isset($cropplans[GETPOSTINT('fk_cropplan')]) ? $cropplans[GETPOSTINT('fk_cropplan')] : null;
			if (!$cropplan) {
				$error++;
				setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('TreatmentCropPlan')), null, 'errors');
			} else {
				$target->fk_cropplan = (int) $cropplan->rowid;
				$target->fk_plot = (int) $cropplan->fk_plot;
				$target->fk_crop = (int) $cropplan->fk_crop;
				$target->crop_label = $langs->transnoentitiesnoconv($cropplan->crop_label);
				$target->crop_eppo = $cropplan->eppo_code;
				$target->location_ref = $cropplan->iacs_parcel ? $cropplan->iacs_parcel : ($cropplan->cadastral_reference ? $cropplan->cadastral_reference : $cropplan->plot_ref);
				$target->zone_zvn = (int) $cropplan->zone_zvn;
				if ($target->treated_qty === null) {
					$target->treated_qty = ($cropplan->size_conducted > 0 ? $cropplan->size_conducted : $cropplan->size_total);
				}
				$target->treated_unit = 'HA';
			}
		} elseif ($target->target_type == DoliTraceTreatmentTarget::TYPE_BUILDING) {
			$buildings = dolitraceTreatmentBuildings($db, $object->fk_soc);
			$building = isset($buildings[GETPOSTINT('fk_building')]) ? $buildings[GETPOSTINT('fk_building')] : null;
			if (!$building) {
				$error++;
				setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('TreatmentBuilding')), null, 'errors');
			} else {
				$target->fk_building = (int) $building->rowid;
				$target->location_ref = $building->ref;
			}
			$target->treated_unit = (GETPOST('treated_unit', 'aZ09') == 'M3' ? 'M3' : 'M2');
		} else {
			$target->seed_batch = GETPOST('seed_batch', 'alphanohtml');
			if ($target->seed_batch === '') {
				$error++;
				setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('TreatmentSeedBatch')), null, 'errors');
			}
			$target->treated_unit = (GETPOST('treated_unit', 'aZ09') == 'T' ? 'T' : 'KG');
		}
		if (!$error && !($target->treated_qty > 0)) {
			$error++;
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('TreatmentTreatedQty')), null, 'errors');
		}
		if (!$error && $target->create($user) < 0) {
			$error++;
			setEventMessages($target->error, $target->errors, 'errors');
		}
		if (!$error && ($object->fetchLines() < 0 || $object->updateAutoQuantities($user) < 0)) {
			$error++;
			setEventMessages($object->error, $object->errors, 'errors');
		}
		if (!$error) {
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
			exit;
		}
		$action = '';
	}

	// Products of the record, with their adversity (plant protection) and their dose
	if ($action == 'addproduct' && $permissiontoadd) {
		$product = new DoliTraceTreatmentProduct($db);
		$product->fk_treatment = $object->id;
		$product->rang = count($object->products) + 1;
		if ($object->kind == $object::KIND_PLANTPROTECTION) {
			$agrodrugs = dolitraceTreatmentAgroDrugs($db);
			$agrodrug = isset($agrodrugs[GETPOSTINT('fk_agrodrug')]) ? $agrodrugs[GETPOSTINT('fk_agrodrug')] : null;
			$adversities = dolitraceTreatmentAdversities($db);
			$adversity = isset($adversities[GETPOSTINT('fk_adversity')]) ? $adversities[GETPOSTINT('fk_adversity')] : null;
			if (!$agrodrug) {
				$error++;
				setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('TreatmentAgroDrug')), null, 'errors');
			} else {
				$product->fk_agrodrug = (int) $agrodrug->rowid;
				$product->fk_product = ($agrodrug->fk_product > 0 ? (int) $agrodrug->fk_product : null);
				$product->product_label = $agrodrug->label;
				$product->reg_number = $agrodrug->reg_number;
			}
			if (!$adversity) {
				$error++;
				setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('TreatmentAdversity')), null, 'errors');
			} else {
				$product->fk_adversity = (int) $adversity->rowid;
				$product->adversity_code = $adversity->code;
				$product->adversity_label = $adversity->label;
			}
		} else {
			$fertilizers = dolitraceTreatmentFertilizers($db);
			$fertilizer = isset($fertilizers[GETPOSTINT('fk_fertilizer')]) ? $fertilizers[GETPOSTINT('fk_fertilizer')] : null;
			if (!$fertilizer) {
				$error++;
				setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('TreatmentFertilizer')), null, 'errors');
			} else {
				$product->fk_fertilizer = (int) $fertilizer->rowid;
				$product->fk_product = ($fertilizer->fk_product > 0 ? (int) $fertilizer->fk_product : null);
				$product->product_label = $fertilizer->label;
				$product->reg_number = $fertilizer->registration_number;
				$product->n_content = $fertilizer->n_content;
				$product->p2o5_content = $fertilizer->p2o5_content;
				$product->k2o_content = $fertilizer->k2o_content;
			}
		}
		$units = dolitraceTreatmentDoseUnits($db, $object->kind, $object->technical_type);
		$unit = isset($units[GETPOSTINT('fk_dose_unit')]) ? $units[GETPOSTINT('fk_dose_unit')] : null;
		$product->dose = price2num(GETPOST('dose', 'alpha'));
		if (!($product->dose > 0)) {
			$error++;
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('TreatmentDose')), null, 'errors');
		}
		if (!$unit) {
			$error++;
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('TreatmentDoseUnit')), null, 'errors');
		} else {
			$product->fk_dose_unit = (int) $unit->rowid;
			$product->dose_unit_label = $unit->label;
			$product->qty_unit = $unit->qty_unit;
		}
		// Total quantity: given by the user, otherwise computed (see DoliTraceTreatment::updateAutoQuantities())
		$product->qty_auto = (GETPOST('qty_total', 'alpha') === '' ? 1 : 0);
		$product->qty_total = ($product->qty_auto ? null : price2num(GETPOST('qty_total', 'alpha')));
		$product->batch = GETPOST('batch', 'alphanohtml');
		$product->stock_status = DoliTraceTreatmentProduct::STOCK_NONE;
		if (!$error && $product->create($user) < 0) {
			$error++;
			setEventMessages($product->error, $product->errors, 'errors');
		}
		if (!$error && ($object->fetchLines() < 0 || $object->updateAutoQuantities($user) < 0)) {
			$error++;
			setEventMessages($object->error, $object->errors, 'errors');
		}
		if (!$error) {
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
			exit;
		}
		$action = '';
	}

	if (in_array($action, array('confirm_deletetarget', 'confirm_deleteproduct')) && $confirm == 'yes' && $permissiontoadd) {
		$row = ($action == 'confirm_deletetarget' ? new DoliTraceTreatmentTarget($db) : new DoliTraceTreatmentProduct($db));
		if ($row->fetch($lineid) > 0 && $row->fk_treatment == $object->id) {
			if ($row->delete($user) < 0 || $object->fetchLines() < 0 || $object->updateAutoQuantities($user) < 0) {
				setEventMessages($row->error ? $row->error : $object->error, $row->error ? $row->errors : $object->errors, 'errors');
			} else {
				header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
				exit;
			}
		}
		$action = '';
	}

	// Actions cancel, add, update, update_extras, confirm_validate, confirm_delete, confirm_deleteline, confirm_clone, confirm_close, confirm_setdraft, confirm_reopen
	include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';

	// Actions when linking object each other
	include DOL_DOCUMENT_ROOT.'/core/actions_dellink.inc.php';

	// Actions when printing a doc from card
	include DOL_DOCUMENT_ROOT.'/core/actions_printing.inc.php';

	// Action to build doc
	include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';

	// Other special actions
	/*
	if ($action == 'set_thirdparty' && $permissiontoadd) {
		$object->setValueFrom('fk_soc', GETPOSTINT('fk_soc'), '', null, 'date', '', $user, $triggermodname);
	}
	if ($action == 'classin' && $permissiontoadd) {
		$object->setProject(GETPOSTINT('projectid'));
	}
	*/

}



/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

$title = $langs->trans("TreatmentRecord").($object->id > 0 ? " - ".$object->ref : '');
if ($action == 'create') {
	$title = $langs->trans("NewTreatmentRecord");
}
$help_url = '';

llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'mod-dolitrace page-card');

// Example : Adding jquery code
// print '<script type="text/javascript">
// jQuery(document).ready(function() {
// 	function init_myfunc()
// 	{
// 		jQuery("#myid").removeAttr(\'disabled\');
// 		jQuery("#myid").attr(\'disabled\',\'disabled\');
// 	}
// 	init_myfunc();
// 	jQuery("#mybutton").click(function() {
// 		init_myfunc();
// 	});
// });
// </script>';

// Part to create
if ($action == 'create') {
	if (empty($permissiontoadd)) {
		accessforbidden('NotEnoughPermissions', 0, 1);
	}

	print load_fiche_titre($title, '', $object->picto);

	print '<form method="POST" action="'.dolBuildUrl($_SERVER["PHP_SELF"]).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';
	}
	if ($dol_openinpopup) {
		print '<input type="hidden" name="dol_openinpopup" value="'.$dol_openinpopup.'">';
	}

	print dol_get_fiche_head(array(), '');


	print '<table class="border centpercent tableforfieldcreate">'."\n";

	// Common attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_add.tpl.php';

	// Other attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_add.tpl.php';

	print '</table>'."\n";

	print dol_get_fiche_end();

	print $form->buttonsSaveCancel("Create");

	print '</form>';

	//dol_set_focus('input[name="ref"]');
}

// Part to edit record
if (($id || $ref) && $action == 'edit') {
	print load_fiche_titre($langs->trans("TreatmentRecord"), '', $object->picto);

	print '<form method="POST" action="'.dolBuildUrl($_SERVER["PHP_SELF"]).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';
	}

	print dol_get_fiche_head();

	print '<table class="border centpercent tableforfieldedit">'."\n";

	// Common attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_edit.tpl.php';

	// Other attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_edit.tpl.php';

	print '</table>';

	print dol_get_fiche_end();

	print $form->buttonsSaveCancel();

	print '</form>';
}

// Part to show record
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
	$formconfirm = '';

	// Confirmation to delete (using preloaded confirm popup)
	if ($object->status == $object::STATUS_DRAFT && ($action == 'delete' || ($conf->use_javascript_ajax && empty($conf->dol_use_jmobile)))) {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('DeleteTreatmentRecord'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 0, 'action-delete');
	}
	// Confirmation to delete an area or a product
	if ($action == 'deletetarget' || $action == 'deleteproduct') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&lineid='.$lineid, $langs->trans('DeleteLine'), $langs->trans('ConfirmDeleteLine'), 'confirm_'.$action, '', 0, 1);
	}

	// Confirmation to validate: the record can no longer be modified
	if ($action == 'validate') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('Validate'), $langs->trans('ConfirmValidateTreatment'), 'confirm_validate', '', 0, 1);
	}

	// Confirmation to correct or to cancel a validated record: the reason is required
	if ($action == 'correct' || $action == 'cancelrecord') {
		$formquestion = array(array('type' => 'text', 'name' => 'reason', 'label' => $langs->trans('TreatmentReason'), 'value' => '', 'morecss' => 'minwidth300'));
		$text = $langs->trans($action == 'correct' ? 'ConfirmCorrectTreatment' : 'ConfirmCancelTreatment', $object->ref);
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans($action == 'correct' ? 'TreatmentCorrect' : 'TreatmentCancel'), $text, 'confirm_'.$action, $formquestion, 0, 1, 250, 600);
	}

	// Confirmation of action xxxx (You can use it for xxx = 'close', xxx = 'reopen', ...)
	// if ($action == 'xxx') {
	// 	$text = $langs->trans('ConfirmActionXxx', $object->ref);
	// 	if (isModEnabled('notification')) {
	// 		require_once DOL_DOCUMENT_ROOT . '/core/class/notify.class.php';
	// 		$notify = new Notify($db);
	// 		$text .= '<br>';
	// 		$text .= $notify->confirmMessage('DOLITRACETREATMENT_CLOSE', $object->socid, $object);
	// 	}

	// 	$formquestion = array();

	// 	$forcecombo=0;
	// 	if ($conf->browser->name == 'ie') $forcecombo = 1;	// There is a bug in IE10 that make combo inside popup crazy
	// 	$formquestion = array(
	// 		// 'text' => $langs->trans("ConfirmClone"),
	// 		// array('type' => 'checkbox', 'name' => 'clone_content', 'label' => $langs->trans("CloneMainAttributes"), 'value' => 1),
	// 		// array('type' => 'checkbox', 'name' => 'update_prices', 'label' => $langs->trans("PuttingPricesUpToDate"), 'value' => 1),
	// 		// array('type' => 'other',    'name' => 'idwarehouse',   'label' => $langs->trans("SelectWarehouseForStockDecrease"), 'value' => $formproduct->selectWarehouses(GETPOST('idwarehouse')?GETPOST('idwarehouse'):'ifone', 'idwarehouse', '', 1, 0, 0, '', 0, $forcecombo))
	// 	);
	// 	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('XXX'), $text, 'confirm_xxx', $formquestion, 0, 1, 220);
	// }

	// Call Hook formConfirm
	$parameters = array('formConfirm' => $formconfirm, 'lineid' => $lineid);
	$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
	if (empty($reshook)) {
		$formconfirm .= $hookmanager->resPrint;
	} elseif ($reshook > 0) {
		$formconfirm = $hookmanager->resPrint;
	}

	// Print form confirm
	print $formconfirm;



	$head = dolitracetreatmentPrepareHead($object);

	print dol_get_fiche_head($head, 'card', $langs->trans("TreatmentRecord"), -1, $object->picto, 0, '', '', 0, '', 1);


	// Object card
	// ------------------------------------------------------------

	$linkback = '<a href="'.dol_buildpath('/dolitrace/dolitracetreatment_list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

	$morehtmlref = '<div class="refidno">';
	/*
		// Ref customer
		$morehtmlref .= $form->editfieldkey("RefCustomer", 'ref_client', $object->ref_client, $object, $usercancreate, 'string', '', 0, 1);
		$morehtmlref .= $form->editfieldval("RefCustomer", 'ref_client', $object->ref_client, $object, $usercancreate, 'string'.(getDolGlobalInt('THIRDPARTY_REF_INPUT_SIZE') ? ':'.getDolGlobalInt('THIRDPARTY_REF_INPUT_SIZE') : ''), '', null, null, '', 1);
		// Thirdparty
		$morehtmlref .= '<br>'.$object->thirdparty->getNomUrl(1, 'customer');
		if (!getDolGlobalInt('MAIN_DISABLE_OTHER_LINK') && $object->thirdparty->id > 0) {
			$morehtmlref .= ' (<a href="'.DOL_URL_ROOT.'/commande/list.php?socid='.$object->thirdparty->id.'&search_societe='.urlencode($object->thirdparty->name).'">'.$langs->trans("OtherOrders").'</a>)';
		}
		// Project
		if (isModEnabled('project')) {
			$langs->load("projects");
			$morehtmlref .= '<br>';
			if ($permissiontoadd) {
				$morehtmlref .= img_picto($langs->trans("Project"), 'project', 'class="pictofixedwidth"');
				if ($action != 'classify') {
					$morehtmlref .= '<a class="editfielda" href="'.dolBuildUrl($_SERVER['PHP_SELF'], ['action' => 'classify', 'id' => $object->id], true).'">'.img_edit($langs->transnoentitiesnoconv('SetProject')).'</a> ';
				}
				$morehtmlref .= $form->form_project($_SERVER['PHP_SELF'].'?id='.$object->id, $object->socid, $object->fk_project, ($action == 'classify' ? 'projectid' : 'none'), 0, 0, 0, 1, '', 'maxwidth300');
			} else {
				if (!empty($object->fk_project)) {
					$proj = new Project($db);
					$proj->fetch($object->fk_project);
					$morehtmlref .= $proj->getNomUrl(1);
					if ($proj->title) {
						$morehtmlref .= '<span class="opacitymedium"> - '.dol_escape_htmltag($proj->title).'</span>';
					}
				}
			}
		}
	*/
	$morehtmlref .= '</div>';


	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">'."\n";

	// Common attributes
	//$keyforbreak='fieldkeytoswitchonsecondcolumn';	// We change column just before this field
	//unset($object->fields['fk_project']);				// Hide field already shown in banner
	//unset($object->fields['fk_soc']);					// Hide field already shown in banner
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_view.tpl.php';

	// Other attributes. Fields from hook formObjectOptions and Extrafields.
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';

	// Integrity of a validated record and its correction
	$correctionid = $object->getCorrectionId();
	$check = ($object->status != $object::STATUS_DRAFT ? $object->checkHash() : -1);
	if ($check >= 0) {
		print '<tr><td>'.$form->textwithpicto($langs->trans('TreatmentIntegrity'), $langs->trans('TreatmentIntegrityHelp')).'</td><td>';
		if ($check > 0) {
			print img_picto('', 'fa-check-circle', 'class="pictofixedwidth" style="color: #28a745"').$langs->trans('TreatmentIntegrityOk');
		} elseif ($check == 0) {
			print img_picto('', 'fa-exclamation-triangle', 'class="pictofixedwidth error"').'<span class="error">'.$langs->trans('TreatmentIntegrityKo').'</span>';
		}
		print '</td></tr>';
	}
	if ($correctionid > 0) {
		$correction = new DoliTraceTreatment($db);
		$correction->fetch($correctionid, null, 1, 1);
		print '<tr><td>'.$langs->trans($object->status == $object::STATUS_REPLACED ? 'TreatmentReplacedBy' : 'TreatmentCorrectionInProgress').'</td><td>'.$correction->getNomUrl(1).' '.$correction->getLibStatut(5).'</td></tr>';
	}

	print '</table>';
	print '</div>';
	print '</div>';

	print '<div class="clearboth"></div>';

	print dol_get_fiche_end();

	/*
	 * Areas and products of the record
	 */

	$caneditrows = ($object->status == $object::STATUS_DRAFT && $permissiontoadd);
	$targettype = (int) $object->technical_type;
	$plantprotection = ($object->kind == $object::KIND_PLANTPROTECTION);

	// Areas: plots with the crop of their crop plan, buildings or seed lots
	print load_fiche_titre($langs->trans('TreatmentAreas'), '', 'fa-map-marked-alt');
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="addtarget">';
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	if ($targettype == DoliTraceTreatmentTarget::TYPE_PLOT) {
		print '<td>'.$langs->trans('TreatmentCropPlan').'</td>';
		print '<td>'.$langs->trans('TreatmentCrop').'</td>';
		print '<td>'.$langs->trans('TreatmentLocationRef').'</td>';
		print '<td class="center">'.$langs->trans('TreatmentZoneZvn').'</td>';
		print '<td class="center">'.$langs->trans('TreatmentBbch').'</td>';
	} elseif ($targettype == DoliTraceTreatmentTarget::TYPE_BUILDING) {
		print '<td colspan="5">'.$langs->trans('TreatmentBuilding').'</td>';
	} else {
		print '<td colspan="5">'.$langs->trans('TreatmentSeedBatch').'</td>';
	}
	print '<td class="right">'.$langs->trans('TreatmentTreatedQty').'</td>';
	print '<td class="width50"></td>';
	print '</tr>';

	$buildings = ($targettype == DoliTraceTreatmentTarget::TYPE_BUILDING ? dolitraceTreatmentBuildings($db, $object->fk_soc) : array());
	foreach ($object->targets as $target) {
		print '<tr class="oddeven">';
		if ($target->target_type == DoliTraceTreatmentTarget::TYPE_PLOT) {
			$url = dol_buildpath('/dolitrace/dolitracecropplan_card.php', 1).'?id='.((int) $target->fk_cropplan);
			print '<td>'.img_picto('', 'fa-clipboard-list', 'class="pictofixedwidth"').'<a href="'.$url.'">'.$langs->trans('TreatmentCropPlan').' '.((int) $target->fk_cropplan).'</a></td>';
			print '<td>'.dol_escape_htmltag($target->crop_label).($target->crop_eppo ? ' <span class="opacitymedium">('.dol_escape_htmltag($target->crop_eppo).')</span>' : '').'</td>';
			print '<td>'.dol_escape_htmltag($target->location_ref).'</td>';
			print '<td class="center">'.yn($target->zone_zvn).'</td>';
			print '<td class="center">'.dol_escape_htmltag($target->bbch).'</td>';
		} elseif ($target->target_type == DoliTraceTreatmentTarget::TYPE_BUILDING) {
			$label = isset($buildings[$target->fk_building]) ? $buildings[$target->fk_building]->label : '';
			print '<td colspan="5">'.img_picto('', 'fa-warehouse', 'class="pictofixedwidth"').dol_escape_htmltag($target->location_ref).($label ? ' - '.dol_escape_htmltag($label) : '').'</td>';
		} else {
			print '<td colspan="5">'.dol_escape_htmltag($target->seed_batch).'</td>';
		}
		print '<td class="right nowraponall">'.price($target->treated_qty, 0, $langs, 0, -1, -1).' '.dol_escape_htmltag($target->treated_unit).'</td>';
		print '<td class="right">';
		if ($caneditrows) {
			print '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=deletetarget&lineid='.$target->id.'&token='.newToken().'">'.img_delete().'</a>';
		}
		print '</td>';
		print '</tr>';
	}
	if (empty($object->targets) && !$caneditrows) {
		print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
	}

	if ($caneditrows) {
		print '<tr class="oddeven nohover">';
		if ($targettype == DoliTraceTreatmentTarget::TYPE_PLOT) {
			$cropplans = dolitraceTreatmentCropPlans($db, $object);
			$options = array();
			foreach ($cropplans as $cropplan) {
				$options[$cropplan->rowid] = $cropplan->plot_ref.' - '.$cropplan->plot_label.' / '.$langs->trans($cropplan->crop_label);
			}
			print '<td colspan="4">';
			if (count($options)) {
				print $form->selectarray('fk_cropplan', $options, GETPOSTINT('fk_cropplan'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth300 maxwidth500', 1);
			} else {
				print '<span class="opacitymedium">'.$langs->trans('TreatmentNoCropPlan', dol_print_date($object->date_start, 'day')).'</span>';
			}
			print '</td>';
			print '<td class="center"><input type="text" name="bbch" class="width50 center" maxlength="3" value="'.dol_escape_htmltag(GETPOST('bbch', 'alphanohtml')).'" placeholder="BBCH"></td>';
			print '<td class="right nowraponall"><input type="text" name="treated_qty" class="width75 right" value="'.dol_escape_htmltag(GETPOST('treated_qty', 'alpha')).'" title="'.dol_escape_htmltag($langs->trans('TreatmentTreatedQtyPlotHelp')).'" placeholder="'.dol_escape_htmltag($langs->trans('TreatmentFromPlot')).'"> HA</td>';
		} else {
			print '<td colspan="5">';
			if ($targettype == DoliTraceTreatmentTarget::TYPE_BUILDING) {
				$options = array();
				foreach ($buildings as $building) {
					$options[$building->rowid] = $building->ref.($building->label ? ' - '.$building->label : '');
				}
				print $form->selectarray('fk_building', $options, GETPOSTINT('fk_building'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth300 maxwidth500', 1);
				$units = array('M2' => 'm²', 'M3' => 'm³');
			} else {
				print '<input type="text" name="seed_batch" class="minwidth200" value="'.dol_escape_htmltag(GETPOST('seed_batch', 'alphanohtml')).'">';
				$units = array('KG' => 'kg', 'T' => 't');
			}
			print '</td>';
			print '<td class="right nowraponall"><input type="text" name="treated_qty" class="width75 right" value="'.dol_escape_htmltag(GETPOST('treated_qty', 'alpha')).'"> ';
			print $form->selectarray('treated_unit', $units, GETPOST('treated_unit', 'aZ09'), 0, 0, 0, '', 0, 0, 0, '', 'width75');
			print '</td>';
		}
		print '<td class="right"><input type="submit" class="button small" value="'.dol_escape_htmltag($langs->trans('Add')).'"></td>';
		print '</tr>';
	}
	print '</table>';
	print '</div>';
	print '</form>';

	// Products: plant protection products with their adversity, or fertilizers
	print '<br>';
	print load_fiche_titre($langs->trans($plantprotection ? 'TreatmentProducts' : 'TreatmentFertilizers'), '', $plantprotection ? 'fa-flask' : 'fa-tint');
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="addproduct">';
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans($plantprotection ? 'TreatmentAgroDrug' : 'TreatmentFertilizer').'</td>';
	print '<td>'.$langs->trans('TreatmentRegNumber').'</td>';
	print '<td>'.($plantprotection ? $langs->trans('TreatmentAdversity') : $langs->trans('TreatmentNPK')).'</td>';
	print '<td class="right">'.$langs->trans('TreatmentDose').'</td>';
	print '<td class="right">'.$langs->trans('TreatmentQtyTotal').'</td>';
	print '<td>'.$langs->trans('Batch').'</td>';
	print '<td class="width50"></td>';
	print '</tr>';

	foreach ($object->products as $product) {
		print '<tr class="oddeven">';
		if ($product->fk_agrodrug > 0) {
			$url = dol_buildpath('/dolifarm/dolifarmagrodrug_card.php', 1).'?id='.((int) $product->fk_agrodrug);
		} else {
			$url = dol_buildpath('/dolifarm/dolifarmfertilizer_card.php', 1).'?id='.((int) $product->fk_fertilizer);
		}
		print '<td><a href="'.$url.'">'.dol_escape_htmltag($product->product_label).'</a></td>';
		print '<td>'.dol_escape_htmltag($product->reg_number).'</td>';
		if ($product->fk_agrodrug > 0) {
			print '<td>'.dol_escape_htmltag($product->adversity_label).' <span class="opacitymedium">('.dol_escape_htmltag($product->adversity_code).')</span></td>';
		} else {
			$npk = array();
			foreach (array('n_content', 'p2o5_content', 'k2o_content') as $key) {
				$npk[] = ($product->$key !== null && $product->$key !== '') ? price($product->$key, 0, $langs, 0, -1, -1) : '-';
			}
			print '<td>'.implode('-', $npk).($product->n_applied > 0 ? ' <span class="opacitymedium">('.$langs->trans('TreatmentNAppliedShort', price($product->n_applied, 0, $langs, 0, -1, 2)).')</span>' : '').'</td>';
		}
		print '<td class="right nowraponall">'.price($product->dose, 0, $langs, 0, -1, -1).' '.dol_escape_htmltag($product->dose_unit_label).'</td>';
		print '<td class="right nowraponall">'.($product->qty_total !== null && $product->qty_total !== '' ? price($product->qty_total, 0, $langs, 0, -1, -1).' '.dol_escape_htmltag($product->qty_unit) : '').'</td>';
		print '<td>'.dol_escape_htmltag($product->batch).'</td>';
		print '<td class="right">';
		if ($caneditrows) {
			print '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=deleteproduct&lineid='.$product->id.'&token='.newToken().'">'.img_delete().'</a>';
		}
		print '</td>';
		print '</tr>';
	}
	if (empty($object->products) && !$caneditrows) {
		print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
	}

	if ($caneditrows) {
		print '<tr class="oddeven nohover">';
		$options = array();
		if ($plantprotection) {
			foreach (dolitraceTreatmentAgroDrugs($db) as $agrodrug) {
				$options[$agrodrug->rowid] = $agrodrug->label.($agrodrug->reg_number ? ' ('.$agrodrug->reg_number.')' : '');
			}
			print '<td colspan="2">'.$form->selectarray('fk_agrodrug', $options, GETPOSTINT('fk_agrodrug'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth200 maxwidth300', 1).'</td>';
			$options = array();
			foreach (dolitraceTreatmentAdversities($db) as $adversity) {
				$options[$adversity->rowid] = $adversity->label.' ('.$adversity->code.')';
			}
			print '<td>'.$form->selectarray('fk_adversity', $options, GETPOSTINT('fk_adversity'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth200 maxwidth300', 1).'</td>';
		} else {
			foreach (dolitraceTreatmentFertilizers($db) as $fertilizer) {
				$options[$fertilizer->rowid] = $fertilizer->label;
			}
			print '<td colspan="3">'.$form->selectarray('fk_fertilizer', $options, GETPOSTINT('fk_fertilizer'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth200 maxwidth300', 1).'</td>';
		}
		$options = array();
		foreach (dolitraceTreatmentDoseUnits($db, $object->kind, $object->technical_type) as $unit) {
			$options[$unit->rowid] = $unit->label;
		}
		print '<td class="right nowraponall"><input type="text" name="dose" class="width50 right" value="'.dol_escape_htmltag(GETPOST('dose', 'alpha')).'"> ';
		print $form->selectarray('fk_dose_unit', $options, GETPOSTINT('fk_dose_unit'), 1, 0, 0, '', 0, 0, 0, '', 'width100').'</td>';
		print '<td class="right"><input type="text" name="qty_total" class="width75 right" value="'.dol_escape_htmltag(GETPOST('qty_total', 'alpha')).'" title="'.dol_escape_htmltag($langs->trans('TreatmentQtyTotalHelp')).'" placeholder="'.dol_escape_htmltag($langs->trans('TreatmentAuto')).'"></td>';
		print '<td><input type="text" name="batch" class="width100" value="'.dol_escape_htmltag(GETPOST('batch', 'alphanohtml')).'"></td>';
		print '<td class="right"><input type="submit" class="button small" value="'.dol_escape_htmltag($langs->trans('Add')).'"></td>';
		print '</tr>';
	}
	print '</table>';
	print '</div>';
	print '</form>';

	// Buttons for actions

	if ($action != 'presend' && $action != 'editline') {
		print '<div class="tabsAction">'."\n";
		$parameters = array();
		$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
		if ($reshook < 0) {
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		}

		if (empty($reshook)) {
			if ($object->status == $object::STATUS_DRAFT) {
				// Modify
				print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit&token='.newToken(), '', $permissiontoadd);

				// Validate
				if (count($object->targets) > 0 && count($object->products) > 0) {
					print dolGetButtonAction('', $langs->trans('Validate'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=validate&token='.newToken(), '', $permissiontovalidate);
				} else {
					print dolGetButtonAction($langs->trans("ErrorTreatmentNeedsAreaAndProduct"), $langs->trans("Validate"), 'default', '#', '', 0);
				}

				// Delete (with preloaded confirm popup)
				$deleteUrl = $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken();
				$buttonId = 'action-delete-no-ajax';
				if ($conf->use_javascript_ajax && empty($conf->dol_use_jmobile)) {	// We can use preloaded confirm if not jmobile
					$deleteUrl = '';
					$buttonId = 'action-delete';
				}
				$params = array();
				print dolGetButtonAction('', $langs->trans("Delete"), 'delete', $deleteUrl, $buttonId, $permissiontodelete, $params);
			}

			if ($object->status == $object::STATUS_VALIDATED) {
				// Correct with a new version, if no correction is already open
				if ($correctionid > 0) {
					print dolGetButtonAction($langs->trans('ErrorTreatmentCorrectionExists'), $langs->trans('TreatmentCorrect'), 'default', '#', '', 0);
				} else {
					print dolGetButtonAction('', $langs->trans('TreatmentCorrect'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=correct&token='.newToken(), '', $permissiontovalidate);
				}
				// Cancel, with the reason
				print dolGetButtonAction('', $langs->trans('TreatmentCancel'), 'danger', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=cancelrecord&token='.newToken(), '', $permissiontovalidate);
			}
		}
		print '</div>'."\n";
	}

	/*
	 * History of the record
	 */
	$history = $object->getHistory();
	if (count($history)) {
		print load_fiche_titre($langs->trans('TreatmentHistory'), '', 'fa-history');
		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans('Date').'</td>';
		print '<td>'.$langs->trans('User').'</td>';
		print '<td class="center">'.$langs->trans('TreatmentVersion').'</td>';
		print '<td>'.$langs->trans('Action').'</td>';
		print '<td>'.$langs->trans('TreatmentReason').'</td>';
		print '<td>'.$langs->trans('TreatmentContentHash').'</td>';
		print '</tr>';
		$users = array();
		foreach ($history as $line) {
			if (!isset($users[$line->fk_user])) {
				$users[$line->fk_user] = new User($db);
				$users[$line->fk_user]->fetch($line->fk_user);
			}
			$changes = json_decode((string) $line->changes, true);
			$text = $langs->trans('TreatmentLog'.$line->action);
			if ($line->action == 'REPLACE' && !empty($changes['replaced_by'])) {
				$text .= ' <a href="'.$_SERVER['PHP_SELF'].'?id='.((int) $changes['replaced_by']).'">'.dol_escape_htmltag($changes['replaced_by_ref']).'</a>';
			}
			if ($line->action == 'CORRECT' && !empty($changes['previous'])) {
				$text .= ' <a href="'.$_SERVER['PHP_SELF'].'?id='.((int) $changes['previous']).'">'.dol_escape_htmltag($changes['previous_ref']).'</a>';
			}
			print '<tr class="oddeven">';
			print '<td class="nowraponall">'.dol_print_date($db->jdate($line->datec), 'dayhour').'</td>';
			print '<td class="tdoverflowmax150">'.($users[$line->fk_user]->id > 0 ? $users[$line->fk_user]->getNomUrl(-1) : '').'</td>';
			print '<td class="center">'.((int) $line->version).'</td>';
			print '<td>'.$text.'</td>';
			print '<td>'.dol_escape_htmltag((string) $line->reason).'</td>';
			print '<td class="tdoverflowmax100" title="'.dol_escape_htmltag((string) $line->content_hash).'"><span class="opacitymedium">'.dol_escape_htmltag(dol_trunc((string) $line->content_hash, 12)).'</span></td>';
			print '</tr>';
		}
		print '</table>';
		print '</div>';
	}


	// Select mail models is same action as presend
	if (GETPOST('modelselected')) {
		$action = 'presend';
	}

	if ($action != 'presend') {
		print '<div class="fichecenter"><div class="fichehalfleft">';
		print '<a name="builddoc"></a>'; // ancre

		$includedocgeneration = 0;

		// Documents
		if ($includedocgeneration) {
			$objref = dol_sanitizeFileName($object->ref);
			$relativepath = $objref.'/'.$objref.'.pdf';
			$filedir = $conf->dolitrace->dir_output.'/'.$object->element.'/'.$objref;
			$urlsource = $_SERVER["PHP_SELF"]."?id=".$object->id;
			$genallowed = $permissiontoread; // If you can read, you can build the PDF to read content
			$delallowed = $permissiontoadd; // If you can create/edit, you can remove a file on card
			print $formfile->showdocuments('dolitrace:DoliTraceTreatment', $object->element.'/'.$objref, $filedir, $urlsource, $genallowed, $delallowed, $object->model_pdf, 1, 0, 0, 28, 0, '', '', '', $langs->defaultlang);
		}

		// Show links to link elements
		$tmparray = $form->showLinkToObjectBlock($object, array(), array('dolitracetreatment'), 1);
		if (is_array($tmparray)) {
			$linktoelem = $tmparray['linktoelem'];
			$htmltoenteralink = $tmparray['htmltoenteralink'];
			print $htmltoenteralink;
			$somethingshown = $form->showLinkedObjectBlock($object, $linktoelem);
		} else {
			// backward compatibility
			$somethingshown = $form->showLinkedObjectBlock($object, $tmparray);
		}

		print '</div><div class="fichehalfright">';

		// BEGIN MODULEBUILDER TAB AGENDA
		$MAXEVENT = 10;

		$morehtmlcenter = dolGetButtonTitle($langs->trans('SeeAll'), '', 'fa fa-bars imgforviewmode', dol_buildpath('/dolitrace/dolitracetreatment_agenda.php', 1).'?id='.$object->id);

		$includeeventlist = 0;

		// List of actions on element
		if ($includeeventlist) {
			include_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
			$formactions = new FormActions($db);
			$somethingshown = $formactions->showactions($object, $object->element.'@'.$object->module, (is_object($object->thirdparty) ? $object->thirdparty->id : 0), 1, '', $MAXEVENT, '', $morehtmlcenter);
		}
		// END MODULEBUILDER TAB AGENDA

		print '</div></div>';
	}

}

// End of page
llxFooter();
$db->close();
