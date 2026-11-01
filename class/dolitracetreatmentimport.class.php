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
 * \file        class/dolitracetreatmentimport.class.php
 * \ingroup     dolitrace
 * \brief       Import of records of the register from a CSV with the columns of the export
 *
 * The lines with the same value in the column "record" make one record. Records are always created as drafts:
 * the user checks them and validates them from the card. Areas and products are matched with the data of the farm
 * (crop plans in progress at the date, buildings, plant protection products, fertilizers, dictionaries); copies
 * (labels, registration numbers, EPPO codes) are taken again from the data, the CSV only chooses them.
 */

dol_include_once('/dolitrace/class/dolitracetreatment.class.php');
dol_include_once('/dolitrace/class/dolitracetreatmentexport.class.php');
dol_include_once('/dolifarm/lib/dolifarm_farm.lib.php');

/**
 * Class to import records of the register from CSV
 */
class DoliTraceTreatmentImport
{
	/**
	 * @var DoliDB	Database handler
	 */
	public $db;

	/**
	 * @var string	Error
	 */
	public $error = '';

	/**
	 * @var string[]	Errors
	 */
	public $errors = array();

	/**
	 * @var array<string,array<int,array<string,string>>>	Lines of the file by record
	 */
	public $records = array();

	/**
	 * @var array<int,array{record:string,line:int,id:int,ref:string,errors:string[],warnings:string[]}>	Result by record
	 */
	public $results = array();

	/**
	 * @var string	Import key set on the created records
	 */
	public $import_key = '';

	/**
	 * @var array<string,string>	Areas of the records created in this run (kind, start and area => record of the file)
	 */
	private $imported = array();

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
	 * Template of the file: the header of the export and one example line
	 *
	 * @return	string	CSV
	 */
	public static function getTemplate()
	{
		$example = array(
			'record' => '1', 'kind' => 'plant_protection', 'technical_type' => 'surfaces', 'date_start' => '2026-04-10 08:30',
			'operator_license' => 'PAN-0000', 'machine' => 'MAC-001', 'water_volume' => '500', 'weather' => 'Sereno', 'application_method' => 'PPP_ATOMIZER',
			'target_type' => 'plot', 'plot_ref' => 'APPEZ-0001', 'crop_eppo' => 'TRZDU', 'bbch' => '31', 'treated_qty' => '1.5', 'treated_unit' => 'HA',
			'product_type' => 'plant_protection_product', 'reg_number' => '12345', 'adversity_eppo' => 'PLASVI', 'dose' => '2.5', 'dose_unit' => 'KG_HA', 'batch' => 'L2026-01',
		);
		$out = fopen('php://temp', 'r+');
		fwrite($out, "\xEF\xBB\xBF");
		fputcsv($out, DoliTraceTreatmentExport::$csvColumns, ';', '"', '\\');
		$line = array();
		foreach (DoliTraceTreatmentExport::$csvColumns as $column) {
			$line[] = isset($example[$column]) ? $example[$column] : '';
		}
		fputcsv($out, $line, ';', '"', '\\');
		rewind($out);
		$csv = stream_get_contents($out);
		fclose($out);
		return $csv;
	}

	/**
	 * Read the file: separator ";" or ",", UTF-8 with or without BOM, header with the names of the columns
	 *
	 * @param	string	$content	Content of the file
	 * @return	int					Number of records, <0 if KO
	 */
	public function parse($content)
	{
		global $langs;

		$this->records = array();
		if (substr($content, 0, 3) == "\xEF\xBB\xBF") {
			$content = substr($content, 3);
		}
		if (!utf8_check($content)) {
			$content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
		}
		$firstline = strtok($content, "\r\n");
		$separator = (substr_count((string) $firstline, ';') >= substr_count((string) $firstline, ',')) ? ';' : ',';

		$in = fopen('php://temp', 'r+');
		fwrite($in, $content);
		rewind($in);
		$header = fgetcsv($in, 0, $separator, '"', '\\');
		$header = is_array($header) ? array_map(function ($name) {
			return strtolower(trim((string) $name));
		}, $header) : array();
		$missing = array_diff(array('record', 'kind', 'technical_type', 'date_start'), $header);
		if (count($missing)) {
			fclose($in);
			$this->error = $langs->transnoentities('ErrorTreatmentImportColumns', implode(', ', $missing));
			return -1;
		}
		$number = 1;
		while (($values = fgetcsv($in, 0, $separator, '"', '\\')) !== false) {
			$number++;
			if ($values === array(null) || trim(implode('', $values)) === '') {
				continue;
			}
			$line = array('_line' => (string) $number);
			foreach ($header as $i => $name) {
				$line[$name] = isset($values[$i]) ? trim((string) $values[$i]) : '';
			}
			$key = $line['record'] !== '' ? $line['record'] : '#'.$number;
			$this->records[$key][] = $line;
		}
		fclose($in);
		return count($this->records);
	}

