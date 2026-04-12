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
 * \file       dolitracecropplan_card.php
 * \ingroup    dolitrace
 * \brief      Page to create/edit/view dolitracecropplan
 */


// General defined Options
//if (! defined('CSRFCHECK_WITH_TOKEN'))     define('CSRFCHECK_WITH_TOKEN', '1');                   // Force use of CSRF protection with tokens even for GET
//if (! defined('MAIN_AUTHENTICATION_MODE')) define('MAIN_AUTHENTICATION_MODE', 'aloginmodule');    // Force authentication handler
//if (! defined('MAIN_LANG_DEFAULT'))        define('MAIN_LANG_DEFAULT', 'auto');                   // Force LANG (language) to a particular value
//if (! defined('MAIN_SECURITY_FORCECSP'))   define('MAIN_SECURITY_FORCECSP', 'none');              // Disable all Content Security Policies
//if (! defined('NOBROWSERNOTIF'))           define('NOBROWSERNOTIF', '1');                 // Disable browser notification
//if (! defined('NOIPCHECK'))                define('NOIPCHECK', '1');                      // Do not check IP defined into conf $dolibarr_main_restrict_ip
//if (! defined('NOLOGIN'))                  define('NOLOGIN', '1');                        // Do not use login - if this page is public (can be called outside logged session). This includes the NOIPCHECK too.
//if (! defined('NOREQUIREAJAX'))            define('NOREQUIREAJAX', '1');                  // Do not load ajax.lib.php library
//if (! defined('NOREQUIREDB'))              define('NOREQUIREDB', '1');                    // Do not create database handler $db
//if (! defined('NOREQUIREHTML'))            define('NOREQUIREHTML', '1');                  // Do not load html.form.class.php
//if (! defined('NOREQUIREMENU'))            define('NOREQUIREMENU', '1');                  // Do not load and show top and left menu
//if (! defined('NOREQUIRESOC'))             define('NOREQUIRESOC', '1');                   // Do not load object $mysoc
//if (! defined('NOREQUIRETRAN'))            define('NOREQUIRETRAN', '1');                  // Do not load object $langs
//if (! defined('NOREQUIREUSER'))            define('NOREQUIREUSER', '1');                  // Do not load object $user
//if (! defined('NOSCANGETFORINJECTION'))    define('NOSCANGETFORINJECTION', '1');          // Do not check injection attack on GET parameters
//if (! defined('NOSCANPOSTFORINJECTION'))   define('NOSCANPOSTFORINJECTION', '1');         // Do not check injection attack on POST parameters
//if (! defined('NOSESSION'))                define('NOSESSION', '1');                      // On CLI mode, no need to use web sessions
//if (! defined('NOSTYLECHECK'))             define('NOSTYLECHECK', '1');                   // Do not check style html tag into posted data
//if (! defined('NOTOKENRENEWAL'))           define('NOTOKENRENEWAL', '1');                 // Do not roll the Anti CSRF token (used if MAIN_SECURITY_CSRF_WITH_TOKEN is on)


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
// [MOD] Inclusione necessaria per gestire la visualizzazione del link progetto
include_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';

dol_include_once('/dolitrace/class/dolitracecropplan.class.php');
// [MOD] Inclusione libreria per funzioni helper (getFarmTypeId)
dol_include_once('/dolitrace/lib/dolitrace_cropplan.lib.php');

// Load translation files required by the page
$langs->loadLangs(array("dolitrace@dolitrace", "other", "projects"));

// Get parameters
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$lineid   = GETPOSTINT('lineid');
//$socid = GETPOSTINT('socid');

$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : getDolDefaultContextPage(__FILE__); // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha');                   // if not set, a default page will be used
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha'); // if not set, $backtopage will be used
$optioncss = GETPOST('optioncss', 'aZ'); // Option for the css output (always '' except when 'print')
$dol_openinpopup = GETPOST('dol_openinpopup', 'aZ09');

// Initialize a technical objects
$object = new DoliTraceCropplan($db);
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

// ==============================================================================
// [MOD] CONTEXT PERSISTENCE (Cruciale per il reload dinamico)
// Se la pagina viene ricaricata (dal cambio select Farm), il valore arriva in GET.
// Dobbiamo forzarlo nell'oggetto PRIMA di qualsiasi operazione di visualizzazione.
// ==============================================================================
$user_selected_soc = GETPOSTINT('fk_soc');
if ($user_selected_soc > 0) {
    $object->fk_soc = $user_selected_soc;
}
// ==============================================================================

