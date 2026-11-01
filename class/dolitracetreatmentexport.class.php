<?php
/* Copyright (C) 2026      Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file        class/dolitracetreatmentexport.class.php
 * \ingroup     dolitrace
 * \brief       Export of the register of treatments and fertilizations: JSON, CSV and PDF
 *
 * The format "dolifarm-register" is versioned: a new column or key keeps the version, a changed or removed one raises it.
 * The CSV has one line per product x area and the same columns are read by the import (see DoliTraceTreatmentImport).
 */

dol_include_once('/dolitrace/class/dolitracetreatment.class.php');
dol_include_once('/dolitrace/class/dolitraceoperation.class.php');
dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');

/**
 * Export of the register
 */
class DoliTraceTreatmentExport
{
	const FORMAT = 'dolifarm-register';
	const FORMAT_VERSION = '1.4';

	/**
	 * @var string[]	Columns of the CSV, in their order
	 */
	public static $csvColumns = array(
		'record', 'ref', 'version', 'status', 'kind', 'technical_type', 'farm_cuaa', 'farm_name',
		'date_start', 'date_end', 'reason', 'operator_name', 'operator_license', 'operator_license_end',
		'contractor_name', 'contractor_vat', 'machine', 'machine_check_end', 'machine_check_date', 'machine_cert_ref', 'water_volume', 'weather', 'application_method', 'justification_type', 'justification_ref', 'late',
		'target_type', 'plot_ref', 'location_ref', 'pcg_ref', 'zone_zvn', 'bio_status', 'crop_eppo', 'crop_label', 'bbch', 'building_ref', 'seed_batch', 'treated_qty', 'treated_unit',
		'product_type', 'product_ref', 'product_label', 'reg_number', 'fert_type', 'fert_group', 'n_content', 'p2o5_content', 'k2o_content', 'cu_content', 'efficiency', 'dry_matter', 'density',
		'adversity_eppo', 'adversity_label', 'dose', 'dose_unit', 'dose_unit_label', 'qty_total', 'qty_unit', 'qty_agea', 'qty_agea_unit', 'qty_agea_basis', 'n_applied', 'cu_applied', 'batch',
		'content_hash',
	);

	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error
	 */
	public $error = '';

