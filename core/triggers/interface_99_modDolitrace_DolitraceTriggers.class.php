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

                        // B. CODICE LOTTO DI PRODUZIONE
                        // Only the code is saved on the plan: the lot and the stock are created by the stock
                        // movement of each harvest (MouvementStock), never with direct SQL on the stock tables
                        if ($product_id > 0) {
                            $year_short = date('y');
                            $crop_ref_clean = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($obj_crop->ref)), 0, 4);
                            // During the validation the final ref is in newref, ref is still the provisional one
                            $plan_ref_clean = preg_replace('/[^A-Z0-9]/', '', strtoupper(!empty($object->newref) ? $object->newref : $object->ref));

                            $batch_code = sprintf("%s-%s-%s", $year_short, $crop_ref_clean, $plan_ref_clean);

                            $sql_up = "UPDATE ".MAIN_DB_PREFIX."dolitrace_cropplan SET fk_output_product=".((int)$product_id).", batch_output='".$this->db->escape($batch_code)."' WHERE rowid=".((int)$object->id);
                            if (!$this->db->query($sql_up)) {
                                $this->error = $this->db->lasterror();
                                return -1;
                            }
                            $object->fk_output_product = $product_id;
                            $object->batch_output = $batch_code;
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
			// Harvest: the harvested quantity is in stock while the harvest is validated
			case 'DOLITRACE_HARVEST_VALIDATE':
			case 'DOLITRACE_HARVEST_REOPEN':
				return $this->harvestStockMovement($object, $user, $langs, true);
			case 'DOLITRACE_HARVEST_UNVALIDATE':
			case 'DOLITRACE_HARVEST_CANCEL':
			case 'DOLITRACEHARVEST_DELETE':
				return $this->harvestStockMovement($object, $user, $langs, false);
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

	/**
	 * Put the harvested quantity in stock, or take it out again, with the stock movements of Dolibarr.
	 * The movements already done for the harvest are summed first, so a status change never moves the quantity twice.
	 * The lot of the harvest (or of its crop plan) is created by the movement itself.
	 *
	 * @param	CommonObject	$object		Harvest (DoliTraceHarvest)
	 * @param	User			$user		User
	 * @param	Translate		$langs		Langs
	 * @param	bool			$instock	true = the quantity must be in stock, false = it must be out of stock
	 * @return	int							<0 if KO (the status change is cancelled), 0 if nothing to do, >0 if moved
	 */
	private function harvestStockMovement($object, User $user, Translate $langs, $instock)
	{
		global $conf;

		if (!isModEnabled('stock')) {
			return 0;
		}
		require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
		$langs->load('dolitrace@dolitrace');

		$origintype = $object->element.'@dolitrace';
		$product_id = (int) $object->fk_product_out;
		$quantity = (float) $object->qty_harvested;

		// Quantity already in stock for this harvest, with the warehouse and the lot of its last receipt
		$moved = 0;
		$moved_product_id = 0;
		$warehouse_id = 0;
		$batch_number = '';
		$sql = "SELECT fk_product, fk_entrepot, batch, value FROM ".$this->db->prefix()."stock_mouvement";
		$sql .= " WHERE origintype = '".$this->db->escape($origintype)."' AND fk_origin = ".((int) $object->id);
		$sql .= " ORDER BY rowid";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$moved += (float) $obj->value;
			if ($obj->value > 0) {
				$warehouse_id = (int) $obj->fk_entrepot;
				$moved_product_id = (int) $obj->fk_product;
				$batch_number = (string) $obj->batch;
			}
		}

		$mouv = new MouvementStock($this->db);
		$mouv->setOrigin($origintype, $object->id);

		if ($instock) {
			if ($moved > 0 || $product_id <= 0 || $quantity <= 0) {
				return 0;
			}

			// Warehouse of the harvested products of the farm dossier of the crop plan,
			// otherwise the production warehouse saved at the activation of the module
			$warehouse_id = 0;
			if ($object->fk_cropplan > 0) {
				dol_include_once('/dolifarm/lib/dolifarm_warehouse.lib.php');
				$sql = "SELECT fk_soc FROM ".$this->db->prefix()."dolitrace_cropplan WHERE rowid = ".((int) $object->fk_cropplan);
				$resql = $this->db->query($sql);
				if ($resql && ($obj = $this->db->fetch_object($resql)) && $obj->fk_soc > 0 && function_exists('dolifarmGetFarmWarehouse')) {
					$warehouse_id = dolifarmGetFarmWarehouse($this->db, (int) $obj->fk_soc, 'products');
				}
			}
			if (!$warehouse_id) {
				$sql = "SELECT rowid FROM ".$this->db->prefix()."entrepot";
				$sql .= " WHERE rowid = ".getDolGlobalInt('DOLITRACE_WAREHOUSE_PROD')." AND entity IN (".getEntity('stock').")";
				$resql = $this->db->query($sql);
				if ($resql && ($obj = $this->db->fetch_object($resql))) {
					$warehouse_id = (int) $obj->rowid;
				}
			}
			if (!$warehouse_id) {
				$this->error = $langs->trans('ErrorDolitraceNoProductionWarehouse');
				return -1;
			}

			// Lot of the harvest, otherwise the production lot of its crop plan (saved on the harvest for traceability)
			$batch_number = (string) $object->batch_number;
			if ($batch_number === '' && $object->fk_cropplan > 0) {
				$sql = "SELECT batch_output FROM ".$this->db->prefix()."dolitrace_cropplan WHERE rowid = ".((int) $object->fk_cropplan);
				$resql = $this->db->query($sql);
				if ($resql && ($obj = $this->db->fetch_object($resql)) && !empty($obj->batch_output)) {
					$batch_number = $obj->batch_output;
					$sql = "UPDATE ".$this->db->prefix()."dolitrace_harvest SET batch_number = '".$this->db->escape($batch_number)."' WHERE rowid = ".((int) $object->id);
					if (!$this->db->query($sql)) {
						$this->error = $this->db->lasterror();
						return -1;
					}
					$object->batch_number = $batch_number;
				}
			}

			$label = $langs->trans('HarvestStockReceived', $object->ref);
			$datem = !empty($object->date_harvest) ? $object->date_harvest : dol_now();
			$result = $mouv->reception($user, $product_id, $warehouse_id, $quantity, 0, $label, '', '', $batch_number, $datem);
		} else {
			if ($moved <= 0) {
				return 0;
			}

			// The quantity goes out of the product, the warehouse and the lot where it went in
			$label = $langs->trans('HarvestStockCancelled', $object->ref);
			$result = $mouv->livraison($user, $moved_product_id, $warehouse_id, $moved, 0, $label, dol_now(), '', '', $batch_number);
		}

		if ($result <= 0) {
			$this->error = $mouv->error;
			$this->errors = array_merge($this->errors, $mouv->errors);
			dol_syslog("Trigger '".$this->name."': stock movement of harvest ".$object->id." failed: ".$mouv->error, LOG_ERR);
			return -1;
		}
		dol_syslog("Trigger '".$this->name."': harvest ".$object->id." ".($instock ? 'received in' : 'taken out of')." warehouse ".$warehouse_id." lot ".$batch_number);

		return 1;
	}
}