// There is several ways to check permission.
// Set $enablepermissioncheck to 1 to enable a minimum low level of checks
$enablepermissioncheck = getDolGlobalInt('DOLITRACE_ENABLE_PERMISSION_CHECK');
if ($enablepermissioncheck) {
    $permissiontoread = $user->hasRight('dolitrace', 'dolitracecropplan', 'read');
    $permissiontoadd = $user->hasRight('dolitrace', 'dolitracecropplan', 'write'); // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
    $permissiontodelete = $user->hasRight('dolitrace', 'dolitracecropplan', 'delete') || ($permissiontoadd && isset($object->status) && $object->status == $object::STATUS_DRAFT);
    $permissionnote = $user->hasRight('dolitrace', 'dolitracecropplan', 'write'); // Used by the include of actions_setnotes.inc.php
    $permissiondellink = $user->hasRight('dolitrace', 'dolitracecropplan', 'write'); // Used by the include of actions_dellink.inc.php
} else {
    $permissiontoread = 1;
    $permissiontoadd = 1; // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
    $permissiontodelete = 1;
    $permissionnote = 1;
    $permissiondellink = 1;
}

$upload_dir = $conf->dolitrace->multidir_output[isset($object->entity) ? $object->entity : 1].'/dolitracecropplan';

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
    $backurlforlist = dol_buildpath('/dolitrace/dolitracecropplan_list.php', 1);

    if (empty($backtopage) || ($cancel && empty($id))) {
        if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
            if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
                $backtopage = $backurlforlist;
            } else {
                $backtopage = dol_buildpath('/dolitrace/dolitracecropplan_card.php', 1).'?id='.((!empty($id) && $id > 0) ? $id : '__ID__');
            }
        }
    }

    $triggermodname = $object->TRIGGER_PREFIX.'_MODIFY'; // Name of trigger action code to execute when we modify record. Used in actions_addupdatedelete.inc.php

    // Actions cancel, add, update, update_extras, confirm_validate, confirm_delete, confirm_deleteline, confirm_clone, confirm_close, confirm_setdraft, confirm_reopen
    include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';

    // Actions when linking object each other
    include DOL_DOCUMENT_ROOT.'/core/actions_dellink.inc.php';

    // Actions when printing a doc from card
    include DOL_DOCUMENT_ROOT.'/core/actions_printing.inc.php';

    // Action to move up and down lines of object
    //include DOL_DOCUMENT_ROOT.'/core/actions_lineupdown.inc.php';

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

    // Actions to send emails
    $triggersendname = 'DOLITRACE_MYOBJECT_SENTBYMAIL';
    $autocopy = 'MAIN_MAIL_AUTOCOPY_MYOBJECT_TO';
    $trackid = 'dolitracecropplan'.$object->id;
    include DOL_DOCUMENT_ROOT.'/core/actions_sendmails.inc.php';
}



/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

$title = $langs->trans("DoliTraceCropplan")." - ".$langs->trans('Card');
//$title = $object->ref." - ".$langs->trans('Card');
if ($action == 'create') {
    $title = $langs->trans("NewObject", $langs->transnoentitiesnoconv("DoliTraceCropplan"));
}
$help_url = '';

llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'mod-dolitrace page-card');

// ==============================================================================
// [MOD] PREPARAZIONE FILTRO FARM (SQL Standard per select_company)
// ==============================================================================
dol_include_once('/dolifarm/lib/dolifarm.lib.php');

