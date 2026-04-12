<?php
/* Copyright (C) 2017       Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024-2025  Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2026       SuperAdmin
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
 * \file       dolitraceharvest_card.php
 * \ingroup    dolitrace
 * \brief      Page to create/edit/view dolitraceharvest
 */

// Load Dolibarr environment
$res = 0;
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
if (! $res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (! $res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (! $res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (! $res) die("Include of main fails");

include_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

dol_include_once('/dolitrace/class/dolitraceharvest.class.php');
dol_include_once('/dolitrace/class/dolitracecropplan.class.php'); 
dol_include_once('/dolitrace/lib/dolitrace_harvest.lib.php');

// Load translation files required by the page
$langs->loadLangs(array("dolitrace@dolitrace", "other"));

// Get parameters
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$lineid   = GETPOSTINT('lineid');

$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : getDolDefaultContextPage(__FILE__); 
$backtopage = GETPOST('backtopage', 'alpha');                   
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha'); 
$optioncss = GETPOST('optioncss', 'aZ'); 
$dol_openinpopup = GETPOST('dol_openinpopup', 'aZ09');

// Initialize technical objects
$object = new DoliTraceHarvest($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->dolitrace->dir_output.'/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array($object->element.'card', 'globalcard')); 
$soc = null;

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);
$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

if (empty($action) && empty($id) && empty($ref)) {
    $action = 'view';
}

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; 

// ============================================================================
// [CRITICO] LOGICA DI PRE-CARICAMENTO E FORZATURA DATI
// Questa logica deve scattare sia in 'create' (visualizzazione form) 
// sia in 'add' (invio form) per garantire che i dati siano presenti.
// ============================================================================

// 1. Recuperiamo l'ID del Crop Plan (da GET o da POST)
$forced_cropplan_id = GETPOSTINT('fk_cropplan');

// Se stiamo creando una nuova raccolta collegata a un piano
if (($action == 'create' || $action == 'add') && $forced_cropplan_id > 0) {
    
    // Assegniamo subito l'ID al nostro oggetto Raccolta
    $object->fk_cropplan = $forced_cropplan_id;
    
    // Istanziamo il Crop Plan per leggere i dati
    $tmp_cropplan = new DoliTraceCropplan($db);
    $res_cp = $tmp_cropplan->fetch($forced_cropplan_id);
    
    if ($res_cp > 0) {
        // A. Passaporto Digitale (UUID)
        $object->uuid = $tmp_cropplan->uuid;
        
        // B. Mapping Prodotto (Il cuore della tua richiesta)
        // Prendiamo fk_output_product dal Piano e lo mettiamo in fk_product_out della Raccolta
        if (!empty($tmp_cropplan->fk_output_product)) {
            $object->fk_product_out = $tmp_cropplan->fk_output_product;
        } else {
            // Fallback: prova a leggere fk_product se il campo precedente è vuoto
            // Utile se la struttura del DB è cambiata
            $object->fk_product_out = $tmp_cropplan->fk_product;
        }
    }
}
// ============================================================================


$enablepermissioncheck = getDolGlobalInt('DOLITRACE_ENABLE_PERMISSION_CHECK');
if ($enablepermissioncheck) {
    $permissiontoread = $user->hasRight('dolitrace', 'dolitraceharvest', 'read');
    $permissiontoadd = $user->hasRight('dolitrace', 'dolitraceharvest', 'write'); 
    $permissiontodelete = $user->hasRight('dolitrace', 'dolitraceharvest', 'delete') || ($permissiontoadd && isset($object->status) && $object->status == $object::STATUS_DRAFT);
    $permissionnote = $user->hasRight('dolitrace', 'dolitraceharvest', 'write'); 
    $permissiondellink = $user->hasRight('dolitrace', 'dolitraceharvest', 'write'); 
} else {
    $permissiontoread = 1;
    $permissiontoadd = 1; 
    $permissiontodelete = 1;
    $permissionnote = 1;
    $permissiondellink = 1;
}

$upload_dir = $conf->dolitrace->multidir_output[isset($object->entity) ? $object->entity : 1].'/dolitraceharvest';

if (!isModEnabled($object->module)) accessforbidden("Module ".$object->module." not enabled");
if (!$permissiontoread) accessforbidden();

$error = 0;


/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); 
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook)) {
    $backurlforlist = dol_buildpath('/dolitrace/dolitraceharvest_list.php', 1);

    if (empty($backtopage) || ($cancel && empty($id))) {
        if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
            if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
                $backtopage = $backurlforlist;
            } else {
                $backtopage = dol_buildpath('/dolitrace/dolitraceharvest_card.php', 1).'?id='.((!empty($id) && $id > 0) ? $id : '__ID__');
            }
        }
    }

    $triggermodname = $object->TRIGGER_PREFIX.'_MODIFY'; 

    include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';
    include DOL_DOCUMENT_ROOT.'/core/actions_dellink.inc.php';
    include DOL_DOCUMENT_ROOT.'/core/actions_printing.inc.php';
    include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';
    
    // Actions send email
    $triggersendname = 'DOLITRACE_HARVEST_SENTBYMAIL';
    $autocopy = 'MAIN_MAIL_AUTOCOPY_HARVEST_TO';
    $trackid = 'dolitraceharvest'.$object->id;
    include DOL_DOCUMENT_ROOT.'/core/actions_sendmails.inc.php';
}


