<?php
/* Copyright (C) 2026      SuperAdmin
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
	const FORMAT_VERSION = '1.2';

	/**
	 * @var string[]	Columns of the CSV, in their order
	 */
	public static $csvColumns = array(
		'record', 'ref', 'version', 'status', 'kind', 'technical_type', 'farm_cuaa', 'farm_name',
		'date_start', 'date_end', 'reason', 'operator_name', 'operator_license', 'operator_license_end',
		'contractor_name', 'contractor_vat', 'machine', 'machine_check_end', 'machine_check_date', 'machine_cert_ref', 'water_volume', 'weather', 'application_method', 'late',
		'target_type', 'plot_ref', 'location_ref', 'pcg_ref', 'zone_zvn', 'crop_eppo', 'crop_label', 'bbch', 'building_ref', 'seed_batch', 'treated_qty', 'treated_unit',
		'product_type', 'product_ref', 'product_label', 'reg_number', 'fert_type', 'fert_group', 'n_content', 'p2o5_content', 'k2o_content',
		'adversity_eppo', 'adversity_label', 'dose', 'dose_unit', 'dose_unit_label', 'qty_total', 'qty_unit', 'n_applied', 'batch',
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
		$method = isset($content['application_method']) ? $this->lookupCode('dolitrace_c_application_method', $content['application_method'], 'label, agea_value') : null;
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
			$building = $this->lookup('dolifarm_building', $target->fk_building, 'ref');
			$cropplan = $this->lookup('dolitrace_cropplan', $target->fk_cropplan, 'pcg_ref');
			$data['targets'][] = array(
				'type' => array(1 => 'plot', 2 => 'building', 3 => 'seeds')[(int) $target->target_type] ?? (string) $target->target_type,
				'plot_ref' => $plot ? $plot->ref : '',
				'location_ref' => $content['targets'][$i]['location_ref'],
				// Area of the graphic crop plan of the SIAN, from the crop plan (not part of the content of the record)
				'pcg_ref' => $cropplan ? (string) $cropplan->pcg_ref : '',
				'zone_zvn' => $content['targets'][$i]['zone_zvn'],
				'crop_eppo' => $content['targets'][$i]['crop_eppo'],
				'crop_label' => $content['targets'][$i]['crop_label'],
				'bbch' => $content['targets'][$i]['bbch'],
				'bbch_stage_agea' => $this->bbchStageAgea($content['targets'][$i]['bbch']),
				'building_ref' => $building ? $building->ref : '',
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
				'adversity_eppo' => $content['products'][$i]['adversity_code'],
				'adversity_label' => $content['products'][$i]['adversity_label'],
				'dose' => $content['products'][$i]['dose'],
				'dose_unit' => $unit ? $unit->code : '',
				'dose_unit_label' => $content['products'][$i]['dose_unit_label'],
				'qty_total' => $content['products'][$i]['qty_total'],
				'qty_unit' => $content['products'][$i]['qty_unit'],
				'n_applied_kg_ha' => $content['products'][$i]['n_applied'],
				'batch' => $content['products'][$i]['batch'],
			);
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
	 * @return	string							JSON
	 */
	public function toJson($records, $filters, $operations = array())
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
		);
		foreach ($records as $record) {
			$data['records'][] = $this->getRecordData($record);
		}
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
		$sql .= " o.irrigation_method, o.water_volume, o.irrigated_area, o.fertigation, o.fk_treatment,";
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
			'content_hash' => $data['content_hash'],
		);
		$targets = count($data['targets']) ? $data['targets'] : array(array());
		$products = count($data['products']) ? $data['products'] : array(array());
		$lines = array();
		foreach ($targets as $target) {
			foreach ($products as $product) {
				$line = $head;
				foreach (array('plot_ref', 'location_ref', 'pcg_ref', 'zone_zvn', 'crop_eppo', 'crop_label', 'bbch', 'building_ref', 'seed_batch', 'treated_qty', 'treated_unit') as $key) {
					$line[$key] = isset($target[$key]) ? $target[$key] : '';
				}
				$line['target_type'] = isset($target['type']) ? $target['type'] : '';
				$line['product_type'] = isset($product['type']) ? $product['type'] : '';
				$line['product_ref'] = isset($product['ref']) ? $product['ref'] : '';
				$line['product_label'] = isset($product['label']) ? $product['label'] : '';
				$line['n_applied'] = isset($product['n_applied_kg_ha']) ? $product['n_applied_kg_ha'] : '';
				foreach (array('reg_number', 'fert_type', 'fert_group', 'n_content', 'p2o5_content', 'k2o_content', 'adversity_eppo', 'adversity_label', 'dose', 'dose_unit', 'dose_unit_label', 'qty_total', 'qty_unit', 'batch') as $key) {
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
		$unitlabels = array('HA' => 'ha', 'M2' => 'm²', 'M3' => 'm³', 'KG' => 'kg', 'T' => 't');
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
							!empty($product) ? $product['label'].($product['reg_number'] ? ' - '.$product['reg_number'] : '').($product['batch'] ? ' - '.$outputlangs->transnoentities('Batch').' '.$product['batch'] : '') : '',
							!empty($product) ? ($product['adversity_label'] ? $product['adversity_label'].' ('.$product['adversity_eppo'].')' : ($product['n_content'] !== '' ? 'N-P-K '.$number((float) $product['n_content']).'-'.$number((float) $product['p2o5_content']).'-'.$number((float) $product['k2o_content']) : '')) : '',
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