$farm_type_id = getFarmTypeId($db); 
if ($farm_type_id > 0) {
    // Usiamo SQL standard qui perché select_company non usa il parser ':=:'
    $filter_farm_sql = "status=1 AND fk_typent = " . ((int)$farm_type_id);
} else {
    $filter_farm_sql = "status=1";
}

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

    // -------------------------------------------------------------------------
    // [MOD] 1. FARM: Gestione Manuale con JS nativo
    // -------------------------------------------------------------------------
    print '<tr>';
    print '<td class="titlefield create">' . $langs->trans("Farm") . '</td>';
    print '<td>';
    
    // Stampa select pulita (senza onchange inline che conflitto con Select2)
    print $form->select_company($object->fk_soc, 'fk_soc', $filter_farm_sql, 1, 0, 0);
    
    // SCRIPT JQUERY ROBUSTO PER RELOAD
    // Usa $(document).ready e aggancia l'evento 'change' in modo sicuro anche su Select2
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function() {
            jQuery("#fk_soc").change(function() {
                var newUrl = '<?php echo $_SERVER["PHP_SELF"]; ?>?action=create&fk_soc=' + jQuery(this).val();
                window.location.href = newUrl;
            });
        });
    </script>
    <?php
    print '</td></tr>';
    
    // Rimuoviamo da fields per non duplicare
    unset($object->fields['fk_soc']);

    // -------------------------------------------------------------------------
    // [MOD] 2. PLOT: Configurazione Dinamica prima del Template
    // -------------------------------------------------------------------------
    if ($object->fk_soc > 0) {
        // Sovrascriviamo definizione (=) per evitare errori di sintassi
        $filter_plot_dynamic = "(fk_soc:=:" . ((int)$object->fk_soc) . ") AND (status:=:1)";
        $object->fields['fk_plot']['type'] = "integer:DoliFarmPlot:dolifarm/class/dolifarmplot.class.php:1:" . $filter_plot_dynamic;
    } 

    // -------------------------------------------------------------------------
    // [MOD] 3. PROJECT: Nascondi in creazione
    // -------------------------------------------------------------------------
    unset($object->fields['fk_project']);

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
    print load_fiche_titre($langs->trans("DoliTraceCropplan"), '', $object->picto);

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

    // -------------------------------------------------------------------------
    // [MOD] 1. FARM: Gestione Manuale con JS nativo
    // -------------------------------------------------------------------------
    print '<tr>';
    print '<td class="titlefield">' . $langs->trans("Farm") . '</td>';
    print '<td>';
    
    print $form->select_company($object->fk_soc, 'fk_soc', $filter_farm_sql, 1, 0, 0);
    
    // SCRIPT JQUERY ROBUSTO PER RELOAD (Edit Mode)
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function() {
            jQuery("#fk_soc").change(function() {
                var newUrl = '<?php echo $_SERVER["PHP_SELF"]; ?>?action=edit&id=<?php echo $object->id; ?>&fk_soc=' + jQuery(this).val();
                window.location.href = newUrl;
            });
        });
    </script>
    <?php
    print '</td></tr>';
    unset($object->fields['fk_soc']);

    // -------------------------------------------------------------------------
    // [MOD] 2. PLOT: Configurazione Dinamica
    // -------------------------------------------------------------------------
    if ($object->fk_soc > 0) {
        $filter_plot_dynamic = "(fk_soc:=:" . ((int)$object->fk_soc) . ") AND (status:=:1)";
        $object->fields['fk_plot']['type'] = "integer:DoliFarmPlot:dolifarm/class/dolifarmplot.class.php:1:" . $filter_plot_dynamic;
    } else {
        $object->fields['fk_plot']['type'] = "integer:DoliFarmPlot:dolifarm/class/dolifarmplot.class.php:1:(rowid:=:-1)";
    }

    // -------------------------------------------------------------------------
    // [MOD] 3. PROJECT: Visualizzazione come Link (Sola Lettura)
    // -------------------------------------------------------------------------
    if ($object->fk_project > 0) {
        print '<tr>';
        print '<td class="titlefield">' . $langs->trans("Project") . '</td>';
        print '<td>';
        
        $project_static = new Project($db);
        $res_proj = $project_static->fetch($object->fk_project);
        if ($res_proj > 0) {
            print $project_static->getNomUrl(1);
        } else {
            print $object->fk_project;
        }
        // Input hidden per non perdere il valore al salvataggio
        print '<input type="hidden" name="fk_project" value="'.$object->fk_project.'">';
        
        print '</td></tr>';
    }
    // Rimuoviamo da fields per non duplicare (anche se vuoto)
    unset($object->fields['fk_project']);

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
    $head = dolitracecropplanPrepareHead($object);

    print dol_get_fiche_head($head, 'card', $langs->trans("DoliTraceCropplan"), -1, $object->picto, 0, '', '', 0, '', 1);

    $formconfirm = '';

    // Confirmation to delete (using preloaded confirm popup)
    if ($action == 'delete' || ($conf->use_javascript_ajax && empty($conf->dol_use_jmobile))) {
        $formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('DeleteDoliTraceCropplan'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 0, 'action-delete');
    }
    // Confirmation to delete line
    if ($action == 'deleteline') {
        $formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&lineid='.$lineid, $langs->trans('DeleteLine'), $langs->trans('ConfirmDeleteLine'), 'confirm_deleteline', '', 0, 1);
    }

    // Clone confirmation
    if ($action == 'clone') {
        // Create an array for form
        $formquestion = array();
        $formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ToClone'), $langs->trans('ConfirmCloneAsk', $object->ref), 'confirm_clone', $formquestion, 'yes', 1);
    }

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


    // Object card
    // ------------------------------------------------------------
    $linkback = '<a href="'.dol_buildpath('/dolitrace/dolitracecropplan_list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

    $morehtmlref = '<div class="refidno">';
    $morehtmlref .= '</div>';


    dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);


    print '<div class="fichecenter">';
    print '<div class="fichehalfleft">';
    print '<div class="underbanner clearboth"></div>';
    print '<table class="border centpercent tableforfield">'."\n";

    // Common attributes
    include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_view.tpl.php';

    // Other attributes. Fields from hook formObjectOptions and Extrafields.
    include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';

    print '</table>';
    print '</div>';
    print '<div class="fichehalfright">';
    // Verifichiamo che l'UUID esista (dovrebbe sempre esserci se salvato)
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

        // 4. DISPLAY
        print '<div class="box-flex-container" >';
        
           // Visualizzazione UUID
            print '<div style="margin: 10px 0; font-family:monospace; color:#666;">';
            print '<span class="fa fa-fingerprint"></span> <b>'.$langs->trans("DigitalPassport").':</b><br>';
            print $object->uuid;
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
            print '</div>';

        print '</div>'; // Chiusura box container

    } else {
        print '<div class="opacitymedium" style="text-align:center; padding:20px;">';
        print $langs->trans("SaveToGeneratePassport");
        print '</div>';
    }
    print '</div>';

    print '</div>';

    print '<div class="clearboth"></div>';

    print dol_get_fiche_end();


    /*
     * Lines
     */

    if (!empty($object->table_element_line)) {
        // Show object lines
        $result = $object->getLinesArray();

        print ' <form name="addproduct" id="addproduct" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.(($action != 'editline') ? '' : '#line_'.GETPOSTINT('lineid')).'" method="POST">
        <input type="hidden" name="token" value="' . newToken().'">
        <input type="hidden" name="action" value="' . (($action != 'editline') ? 'addline' : 'updateline').'">
        <input type="hidden" name="mode" value="">
        <input type="hidden" name="page_y" value="">
        <input type="hidden" name="id" value="' . $object->id.'">
        ';

        if (!empty($conf->use_javascript_ajax) && $object->status == 0) {
            include DOL_DOCUMENT_ROOT.'/core/tpl/ajaxrow.tpl.php';
        }

        print '<div class="div-table-responsive-no-min">';
        if (!empty($object->lines) || ($object->status == $object::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines' && $action != 'editline')) {
            print '<table id="tablelines" class="noborder noshadow" width="100%">';
        }

        if (!empty($object->lines)) {
            $object->printObjectLines($action, $mysoc, null, GETPOSTINT('lineid'), 1);
        }

        // Form to add new line
        if ($object->status == 0 && $permissiontoadd && $action != 'selectlines') {
            if ($action != 'editline') {
                // Add products/services form

                $parameters = array();
                $reshook = $hookmanager->executeHooks('formAddObjectLine', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
                if ($reshook < 0) {
                    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
                }
                if (empty($reshook)) {
                    $object->formAddObjectLine(1, $mysoc, $soc);
                }
            }
        }

        if (!empty($object->lines) || ($object->status == $object::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines' && $action != 'editline')) {
            print '</table>';
        }
        print '</div>';

        print "</form>\n";
    }


    // Buttons for actions

    if ($action != 'presend' && $action != 'editline') {
        print '<div class="tabsAction">'."\n";
        // -----------------------------------------------------------------
        // Buttons for Harvests and Operations
        // -----------------------------------------------------------------
        if ($object->status == 1 && $user->rights->dolitrace->dolitracecropplan->write) {
            
            // A. AGGIUNGI OPERAZIONE (Trattamento, Irrigazione, ecc.)
            // Punta alla scheda di creazione operazione, passando l'ID del piano corrente
            print '<a class="butAction" href="' . dol_buildpath('/dolitrace/dolitraceoperation_card.php', 1) . '?action=create&fk_cropplan=' . $object->id . '">';
            // Usa un'icona appropriata (tractor, wrench, o simile)
            print '<span class="fa fa-cogs"></span> ' . $langs->trans("AddOperation");
            print '</a>';

            // B. AGGIUNGI RACCOLTA (Harvest)
            // Punta alla scheda di creazione raccolta. 
            print '<a class="butAction" href="' . dol_buildpath('/dolitrace/dolitraceharvest_card.php', 1) . '?action=create&fk_cropplan=' . $object->id . '">';
            print '<span class="fa fa-leaf"></span> ' . $langs->trans("AddHarvest");
            print '</a>';
        }
        $parameters = array();
        $reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
        if ($reshook < 0) {
            setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
        }

        if (empty($reshook)) {
            // Send
            if (empty($user->socid)) {
                print dolGetButtonAction('', $langs->trans('SendMail'), 'email', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=presend&token='.newToken().'&mode=init#formmailbeforetitle');
            }

            // Back to draft
            if ($object->status == $object::STATUS_VALIDATED) {
                print dolGetButtonAction('', $langs->trans('SetToDraft'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=confirm_setdraft&confirm=yes&token='.newToken(), '', $permissiontoadd);
            }

            // Modify
            print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit&token='.newToken(), '', $permissiontoadd);

            // Validate
            if ($object->status == $object::STATUS_DRAFT) {
                if (empty($object->table_element_line) || (is_array($object->lines) && count($object->lines) > 0)) {
                    print dolGetButtonAction('', $langs->trans('Validate'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_validate&confirm=yes&token='.newToken(), '', $permissiontoadd);
                } else {
                    $langs->load("errors");
                    print dolGetButtonAction($langs->trans("ErrorAddAtLeastOneLineFirst"), $langs->trans("Validate"), 'default', '#', '', 0);
                }
            }

            // Clone
            if ($permissiontoadd) {
                print dolGetButtonAction('', $langs->trans('ToClone'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.(!empty($object->socid) ? '&socid='.$object->socid : '').'&action=clone&token='.newToken(), '', $permissiontoadd);
            }

            // Delete (with preloaded confirm popup)
            $deleteUrl = $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken();
            $buttonId = 'action-delete-no-ajax';
            if ($conf->use_javascript_ajax && empty($conf->dol_use_jmobile)) {  // We can use preloaded confirm if not jmobile
                $deleteUrl = '';
                $buttonId = 'action-delete';
            }
            $params = array();
            print dolGetButtonAction('', $langs->trans("Delete"), 'delete', $deleteUrl, $buttonId, $permissiontodelete, $params);
        }
        print '</div>'."\n";
    }


    // Select mail models is same action as presend
    if (GETPOST('modelselected')) {
        $action = 'presend';
    }

    if ($action != 'presend') {
        print '<div class="fichecenter"><div class="fichehalfleft">';
        print '<a name="builddoc"></a>'; // ancre

        $includedocgeneration = 1;

        // Documents
        if ($includedocgeneration) {
            $objref = dol_sanitizeFileName($object->ref);
            $relativepath = $objref.'/'.$objref.'.pdf';
            $filedir = $conf->dolitrace->dir_output.'/'.$object->element.'/'.$objref;
            $urlsource = $_SERVER["PHP_SELF"]."?id=".$object->id;
            $genallowed = $permissiontoread; // If you can read, you can build the PDF to read content
            $delallowed = $permissiontoadd; // If you can create/edit, you can remove a file on card
            print $formfile->showdocuments('dolitrace:DoliTraceCropplan', $object->element.'/'.$objref, $filedir, $urlsource, $genallowed, $delallowed, $object->model_pdf, 1, 0, 0, 28, 0, '', '', '', $langs->defaultlang);
        }

        // Show links to link elements
        $tmparray = $form->showLinkToObjectBlock($object, array(), array('dolitracecropplan'), 1);
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

        $MAXEVENT = 10;

        $morehtmlcenter = dolGetButtonTitle($langs->trans('SeeAll'), '', 'fa fa-bars imgforviewmode', dol_buildpath('/dolitrace/dolitracecropplan_agenda.php', 1).'?id='.$object->id);

        $includeeventlist = 0;

        // List of actions on element
        if ($includeeventlist) {
            include_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
            $formactions = new FormActions($db);
            $somethingshown = $formactions->showactions($object, $object->element.'@'.$object->module, (is_object($object->thirdparty) ? $object->thirdparty->id : 0), 1, '', $MAXEVENT, '', $morehtmlcenter);
        }

        print '</div></div>';
    }

    //Select mail models is same action as presend
    if (GETPOST('modelselected')) {
        $action = 'presend';
    }

    // Presend form
    $modelmail = 'dolitracecropplan';
    $defaulttopic = 'InformationMessage';
    $diroutput = $conf->dolitrace->dir_output;
    $trackid = 'dolitracecropplan'.$object->id;

    include DOL_DOCUMENT_ROOT.'/core/tpl/card_presend.tpl.php';
}

// End of page
llxFooter();
$db->close();