/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

$title = $langs->trans("DoliTraceHarvest")." - ".$langs->trans('Card');
if ($action == 'create') {
    $title = $langs->trans("NewObject", $langs->transnoentitiesnoconv("DoliTraceHarvest"));
}

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolitrace page-card');

// Part to create
if ($action == 'create') {
    if (empty($permissiontoadd)) {
        accessforbidden('NotEnoughPermissions', 0, 1);
    }

    print load_fiche_titre($title, '', $object->picto);

    print '<form method="POST" action="'.dolBuildUrl($_SERVER["PHP_SELF"]).'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="add">';
    if ($backtopage) print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
    if ($backtopageforcancel) print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';
    if ($dol_openinpopup) print '<input type="hidden" name="dol_openinpopup" value="'.$dol_openinpopup.'">';

    print dol_get_fiche_head(array(), '');

    print '<table class="border centpercent tableforfieldcreate">'."\n";

    // ------------------------------------------------------------------------
    // GESTIONE CAMPI AUTOMATICI / BLOCCATI
    // ------------------------------------------------------------------------
    
    // 1. REF (Automatico - mostriamo Draft)
    print '<tr>';
    print '<td class="titlefield create">' . $langs->trans("Ref") . '</td>';
    print '<td><span class="badge badge-status4 badge-status">' . $langs->trans("Draft") . '</span></td>';
    print '</tr>';
    // Rimuoviamo il campo ref standard per evitare duplicati
    if (isset($object->fields['ref'])) unset($object->fields['ref']); 

    // 2. CROP PLAN (Link sola lettura se presente)
    if ($object->fk_cropplan > 0) {
        $cp_static = new DoliTraceCropplan($db);
        $cp_static->fetch($object->fk_cropplan);
        print '<tr>';
        print '<td class="titlefield create">' . $langs->trans("LinkedCropPlan") . '</td>';
        print '<td>';
        print $cp_static->getNomUrl(1);
        print ' <span class="fa fa-lock" title="'.$langs->trans("LockedBySystem").'" style="color:#999; padding-left:5px;"></span>';
        // HIDDEN: Fondamentale per passare il valore al salvataggio
        print '<input type="hidden" name="fk_cropplan" value="'.$object->fk_cropplan.'">';
        print '</td></tr>';
        
        // Rimuoviamo il campo standard dalla lista, così Dolibarr non lo genera doppio
        unset($object->fields['fk_cropplan']); 
    }

    // 3. PRODUCT OUT (BLOCCATO SE EREDITATO DA CROP PLAN)
    // Verifichiamo se l'oggetto ha il prodotto impostato dalla logica iniziale
    if ($object->fk_cropplan > 0 && $object->fk_product_out > 0) {
        
        $prod_static = new Product($db);
        $prod_static->fetch($object->fk_product_out);
        
        print '<tr>';
        print '<td class="titlefield create">' . $langs->trans("ProductOut") . '</td>';
        print '<td>';
        // Mostriamo il prodotto con icona e label
        print $prod_static->getNomUrl(1) . ' - <strong>' . $prod_static->label . '</strong>';
	
        // HIDDEN: Fondamentale per passare il valore al salvataggio
        print '<input type="hidden" name="fk_product_out" value="'.$object->fk_product_out.'">';
        print '</td></tr>';
        
        // Rimuoviamo il campo standard dalla lista
        unset($object->fields['fk_product_out']);
    }

    // 4. UUID (Ereditato e Nascosto)
    if (!empty($object->uuid)) {
        print '<input type="hidden" name="uuid" value="'.$object->uuid.'">';
        unset($object->fields['uuid']);
    }

    // ------------------------------------------------------------------------
    // GENERAZIONE CAMPI STANDARD RIMANENTI
    // ------------------------------------------------------------------------
    
    include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_add.tpl.php';
    include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_add.tpl.php';

    print '</table>'."\n";

    print dol_get_fiche_end();

    print $form->buttonsSaveCancel("Create");

    print '</form>';
}