	/**
	 * Create the records read by parse() as drafts of a farm
	 *
	 * With $dryrun all is checked and created inside a transaction that is rolled back: the results are the same as a real import.
	 *
	 * @param	User	$user		User that imports
	 * @param	int		$socid		Third party of the farm
	 * @param	bool	$dryrun		Check only
	 * @return	int					Number of records created (or that would be created), <0 if KO
	 */
	public function import(User $user, $socid, $dryrun)
	{
		global $langs;

		$langs->loadLangs(array('dolitrace@dolitrace', 'dolifarm@dolifarm'));
		$this->results = array();
		$this->imported = array();
		$this->import_key = dol_print_date(dol_now(), '%Y%m%d%H%M%S');
		$farm = dolitraceTreatmentFetchRows($this->db, "SELECT rowid, nom, idprof4 FROM ".$this->db->prefix()."societe WHERE rowid = ".((int) $socid)." AND entity IN (".getEntity('societe').")");
		if (!isset($farm[$socid])) {
			$this->error = $langs->transnoentities('ErrorFieldRequired', $langs->transnoentitiesnoconv('TreatmentFarm'));
			return -1;
		}
		$farm = $farm[$socid];

		if ($dryrun) {
			$this->db->begin();
		}
		$nb = 0;
		foreach ($this->records as $key => $lines) {
			$result = array('record' => (string) $key, 'line' => (int) $lines[0]['_line'], 'id' => 0, 'ref' => '', 'errors' => array(), 'warnings' => array());
			$this->db->begin();
			$object = $this->importRecord($user, $farm, $lines, $result);
			if ($object && empty($result['errors'])) {
				$this->db->commit();
				$result['id'] = $object->id;
				$result['ref'] = $object->ref;
				$nb++;
			} else {
				$this->db->rollback();
			}
			$this->results[] = $result;
		}
		if ($dryrun) {
			$this->db->rollback();
		}
		dol_syslog(__METHOD__.' socid='.$socid.' dryrun='.($dryrun ? 1 : 0).' records='.count($this->records).' created='.$nb, LOG_INFO);
		return $nb;
	}