	/**
	 * @var array<string,array<int,stdClass|null>>	Rows of the registries already read, by table and id
	 */
	private $cache = array();

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db		Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Records of the register that the user can see
	 *
	 * @param	User		$user		User
	 * @param	int			$socid		Farm, 0 for all the farms of the user
	 * @param	int|string	$datefrom	First day (timestamp), '' for no limit
	 * @param	int|string	$dateto		Last day (timestamp), '' for no limit
	 * @param	int[]		$statuses	Statuses of the records
	 * @return	DoliTraceTreatment[]|int	Records with their products and areas, <0 if KO
	 */
	public function getRecords(User $user, $socid, $datefrom, $dateto, $statuses = array(DoliTraceTreatment::STATUS_VALIDATED))
	{
		$sql = "SELECT t.rowid FROM ".$this->db->prefix()."dolitrace_treatment as t";
		$sql .= " WHERE t.entity IN (".getEntity('dolitracetreatment').")";
		$sql .= " AND t.status IN (".$this->db->sanitize(implode(',', array_map('intval', $statuses))).")";
		if ($socid > 0) {
			$sql .= " AND t.fk_soc = ".((int) $socid);
		}
		if ($datefrom !== '' && $datefrom !== null) {
			$sql .= " AND t.date_start >= '".$this->db->idate((int) $datefrom)."'";
		}
		if ($dateto !== '' && $dateto !== null) {
			$sql .= " AND t.date_start <= '".$this->db->idate((int) $dateto)."'";
		}
		$sql .= dolifarmSqlSocFilter($user, 't.fk_soc');
		$sql .= " ORDER BY t.fk_soc, t.date_start, t.ref";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$records = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$record = new DoliTraceTreatment($this->db);
			if ($record->fetch($obj->rowid) > 0) {
				$records[] = $record;
			}
		}
		$this->db->free($resql);
		return $records;
	}

	/**
	 * Row of a table of the registries, read once
	 *
	 * @param	string	$table		Table without prefix
	 * @param	int		$id			Id
	 * @param	string	$fields		Fields to read
	 * @return	stdClass|null		Row
	 */
	private function lookup($table, $id, $fields)
	{
		if (empty($id)) {
			return null;
		}
		if (!array_key_exists($table, $this->cache) || !array_key_exists($id, $this->cache[$table])) {
			$rows = dolitraceTreatmentFetchRows($this->db, "SELECT rowid, ".$fields." FROM ".$this->db->prefix().$table." WHERE rowid = ".((int) $id));
			$this->cache[$table][$id] = isset($rows[$id]) ? $rows[$id] : null;
		}
		return $this->cache[$table][$id];
	}

	/**
	 * Quantity of a product for each unit treated, in the units of the AGEA field notebook
	 *
	 * Total in kg or l (t and m³ converted) divided by the treated quantity of the areas: hectares for the surfaces,
	 * m³ or m² for the closed spaces, kg or number of seeds for the seeds.
	 *
	 * @param	string|float	$qtytotal	Total quantity of the product
	 * @param	string			$qtyunit	Unit of the total (kg, l, t, m3)
	 * @param	array<int,array<string,mixed>>	$targets	Areas of the content of the record
	 * @return	array{qty:string,unit:string,basis:string}	Quantity (decimal point, as the other quantities of the record), unit (kg, l) and basis (ha, m2, m3, kg, seed)
	 */
	public function ageaQuantity($qtytotal, $qtyunit, $targets)
	{
		$none = array('qty' => '', 'unit' => '', 'basis' => '');
		$units = array('kg' => array('kg', 1), 't' => array('kg', 1000), 'l' => array('l', 1), 'm3' => array('l', 1000));
		if ($qtytotal === '' || $qtytotal === null || !isset($units[$qtyunit])) {
			return $none;
		}
		// Treated quantity in the basis of the first area, the others converted when they can be
		$bases = array('HA' => array('ha', 1), 'M2' => array('m2', 1), 'M3' => array('m3', 1), 'KG' => array('kg', 1), 'T' => array('kg', 1000), 'KSEED' => array('seed', 1000));
		$basis = '';
		$treated = 0.0;
		foreach ($targets as $target) {
			$unit = isset($target['treated_unit']) ? $target['treated_unit'] : '';
			if (!isset($bases[$unit]) || $target['treated_qty'] === '' || $target['treated_qty'] === null) {
				continue;
			}
			if ($basis === '') {
				$basis = $bases[$unit][0];
			}
			if ($bases[$unit][0] == $basis) {
				$treated += (float) $target['treated_qty'] * $bases[$unit][1];
			}
		}
		if ($basis === '' || $treated <= 0) {
			return $none;
		}
		$qty = (float) $qtytotal * $units[$qtyunit][1];
		// A dose for each seed is very small: 12 decimals
		$qty = rtrim(rtrim(sprintf('%.12F', $qty / $treated), '0'), '.');
		return array('qty' => $qty, 'unit' => $units[$qtyunit][0], 'basis' => $basis);
	}

	/**
	 * Main BBCH stage as in the AGEA list of the phenological stages (sheet 2)
	 *
	 * @param	string|null		$bbch		BBCH code, two digits
	 * @return	string						For example "BBCH 6 - Fioritura", '' if not a BBCH code
	 */
	public function bbchStageAgea($bbch)
	{
		$stages = array('Germinazione', 'Sviluppo Fogliare', 'Formazione germogli laterali', 'Allungamento dello stelo', 'Sviluppo parti vegetative',
			'Emergenza infiorescenza', 'Fioritura', 'Sviluppo dei frutti', 'Maturazione', 'Senescenza');
		$bbch = trim((string) $bbch);
		if (!preg_match('/^[0-9]{1,2}$/', $bbch)) {
			return '';
		}
		$stage = (strlen($bbch) == 2 ? (int) $bbch[0] : 0);
		return 'BBCH '.$stage.' - '.$stages[$stage];
	}

	/**
	 * Row of a dictionary by its code, with cache
	 *
	 * @param	string		$table		Table without prefix
	 * @param	string		$code		Code
	 * @param	string		$fields		Fields to read
	 * @return	object|null				Row, null if not found
	 */
	private function lookupCode($table, $code, $fields)
	{
		if ((string) $code === '') {
			return null;
		}
		$key = 'code:'.$table;
		if (!array_key_exists($key, $this->cache) || !array_key_exists($code, $this->cache[$key])) {
			$this->cache[$key][$code] = null;
			$resql = $this->db->query("SELECT ".$fields." FROM ".$this->db->prefix().$table." WHERE code = '".$this->db->escape($code)."'");
			if ($resql) {
				$obj = $this->db->fetch_object($resql);
				$this->cache[$key][$code] = $obj ? $obj : null;
				$this->db->free($resql);
			}
		}
		return $this->cache[$key][$code];
	}

	/**
	 * Data of a record in the format of the register
	 *
	 * @param	DoliTraceTreatment	$record		Record with its products and areas
	 * @return	array<string,mixed>				Record
	 */
	public function getRecordData(DoliTraceTreatment $record)
	{
		$statuses = array(DoliTraceTreatment::STATUS_DRAFT => 'draft', DoliTraceTreatment::STATUS_VALIDATED => 'validated', DoliTraceTreatment::STATUS_REPLACED => 'replaced', DoliTraceTreatment::STATUS_CANCELED => 'canceled');
		$content = $record->getRegisterContent();
		$farm = $this->lookup('societe', $record->fk_soc, 'nom, idprof4, tva_intra');
		$contractor = $this->lookup('societe', $record->fk_soc_contractor, 'nom, idprof4, tva_intra');
		$machine = $this->lookup('dolifarm_machine', $record->fk_machine, 'ref');
		$operator = $this->lookup('dolifarm_operator', $record->fk_operator, 'ref');
		$method = isset($content['application_method']) ? $this->lookupCode('dolitrace_c_application_method', $content['application_method'], 'label, agea_value') : null;
		// Organic production (format 1.4): document justifying the need of the plant protection
		$justification = isset($content['justification_type']) ? $this->lookupCode('dolitrace_c_justification', $content['justification_type'], 'label') : null;
		$previous = $this->lookup('dolitrace_treatment', $record->fk_previous, 'ref');
		$hashcheck = $record->status == DoliTraceTreatment::STATUS_DRAFT ? 0 : $record->checkHash();

		$data = array(
			'ref' => $content['ref'],
			'version' => $content['version'],
			'previous_ref' => $previous ? $previous->ref : '',
			'status' => isset($statuses[$record->status]) ? $statuses[$record->status] : (string) $record->status,
			'kind' => $content['kind'] == DoliTraceTreatment::KIND_FERTILIZATION ? 'fertilization' : 'plant_protection',
			'technical_type' => array(1 => 'surfaces', 2 => 'closed_spaces', 3 => 'seeds')[$content['technical_type']] ?? (string) $content['technical_type'],
			'farm' => array(
				'name' => $farm ? $farm->nom : '',
				'cuaa' => $farm ? (string) $farm->idprof4 : '',
				'vat' => $farm ? (string) $farm->tva_intra : '',
			),
			'date_start' => $this->dateIso($record->date_start, true),
			'date_end' => $this->dateIso($record->date_end, true),
			'reason' => $content['reason'],
			'operator' => array(
				'ref' => $operator ? $operator->ref : '',
				'name' => $content['operator_name'],
				'license' => $content['operator_license'],
				'license_end' => $content['operator_license_end'],
			),
			'contractor' => $contractor ? array(
				'name' => $contractor->nom,
				'cuaa' => (string) $contractor->idprof4,
				'vat' => (string) $contractor->tva_intra,
				'mode' => array(1 => 'countersign', 2 => 'form')[(int) $record->contractor_mode] ?? '',
				'signed' => (int) $record->contractor_signed,
				'delegation_ref' => $content['delegation_ref'],
			) : null,
			'machine' => array(
				'ref' => $machine ? $machine->ref : '',
				'label' => $content['machine_label'],
				'check_end' => $content['machine_check_end'],
				'check_date' => isset($content['machine_check_date']) ? $content['machine_check_date'] : '',
				'cert_ref' => isset($content['machine_cert_ref']) ? $content['machine_cert_ref'] : '',
			),
			'application_method' => $method ? array(
				'code' => $content['application_method'],
				'label' => (string) $method->label,
				'agea_value' => (string) $method->agea_value,
			) : null,
			'justification' => (isset($content['justification_type']) || isset($content['justification_ref'])) ? array(
				'type' => isset($content['justification_type']) ? $content['justification_type'] : '',
				'label' => $justification ? (string) $justification->label : '',
				'ref' => isset($content['justification_ref']) ? $content['justification_ref'] : '',
			) : null,
			'water_volume_l_ha' => $content['water_volume'],
			'weather' => $content['weather'],
			'late' => (int) $record->late,
			'date_valid' => $this->dateIso($record->date_valid, true),
			'cancel_reason' => (string) $record->cancel_reason,
			'content_hash' => (string) $record->content_hash,
			'integrity' => $record->status == DoliTraceTreatment::STATUS_DRAFT ? '' : ($hashcheck > 0 ? 'ok' : ($hashcheck == 0 ? 'altered' : 'none')),
			'targets' => array(),
			'products' => array(),
		);
		foreach ($record->targets as $i => $target) {
			$plot = $this->lookup('dolifarm_plot', $target->fk_plot, 'ref');
			$building = $this->lookup('dolifarm_building', $target->fk_building, 'ref, address, cadastral_comune, cadastral_sheet, cadastral_parcel, cadastral_sub, volume');
			$cropplan = $this->lookup('dolitrace_cropplan', $target->fk_cropplan, 'pcg_ref');
			$data['targets'][] = array(
				'type' => array(1 => 'plot', 2 => 'building', 3 => 'seeds')[(int) $target->target_type] ?? (string) $target->target_type,
				'plot_ref' => $plot ? $plot->ref : '',
				'location_ref' => $content['targets'][$i]['location_ref'],
				// Area of the graphic crop plan of the SIAN, from the crop plan (not part of the content of the record)
				'pcg_ref' => $cropplan ? (string) $cropplan->pcg_ref : '',
				'zone_zvn' => $content['targets'][$i]['zone_zvn'],
				// Organic status of the plot at the date (code of dolifarm_c_biostatus), copied at the validation (format 1.4)
				'bio_status' => isset($content['targets'][$i]['bio_status']) ? $content['targets'][$i]['bio_status'] : '',
				'crop_eppo' => $content['targets'][$i]['crop_eppo'],
				'crop_label' => $content['targets'][$i]['crop_label'],
				'bbch' => $content['targets'][$i]['bbch'],
				'bbch_stage_agea' => $this->bbchStageAgea($content['targets'][$i]['bbch']),
				'building_ref' => $building ? $building->ref : '',
				// Silo or warehouse of the treated products and seeds (sheets 4 and 5)
				'building' => $building ? array(
					'address' => (string) $building->address,
					'cadastral_comune' => (string) $building->cadastral_comune,
					'cadastral_sheet' => (string) $building->cadastral_sheet,
					'cadastral_parcel' => (string) $building->cadastral_parcel,
					'cadastral_sub' => (string) $building->cadastral_sub,
					'capacity_m3' => $building->volume !== null ? (float) $building->volume : null,
				) : null,
				'seed_batch' => $content['targets'][$i]['seed_batch'],
				'treated_qty' => $content['targets'][$i]['treated_qty'],
				'treated_unit' => $content['targets'][$i]['treated_unit'],
			);
		}
		foreach ($record->products as $i => $product) {
			$agrodrug = $this->lookup('dolifarm_agrodrug', $product->fk_agrodrug, 'ref');
			$fertilizer = $this->lookup('dolifarm_fertilizer', $product->fk_fertilizer, 'ref, fk_fert_type');
			$ferttype = $fertilizer ? $this->lookupCode('dolifarm_c_fert_type', $fertilizer->fk_fert_type, 'label, fert_group, agea_value') : null;
			$unit = $this->lookup('dolitrace_c_dose_unit', $product->fk_dose_unit, 'code');
			$data['products'][] = array(
				'type' => $product->fk_fertilizer > 0 ? 'fertilizer' : 'plant_protection_product',
				'ref' => $agrodrug ? $agrodrug->ref : ($fertilizer ? $fertilizer->ref : ''),
				'label' => $content['products'][$i]['product_label'],
				'reg_number' => $content['products'][$i]['reg_number'],
				'fert_type' => $fertilizer ? (string) $fertilizer->fk_fert_type : '',
				'fert_group' => $ferttype ? (string) $ferttype->fert_group : '',
				'fert_type_agea' => $ferttype ? (string) $ferttype->agea_value : '',
				'n_content' => $content['products'][$i]['n_content'],
				'p2o5_content' => $content['products'][$i]['p2o5_content'],
				'k2o_content' => $content['products'][$i]['k2o_content'],
				'cu_content' => isset($content['products'][$i]['cu_content']) ? $content['products'][$i]['cu_content'] : '',
				'efficiency' => isset($content['products'][$i]['efficiency']) ? $content['products'][$i]['efficiency'] : '',
				'dry_matter' => isset($content['products'][$i]['dry_matter']) ? $content['products'][$i]['dry_matter'] : '',
				'density' => isset($content['products'][$i]['density']) ? $content['products'][$i]['density'] : '',
				'adversity_eppo' => $content['products'][$i]['adversity_code'],
				'adversity_label' => $content['products'][$i]['adversity_label'],
				'dose' => $content['products'][$i]['dose'],
				'dose_unit' => $unit ? $unit->code : '',
				'dose_unit_label' => $content['products'][$i]['dose_unit_label'],
				'qty_total' => $content['products'][$i]['qty_total'],
				'qty_unit' => $content['products'][$i]['qty_unit'],
				'n_applied_kg_ha' => $content['products'][$i]['n_applied'],
				'cu_applied_kg_ha' => isset($content['products'][$i]['cu_applied']) ? $content['products'][$i]['cu_applied'] : '',
				'batch' => $content['products'][$i]['batch'],
			);
			// Quantity in the units of the AGEA lists: kg or l for each ha, m², m³, kg of seed or seed
			$agea = $this->ageaQuantity($content['products'][$i]['qty_total'], $content['products'][$i]['qty_unit'], $content['targets']);
			$data['products'][$i]['qty_agea'] = $agea['qty'];
			$data['products'][$i]['qty_agea_unit'] = $agea['unit'];
			$data['products'][$i]['qty_agea_basis'] = $agea['basis'];
		}
		return $data;
	}

	/**
	 * Date in ISO format: date and hour in the time zone of the user, as shown on the card
	 *
	 * @param	int|string|null		$date		Timestamp
	 * @param	bool				$withhour	Add hours and minutes
	 * @return	string						Date, '' if empty
	 */
	private function dateIso($date, $withhour)
	{
		return empty($date) ? '' : dol_print_date($date, $withhour ? '%Y-%m-%d %H:%M' : '%Y-%m-%d', ($withhour ? 'tzuser' : 'tzserver'));
	}

	/**
	 * Register in JSON
	 *
	 * @param	DoliTraceTreatment[]	$records	Records
	 * @param	array<string,string>	$filters	Filters of the export (farm, period)
	 * @param	array<int,array<string,mixed>>	$operations	Crop events and irrigations
	 * @param	array<int,array<string,mixed>>	$storage	Storage sites
	 * @param	?array<string,mixed>	$organic	Organic production (DoliTraceBioRegister::getOrganicSection), null when not exported
	 * @return	string							JSON
	 */
	public function toJson($records, $filters, $operations = array(), $storage = array(), ?array $organic = null)
	{
		$data = array(
			'format' => self::FORMAT,
			'format_version' => self::FORMAT_VERSION,
			'generated_at' => dol_print_date(dol_now(), '%Y-%m-%dT%H:%M:%SZ', 'gmt'),
			'regulation' => 'Reg. (EU) 2023/564',
			'filters' => $filters,
			'records' => array(),
			'crop_events' => array(),
			'irrigations' => array(),
			'storage' => $storage,
			'operators' => array(),
			'machines' => array(),
			// Organic production: status and history of the plots, status of the lots, mass balance, summary by plot
			'organic' => $organic,
		);
		foreach ($records as $record) {
			$data['records'][] = $this->getRecordData($record);
		}
		// Registers of the operators (sheet 11) and of the machines (sheet 10) that did the treatments
		$operatorids = array();
		$machineids = array();
		foreach ($records as $record) {
			if ($record->fk_operator > 0) {
				$operatorids[(int) $record->fk_operator] = (int) $record->fk_operator;
			}
			if ($record->fk_machine > 0) {
				$machineids[(int) $record->fk_machine] = (int) $record->fk_machine;
			}
		}
		$data['operators'] = $this->getOperators($operatorids);
		$data['machines'] = $this->getMachines($machineids);
		foreach ($operations as $operation) {
			if ($operation['type'] == DoliTraceOperation::TYPE_IRRIGATION) {
				$data['irrigations'][] = $operation;
			} else {
				$data['crop_events'][] = $operation;
			}
		}
		return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	/**
	 * Operators of the treatments (AGEA field notebook, sheet 11): current data of their cards
	 *
	 * First name and last name come from the user or the contact linked to the operator; without them only the name
	 * of the card is given, it is not split.
	 *
	 * @param	int[]	$ids	Ids of the operators
	 * @return	array<int,array<string,string>>	Operators
	 */
	public function getOperators($ids)
	{
		$operators = array();
		if (empty($ids)) {
			return $operators;
		}
		$prefix = $this->db->prefix();
		$sql = "SELECT o.ref, o.label, o.tax_code, o.license_number, o.license_issuer, o.license_date, o.license_end,";
		$sql .= " u.firstname as user_firstname, u.lastname as user_lastname, c.firstname as contact_firstname, c.lastname as contact_lastname,";
		$sql .= " s.nom as company_name, s.tva_intra as company_vat, s.idprof4 as company_cuaa";
		$sql .= " FROM ".$prefix."dolifarm_operator as o";
		$sql .= " LEFT JOIN ".$prefix."user as u ON u.rowid = o.fk_user";
		$sql .= " LEFT JOIN ".$prefix."socpeople as c ON c.rowid = o.fk_socpeople";
		$sql .= " LEFT JOIN ".$prefix."societe as s ON s.rowid = o.fk_soc";
		$sql .= " WHERE o.rowid IN (".$this->db->sanitize(implode(',', array_map('intval', $ids))).")";
		$sql .= " ORDER BY o.ref";
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' '.$this->db->lasterror(), LOG_ERR);
			return $operators;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$firstname = (string) ($obj->user_lastname !== null ? $obj->user_firstname : $obj->contact_firstname);
			$lastname = (string) ($obj->user_lastname !== null ? $obj->user_lastname : $obj->contact_lastname);
			$operators[] = array(
				'ref' => (string) $obj->ref,
				'name' => (string) $obj->label,
				'firstname' => $firstname,
				'lastname' => $lastname,
				'tax_code' => (string) $obj->tax_code,
				'license_number' => (string) $obj->license_number,
				'license_issuer' => (string) $obj->license_issuer,
				'license_date' => $this->dateIso($this->db->jdate($obj->license_date), false),
				'license_end' => $this->dateIso($this->db->jdate($obj->license_end), false),
				'company' => (string) $obj->company_name,
				'company_vat' => (string) $obj->company_vat,
				'company_cuaa' => (string) $obj->company_cuaa,
			);
		}
		$this->db->free($resql);
		return $operators;
	}

	/**
	 * Machines of the treatments (AGEA field notebook, sheet 10): current data of their cards
	 *
	 * @param	int[]	$ids	Ids of the machines
	 * @return	array<int,array<string,string>>	Machines
	 */
	public function getMachines($ids)
	{
		$machines = array();
		if (empty($ids)) {
			return $machines;
		}
		$prefix = $this->db->prefix();
		$sql = "SELECT m.ref, m.label, m.sprayer_cert_ref, m.date_sprayer_check, m.date_sprayer_check_end, s.nom as owner_name";
		$sql .= " FROM ".$prefix."dolifarm_machine as m";
		$sql .= " LEFT JOIN ".$prefix."societe as s ON s.rowid = m.fk_soc";
		$sql .= " WHERE m.rowid IN (".$this->db->sanitize(implode(',', array_map('intval', $ids))).")";
		$sql .= " ORDER BY m.ref";
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' '.$this->db->lasterror(), LOG_ERR);
			return $machines;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$machines[] = array(
				'ref' => (string) $obj->ref,
				'label' => (string) $obj->label,
				'owner' => (string) $obj->owner_name,
				'sprayer_cert_ref' => (string) $obj->sprayer_cert_ref,
				'sprayer_check_date' => $this->dateIso($this->db->jdate($obj->date_sprayer_check), false),
				'sprayer_check_end' => $this->dateIso($this->db->jdate($obj->date_sprayer_check_end), false),
			);
		}
		$this->db->free($resql);
		return $machines;
	}

	/**
	 * Storage sites of the plant protection products and fertilizers (AGEA field notebook, sheet 9): warehouse of the
	 * technical inputs of the farm dossiers, with the cadastral data of the building that keeps it, and the stock of each
	 * product at the start and at the end of the period, computed from the current stock and the movements (read only)
	 *
	 * @param	User		$user		User
	 * @param	int			$socid		Farm, 0 for all the farms of the user
	 * @param	int|string	$datefrom	First day (timestamp), '' for no limit
	 * @param	int|string	$dateto		Last day (timestamp), '' for today
	 * @return	array<int,array<string,mixed>>|int	Storage sites, <0 if KO
	 */
	public function getStorage(User $user, $socid, $datefrom, $dateto)
	{
		if (!isModEnabled('stock')) {
			return array();
		}
		$prefix = $this->db->prefix();
		$sql = "SELECT DISTINCT e.rowid, e.ref, e.lieu, e.address, e.zip, e.town, d.fk_soc, s.nom as farm_name, s.idprof4 as farm_cuaa";
		$sql .= " FROM ".$prefix."dolifarm_farmdossier as d";
		$sql .= " INNER JOIN ".$prefix."entrepot as e ON e.rowid = d.fk_warehouse_inputs";
		$sql .= " LEFT JOIN ".$prefix."societe as s ON s.rowid = d.fk_soc";
		$sql .= " WHERE d.entity IN (".getEntity('dolifarmfarmdossier').")";
		if ($socid > 0) {
			$sql .= " AND d.fk_soc = ".((int) $socid);
		}
		$sql .= dolifarmSqlSocFilter($user, 'd.fk_soc');
		$sql .= " ORDER BY d.fk_soc, e.ref";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$sites = array();
		while ($obj = $this->db->fetch_object($resql)) {
			if (isset($sites[$obj->rowid])) {
				continue;
			}
			$sites[$obj->rowid] = $obj;
		}
		$this->db->free($resql);

		// Products of the register: plant protection products and fertilizers linked to a product of the stock
		$products = array();
		$sql = "SELECT 'plant_protection_product' as type, a.ref, a.label, a.reg_number, a.fk_product FROM ".$prefix."dolifarm_agrodrug as a";
		$sql .= " WHERE a.fk_product > 0 AND a.entity IN (".getEntity('dolifarmagrodrug').")";
		$sql .= " UNION ALL SELECT 'fertilizer' as type, f.ref, f.label, f.registration_number as reg_number, f.fk_product FROM ".$prefix."dolifarm_fertilizer as f";
		$sql .= " WHERE f.fk_product > 0 AND f.entity IN (".getEntity('dolifarmfertilizer').")";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			if (!isset($products[$obj->fk_product])) {
				$products[$obj->fk_product] = $obj;
			}
		}
		$this->db->free($resql);

		// Units of the products: quantities in kg, litres or units as in the AGEA list
		$units = array(
			'T' => array('kg', 1000), 'KG' => array('kg', 1), 'G' => array('kg', 0.001), 'MG' => array('kg', 0.000001),
			'M3' => array('l', 1000), 'DM3' => array('l', 1), 'L' => array('l', 1), 'CM3' => array('l', 0.001),
			'P' => array('unit', 1), 'SET' => array('unit', 1),
		);
		$productunits = array();
		if (count($products)) {
			$sql = "SELECT p.rowid, u.code FROM ".$prefix."product as p LEFT JOIN ".$prefix."c_units as u ON u.rowid = p.fk_unit";
			$sql .= " WHERE p.rowid IN (".$this->db->sanitize(implode(',', array_map('intval', array_keys($products)))).")";
			$resql = $this->db->query($sql);
			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
					$productunits[$obj->rowid] = (string) $obj->code;
				}
				$this->db->free($resql);
			}
		}

		$start = ($datefrom !== '' && $datefrom !== null) ? (int) $datefrom : null;
		$end = ($dateto !== '' && $dateto !== null) ? (int) $dateto : dol_now();
		$storage = array();
		foreach ($sites as $site) {
			$building = null;
			$sql = "SELECT ref, cadastral_comune, cadastral_section, cadastral_sheet, cadastral_parcel, cadastral_sub FROM ".$prefix."dolifarm_building";
			$sql .= " WHERE fk_warehouse = ".((int) $site->rowid)." AND entity IN (".getEntity('dolifarmbuilding').") ORDER BY rowid";
			$resql = $this->db->query($sql);
			if ($resql) {
				$building = $this->db->fetch_object($resql);
				$this->db->free($resql);
			}

			// Stock now and movements after the start and after the end of the period
			$stock = array();
			$sql = "SELECT fk_product, reel FROM ".$prefix."product_stock WHERE fk_entrepot = ".((int) $site->rowid);
			$resql = $this->db->query($sql);
			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
					$stock[$obj->fk_product] = array('now' => (float) $obj->reel, 'after_start' => 0.0, 'after_end' => 0.0);
				}
				$this->db->free($resql);
			}
			$sql = "SELECT fk_product,";
			$sql .= " SUM(CASE WHEN ".($start !== null ? "datem >= '".$this->db->idate($start)."'" : "1 = 0")." THEN value ELSE 0 END) as after_start,";
			$sql .= " SUM(CASE WHEN datem > '".$this->db->idate($end)."' THEN value ELSE 0 END) as after_end";
			$sql .= " FROM ".$prefix."stock_mouvement WHERE fk_entrepot = ".((int) $site->rowid)." GROUP BY fk_product";
			$resql = $this->db->query($sql);
			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
					if (!isset($stock[$obj->fk_product])) {
						$stock[$obj->fk_product] = array('now' => 0.0, 'after_start' => 0.0, 'after_end' => 0.0);
					}
					$stock[$obj->fk_product]['after_start'] = (float) $obj->after_start;
					$stock[$obj->fk_product]['after_end'] = (float) $obj->after_end;
				}
				$this->db->free($resql);
			}

			$lines = array();
			foreach ($stock as $fk_product => $values) {
				if (!isset($products[$fk_product])) {
					continue;
				}
				$product = $products[$fk_product];
				$code = isset($productunits[$fk_product]) ? $productunits[$fk_product] : '';
				$unit = isset($units[$code]) ? $units[$code] : array('', 1);
				$qtystart = ($start !== null ? $values['now'] - $values['after_start'] : null);
				$qtyend = $values['now'] - $values['after_end'];
				$lines[] = array(
					'type' => $product->type,
					'ref' => (string) $product->ref,
					'label' => (string) $product->label,
					'reg_number' => (string) $product->reg_number,
					'date_start' => $start !== null ? dol_print_date($start, '%Y-%m-%d') : '',
					'qty_start' => $qtystart !== null ? (float) price2num($qtystart * $unit[1], 'MS') : null,
					'date_end' => dol_print_date($end, '%Y-%m-%d'),
					'qty_end' => (float) price2num($qtyend * $unit[1], 'MS'),
					'unit' => $unit[0],
					'stock_unit' => $code,
				);
			}
			$storage[] = array(
				'warehouse_ref' => (string) $site->ref,
				'warehouse_label' => (string) $site->lieu,
				'farm' => array('name' => (string) $site->farm_name, 'cuaa' => (string) $site->farm_cuaa),
				'address' => (string) $site->address,
				'zip' => (string) $site->zip,
				'town' => (string) $site->town,
				'building_ref' => $building ? (string) $building->ref : '',
				'cadastral_comune' => $building ? (string) $building->cadastral_comune : '',
				'cadastral_section' => $building ? (string) $building->cadastral_section : '',
				'cadastral_sheet' => $building ? (string) $building->cadastral_sheet : '',
				'cadastral_parcel' => $building ? (string) $building->cadastral_parcel : '',
				'cadastral_sub' => $building ? (string) $building->cadastral_sub : '',
				'products' => $lines,
			);
		}
		return $storage;
	}

	/**
	 * Crop events and irrigations of the period (crop operations of doliTrace), for the sections of the field notebook
	 * that are not treatments or fertilizations
	 *
	 * Fertilizations and plant protection treatments are in the register: the operations of these types are left out.
	 *
	 * @param	User		$user		User
	 * @param	int			$socid		Farm, 0 for all the farms of the user
	 * @param	int|string	$datefrom	First day (timestamp), '' for no limit
	 * @param	int|string	$dateto		Last day (timestamp), '' for no limit
	 * @return	array<int,array<string,mixed>>|int	Operations, <0 if KO
	 */
	public function getOperations(User $user, $socid, $datefrom, $dateto)
	{
		$sql = "SELECT o.rowid, o.ref, o.date_op, o.date_op_end, o.fk_operation_type, o.label, o.fk_pheno_stage, o.status,";
		$sql .= " o.irrigation_method, o.water_volume, o.irrigated_area, o.fertigation, o.fk_treatment, o.seed_status, o.bdsb_ref, o.bdsb_date,";
		$sql .= " cp.ref as cropplan_ref, cp.pcg_ref, cp.fk_soc, p.ref as plot_ref, p.iacs_parcel,";
		$sql .= " c.eppo_code as crop_eppo, c.label as crop_label, s.nom as farm_name, s.idprof4 as farm_cuaa,";
		$sql .= " ot.label as type_label, ot.agea_value as type_agea, im.label as irrigation_method_label, im.agea_value as irrigation_method_agea, tr.ref as treatment_ref";
		$sql .= " FROM ".$this->db->prefix()."dolitrace_operation as o";
		$sql .= " INNER JOIN ".$this->db->prefix()."dolitrace_cropplan as cp ON cp.rowid = o.fk_cropplan";
		$sql .= " LEFT JOIN ".$this->db->prefix()."dolifarm_plot as p ON p.rowid = cp.fk_plot";
		$sql .= " LEFT JOIN ".$this->db->prefix()."dolifarm_crops as c ON c.rowid = cp.fk_crop";
		$sql .= " LEFT JOIN ".$this->db->prefix()."societe as s ON s.rowid = cp.fk_soc";
		$sql .= " LEFT JOIN ".$this->db->prefix()."dolifarm_c_operation_type as ot ON ot.code = o.fk_operation_type";
		$sql .= " LEFT JOIN ".$this->db->prefix()."dolitrace_c_irrigation_method as im ON im.code = o.irrigation_method";
		$sql .= " LEFT JOIN ".$this->db->prefix()."dolitrace_treatment as tr ON tr.rowid = o.fk_treatment";
		$sql .= " WHERE o.entity IN (".getEntity('dolitraceoperation').")";
		$sql .= " AND (o.status IS NULL OR o.status <> ".((int) DoliTraceOperation::STATUS_CANCELED).")";
		$sql .= " AND o.fk_operation_type NOT IN ('TT_Fertiliser', 'TT_Pesticide')";
		if ($socid > 0) {
			$sql .= " AND cp.fk_soc = ".((int) $socid);
		}
		if ($datefrom !== '' && $datefrom !== null) {
			$sql .= " AND o.date_op >= '".$this->db->idate((int) $datefrom)."'";
		}
		if ($dateto !== '' && $dateto !== null) {
			$sql .= " AND o.date_op <= '".$this->db->idate((int) $dateto)."'";
		}
		$sql .= dolifarmSqlSocFilter($user, 'cp.fk_soc');
		$sql .= " ORDER BY cp.fk_soc, o.date_op, o.rowid";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		global $langs;

		// The labels of the crops of doliFarm are translation keys
		$langs->load('dolifarm@dolifarm');
		$operations = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$operation = array(
				'id' => (int) $obj->rowid,
				'ref' => (string) $obj->ref,
				'type' => (string) $obj->fk_operation_type,
				'type_label' => (string) ($obj->type_label ? $obj->type_label : $obj->fk_operation_type),
				'type_agea' => (string) $obj->type_agea,
				'date' => $this->dateIso($this->db->jdate($obj->date_op), true),
				'date_end' => $this->dateIso($this->db->jdate($obj->date_op_end), true),
				'label' => (string) $obj->label,
				'farm' => array('name' => (string) $obj->farm_name, 'cuaa' => (string) $obj->farm_cuaa),
				'cropplan_ref' => (string) $obj->cropplan_ref,
				'pcg_ref' => (string) $obj->pcg_ref,
				'plot_ref' => (string) $obj->plot_ref,
				'location_ref' => (string) $obj->iacs_parcel,
				'crop_eppo' => (string) $obj->crop_eppo,
				'crop_label' => $langs->transnoentitiesnoconv((string) $obj->crop_label),
				'bbch' => (string) $obj->fk_pheno_stage,
				'bbch_stage_agea' => $this->bbchStageAgea($obj->fk_pheno_stage),
			);
			if ($obj->fk_operation_type == DoliTraceOperation::TYPE_IRRIGATION) {
				$operation['irrigation'] = array(
					'method' => (string) $obj->irrigation_method,
					'method_label' => (string) $obj->irrigation_method_label,
					'method_agea' => (string) $obj->irrigation_method_agea,
					'water_volume_m3' => $obj->water_volume !== null ? (float) $obj->water_volume : null,
					'irrigated_area_ha' => $obj->irrigated_area !== null ? (float) $obj->irrigated_area : null,
					'fertigation' => (int) $obj->fertigation,
					'fertigation_record_ref' => (string) $obj->treatment_ref,
				);
			}
			// Organic production (format 1.4): reproductive material of the sowing and planting
			if (in_array((string) $obj->fk_operation_type, DoliTraceOperation::$seedingTypes)) {
				$operation['seed'] = array(
					'status' => (string) $obj->seed_status,
					'bdsb_ref' => (string) $obj->bdsb_ref,
					'bdsb_date' => $this->dateIso($this->db->jdate($obj->bdsb_date), false),
				);
			}
			$operations[] = $operation;
		}
		$this->db->free($resql);
		return $operations;
	}

	/**
	 * Lines of the CSV of a record: one line per product x area
	 *
	 * @param	DoliTraceTreatment	$record		Record
	 * @return	array<int,array<string,string>>	Lines, by column
	 */
	public function getCsvLines(DoliTraceTreatment $record)
	{
		$data = $this->getRecordData($record);
		$head = array(
			'record' => $data['ref'], 'ref' => $data['ref'], 'version' => $data['version'], 'status' => $data['status'],
			'kind' => $data['kind'], 'technical_type' => $data['technical_type'],
			'farm_cuaa' => $data['farm']['cuaa'], 'farm_name' => $data['farm']['name'],
			'date_start' => $data['date_start'], 'date_end' => $data['date_end'], 'reason' => $data['reason'],
			'operator_name' => $data['operator']['name'], 'operator_license' => $data['operator']['license'], 'operator_license_end' => $data['operator']['license_end'],
			'contractor_name' => $data['contractor'] ? $data['contractor']['name'] : '', 'contractor_vat' => $data['contractor'] ? $data['contractor']['vat'] : '',
			'machine' => $data['machine']['ref'], 'machine_check_end' => $data['machine']['check_end'],
			'machine_check_date' => $data['machine']['check_date'], 'machine_cert_ref' => $data['machine']['cert_ref'],
			'water_volume' => $data['water_volume_l_ha'], 'weather' => $data['weather'],
			'application_method' => $data['application_method'] ? $data['application_method']['code'] : '', 'late' => $data['late'],
			'justification_type' => $data['justification'] ? $data['justification']['type'] : '', 'justification_ref' => $data['justification'] ? $data['justification']['ref'] : '',
			'content_hash' => $data['content_hash'],
		);
		$targets = count($data['targets']) ? $data['targets'] : array(array());
		$products = count($data['products']) ? $data['products'] : array(array());
		$lines = array();
		foreach ($targets as $target) {
			foreach ($products as $product) {
				$line = $head;
				foreach (array('plot_ref', 'location_ref', 'pcg_ref', 'zone_zvn', 'bio_status', 'crop_eppo', 'crop_label', 'bbch', 'building_ref', 'seed_batch', 'treated_qty', 'treated_unit') as $key) {
					$line[$key] = isset($target[$key]) ? $target[$key] : '';
				}
				$line['target_type'] = isset($target['type']) ? $target['type'] : '';
				$line['product_type'] = isset($product['type']) ? $product['type'] : '';
				$line['product_ref'] = isset($product['ref']) ? $product['ref'] : '';
				$line['product_label'] = isset($product['label']) ? $product['label'] : '';
				$line['n_applied'] = isset($product['n_applied_kg_ha']) ? $product['n_applied_kg_ha'] : '';
				$line['cu_applied'] = isset($product['cu_applied_kg_ha']) ? $product['cu_applied_kg_ha'] : '';
				foreach (array('reg_number', 'fert_type', 'fert_group', 'n_content', 'p2o5_content', 'k2o_content', 'cu_content', 'efficiency', 'dry_matter', 'density', 'adversity_eppo', 'adversity_label', 'dose', 'dose_unit', 'dose_unit_label', 'qty_total', 'qty_unit', 'qty_agea', 'qty_agea_unit', 'qty_agea_basis', 'batch') as $key) {
					$line[$key] = isset($product[$key]) ? $product[$key] : '';
				}
				$ordered = array();
				foreach (self::$csvColumns as $column) {
					$ordered[$column] = isset($line[$column]) ? (string) $line[$column] : '';
				}
				$lines[] = $ordered;
			}
		}
		return $lines;
	}

	/**
	 * Register in CSV (UTF-8 with BOM, separator ";")
	 *
	 * @param	DoliTraceTreatment[]	$records	Records
	 * @return	string							CSV
	 */
	public function toCsv($records)
	{
		$out = fopen('php://temp', 'r+');
		fwrite($out, "\xEF\xBB\xBF");
		fputcsv($out, self::$csvColumns, ';', '"', '\\');
		foreach ($records as $record) {
			foreach ($this->getCsvLines($record) as $line) {
				fputcsv($out, array_values($line), ';', '"', '\\');
			}
		}
		rewind($out);
		$csv = stream_get_contents($out);
		fclose($out);
		return $csv;
	}

	/**
	 * Register in PDF: one table per farm, one line per product x area
	 *
	 * @param	DoliTraceTreatment[]	$records		Records
	 * @param	array<string,string>	$filters		Filters of the export (farm, period)
	 * @param	Translate				$outputlangs	Language
	 * @return	string									PDF
	 */
	public function toPdf($records, $filters, Translate $outputlangs)
	{
		global $mysoc;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
		$outputlangs->loadLangs(array('main', 'productbatch', 'dolitrace@dolitrace'));

		$format = pdf_getFormat($outputlangs);
		$pdf = pdf_getInstance(array($format['width'], $format['height']), 'mm', 'L');
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetTitle($outputlangs->convToOutputCharset($outputlangs->transnoentities('TreatmentRegister')));
		$pdf->SetAuthor($outputlangs->convToOutputCharset($mysoc->name));
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(true);
		$pdf->SetMargins(8, 8, 8);
		$pdf->SetAutoPageBreak(true, 12);
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 7);

		$byfarm = array();
		foreach ($records as $record) {
			$byfarm[(int) $record->fk_soc][] = $record;
		}
		if (empty($byfarm)) {
			$byfarm[0] = array();
		}
		// The PDF is read by people: dates and numbers in the format of the language, units in lower case
		$day = function ($iso) use ($outputlangs) {
			$parts = explode('-', (string) $iso);
			return count($parts) == 3 ? dol_print_date(dol_mktime(12, 0, 0, (int) $parts[1], (int) $parts[2], (int) $parts[0]), 'day', false, $outputlangs) : '';
		};
		$number = function ($value) use ($outputlangs) {
			return ($value === '' || $value === null) ? '' : price((float) $value, 0, $outputlangs, 0, 0, -1);
		};
		$unitlabels = array('HA' => 'ha', 'M2' => 'm²', 'M3' => 'm³', 'KG' => 'kg', 'T' => 't', 'KSEED' => $outputlangs->transnoentitiesnoconv('TreatmentThousandSeeds'));
		$period = trim($day($filters['date_from'] ?? '').' - '.$day($filters['date_to'] ?? ''), ' -');

		foreach ($byfarm as $records) {
			$pdf->AddPage();
			$first = count($records) ? $this->getRecordData($records[0]) : null;
			$farm = $first ? $first['farm'] : null;
			if (!$farm && !empty($filters['farm_id']) && (int) $filters['farm_id'] > 0) {
				// No record in the period: the header still says which farm
				$soc = $this->lookup('societe', (int) $filters['farm_id'], 'nom, idprof4');
				$farm = $soc ? array('name' => $soc->nom, 'cuaa' => (string) $soc->idprof4) : null;
			}
			$html = '<h3>'.dol_escape_htmltag($outputlangs->transnoentities('TreatmentRegister')).'</h3>';
			$html .= '<p>';
			if ($farm) {
				$html .= '<b>'.dol_escape_htmltag($farm['name']).'</b>'.($farm['cuaa'] ? ' - CUAA '.dol_escape_htmltag($farm['cuaa']) : '').'<br>';
			}
			$html .= ($period ? dol_escape_htmltag($outputlangs->transnoentities('Period').': '.$period).' - ' : '');
			$html .= dol_escape_htmltag($outputlangs->transnoentities('TreatmentPdfGenerated', dol_print_date(dol_now(), 'dayhour', 'tzuser', $outputlangs))).'</p>';

			$columns = array(
				array('Ref', 9), array('Date', 8), array('TreatmentCrop', 15), array('TreatmentTreatedQty', 7),
				array('TreatmentPdfProduct', 16), array('TreatmentAdversity', 14), array('TreatmentDose', 8), array('TreatmentQtyTotal', 7),
				array('TreatmentOperator', 10), array('Status', 6),
			);
			$html .= '<table border="0.3" cellpadding="2"><thead><tr style="background-color:#e8e8e8; font-weight:bold;">';
			foreach ($columns as $column) {
				$html .= '<th width="'.$column[1].'%">'.dol_escape_htmltag($outputlangs->transnoentities($column[0])).'</th>';
			}
			$html .= '</tr></thead><tbody>';
			if (empty($records)) {
				$html .= '<tr><td width="100%">'.dol_escape_htmltag($outputlangs->transnoentities('None')).'</td></tr>';
			}
			foreach ($records as $record) {
				$data = $this->getRecordData($record);
				$targets = count($data['targets']) ? $data['targets'] : array(array());
				$products = count($data['products']) ? $data['products'] : array(array());
				$statuslabel = $outputlangs->transnoentities($record->fields['status']['arrayofkeyval'][$record->status] ?? '');
				if ($record->late) {
					$statuslabel .= ' - '.$outputlangs->transnoentities('TreatmentLate');
				}
				if ($record->status == DoliTraceTreatment::STATUS_CANCELED && $record->cancel_reason) {
					$statuslabel .= ': '.$record->cancel_reason;
				}
				foreach ($targets as $target) {
					foreach ($products as $product) {
						$area = '';
						if (!empty($target)) {
							if ($target['type'] == 'plot') {
								$area = $target['crop_label'].($target['crop_eppo'] ? ' ('.$target['crop_eppo'].')' : '').' - '.$target['location_ref'].($target['bbch'] !== '' ? ' - BBCH '.$target['bbch'] : '');
								// Organic status of the plot at the date
								if (preg_match('/^BIO_CONV_([0-9])$/', $target['bio_status'], $reg)) {
									$area .= ' - '.$outputlangs->transnoentities('TreatmentBioConvShort', $reg[1]);
								} elseif (in_array($target['bio_status'], array('BIO_FULL', 'BIODYNAMIC'))) {
									$area .= ' - '.$outputlangs->transnoentities($target['bio_status'] == 'BIODYNAMIC' ? 'TreatmentBiodynamicShort' : 'TreatmentBioShort');
								}
							} elseif ($target['type'] == 'building') {
								$area = $target['location_ref'];
							} else {
								$area = $target['seed_batch'];
							}
						}
						$cells = array(
							$data['ref'].($data['version'] > 1 ? ' (v'.$data['version'].')' : ''),
							dol_print_date($record->date_start, 'dayhour', 'tzuser', $outputlangs),
							$area,
							!empty($target) ? $number($target['treated_qty']).' '.($unitlabels[$target['treated_unit']] ?? $target['treated_unit']) : '',
							!empty($product) ? $product['label'].($product['reg_number'] ? ' - '.$product['reg_number'] : '').($product['batch'] ? ' - '.$outputlangs->transnoentities('Batch').' '.$product['batch'] : '').($product['cu_applied_kg_ha'] !== '' ? ' - '.$outputlangs->transnoentities('TreatmentCuAppliedShort', $number($product['cu_applied_kg_ha'])) : '') : '',
							(!empty($product) ? ($product['adversity_label'] ? $product['adversity_label'].' ('.$product['adversity_eppo'].')' : ($product['n_content'] !== '' ? 'N-P-K '.$number((float) $product['n_content']).'-'.$number((float) $product['p2o5_content']).'-'.$number((float) $product['k2o_content']) : '')) : '')
								.($data['justification'] ? ' - '.$outputlangs->transnoentities('TreatmentJustificationShort').': '.trim($data['justification']['label'].' '.$data['justification']['ref']) : ''),
							!empty($product) ? $number($product['dose']).' '.$product['dose_unit_label'] : '',
							!empty($product) && $product['qty_total'] !== '' ? $number($product['qty_total']).' '.$product['qty_unit'] : '',
							$data['operator']['name'].($data['operator']['license'] ? ' - '.$data['operator']['license'] : '').($data['contractor'] ? ' / '.$data['contractor']['name'] : ''),
							$statuslabel,
						);
						$html .= '<tr nobr="true">';
						foreach ($cells as $i => $cell) {
							$html .= '<td width="'.$columns[$i][1].'%">'.dol_escape_htmltag((string) $cell).'</td>';
						}
						$html .= '</tr>';
					}
				}
			}
			$html .= '</tbody></table>';
			$pdf->writeHTML($html, true, false, true, false, '');
		}
		return $pdf->Output('', 'S');
	}
}