// Part to edit record
if (($id || $ref) && $action == 'edit') {
    print load_fiche_titre($langs->trans("DoliTraceHarvest"), '', $object->picto);

    print '<form method="POST" action="'.dolBuildUrl($_SERVER["PHP_SELF"]).'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="update">';
    print '<input type="hidden" name="id" value="'.$object->id.'">';
    if ($backtopage) print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
    if ($backtopageforcancel) print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';

    print dol_get_fiche_head();

    print '<table class="border centpercent tableforfieldedit">'."\n";

    // ------------------------------------------------------------------------
    // GESTIONE EDIT (Lock campi ereditati)
    // ------------------------------------------------------------------------
    
    if (isset($object->fields['ref'])) unset($object->fields['ref']); 

    // CROP PLAN (Lock)
    if ($object->fk_cropplan > 0) {
        $cp_static = new DoliTraceCropplan($db);
        $cp_static->fetch($object->fk_cropplan);
        print '<tr>';
        print '<td class="titlefield">' . $langs->trans("LinkedCropPlan") . '</td>';
        print '<td>';
        print $cp_static->getNomUrl(1);
        print ' <span class="fa fa-lock" style="color:#999;"></span>';
        print '<input type="hidden" name="fk_cropplan" value="'.$object->fk_cropplan.'">';
        print '</td></tr>';
        unset($object->fields['fk_cropplan']); 
    }

    // PRODUCT OUT (Lock se ereditato)
    if ($object->fk_cropplan > 0 && $object->fk_product_out > 0) {
        $prod_static = new Product($db);
        $prod_static->fetch($object->fk_product_out);
        print '<tr>';
        print '<td class="titlefield">' . $langs->trans("ProductOut") . '</td>';
        print '<td>';
        print $prod_static->getNomUrl(1) . ' - ' . $prod_static->label;
        print ' <span class="fa fa-lock" style="color:#999;"></span>';
        print '<input type="hidden" name="fk_product_out" value="'.$object->fk_product_out.'">';
        print '</td></tr>';
        unset($object->fields['fk_product_out']);
    }

    unset($object->fields['uuid']);

    include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_edit.tpl.php';
    include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_edit.tpl.php';

    print '</table>';

    print dol_get_fiche_end();

    print $form->buttonsSaveCancel();

    print '</form>';
}

