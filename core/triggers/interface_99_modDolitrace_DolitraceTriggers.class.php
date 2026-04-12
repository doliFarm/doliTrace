<?php
/* Copyright (C) 2023		Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) ---Replace with your own copyright and developer email---
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/triggers/interface_99_modMyModule_MyModuleTriggers.class.php
 * \ingroup mymodule
 * \brief   Example of trigger file.
 *
 * You can create other triggered files by copying this one.
 * - File name should be either:
 *      - interface_99_modMyModule_MyTrigger.class.php
 *      - interface_99_all_MyTrigger.class.php
 * - The file must stay in core/triggers
 * - The class name must be InterfaceMyTrigger
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
dol_include_once('/dolitrace/lib/dolitrace.lib.php');
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/productbatch.class.php';


/**
 *  Class of triggers for MyModule module
 */
class InterfaceDolitraceTriggers extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->family = "dolitrace";
		$this->description = "Triggers for DoliTrace: CropPlan to Batch Automation";
		$this->version = self::VERSIONS['dev'];
		$this->picto = 'dolitrace@dolitrace';
	}

	/**
	 * Function called when a Dolibarr business event is done.
	 * All functions "runTrigger" are triggered if the file is inside the directory core/triggers
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		Return integer <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{ 
		if (!isModEnabled('dolitrace')) {
			return 0; // If module is not enabled, we do nothing
		}

		// Put here code you want to execute when a Dolibarr business events occurs.
		// Data and type of action are stored into $object and $action

		// You can isolate code for each action in a separate method: this method should be named like the trigger in camelCase.
		// For example : COMPANY_CREATE => public function companyCreate($action, $object, User $user, Translate $langs, Conf $conf)
		$methodName = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($action)))));
		$callback = array($this, $methodName);
		if (is_callable($callback)) {
			dol_syslog(
				"Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id
			);

			return call_user_func($callback, $action, $object, $user, $langs, $conf);
		}
		// Or you can execute some code here
		switch ($action) {  // @phan-suppress-current-line PhanNoopSwitchCases
			case 'DOLITRACE_CROPPLAN_VALIDATE': 
				 {
                    dol_syslog("--- DOLITRACE TRIGGER: Avvio Validazione Piano ID: " . $object->id);
                   
					/*
					* CREAZIONE PRODOTTO ASSOCIATO AL CROPPLAN	
					*/                   
					// 1. Recupero dati Coltura
                    $sql = "SELECT rowid, fk_default_product, ref, label FROM " . MAIN_DB_PREFIX . "dolifarm_crops WHERE rowid = " . ((int)$object->fk_crop);
                    $resql = $this->db->query($sql);
                    
                    if ($resql && $this->db->num_rows($resql)) {
                        $obj_crop = $this->db->fetch_object($resql);
                        $product_id = (int)$obj_crop->fk_default_product;
                        
                        // A. CREAZIONE PRODOTTO (Se manca)
                        if (empty($product_id) || $product_id <= 0) {
                            $prod_ref = 'PROD-' . $obj_crop->ref;
                            
                            $checkProd = new Product($this->db);
                            if ($checkProd->fetch(0, $prod_ref) > 0) {
                                $product_id = $checkProd->id;
                            } else {
                                $newProd = new Product($this->db);
                                $newProd->ref = $prod_ref; 
                                $newProd->label = $obj_crop->label;
                                $newProd->type = Product::TYPE_PRODUCT; 
                                $newProd->status = 1; 
                                $newProd->tobuy = 0; 
                                $newProd->tosell = 1; 
                                $newProd->status_batch = 1; // Gestione Lotti Attiva
                                
                                if ($newProd->create($user) > 0) {
                                    $product_id = $newProd->id;
                                    $this->db->query("UPDATE ".MAIN_DB_PREFIX."dolifarm_crops SET fk_default_product = ".((int)$product_id)." WHERE rowid = ".((int)$obj_crop->rowid));
                                } else {
                                    $this->error = "Errore creazione Prodotto: " . $newProd->error;
                                    return -1; // Blocca validazione
                                }
                            }
                        }

                        // B. GESTIONE LOTTO
                        if ($product_id > 0) {
                            $year_short = date('y');
                            $crop_ref_clean = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($obj_crop->ref)), 0, 4); 
                            $plan_ref_clean = preg_replace('/[^A-Z0-9]/', '', strtoupper($object->ref));
                            
                            $batch_code = sprintf("%s-%s-%s", $year_short, $crop_ref_clean, $plan_ref_clean);
                            $eatby_date = (dol_now() + (86400 * 180));

                            // 1. TROVARE UN MAGAZZINO ATTIVO
                            // Cerchiamo 'W_PROD' (creato dall'installazione) oppure il primo disponibile
                            $warehouse_id = 0;
                            
                            // Prima cerchiamo il W_PROD specifico
                            $sql_w = "SELECT rowid FROM ".MAIN_DB_PREFIX."entrepot WHERE ref = 'W_PROD' AND entity = ".$conf->entity;
                            $res_w = $this->db->query($sql_w);
                            if ($obj_w = $this->db->fetch_object($res_w)) {
                                $warehouse_id = $obj_w->rowid;
                            } else {
                                // Fallback: Primo magazzino attivo qualunque
                                $sql_w_any = "SELECT rowid FROM ".MAIN_DB_PREFIX."entrepot WHERE entity = ".$conf->entity." AND statut = 1 LIMIT 1";
                                $res_w_any = $this->db->query($sql_w_any);
                                if ($obj_w_any = $this->db->fetch_object($res_w_any)) {
                                    $warehouse_id = $obj_w_any->rowid;
                                }
                            }

                            // 2. CHECK BLOCCANTE ("Il Pianto")
                            if ($warehouse_id == 0) {
                                $this->error = "ERRORE CRITICO: Nessun Magazzino attivo trovato! Impossibile generare il lotto di produzione. Crea almeno un magazzino.";
                                return -1; // <--- Blocca la validazione e mostra errore rosso all'utente
                            }

                            // 3. RECUPERO/CREAZIONE LINK STOCK (Necessario per Dolibarr)
                            $fk_product_stock = 0;
                            $sql_ps = "SELECT rowid FROM ".MAIN_DB_PREFIX."product_stock WHERE fk_product = ".((int)$product_id)." AND fk_entrepot = ".((int)$warehouse_id);
                            $res_ps = $this->db->query($sql_ps);
                            
                            if ($obj_ps = $this->db->fetch_object($res_ps)) {
                                $fk_product_stock = $obj_ps->rowid;
                            } else {
                                // Creiamo l'associazione stock a 0
                                $sql_ins = "INSERT INTO ".MAIN_DB_PREFIX."product_stock (fk_product, fk_entrepot, reel, import_key) VALUES (".((int)$product_id).", ".((int)$warehouse_id).", 0, 'DOLITRACE_AUTO')";
                                if ($this->db->query($sql_ins)) {
                                    $fk_product_stock = $this->db->last_insert_id(MAIN_DB_PREFIX."product_stock");
                                }
                            }

                            // 4. CREAZIONE LOTTO
                            if ($fk_product_stock > 0) {
                                // Check esistenza
                                $sql_check_b = "SELECT rowid FROM ".MAIN_DB_PREFIX."product_batch WHERE batch = '".$this->db->escape($batch_code)."' AND fk_product_stock = ".((int)$fk_product_stock);
                                if ($this->db->num_rows($this->db->query($sql_check_b)) == 0) {
                                    $batch = new Productbatch($this->db);
                                    $batch->batch = $batch_code;
                                    $batch->fk_product_stock = $fk_product_stock;
                                    $batch->eatby = $eatby_date; 
                                    $batch->sellby = $eatby_date; 
                                    $batch->datec = dol_now();
                                    $batch->qty = 0; 
                                    
                                    if ($batch->create($user) > 0) {
                                        setEventMessages("Lotto generato con successo: " . $batch_code, [], 'mesgs');
                                    } else {
                                        $this->error = "Errore creazione Lotto: " . $batch->error;
                                        return -1;
                                    }
                                } 

                                // 5. AGGIORNAMENTO CROPPLAN
                                $sql_up = "UPDATE ".MAIN_DB_PREFIX."dolitrace_cropplan SET fk_output_product=".((int)$product_id).", batch_output='".$this->db->escape($batch_code)."' WHERE rowid=".((int)$object->id);
                                $this->db->query($sql_up);								
                            }
                        }
                    }
					/*
					* CREAZIONE PROGETTO ASSOCIATO AL CROPPLAN	
					*/
					if (!isModEnabled('projet')) {
						dol_syslog("DolitraceTrigger Warning: Project module not enabled. Skipping project creation.", LOG_WARNING);
						return 0;
            		}
					// Se ha già un progetto collegato, non ne creiamo un altro
					if (!empty($object->fk_project) && $object->fk_project > 0) {
						dol_syslog("DolitraceTrigger Info: Crop Plan already linked to project ID " . $object->fk_project, LOG_INFO);
						return 0;
					}
					// 2. Preparazione Nuovo Progetto
					dol_include_once('/projet/class/project.class.php');
					$newproject = new Project($this->db);

					// Impostiamo i dati del progetto basandoci sul Crop Plan
					$newproject->title = $object->label; // O usa $object->ref se preferisci
					$newproject->socid = $object->fk_soc; // Associa al Terzo (Azienda Agricola)
					$newproject->description = $langs->trans("AutoGeneratedFromCropPlan") . ': ' . $object->ref;
					$newproject->public = 1; // Visibile a tutti coloro che hanno permessi sui progetti
					$newproject->status = 1; // Progetto Validato (Aperto)
					
					// Date (se presenti nel Crop Plan)
					if (!empty($object->date_start)) $newproject->date_start = $object->date_start;
					if (!empty($object->date_end))   $newproject->date_end   = $object->date_end;
					// Creazione di Ref
					$default_model = empty($conf->global->PROJECT_ADDON) ? '' : $conf->global->PROJECT_ADDON;
					
					if (!empty($default_model)) {
						// Carichiamo il file del modello di numerazione (es. mod_project_simple.php)
						$file = DOL_DOCUMENT_ROOT . "/core/modules/project/" . $default_model . ".php";
						if (file_exists($file)) {
							require_once $file;
							$classname = $default_model;
							$objModel = new $classname();
							
							// Otteniamo il prossimo numero
							$next_ref = $objModel->getNextValue($user->entity, $newproject);
							
							if ($next_ref) {
								$newproject->ref = $next_ref;
							}
						}
					}
					// Se non siamo riusciti a generare Ref ne assignamo uno provvisorio
					if (empty($newproject->ref)) {
						dol_syslog("DolitraceTrigger Warning: No numbering model found for Project. Using PROV ref.", LOG_WARNING);
						$newproject->ref = '(PROV-CROP-'.$object->id.')';
					}
					// ----------------------------------------------------------------
					// 3. Creazione Progetto
					// Il metodo create genererà automaticamente il REF se il modulo numerazione progetti è configurato
					$result = $newproject->create($user);

					if ($result > 0) {
						dol_syslog("DolitraceTrigger: Project created successfully with ID " . $newproject->id, LOG_INFO);

						// 4. Aggiornamento Crop Plan (Collegamento Inverso)
						// Usiamo SQL diretto per evitare loop di trigger o problemi di permessi sull'update standard
						$sql = "UPDATE ".MAIN_DB_PREFIX."dolitrace_cropplan";
						$sql .= " SET fk_project = " . ((int)$newproject->id);
						$sql .= " WHERE rowid = " . ((int)$object->id);
						
						$res_upd = $this->db->query($sql);
						if (!$res_upd) {
							dol_syslog("DolitraceTrigger Error: Failed to link project to crop plan. " . $this->db->lasterror(), LOG_ERR);
						} else {
							// Aggiorniamo l'oggetto in memoria nel caso serva dopo
							$object->fk_project = $newproject->id;
						}

					} else {
						$this->error = "Error creating project: " . $newproject->error;
						dol_syslog("DolitraceTrigger Error: " . $this->error, LOG_ERR);
						// Ritorniamo -1 per bloccare la validazione e mostrare l'errore all'utente?
						// Meglio di no, lasciamo validare il piano ma logghiamo l'errore del progetto.
						// return -1; 
					}
                }
				break;
			case 'DOLITRACE_HARVEST_VALIDATE': 
				{
					dol_syslog("DolitraceTrigger: Detected HARVEST creation. processing stock...", LOG_INFO);

					// 1. Dati dalla Raccolta
					$product_id = isset($object->fk_product_out) ? $object->fk_product_out : 0;
					$quantity = isset($object->qty_harvested) ? $object->qty_harvested : 0;
					$batch_number = isset($object->batch_number) ? $object->batch_number : '';
					$date_mouv = !empty($object->date_harvest) ? $object->date_harvest : dol_now();

					if (empty($product_id) || empty($quantity)) return 0;

					// 2. RECUPERO MAGAZZINO DI DEFAULT
					// Strategia:
					// a. Cerca ID nella configurazione globale (impostato dall'init del modulo)
					// b. Fallback: Cerca un magazzino con ref 'DOLITRACE'
					
					$warehouse_id = getDolGlobalInt('DOLITRACE_DEFAULT_WAREHOUSE');

					if (empty($warehouse_id)) {
						// Tentativo di fallback: Cerchiamo un magazzino chiamato 'DOLITRACE'
						$sql_wh = "SELECT rowid FROM " . $this->db->prefix . "entrepot WHERE ref = 'DOLITRACE' LIMIT 1";
						$res_wh = $this->db->query($sql_wh);
						if ($res_wh && $this->db->num_rows($res_wh) > 0) {
							$obj_wh = $this->db->fetch_object($res_wh);
							$warehouse_id = $obj_wh->rowid;
						}
					}

					// Se ancora non abbiamo un magazzino, ci fermiamo (loggando l'errore)
					if (empty($warehouse_id)) {
						dol_syslog("DolitraceTrigger Error: Default warehouse not found (Constant DOLITRACE_DEFAULT_WAREHOUSE is missing and no warehouse with ref 'DOLITRACE' exists).", LOG_ERR);
						// Non blocchiamo la creazione della raccolta, ma avvisiamo nei log
						return 0; 
					}

					// 3. Esecuzione Movimento Stock
					if (isModEnabled('stock')) {
						dol_include_once('/product/stock/class/mouvementstock.class.php');
						
						$mouv = new MouvementStock($this->db);
						$label = $langs->trans("Harvest") . " " . $object->ref;

						$this->db->begin();

						// _create(user, product_id, warehouse_id, qty, type, price, label, date, dlc, dluo, batch, origin_type, origin_id)
						$result = $mouv->_create(
							$user, 
							$product_id, 
							$warehouse_id, 
							$quantity, 
							1, // Input (Carico)
							0, 
							$label, 
							$date_mouv, 
							'', '', 
							$batch_number, 
							$object->element, 
							$object->id
						);

						if ($result > 0) {
							$this->db->commit();
							dol_syslog("DolitraceTrigger: Stock moved to Warehouse ID $warehouse_id. Batch: $batch_number", LOG_INFO);
						} else {
							$this->db->rollback();
							dol_syslog("DolitraceTrigger Stock Error: " . $mouv->error, LOG_ERR);
							// return -1; // Decommenta se vuoi bloccare tutto in caso di errore stock
						}
					}
				}
			break;
			// Users
			//case 'USER_CREATE':
			//case 'USER_MODIFY':
			//case 'USER_NEW_PASSWORD':
			//case 'USER_ENABLEDISABLE':
			//case 'USER_DELETE':

			// Actions
			//case 'ACTION_MODIFY':
			//case 'ACTION_CREATE':
			//case 'ACTION_DELETE':

			// Groups
			//case 'USERGROUP_CREATE':
			//case 'USERGROUP_MODIFY':
			//case 'USERGROUP_DELETE':

			// Companies
			//case 'COMPANY_CREATE':
			//case 'COMPANY_MODIFY':
			//case 'COMPANY_DELETE':

			// Contacts
			//case 'CONTACT_CREATE':
			//case 'CONTACT_MODIFY':
			//case 'CONTACT_DELETE':
			//case 'CONTACT_ENABLEDISABLE':

			// Products
			//case 'PRODUCT_CREATE':
			//case 'PRODUCT_MODIFY':
			//case 'PRODUCT_DELETE':
			//case 'PRODUCT_PRICE_MODIFY':
			//case 'PRODUCT_SET_MULTILANGS':
			//case 'PRODUCT_DEL_MULTILANGS':

			//Stock movement
			//case 'STOCK_MOVEMENT':

			//MYECMDIR
			//case 'MYECMDIR_CREATE':
			//case 'MYECMDIR_MODIFY':
			//case 'MYECMDIR_DELETE':

			// Sales orders
			//case 'ORDER_CREATE':
			//case 'ORDER_MODIFY':
			//case 'ORDER_VALIDATE':
			//case 'ORDER_DELETE':
			//case 'ORDER_CANCEL':
			//case 'ORDER_SENTBYMAIL':
			//case 'ORDER_CLASSIFY_BILLED':		// TODO Replace it with ORDER_BILLED
			//case 'ORDER_CLASSIFY_UNBILLED':	// TODO Replace it with ORDER_UNBILLED
			//case 'ORDER_SETDRAFT':
			//case 'LINEORDER_INSERT':
			//case 'LINEORDER_MODIFY':
			//case 'LINEORDER_DELETE':

			// Supplier orders
			//case 'ORDER_SUPPLIER_CREATE':
			//case 'ORDER_SUPPLIER_MODIFY':
			//case 'ORDER_SUPPLIER_VALIDATE':
			//case 'ORDER_SUPPLIER_DELETE':
			//case 'ORDER_SUPPLIER_APPROVE':
			//case 'ORDER_SUPPLIER_CLASSIFY_BILLED':		// TODO Replace with ORDER_SUPPLIER_BILLED
			//case 'ORDER_SUPPLIER_CLASSIFY_UNBILLED':		// TODO Replace with ORDER_SUPPLIER_UNBILLED
			//case 'ORDER_SUPPLIER_REFUSE':
			//case 'ORDER_SUPPLIER_CANCEL':
			//case 'ORDER_SUPPLIER_SENTBYMAIL':
			//case 'ORDER_SUPPLIER_RECEIVE':
			//case 'LINEORDER_SUPPLIER_DISPATCH':
			//case 'LINEORDER_SUPPLIER_CREATE':
			//case 'LINEORDER_SUPPLIER_MODIFY':
			//case 'LINEORDER_SUPPLIER_DELETE':

			// Proposals
			//case 'PROPAL_CREATE':
			//case 'PROPAL_MODIFY':
			//case 'PROPAL_VALIDATE':
			//case 'PROPAL_SENTBYMAIL':
			//case 'PROPAL_CLASSIFY_BILLED':		// TODO Replace it with PROPAL_BILLED
			//case 'PROPAL_CLASSIFY_UNBILLED':		// TODO Replace it with PROPAL_UNBILLED
			//case 'PROPAL_CLOSE_SIGNED':
			//case 'PROPAL_CLOSE_REFUSED':
			//case 'PROPAL_DELETE':
			//case 'LINEPROPAL_INSERT':
			//case 'LINEPROPAL_MODIFY':
			//case 'LINEPROPAL_DELETE':

			// SupplierProposal
			//case 'SUPPLIER_PROPOSAL_CREATE':
			//case 'SUPPLIER_PROPOSAL_MODIFY':
			//case 'SUPPLIER_PROPOSAL_VALIDATE':
			//case 'SUPPLIER_PROPOSAL_SENTBYMAIL':
			//case 'SUPPLIER_PROPOSAL_CLOSE_SIGNED':
			//case 'SUPPLIER_PROPOSAL_CLOSE_REFUSED':
			//case 'SUPPLIER_PROPOSAL_DELETE':
			//case 'LINESUPPLIER_PROPOSAL_INSERT':
			//case 'LINESUPPLIER_PROPOSAL_MODIFY':
			//case 'LINESUPPLIER_PROPOSAL_DELETE':

			// Contracts
			//case 'CONTRACT_CREATE':
			//case 'CONTRACT_MODIFY':
			//case 'CONTRACT_ACTIVATE':
			//case 'CONTRACT_CANCEL':
			//case 'CONTRACT_CLOSE':
			//case 'CONTRACT_DELETE':
			//case 'LINECONTRACT_INSERT':
			//case 'LINECONTRACT_MODIFY':
			//case 'LINECONTRACT_DELETE':

			// Bills
			//case 'BILL_CREATE':
			//case 'BILL_MODIFY':
			//case 'BILL_VALIDATE':
			//case 'BILL_UNVALIDATE':
			//case 'BILL_SENTBYMAIL':
			//case 'BILL_CANCEL':
			//case 'BILL_DELETE':
			//case 'BILL_PAYED':
			//case 'LINEBILL_INSERT':
			//case 'LINEBILL_MODIFY':
			//case 'LINEBILL_DELETE':

			// Recurring Bills
			//case 'BILLREC_MODIFY':
			//case 'BILLREC_DELETE':
			//case 'BILLREC_AUTOCREATEBILL':
			//case 'LINEBILLREC_MODIFY':
			//case 'LINEBILLREC_DELETE':

			//Supplier Bill
			//case 'BILL_SUPPLIER_CREATE':
			//case 'BILL_SUPPLIER_MODIFY':
			//case 'BILL_SUPPLIER_DELETE':
			//case 'BILL_SUPPLIER_PAYED':
			//case 'BILL_SUPPLIER_UNPAYED':
			//case 'BILL_SUPPLIER_VALIDATE':
			//case 'BILL_SUPPLIER_UNVALIDATE':
			//case 'LINEBILL_SUPPLIER_CREATE':
			//case 'LINEBILL_SUPPLIER_MODIFY':
			//case 'LINEBILL_SUPPLIER_DELETE':

			// Payments
			//case 'PAYMENT_CUSTOMER_CREATE':
			//case 'PAYMENT_SUPPLIER_CREATE':
			//case 'PAYMENT_ADD_TO_BANK':
			//case 'PAYMENT_DELETE':

			// Online
			//case 'PAYMENT_PAYBOX_OK':
			//case 'PAYMENT_PAYPAL_OK':
			//case 'PAYMENT_STRIPE_OK':

			// Donation
			//case 'DON_CREATE':
			//case 'DON_MODIFY':
			//case 'DON_DELETE':

			// Interventions
			//case 'FICHINTER_CREATE':
			//case 'FICHINTER_MODIFY':
			//case 'FICHINTER_VALIDATE':
			//case 'FICHINTER_CLASSIFY_BILLED':			// TODO Replace it with FICHINTER_BILLED
			//case 'FICHINTER_CLASSIFY_UNBILLED':		// TODO Replace it with FICHINTER_UNBILLED
			//case 'FICHINTER_DELETE':
			//case 'LINEFICHINTER_CREATE':
			//case 'LINEFICHINTER_MODIFY':
			//case 'LINEFICHINTER_DELETE':

			// Members
			//case 'MEMBER_CREATE':
			//case 'MEMBER_VALIDATE':
			//case 'MEMBER_SUBSCRIPTION':
			//case 'MEMBER_MODIFY':
			//case 'MEMBER_NEW_PASSWORD':
			//case 'MEMBER_RESILIATE':
			//case 'MEMBER_DELETE':

			// Categories
			//case 'CATEGORY_CREATE':
			//case 'CATEGORY_MODIFY':
			//case 'CATEGORY_DELETE':
			//case 'CATEGORY_SET_MULTILANGS':

			// Projects
			//case 'PROJECT_CREATE':
			//case 'PROJECT_MODIFY':
			//case 'PROJECT_DELETE':

			// Project tasks
			//case 'TASK_CREATE':
			//case 'TASK_MODIFY':
			//case 'TASK_DELETE':

			// Task time spent
			//case 'TASK_TIMESPENT_CREATE':
			//case 'TASK_TIMESPENT_MODIFY':
			//case 'TASK_TIMESPENT_DELETE':
			//case 'PROJECT_ADD_CONTACT':
			//case 'PROJECT_DELETE_CONTACT':
			//case 'PROJECT_DELETE_RESOURCE':

			// Shipping
			//case 'SHIPPING_CREATE':
			//case 'SHIPPING_MODIFY':
			//case 'SHIPPING_VALIDATE':
			//case 'SHIPPING_SENTBYMAIL':
			//case 'SHIPPING_BILLED':
			//case 'SHIPPING_CLOSED':
			//case 'SHIPPING_REOPEN':
			//case 'SHIPPING_DELETE':

			// and more...

			default:
				dol_syslog("Trigger '".$this->name."' for action '".$action."' launched by ".__FILE__.". id=".$object->id);
				break;
		}

		return 0;
	}
}
