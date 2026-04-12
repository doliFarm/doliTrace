<?php
/* Copyright (C) 2026       SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       dolitracecropplan_operations.php
 * \ingroup    dolitrace
 * \brief      Tab for Operations linked to a Crop Plan
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
// Assumiamo che la classe per le operazioni si chiami DoliTraceOperation
dol_include_once('/dolitrace/class/dolitraceoperation.class.php'); 
dol_include_once('/dolitrace/lib/dolitrace_cropplan.lib.php');

// Langs
$langs->loadLangs(array("dolitrace@dolitrace", "products", "other", "dict"));

// Parametri
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

// Oggetti
$object = new DoliTraceCropplan($db);
$child = new DoliTraceOperation($db); // Oggetto Operazione
$product_static = new Product($db);

// Security check
if ($id > 0) {
    $object->fetch($id);
}

if (!isModEnabled('dolitrace')) accessforbidden('Module not enabled');

// Permessi specifici per leggere le Operazioni
// Nota: Verifica che 'dolitraceoperation' sia il nome corretto del diritto nel descrittore modulo
$permissiontoread = $user->hasRight('dolitrace', 'dolitraceoperation', 'read');
$permissiontowrite = $user->hasRight('dolitrace', 'dolitraceoperation', 'write');

if (!$permissiontoread) accessforbidden();


/*
 * View
 */
$form = new Form($db);
$title = $object->ref . " - " . $langs->trans('Operations');
$help_url = '';

llxHeader('', $title, $help_url);

if ($id > 0) {
    // 1. HEADER DEL PIANO COLTURALE
    // Assicurati di aver aggiunto il tab 'operations' nella lib (vedi istruzioni sotto)
    $head = dolitracecropplanPrepareHead($object);
    
    // Il secondo parametro 'operations' deve corrispondere alla chiave nell'array $head della lib
    print dol_get_fiche_head($head, 'operations', $langs->trans("DoliTraceCropplan"), -1, $object->picto);

    // Link al padre
    $linkback = '<a href="' . dol_buildpath('/dolitrace/dolitracecropplan_list.php', 1) . '">' . $langs->trans("BackToList") . '</a>';
    
    // Banner Principale
    dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

    print '<div class="fichecenter">';
    print '<div class="underbanner clearboth"></div>';
    print load_fiche_titre($langs->trans("Operations"), '', 'generic');
    
    // 2. QUERY PER LISTA OPERAZIONI COLLEGATE
    // Colonne basate sullo schema: date_op, type, label, product input, qty
    $sql = "SELECT t.rowid, t.ref, t.label, t.date_op, t.fk_operation_type, t.fk_product, t.qty_used, t.status";
    $sql .= " FROM " . MAIN_DB_PREFIX . "dolitrace_operation as t";
    $sql .= " WHERE t.fk_cropplan = " . ((int)$object->id);
    $sql .= " ORDER BY t.date_op DESC, t.ref DESC";

    $resql = $db->query($sql);
    if ($resql) {
        $num = $db->num_rows($resql);

        // Pulsante "Nuova Operazione"
        if ($permissiontowrite) {
            print '<div class="tabsAction">';
            // Passiamo fk_cropplan per precompilare il form di creazione
            print '<a class="butAction" href="'.dol_buildpath('/dolitrace/dolitraceoperation_card.php', 1).'?action=create&fk_cropplan='.$object->id.'">';
            print $langs->trans("AddOperation");
            print '</a>';
            print '</div>';
        }

        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<td>' . $langs->trans("Ref") . '</td>';
        print '<td>' . $langs->trans("Date") . '</td>';
        print '<td>' . $langs->trans("Type") . '</td>';
        print '<td>' . $langs->trans("Label") . '</td>';
        print '<td>' . $langs->trans("ProductInput") . '</td>'; // Prodotto usato (Input)
        print '<td class="right">' . $langs->trans("QtyUsed") . '</td>';
        print '<td class="right">' . $langs->trans("Status") . '</td>';
        print '</tr>';

        if ($num > 0) {
            while ($obj = $db->fetch_object($resql)) {
                $child->id = $obj->rowid;
                $child->ref = $obj->ref;
                $child->label = $obj->label;
                $child->status = $obj->status;

                print '<tr class="oddeven">';
                
                // Ref con link alla card dell'operazione
                print '<td>';
                print $child->getNomUrl(1);
                print '</td>';
                
                // Data Operazione
                print '<td>' . dol_print_date($db->jdate($obj->date_op), 'dayhour') . '</td>';
                
                // Tipo Operazione (Traduzione del codice o Label Dizionario)
                // Se fk_operation_type è un codice, proviamo a tradurlo
                print '<td>';
                // Qui assumo che sia una stringa/codice. Se fosse un ID integer collegato a c_typent, servirebbe una fetch.
                // Usiamo il trans() per tentare la traduzione del codice.
                print $langs->trans($obj->fk_operation_type); 
                print '</td>';

                // Label Descrittiva
                print '<td>' . dol_escape_htmltag($obj->label) . '</td>';

                // Prodotto Input (Link)
                print '<td>';
                if ($obj->fk_product > 0) {
                    $product_static->fetch($obj->fk_product);
                    print $product_static->getNomUrl(1);
                }
                print '</td>';

                // Quantità Usata
                print '<td class="right">';
                if ($obj->qty_used > 0) {
                    print $obj->qty_used;
                    // Se avessimo l'unità di misura nel DB, la stamperemmo qui. 
                    // Dato che nello schema non c'è una colonna qty_unit esplicita per l'input (c'è water_vol_ha),
                    // stampiamo solo il numero o recuperiamo l'unità dal prodotto fetchato sopra.
                    if ($obj->fk_product > 0 && $product_static->id > 0) {
                         print ' ' . measuringUnitString(0, "weight", $product_static->weight_units); // Esempio se peso
                    }
                }
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