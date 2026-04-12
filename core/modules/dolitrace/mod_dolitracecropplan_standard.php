<?php
/* Copyright (C) 2026       SuperAdmin */

/**
 * \file    htdocs/custom/dolitrace/core/modules/dolitrace/mod_dolitracecropplan_standard.php
 * \ingroup dolitrace
 * \brief   File of class to manage DoliTraceCropplan numbering rules: PP-YY-NNNN
 */
dol_include_once('/dolitrace/core/modules/dolitrace/modules_dolitracecropplan.php');

/**
 * Class to manage the Standard numbering rule for DoliTraceCropplan
 */
class mod_dolitracecropplan_standard extends ModeleNumRefDoliTraceCropplan
{
    public $version = 'dolibarr'; 
    public $prefix = 'PP-'; // Prefisso Fisso
    public $error = '';
    public $nom = 'standard';

    /**
     * Return description of numbering module
     */
    public function info($langs)
    {
        return "Numerazione Standard (PP-YY-NNNN). Esempio: PP-" . date('y') . "-0001";
    }

    /**
     * Return an example of numbering
     */
    public function getExample()
    {
        return $this->prefix . date('y') . "-0001";
    }

    /**
     * Checks if the module can be used
     */
    public function canBeActivated($object)
    {
        return true; // Sempre attivo
    }

    /**
     * Return next free value
     *
     * @param   DoliTraceCropplan   $object     Object we need next value for
     * @return  string                          Next value if OK, '' if KO
     */
    public function getNextValue($object)
    {
        global $db, $conf;

        // 1. COSTRUZIONE PREFISSO: PP + ANNO (2 cifre) + TRATTINO
        // Esempio quest'anno: "PP-26-"
        $current_year_short = date('y'); 
        $search_prefix = $this->prefix . $current_year_short . "-";
        
        // Lunghezza del prefisso (es. PP-26- sono 6 caratteri)
        $prefix_len = strlen($search_prefix);

        // 2. QUERY PER TROVARE IL MASSIMO ATTUALE
        // Cerchiamo solo quelli che iniziano con "PP-26-"
        $sql = "SELECT MAX(ref) as max_ref";
        $sql .= " FROM " . MAIN_DB_PREFIX . "dolitrace_cropplan";
        $sql .= " WHERE ref LIKE '" . $db->escape($search_prefix) . "%'";
        $sql .= " AND entity = " . $conf->entity;

        $resql = $db->query($sql);
        
        $next_num = 1; // Default se non troviamo nulla

        if ($resql) {
            $obj = $db->fetch_object($resql);
            if ($obj && $obj->max_ref) {
                // Abbiamo trovato es. "PP-26-0001"
                // Dobbiamo estrarre la parte numerica dopo il prefisso.
                // Usiamo substr passando la lunghezza del prefisso.
                $current_num_str = substr($obj->max_ref, $prefix_len);
                
                // Convertiamo in intero (es. "0001" -> 1)
                $current_num = (int)$current_num_str;
                
                // Incrementiamo
                $next_num = $current_num + 1;
            }
        } else {
            // Errore SQL
            dol_syslog("mod_dolitracecropplan_standard::getNextValue Error: " . $db->lasterror(), LOG_ERR);
            return ""; 
        }

        // 3. FORMATTAZIONE FINALE
        // %04d significa: numero intero a 4 cifre con zeri iniziali
        $new_ref = $search_prefix . sprintf("%04d", $next_num);

        dol_syslog("mod_dolitracecropplan_standard::getNextValue generated: " . $new_ref, LOG_DEBUG);
        
        return $new_ref;
    }
}