	/**
	 * Create one record with its areas and products
	 *
	 * @param	User						$user		User
	 * @param	stdClass					$farm		Third party of the farm
	 * @param	array<int,array<string,string>>	$lines	Lines of the record
	 * @param	array<string,mixed>			$result		Result, errors and warnings are added
	 * @return	DoliTraceTreatment|null					Record created, null if not
	 */
	private function importRecord(User $user, $farm, $lines, &$result)
	{
		global $langs;

		$head = $lines[0];
		$value = function ($line, $name) {
			return isset($line[$name]) ? $line[$name] : '';
		};

		if (!in_array($value($head, 'status'), array('', 'draft', 'validated'))) {
			$result['errors'][] = $langs->transnoentities('ErrorTreatmentImportStatus', $value($head, 'status'));
			return null;
		}
		if ($value($head, 'farm_cuaa') !== '' && (string) $farm->idprof4 !== '' && strtoupper($value($head, 'farm_cuaa')) != strtoupper((string) $farm->idprof4)) {
			$result['errors'][] = $langs->transnoentities('ErrorTreatmentImportFarm', $value($head, 'farm_cuaa'), $farm->nom);
			return null;
		}

		$object = new DoliTraceTreatment($this->db);
		$object->ref = '(PROV)';
		$object->fk_soc = (int) $farm->rowid;
		$dossierid = dolifarmGetFarmDossier($this->db, $object->fk_soc);
		$object->fk_dossier = ($dossierid > 0 ? $dossierid : null);
		$kinds = array('plant_protection' => DoliTraceTreatment::KIND_PLANTPROTECTION, 'fertilization' => DoliTraceTreatment::KIND_FERTILIZATION, '1' => DoliTraceTreatment::KIND_PLANTPROTECTION, '2' => DoliTraceTreatment::KIND_FERTILIZATION);
		$types = array('surfaces' => 1, 'closed_spaces' => 2, 'seeds' => 3, '1' => 1, '2' => 2, '3' => 3);
		$object->kind = isset($kinds[$value($head, 'kind')]) ? $kinds[$value($head, 'kind')] : 0;
		$object->technical_type = isset($types[$value($head, 'technical_type')]) ? $types[$value($head, 'technical_type')] : 0;
		if (!$object->kind) {
			$result['errors'][] = $langs->transnoentities('ErrorTreatmentImportValue', 'kind', $value($head, 'kind'));
		}
		if (!$object->technical_type) {
			$result['errors'][] = $langs->transnoentities('ErrorTreatmentImportValue', 'technical_type', $value($head, 'technical_type'));
		}
		$object->date_start = $this->parseDate($value($head, 'date_start'));
		if (!$object->date_start) {
			$result['errors'][] = $langs->transnoentities('ErrorTreatmentImportValue', 'date_start', $value($head, 'date_start'));
		}
		$object->date_end = $this->parseDate($value($head, 'date_end'));
		if ($value($head, 'date_end') !== '' && !$object->date_end) {
			$result['errors'][] = $langs->transnoentities('ErrorTreatmentImportValue', 'date_end', $value($head, 'date_end'));
		}
		if (count($result['errors'])) {
			return null;
		}
		$object->reason = $value($head, 'reason');
		$object->weather = $value($head, 'weather');
		$object->water_volume = $this->parseNumber($value($head, 'water_volume'));
		if ($object->water_volume === false) {
			$result['errors'][] = $langs->transnoentities('ErrorTreatmentImportValue', 'water_volume', $value($head, 'water_volume'));
			$object->water_volume = null;
		}
		if ($value($head, 'application_method') !== '') {
			$rows = dolitraceTreatmentFetchRows($this->db, "SELECT rowid, code FROM ".$this->db->prefix()."dolitrace_c_application_method WHERE active = 1 AND kind = ".((int) $object->kind)." AND code = '".$this->db->escape($value($head, 'application_method'))."'");
			if (count($rows) == 1) {
				$object->application_method = reset($rows)->code;
			} else {
				$result['warnings'][] = $langs->transnoentities('WarningTreatmentImportApplicationMethod', $value($head, 'application_method'));
			}
		}

		// Operator, machine and contractor: if not found the draft is created without them and the user completes it
		if ($value($head, 'operator_license') !== '' || $value($head, 'operator_name') !== '') {
			$sql = "SELECT rowid, label FROM ".$this->db->prefix()."dolifarm_operator WHERE entity IN (".getEntity('dolifarmoperator').") AND fk_soc = ".((int) $object->fk_soc);
			if ($value($head, 'operator_license') !== '') {
				$sql .= " AND license_number = '".$this->db->escape($value($head, 'operator_license'))."'";
			} else {
				$sql .= " AND label = '".$this->db->escape($value($head, 'operator_name'))."'";
			}
			$rows = dolitraceTreatmentFetchRows($this->db, $sql);
			if (count($rows) == 1) {
				$object->fk_operator = (int) key($rows);
			} else {
				$result['warnings'][] = $langs->transnoentities('WarningTreatmentImportOperator', trim($value($head, 'operator_name').' '.$value($head, 'operator_license')));
			}
		}
		if ($value($head, 'machine') !== '') {
			$rows = dolitraceTreatmentFetchRows($this->db, "SELECT rowid FROM ".$this->db->prefix()."dolifarm_machine WHERE entity IN (".getEntity('dolifarmmachine').") AND fk_soc = ".((int) $object->fk_soc)." AND ref = '".$this->db->escape($value($head, 'machine'))."'");
			if (count($rows) == 1) {
				$object->fk_machine = (int) key($rows);
			} else {
				$result['warnings'][] = $langs->transnoentities('WarningTreatmentImportMachine', $value($head, 'machine'));
			}
		}
		if ($value($head, 'contractor_vat') !== '' || $value($head, 'contractor_name') !== '') {
			$sql = "SELECT rowid FROM ".$this->db->prefix()."societe WHERE entity IN (".getEntity('societe').")";
			if ($value($head, 'contractor_vat') !== '') {
				$sql .= " AND tva_intra = '".$this->db->escape($value($head, 'contractor_vat'))."'";
			} else {
				$sql .= " AND nom = '".$this->db->escape($value($head, 'contractor_name'))."'";
			}
			$rows = dolitraceTreatmentFetchRows($this->db, $sql);
			if (count($rows) == 1) {
				$object->fk_soc_contractor = (int) key($rows);
			} else {
				$result['warnings'][] = $langs->transnoentities('WarningTreatmentImportContractor', trim($value($head, 'contractor_name').' '.$value($head, 'contractor_vat')));
			}
		}

		// Areas and products: each line is a product x area, the same area or product is on more lines
		$targets = array();
		$products = array();
		foreach ($lines as $line) {
			$target = array();
			foreach (array('target_type', 'plot_ref', 'location_ref', 'crop_eppo', 'bbch', 'building_ref', 'seed_batch', 'treated_qty', 'treated_unit') as $name) {
				$target[$name] = $value($line, $name);
			}
			if (implode('', $target) !== '') {
				$targets[implode('|', $target)] = $target + array('_line' => $line['_line']);
			}
			$product = array();
			foreach (array('product_type', 'product_ref', 'reg_number', 'adversity_eppo', 'dose', 'dose_unit', 'qty_total', 'batch') as $name) {
				$product[$name] = $value($line, $name);
			}
			if (implode('', $product) !== '') {
				$products[implode('|', $product)] = $product + array('_line' => $line['_line']);
			}
		}
		if (!count($targets) || !count($products)) {
			$result['errors'][] = $langs->transnoentities('ErrorTreatmentNeedsAreaAndProduct');
			return null;
		}

		$targetrows = array();
		$cropplans = dolitraceTreatmentCropPlans($this->db, $object);
		$buildings = null;
		foreach ($targets as $data) {
			$row = $this->buildTarget($object, $data, $cropplans, $buildings, $result);
			if ($row) {
				$targetrows[] = $row;
			}
		}
		$productrows = array();
		$units = dolitraceTreatmentDoseUnits($this->db, (int) $object->kind, (int) $object->technical_type);
		foreach ($products as $data) {
			$row = $this->buildProduct($object, $data, $units, $result);
			if ($row) {
				$productrows[] = $row;
			}
		}
		if (count($result['errors'])) {
			return null;
		}
		$areakeys = $this->checkDuplicate($object, $targetrows, $result);
		if (count($result['errors'])) {
			return null;
		}

		$object->import_key = $this->import_key;
		if ($object->create($user) < 0) {
			$result['errors'][] = $object->error ? $object->error : implode(', ', $object->errors);
			return null;
		}
		foreach (array_merge($targetrows, $productrows) as $i => $row) {
			$row->fk_treatment = $object->id;
			$row->rang = $i + 1;
			if ($row->create($user) < 0) {
				$result['errors'][] = $row->error ? $row->error : implode(', ', $row->errors);
				return null;
			}
		}
		if ($object->fetch($object->id) < 0 || $object->updateAutoQuantities($user) < 0) {
			$result['errors'][] = $object->error ? $object->error : implode(', ', $object->errors);
			return null;
		}
		foreach ($areakeys as $areakey) {
			$this->imported[$areakey] = $result['record'];
		}
		return $object;
	}