// Part to show record
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
    $head = dolitraceharvestPrepareHead($object);

    print dol_get_fiche_head($head, 'card', $langs->trans("DoliTraceHarvest"), -1, $object->picto, 0, '', '', 0, '', 1);

    $formconfirm = '';

    // Confirmation to delete
    if ($action == 'delete' || ($conf->use_javascript_ajax && empty($conf->dol_use_jmobile))) {
        $formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('DeleteDoliTraceHarvest'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 0, 'action-delete');
    }
    
    // Confirmation to delete line
    if ($action == 'deleteline') {
        $formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&lineid='.$lineid, $langs->trans('DeleteLine'), $langs->trans('ConfirmDeleteLine'), 'confirm_deleteline', '', 0, 1);
    }

    // Call Hook formConfirm
    $parameters = array('formConfirm' => $formconfirm, 'lineid' => $lineid);
    $reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); 
    if (empty($reshook)) $formconfirm .= $hookmanager->resPrint;
    elseif ($reshook > 0) $formconfirm = $hookmanager->resPrint;

    print $formconfirm;

    // Object card
    $linkback = '<a href="'.dol_buildpath('/dolitrace/dolitraceharvest_list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';
    $morehtmlref = '<div class="refidno"></div>';

    dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

    print '<div class="fichecenter">';
    print '<div class="fichehalfleft">';
    print '<div class="underbanner clearboth"></div>';
    print '<table class="border centpercent tableforfield">'."\n";

    include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_view.tpl.php';
    include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';

    print '</table>';
    print '</div>';
    
    // Box Destra: Dettagli Tecnici (UUID)
    print '<div class="fichehalfright">';
    
    // Visualizzazione UUID
    if (!empty($object->uuid)) {

		// 1. Contenuto del QR
        $qr_content = dol_buildpath('/dolitrace/dolitracecropplan_card.php', 2) . '?id=' . $object->id;
        
        require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';
        // 3. GENERAZIONE DEL CODICE
        $qr_image_data = '';
        if (class_exists('TCPDF2DBarcode')) {
            // Generazione Immagine in Base64
            $barcodeobj = new TCPDF2DBarcode($qr_content, 'QRCODE,H');
            $pngData = $barcodeobj->getBarcodePngData(3, 3, array(0,0,0)); // W=4, H=4
            $qr_image_data = 'data:image/png;base64,' . base64_encode($pngData);
        }

        print '<div class="box-flex-container">';
        print '<div style="margin: 10px 0; font-family:monospace; color:#666;">';
        print '<span class="fa fa-fingerprint"></span> <b>Digital Passport UUID:</b><br>';
        print $object->uuid;
        print '</div></div>';
		// Immagine QR Code
        print '<div style="margin: 10px auto;">';
        if ($qr_image_data) {
            print '<img src="' . $qr_image_data . '" alt="QR Code" style="border: 1px solid #eee; padding: 5px; background: white;">';
        } else {
            // Messaggio di debug utile se fallisce ancora
            print '<div class="error" style="font-size:0.8em; color:red;">';
            print 'Errore: Libreria Barcode non trovata.<br>Percorsi controllati:<br>';
            foreach($tcpdf_paths as $p) print basename(dirname($p)).'/'.basename($p)."<br>";
            print '</div>';
        }

    }

    print '</div>'; // End halfright
    print '</div>'; // End fichecenter

    print '<div class="clearboth"></div>';

    print dol_get_fiche_end();

    /*
     * Buttons for actions
     */
    if ($action != 'presend') {
        print '<div class="tabsAction">'."\n";
        $parameters = array();
        $reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); 
        if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

        if (empty($reshook)) {
            
            // Validate
            if ($object->status == $object::STATUS_DRAFT) {
                if ($permissiontoadd) {
                    print dolGetButtonAction('', $langs->trans('Validate'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_validate&confirm=yes&token='.newToken(), '', $permissiontoadd);
                }
                print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit&token='.newToken(), '', $permissiontoadd);
            }
            
            // Reopen (Set to Draft)
            if ($object->status == $object::STATUS_VALIDATED && $permissiontoadd) {
                 print dolGetButtonAction('', $langs->trans('SetToDraft'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=confirm_setdraft&confirm=yes&token='.newToken(), '', $permissiontoadd);
            }

            // Delete
            $deleteUrl = $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken();
            $buttonId = 'action-delete-no-ajax';
            if ($conf->use_javascript_ajax && empty($conf->dol_use_jmobile)) {
                $deleteUrl = '';
                $buttonId = 'action-delete';
            }
            print dolGetButtonAction('', $langs->trans("Delete"), 'delete', $deleteUrl, $buttonId, $permissiontodelete, array());
        }
        print '</div>'."\n";
    }

    // Documents
    print '<div class="fichecenter"><div class="fichehalfleft">';
    print '<a name="builddoc"></a>'; 
    $objref = dol_sanitizeFileName($object->ref);
    $filedir = $conf->dolitrace->dir_output.'/'.$object->element.'/'.$objref;
    $urlsource = $_SERVER["PHP_SELF"]."?id=".$object->id;
    print $formfile->showdocuments('dolitrace:DoliTraceHarvest', $object->element.'/'.$objref, $filedir, $urlsource, $permissiontoread, $permissiontoadd, $object->model_pdf, 1, 0, 0, 28, 0, '', '', '', $langs->defaultlang);
    print '</div></div>';
}

llxFooter();
$db->close();