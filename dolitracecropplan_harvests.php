<?php
/* Copyright (C) 2026       SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       dolitracecropplan_harvest.php
 * \ingroup    dolitrace
 * \brief      Tab for Harvests linked to a Crop Plan
 */

// --------------------------------------------------------------------
// BOOTSTRAP ROBUSTO
// --------------------------------------------------------------------
$res = 0;
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
if (! $res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (! $res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (! $res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php'; 

// Caricamento Classi del Modulo
dol_include_once('/dolitrace/class/dolitracecropplan.class.php');
dol_include_once('/dolitrace/class/dolitraceharvest.class.php');
dol_include_once('/dolitrace/lib/dolitrace_cropplan.lib.php');

// Langs
$langs->loadLangs(array("dolitrace@dolitrace", "products", "other"));

// Parametri
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

// Oggetti
$object = new DoliTraceCropplan($db);
$child = new DoliTraceHarvest($db);
$product_static = new Product($db);

// Security check
if ($id > 0) {
    $object->fetch($id);
    // $object->fetch_thirdparty(); // Se serve caricare il terzo collegato al piano
}

if (!isModEnabled('dolitrace')) accessforbidden('Module not enabled');

// Permessi specifici per leggere gli Harvest
$permissiontoread = $user->hasRight('dolitrace', 'dolitraceharvest', 'read');
$permissiontowrite = $user->hasRight('dolitrace', 'dolitraceharvest', 'write');

if (!$permissiontoread) accessforbidden();


/*
 * View
 */
$form = new Form($db);
$title = $object->ref . " - " . $langs->trans('Harvests'); // Titolo Tab
$help_url = '';

llxHeader('', $title, $help_url);

if ($id > 0) {
    // 1. HEADER DEL PIANO COLTURALE (Visualizzazione coerente con gli altri tab)
    $head = dolitracecropplanPrepareHead($object);
    print dol_get_fiche_head($head, 'harvest', $langs->trans("DoliTraceCropplan"), -1, $object->picto);

    // Link al padre
    $linkback = '<a href="' . dol_buildpath('/dolitrace/dolitracecropplan_list.php', 1) . '">' . $langs->trans("BackToList") . '</a>';
    
    // Banner Principale
    dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

    print '<div class="fichecenter">';
    print '<div class="underbanner clearboth"></div>';
    print load_fiche_titre($langs->trans("Harvests"), '', 'generic');

    // 2. QUERY PER LISTA HARVEST COLLEGATI
    // Colonne coerenti con lo schema DB fornito: date_harvest, qty, product, batch
    $sql = "SELECT t.rowid, t.ref, t.label, t.date_harvest, t.fk_product_out, t.qty_harvested, t.qty_unit, t.batch_number, t.status";
    $sql .= " FROM " . MAIN_DB_PREFIX . "dolitrace_harvest as t";
    $sql .= " WHERE t.fk_cropplan = " . ((int)$object->id);
    $sql .= " ORDER BY t.date_harvest DESC, t.ref DESC";

    $resql = $db->query($sql);
    if ($resql) {
        $num = $db->num_rows($resql);

        // Pulsante "Nuovo Harvest"
        // Mostra solo se l'utente ha i permessi di scrittura su Harvest
        if ($permissiontowrite) {
            print '<div class="tabsAction">';
            // Passiamo fk_cropplan per precompilare il form di creazione dell'Harvest
            // Passiamo anche fk_product_out se definito nel piano, per velocizzare
            $param_create = '?action=create&fk_cropplan='.$object->id;
            // Se nel cropplan c'è un prodotto output previsto (ipotetico campo), lo passiamo
            // if (!empty($object->fk_product)) $param_create .= '&fk_product_out='.$object->fk_product; 
            
            print '<a class="butAction" href="'.dol_buildpath('/dolitrace/dolitraceharvest_card.php', 1) . $param_create . '">';
            print $langs->trans("AddHarvest");
            print '</a>';
            print '</div>';
        }

        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<td>' . $langs->trans("Ref") . '</td>';
        print '<td>' . $langs->trans("Label") . '</td>';
        print '<td>' . $langs->trans("Date") . '</td>';
        print '<td>' . $langs->trans("Product") . '</td>'; 
        print '<td>' . $langs->trans("Batch") . '</td>';
        print '<td class="right">' . $langs->trans("Qty") . '</td>';
        print '<td class="right">' . $langs->trans("Status") . '</td>';
        print '</tr>';

        if ($num > 0) {
            while ($obj = $db->fetch_object($resql)) {
                $child->id = $obj->rowid;
                $child->ref = $obj->ref;
                $child->label = $obj->label;
                $child->status = $obj->status;

                print '<tr class="oddeven">';
                
                // Ref con link alla card dell'harvest
                print '<td>';
                print $child->getNomUrl(1);
                print '</td>';
                
                // Label
                print '<td>' . dol_escape_htmltag($obj->label) . '</td>';
                
                // Data Raccolto
                print '<td>' . dol_print_date($db->jdate($obj->date_harvest), 'day') . '</td>';
                
                // Prodotto (Link)
                print '<td>';
                if ($obj->fk_product_out > 0) {
                    $product_static->fetch($obj->fk_product_out);
                    print $product_static->getNomUrl(1);
                }
                print '</td>';

                // Lotto (Batch)
                print '<td>' . dol_escape_htmltag($obj->batch_number) . '</td>';

                // Quantità + Unità
                // (Nota: qty_unit potrebbe essere un ID dizionario o stringa, qui assumiamo stringa o label)
                print '<td class="right">';
                print $obj->qty_harvested;
                if ($obj->qty_unit) print ' ' . $obj->qty_unit; 
                print '</td>';

                // Status
                print '<td class="right">' . $child->getLibStatut(5) . '</td>';
                print '</tr>';
            }
        } else {
            print '<tr><td colspan="7" class="opacitymedium">' . $langs->trans("None") . '</td></tr>';
        }
        print '</table>';
        
        $db->free($resql);
    } else {
        dol_print_error($db);
    }

    print '</div>';
    print dol_get_fiche_end();
}

llxFooter();
$db->close();