	/**
	 * Check that the record is not already in the register or twice in the file: same farm, kind and start on a same area
	 *
	 * Records with the same start on other areas are different treatments (e.g. a date without time).
	 *
	 * @param	DoliTraceTreatment			$object		Record to create
	 * @param	DoliTraceTreatmentTarget[]	$targetrows	Areas of the record
	 * @param	array<string,mixed>			$result		Result, errors are added
	 * @return	string[]							Keys of the areas, to find the duplicates in the rest of the file
	 */
	private function checkDuplicate($object, $targetrows, &$result)
	{
		global $langs;

		$areakeys = array();
		$where = array();
		foreach ($targetrows as $target) {
			if (!empty($target->fk_cropplan)) {
				$area = 'cropplan:'.((int) $target->fk_cropplan);
				$where[] = "tt.fk_cropplan = ".((int) $target->fk_cropplan);
			} elseif (!empty($target->fk_building)) {
				$area = 'building:'.((int) $target->fk_building);
				$where[] = "tt.fk_building = ".((int) $target->fk_building);
			} else {
				$area = 'seeds:'.$target->seed_batch;
				$where[] = "tt.seed_batch = '".$this->db->escape($target->seed_batch)."'";
			}
			$areakey = ((int) $object->kind).'|'.((int) $object->date_start).'|'.$area;
			if (isset($this->imported[$areakey])) {
				$result['errors'][] = $langs->transnoentities('ErrorTreatmentImportDuplicate', $this->imported[$areakey]);
				return array();
			}
			$areakeys[] = $areakey;
		}

		// Records created before this run (those of this run are found above, also in a check)
		$sql = "SELECT DISTINCT t.rowid, t.ref FROM ".$this->db->prefix()."dolitrace_treatment as t";
		$sql .= " INNER JOIN ".$this->db->prefix()."dolitrace_treatment_target as tt ON tt.fk_treatment = t.rowid";
		$sql .= " WHERE t.entity IN (".getEntity('dolitracetreatment').") AND t.fk_soc = ".((int) $object->fk_soc)." AND t.kind = ".((int) $object->kind);
		$sql .= " AND t.date_start = '".$this->db->idate($object->date_start)."' AND t.status IN (0, 1)";
		$sql .= " AND (t.import_key IS NULL OR t.import_key <> '".$this->db->escape($this->import_key)."')";
		$sql .= " AND (".implode(' OR ', $where).")";
		$existing = dolitraceTreatmentFetchRows($this->db, $sql);
		if (count($existing)) {
			$result['errors'][] = $langs->transnoentities('ErrorTreatmentImportExists', reset($existing)->ref);
		}
		return $areakeys;
	}

