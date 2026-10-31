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
dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');

/**
 * Export of the register
 */
class DoliTraceTreatmentExport
{
	const FORMAT = 'dolifarm-register';
	const FORMAT_VERSION = '1.0';

	/**
	 * @var string[]	Columns of the CSV, in their order
	 */
	public static $csvColumns = array(
		'record', 'ref', 'version', 'status', 'kind', 'technical_type', 'farm_cuaa', 'farm_name',
		'date_start', 'date_end', 'reason', 'operator_name', 'operator_license', 'operator_license_end',
		'contractor_name', 'contractor_vat', 'machine', 'machine_check_end', 'water_volume', 'weather', 'late',
		'target_type', 'plot_ref', 'location_ref', 'zone_zvn', 'crop_eppo', 'crop_label', 'bbch', 'building_ref', 'seed_batch', 'treated_qty', 'treated_unit',
		'product_type', 'product_ref', 'product_label', 'reg_number', 'n_content', 'p2o5_content', 'k2o_content',
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
			),
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
			$data['targets'][] = array(
				'type' => array(1 => 'plot', 2 => 'building', 3 => 'seeds')[(int) $target->target_type] ?? (string) $target->target_type,
				'plot_ref' => $plot ? $plot->ref : '',
				'location_ref' => $content['targets'][$i]['location_ref'],
				'zone_zvn' => $content['targets'][$i]['zone_zvn'],
				'crop_eppo' => $content['targets'][$i]['crop_eppo'],
				'crop_label' => $content['targets'][$i]['crop_label'],
				'bbch' => $content['targets'][$i]['bbch'],
				'building_ref' => $building ? $building->ref : '',
				'seed_batch' => $content['targets'][$i]['seed_batch'],
				'treated_qty' => $content['targets'][$i]['treated_qty'],
				'treated_unit' => $content['targets'][$i]['treated_unit'],
			);
		}
		foreach ($record->products as $i => $product) {
			$agrodrug = $this->lookup('dolifarm_agrodrug', $product->fk_agrodrug, 'ref');
			$fertilizer = $this->lookup('dolifarm_fertilizer', $product->fk_fertilizer, 'ref');
			$unit = $this->lookup('dolitrace_c_dose_unit', $product->fk_dose_unit, 'code');
			$data['products'][] = array(
				'type' => $product->fk_fertilizer > 0 ? 'fertilizer' : 'plant_protection_product',
				'ref' => $agrodrug ? $agrodrug->ref : ($fertilizer ? $fertilizer->ref : ''),
				'label' => $content['products'][$i]['product_label'],
				'reg_number' => $content['products'][$i]['reg_number'],
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
	public function toJson($records, $filters)
	{
		$data = array(
			'format' => self::FORMAT,
			'format_version' => self::FORMAT_VERSION,
			'generated_at' => dol_print_date(dol_now(), '%Y-%m-%dT%H:%M:%SZ', 'gmt'),
			'regulation' => 'Reg. (EU) 2023/564',
			'filters' => $filters,
			'records' => array(),
		);
		foreach ($records as $record) {
			$data['records'][] = $this->getRecordData($record);
		}
		return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
			'water_volume' => $data['water_volume_l_ha'], 'weather' => $data['weather'], 'late' => $data['late'],
			'content_hash' => $data['content_hash'],
		);
		$targets = count($data['targets']) ? $data['targets'] : array(array());
		$products = count($data['products']) ? $data['products'] : array(array());
		$lines = array();
		foreach ($targets as $target) {
			foreach ($products as $product) {
				$line = $head;
				foreach (array('plot_ref', 'location_ref', 'zone_zvn', 'crop_eppo', 'crop_label', 'bbch', 'building_ref', 'seed_batch', 'treated_qty', 'treated_unit') as $key) {
					$line[$key] = isset($target[$key]) ? $target[$key] : '';
				}
				$line['target_type'] = isset($target['type']) ? $target['type'] : '';
				$line['product_type'] = isset($product['type']) ? $product['type'] : '';
				$line['product_ref'] = isset($product['ref']) ? $product['ref'] : '';
				$line['product_label'] = isset($product['label']) ? $product['label'] : '';
				$line['n_applied'] = isset($product['n_applied_kg_ha']) ? $product['n_applied_kg_ha'] : '';
				foreach (array('reg_number', 'n_content', 'p2o5_content', 'k2o_content', 'adversity_eppo', 'adversity_label', 'dose', 'dose_unit', 'dose_unit_label', 'qty_total', 'qty_unit', 'batch') as $key) {
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
		$period = trim(($filters['date_from'] ?? '').' - '.($filters['date_to'] ?? ''), ' -');

		foreach ($byfarm as $records) {
			$pdf->AddPage();
			$first = count($records) ? $this->getRecordData($records[0]) : null;
			$html = '<h3>'.dol_escape_htmltag($outputlangs->transnoentities('TreatmentRegister')).'</h3>';
			$html .= '<p>';
			if ($first) {
				$html .= '<b>'.dol_escape_htmltag($first['farm']['name']).'</b>'.($first['farm']['cuaa'] ? ' - CUAA '.dol_escape_htmltag($first['farm']['cuaa']) : '').'<br>';
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
							$data['date_start'],
							$area,
							!empty($target) ? $target['treated_qty'].' '.$target['treated_unit'] : '',
							!empty($product) ? $product['label'].($product['reg_number'] ? ' - '.$product['reg_number'] : '').($product['batch'] ? ' - '.$outputlangs->transnoentities('Batch').' '.$product['batch'] : '') : '',
							!empty($product) ? ($product['adversity_label'] ? $product['adversity_label'].' ('.$product['adversity_eppo'].')' : ($product['n_content'] !== '' ? 'N-P-K '.$product['n_content'].'-'.$product['p2o5_content'].'-'.$product['k2o_content'] : '')) : '',
							!empty($product) ? $product['dose'].' '.$product['dose_unit_label'] : '',
							!empty($product) && $product['qty_total'] !== '' ? $product['qty_total'].' '.$product['qty_unit'] : '',
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