	/**
	 * Area of a line: crop plan in progress (by plot and crop), building (by ref) or seed batch
	 *
	 * @param	DoliTraceTreatment			$object		Record
	 * @param	array<string,string>		$data		Columns of the area
	 * @param	array<int,stdClass>			$cropplans	Crop plans in progress at the date
	 * @param	array<int,stdClass>|null	$buildings	Buildings of the farm, loaded when needed
	 * @param	array<string,mixed>			$result		Result, errors are added
	 * @return	DoliTraceTreatmentTarget|null			Area, null if not found
	 */
	private function buildTarget($object, $data, $cropplans, &$buildings, &$result)
	{
		global $langs;

		$prefix = $langs->transnoentities('Line').' '.$data['_line'].': ';
		$types = array('plot' => 1, 'building' => 2, 'seeds' => 3, '' => (int) $object->technical_type);
		$target = new DoliTraceTreatmentTarget($this->db);
		$target->target_type = isset($types[$data['target_type']]) ? $types[$data['target_type']] : 0;
		if ($target->target_type != $object->technical_type) {
			$result['errors'][] = $prefix.$langs->transnoentities('ErrorTreatmentTargetType', $data['target_type']);
			return null;
		}
		$target->bbch = $data['bbch'];
		if ($target->bbch !== '' && !preg_match('/^[0-9]{1,3}$/', $target->bbch)) {
			$result['errors'][] = $prefix.$langs->transnoentities('ErrorTreatmentBbch');
			return null;
		}
		$target->treated_qty = $this->parseNumber($data['treated_qty']);
		if ($target->treated_qty === false) {
			$result['errors'][] = $prefix.$langs->transnoentities('ErrorTreatmentImportValue', 'treated_qty', $data['treated_qty']);
			return null;
		}
		$unit = strtoupper($data['treated_unit']);

		if ($target->target_type == DoliTraceTreatmentTarget::TYPE_PLOT) {
			$found = array();
			foreach ($cropplans as $cropplan) {
				$refs = array($cropplan->plot_ref, $cropplan->iacs_parcel, $cropplan->cadastral_reference);
				$byplot = ($data['plot_ref'] !== '' ? $cropplan->plot_ref == $data['plot_ref'] : ($data['location_ref'] !== '' && in_array($data['location_ref'], $refs)));
				if ($byplot && ($data['crop_eppo'] === '' || strtoupper($data['crop_eppo']) == strtoupper((string) $cropplan->eppo_code))) {
					$found[] = $cropplan;
				}
			}
			$label = implode(' ', array_filter(array($data['plot_ref'], $data['location_ref'], $data['crop_eppo']), 'strlen'));
			if (count($found) != 1) {
				$result['errors'][] = $prefix.$langs->transnoentities(count($found) ? 'ErrorTreatmentImportCropPlanMany' : 'ErrorTreatmentCropPlanNotInProgress', $label, dol_print_date($object->date_start, 'day'));
				return null;
			}
			$cropplan = $found[0];
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
			if ($unit == 'M2' && $target->treated_qty !== null) {
				$target->treated_qty = $target->treated_qty / 10000;
			}
			$target->treated_unit = 'HA';
		} elseif ($target->target_type == DoliTraceTreatmentTarget::TYPE_BUILDING) {
			if ($buildings === null) {
				$buildings = dolitraceTreatmentBuildings($this->db, $object->fk_soc);
			}
			foreach ($buildings as $building) {
				if ($building->ref == $data['building_ref'] || ($data['building_ref'] === '' && $building->ref == $data['location_ref'])) {
					$target->fk_building = (int) $building->rowid;
					$target->location_ref = $building->ref;
				}
			}
			if (empty($target->fk_building)) {
				$result['errors'][] = $prefix.$langs->transnoentities('ErrorTreatmentImportNotFound', $langs->transnoentitiesnoconv('TreatmentBuilding'), $data['building_ref'] !== '' ? $data['building_ref'] : $data['location_ref']);
				return null;
			}
			$target->treated_unit = ($unit == 'M3' ? 'M3' : 'M2');
		} else {
			$target->seed_batch = $data['seed_batch'];
			if ($target->seed_batch === '') {
				$result['errors'][] = $prefix.$langs->transnoentities('ErrorFieldRequired', $langs->transnoentitiesnoconv('TreatmentSeedBatch'));
				return null;
			}
			$target->treated_unit = ($unit == 'T' ? 'T' : 'KG');
		}
		if (!($target->treated_qty > 0)) {
			$result['errors'][] = $prefix.$langs->transnoentities('ErrorFieldRequired', $langs->transnoentitiesnoconv('TreatmentTreatedQty'));
			return null;
		}
		return $target;
	}

	/**
	 * Product of a line: plant protection product (by registration number or ref) with its adversity, or fertilizer; unit of dose by code
	 *
	 * @param	DoliTraceTreatment		$object		Record
	 * @param	array<string,string>	$data		Columns of the product
	 * @param	array<int,stdClass>		$units		Units of dose allowed for the record
	 * @param	array<string,mixed>		$result		Result, errors are added
	 * @return	DoliTraceTreatmentProduct|null		Product, null if not found
	 */
	private function buildProduct($object, $data, $units, &$result)
	{
		global $langs;

		$prefix = $langs->transnoentities('Line').' '.$data['_line'].': ';
		$product = new DoliTraceTreatmentProduct($this->db);
		$product->stock_status = DoliTraceTreatmentProduct::STOCK_NONE;
		$product->batch = $data['batch'];
		$label = $data['product_ref'] !== '' ? $data['product_ref'] : $data['reg_number'];
		if ($object->kind == DoliTraceTreatment::KIND_PLANTPROTECTION) {
			$found = null;
			foreach (dolitraceTreatmentAgroDrugs($this->db) as $agrodrug) {
				if (($data['reg_number'] !== '' && $agrodrug->reg_number == $data['reg_number']) || ($data['reg_number'] === '' && $data['product_ref'] !== '' && $agrodrug->ref == $data['product_ref'])) {
					$found = $agrodrug;
					break;
				}
			}
			if (!$found) {
				$result['errors'][] = $prefix.$langs->transnoentities('ErrorTreatmentImportNotFound', $langs->transnoentitiesnoconv('TreatmentAgroDrug'), $label);
				return null;
			}
			$product->fk_agrodrug = (int) $found->rowid;
			$product->fk_product = ($found->fk_product > 0 ? (int) $found->fk_product : null);
			$product->product_label = $found->label;
			$product->reg_number = $found->reg_number;
			$adversity = null;
			foreach (dolitraceTreatmentAdversities($this->db) as $row) {
				if (strtoupper((string) $row->code) == strtoupper($data['adversity_eppo'])) {
					$adversity = $row;
					break;
				}
			}
			if (!$adversity) {
				$result['errors'][] = $prefix.$langs->transnoentities('ErrorTreatmentImportNotFound', $langs->transnoentitiesnoconv('TreatmentAdversity'), $data['adversity_eppo']);
				return null;
			}
			$product->fk_adversity = (int) $adversity->rowid;
			$product->adversity_code = $adversity->code;
			$product->adversity_label = $adversity->label;
		} else {
			$found = null;
			foreach (dolitraceTreatmentFertilizers($this->db) as $fertilizer) {
				if (($data['product_ref'] !== '' && $fertilizer->ref == $data['product_ref']) || ($data['product_ref'] === '' && $data['reg_number'] !== '' && $fertilizer->registration_number == $data['reg_number'])) {
					$found = $fertilizer;
					break;
				}
			}
			if (!$found) {
				$result['errors'][] = $prefix.$langs->transnoentities('ErrorTreatmentImportNotFound', $langs->transnoentitiesnoconv('TreatmentFertilizer'), $label);
				return null;
			}
			$product->fk_fertilizer = (int) $found->rowid;
			$product->fk_product = ($found->fk_product > 0 ? (int) $found->fk_product : null);
			$product->product_label = $found->label;
			$product->reg_number = $found->registration_number;
			$product->n_content = $found->n_content;
			$product->p2o5_content = $found->p2o5_content;
			$product->k2o_content = $found->k2o_content;
		}

		$product->dose = $this->parseNumber($data['dose']);
		if (!($product->dose > 0)) {
			$result['errors'][] = $prefix.$langs->transnoentities('ErrorTreatmentImportValue', 'dose', $data['dose']);
			return null;
		}
		foreach ($units as $unit) {
			if (strtoupper((string) $unit->code) == strtoupper($data['dose_unit'])) {
				$product->fk_dose_unit = (int) $unit->rowid;
				$product->dose_unit_label = $unit->label;
				$product->qty_unit = $unit->qty_unit;
			}
		}
		if (empty($product->fk_dose_unit)) {
			$result['errors'][] = $prefix.$langs->transnoentities('ErrorTreatmentImportValue', 'dose_unit', $data['dose_unit']);
			return null;
		}
		// Total quantity: given in the file, otherwise computed (see DoliTraceTreatment::updateAutoQuantities())
		$qty = $this->parseNumber($data['qty_total']);
		if ($qty === false) {
			$result['errors'][] = $prefix.$langs->transnoentities('ErrorTreatmentImportValue', 'qty_total', $data['qty_total']);
			return null;
		}
		$product->qty_auto = ($qty === null ? 1 : 0);
		$product->qty_total = $qty;
		return $product;
	}

	/**
	 * Number of the file: decimal point or comma, thousands separator removed
	 *
	 * @param	string		$value	Value
	 * @return	float|null|false	Number, null if empty, false if not a number
	 */
	private function parseNumber($value)
	{
		$value = str_replace(' ', '', $value);
		if ($value === '') {
			return null;
		}
		if (strpos($value, ',') !== false) {
			$value = str_replace(',', '.', str_replace('.', '', $value));
		}
		return is_numeric($value) ? (float) $value : false;
	}

	/**
	 * Date of the file, in the time zone of the user: YYYY-MM-DD or DD/MM/YYYY, with optional HH:MM
	 *
	 * @param	string		$value	Value
	 * @return	int|''				Timestamp, '' if empty or not valid
	 */
	private function parseDate($value)
	{
		if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T](\d{1,2}):(\d{2})(?::\d{2})?)?$/', $value, $reg)) {
			list($year, $month, $day) = array($reg[1], $reg[2], $reg[3]);
		} elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})(?: (\d{1,2}):(\d{2})(?::\d{2})?)?$/', $value, $reg)) {
			list($day, $month, $year) = array($reg[1], $reg[2], $reg[3]);
		} else {
			return '';
		}
		if (!checkdate((int) $month, (int) $day, (int) $year)) {
			return '';
		}
		return dol_mktime(isset($reg[4]) ? (int) $reg[4] : 0, isset($reg[5]) ? (int) $reg[5] : 0, 0, (int) $month, (int) $day, (int) $year, 'tzuser');
	}
}
