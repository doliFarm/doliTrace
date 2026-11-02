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
 * \file        class/dolitracetrace.class.php
 * \ingroup     dolitrace
 * \brief       Traceability of a harvested product (Reg. EC 178/2002 art. 18): from the UUID of the harvest, its lot or its
 *              reference, the history upstream (farm, plot, crop plan, seeds, treatments, operations) and downstream
 *              (stock, movements, customers)
 *
 * Everything is read only.
 */

dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_bio.lib.php');
dol_include_once('/dolitrace/class/dolitracebioregister.class.php');

/**
 * Traceability of a harvested product
 */
class DoliTraceTrace
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Last error
	 */
	public $error = '';

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
	 * New random UUID (version 4): it cannot be guessed, so it can be printed in a public QR code
	 *
	 * @return	string	UUID, 36 characters
	 */
	public static function newUuid()
	{
		$bytes = random_bytes(16);
		$bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
		$bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
		$hex = bin2hex($bytes);
		return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
	}

	/**
	 * Short code of a UUID: its first 8 characters, to write by hand or read on the phone (random in a UUID v4, and
	 * also different between the UUID v1 made by MySQL, whose last characters are those of the server)
	 *
	 * @param	string	$uuid	UUID
	 * @return	string
	 */
	public static function shortCode($uuid)
	{
		return strtoupper(substr((string) $uuid, 0, 8));
	}

	/**
	 * Harvests matching a search: UUID, short code (first 8 characters of the UUID), reference of the harvest or lot.
	 * Canceled harvests are not found.
	 *
	 * @param	User	$user		User
	 * @param	string	$search		Text searched
	 * @return	array<int,array<string,mixed>>|int	Harvests, <0 if KO
	 */
	public function find(User $user, $search)
	{
		$search = trim((string) $search);
		// A QR code read by a scanner can be the whole address of the page: keep its parameter
		if (preg_match('/[?&](?:uuid|u)=([0-9a-fA-F-]{8,36})/', $search, $reg)) {
			$search = $reg[1];
		}
		if ($search === '') {
			return array();
		}
		$prefix = $this->db->prefix();
		$conditions = array(
			"h.uuid = '".$this->db->escape(strtolower($search))."'",
			"h.ref = '".$this->db->escape($search)."'",
			"h.batch_number = '".$this->db->escape($search)."'",
		);
		if (preg_match('/^[0-9a-fA-F]{8}$/', $search)) {
			$conditions[] = "h.uuid LIKE '".$this->db->escape(strtolower($search))."-%'";
		}
		$sql = "SELECT h.rowid, h.ref, h.uuid, h.date_harvest, h.batch_number, h.qty_harvested, h.qty_unit, h.status,";
		$sql .= " p.ref as product_ref, p.label as product_label, pl.ref as plot_ref, s.nom as farm";
		$sql .= " FROM ".$prefix."dolitrace_harvest as h";
		$sql .= " INNER JOIN ".$prefix."dolitrace_cropplan as cp ON cp.rowid = h.fk_cropplan";
		$sql .= " LEFT JOIN ".$prefix."dolifarm_plot as pl ON pl.rowid = cp.fk_plot";
		$sql .= " LEFT JOIN ".$prefix."societe as s ON s.rowid = cp.fk_soc";
		$sql .= " LEFT JOIN ".$prefix."product as p ON p.rowid = h.fk_product_out";
		$sql .= " WHERE h.entity IN (".getEntity('dolitraceharvest').") AND (h.status IS NULL OR h.status <> 9)";
		$sql .= " AND (".implode(' OR ', $conditions).")";
		$sql .= dolifarmSqlSocFilter($user, 'cp.fk_soc');
		$sql .= " ORDER BY h.date_harvest DESC, h.rowid DESC";
		$sql .= $this->db->plimit(100);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$lines = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$lines[] = array(
				'id' => (int) $obj->rowid, 'ref' => (string) $obj->ref, 'uuid' => (string) $obj->uuid, 'date' => $this->db->jdate($obj->date_harvest),
				'batch' => (string) $obj->batch_number, 'qty' => $obj->qty_harvested !== null ? (float) $obj->qty_harvested : null, 'unit' => (string) $obj->qty_unit,
				'status' => (int) $obj->status, 'product' => trim($obj->product_ref.' '.$obj->product_label), 'plot' => (string) $obj->plot_ref, 'farm' => (string) $obj->farm,
			);
		}
		$this->db->free($resql);
		return $lines;
	}

	/**
	 * History of a harvest: the harvest and the other harvests of its lot, the farm, the plot, the crop plan, the seeds,
	 * the operations and the treatments of the crop plan up to the harvest, the stock and the movements of the lot.
	 * The sections the user has no right to read are not loaded (key missing).
	 *
	 * @param	User		$user			User
	 * @param	int			$harvestid		Harvest
	 * @param	Translate	$outputlangs	Language of the labels (crops, statuses)
	 * @return	array<string,mixed>|int		History, 0 if not found or not visible, <0 if KO
	 */
	public function getHistory(User $user, $harvestid, Translate $outputlangs)
	{
		$prefix = $this->db->prefix();
		$outputlangs->loadLangs(array('dolifarm@dolifarm', 'dolitrace@dolitrace'));

		$sql = "SELECT h.rowid, h.ref, h.uuid, h.label, h.date_harvest, h.fk_product_out, h.qty_harvested, h.qty_unit, h.batch_number, h.bio_status,";
		$sql .= " h.status, h.note_public, h.date_valid,";
		$sql .= " cp.rowid as cp_id, cp.ref as cp_ref, cp.label as cp_label, cp.season, cp.date_start as cp_start, cp.date_end as cp_end,";
		$sql .= " cp.fk_soc, cp.fk_plot, cp.fk_dossier, cp.estimated_yield, c.label as crop_label, c.eppo_code as crop_eppo,";
		$sql .= " p.ref as product_ref, p.label as product_label,";
		$sql .= " pl.ref as plot_ref, pl.label as plot_label, pl.cadastral_comune, pl.cadastral_sheet, pl.cadastral_parcel, pl.size_sau, pl.size_total,";
		$sql .= " pl.geo_lat, pl.geo_lon, d.ref as dossier_ref";
		$sql .= " FROM ".$prefix."dolitrace_harvest as h";
		$sql .= " INNER JOIN ".$prefix."dolitrace_cropplan as cp ON cp.rowid = h.fk_cropplan";
		$sql .= " LEFT JOIN ".$prefix."dolifarm_crops as c ON c.rowid = cp.fk_crop";
		$sql .= " LEFT JOIN ".$prefix."dolifarm_plot as pl ON pl.rowid = cp.fk_plot";
		$sql .= " LEFT JOIN ".$prefix."dolifarm_farmdossier as d ON d.rowid = cp.fk_dossier";
		$sql .= " LEFT JOIN ".$prefix."product as p ON p.rowid = h.fk_product_out";
		$sql .= " WHERE h.rowid = ".((int) $harvestid)." AND h.entity IN (".getEntity('dolitraceharvest').")";
		$sql .= dolifarmSqlSocFilter($user, 'cp.fk_soc');
		$resql = $this->db->query($sql);
		if (!$resql) {
			// The EPPO code of the crop is a column of doliFarm 1.2: without it, the crop label only
			$sql = str_replace(', c.eppo_code as crop_eppo', ", '' as crop_eppo", $sql);
			$resql = $this->db->query($sql);
		}
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!$obj) {
			return 0;
		}

		$register = new DoliTraceBioRegister($this->db);
		$biolabels = $register->getBioStatusLabels();
		$date = $this->db->jdate($obj->date_harvest);
		$plotcode = ((int) $obj->fk_plot > 0 ? dolifarmBioStatusAt($this->db, (int) $obj->fk_plot, $date) : '');
		$cropstart = $obj->cp_start ? $this->db->jdate($obj->cp_start) : dol_mktime(0, 0, 0, 1, 1, (int) $obj->season);

		$history = array(
			'harvest' => array(
				'id' => (int) $obj->rowid, 'ref' => (string) $obj->ref, 'uuid' => (string) $obj->uuid, 'short' => self::shortCode($obj->uuid),
				'label' => (string) $obj->label, 'date' => $date, 'product_id' => (int) $obj->fk_product_out,
				'product' => trim($obj->product_ref.' - '.$obj->product_label, ' -'), 'product_label' => (string) $obj->product_label,
				'qty' => $obj->qty_harvested !== null ? (float) $obj->qty_harvested : null, 'unit' => (string) $obj->qty_unit,
				'batch' => (string) $obj->batch_number, 'bio_status' => (string) $obj->bio_status, 'status' => (int) $obj->status,
				'note_public' => (string) $obj->note_public,
			),
			'farm' => array(
				'socid' => (int) $obj->fk_soc, 'dossier' => (string) $obj->dossier_ref,
				'header' => $register->getFarmHeader((int) $obj->fk_soc, $outputlangs),
			),
			'plot' => array(
				'id' => (int) $obj->fk_plot, 'ref' => (string) $obj->plot_ref, 'label' => (string) $obj->plot_label, 'comune' => (string) $obj->cadastral_comune,
				'sheet' => (string) $obj->cadastral_sheet, 'parcel' => (string) $obj->cadastral_parcel,
				'surface' => $obj->size_sau !== null && (float) $obj->size_sau > 0 ? (float) $obj->size_sau : ($obj->size_total !== null ? (float) $obj->size_total : null),
				'lat' => $obj->geo_lat !== null ? (float) $obj->geo_lat : null, 'lon' => $obj->geo_lon !== null ? (float) $obj->geo_lon : null,
				'bio_code' => $plotcode, 'bio_label' => $biolabels[$plotcode] ?? $plotcode,
			),
			'cropplan' => array(
				'id' => (int) $obj->cp_id, 'ref' => (string) $obj->cp_ref, 'label' => (string) $obj->cp_label, 'season' => (int) $obj->season,
				'crop' => $outputlangs->transnoentities((string) $obj->crop_label), 'crop_eppo' => strtoupper((string) $obj->crop_eppo),
				'date_start' => $obj->cp_start ? $this->db->jdate($obj->cp_start) : null, 'date_end' => $obj->cp_end ? $this->db->jdate($obj->cp_end) : null,
			),
			'lot' => $this->getLotHarvests((int) $obj->fk_product_out, (string) $obj->batch_number, (int) $obj->rowid),
			'warnings' => array(),
		);

		if ($user->hasRight('dolitrace', 'dolitraceoperation', 'read')) {
			$history['operations'] = $this->getOperations((int) $obj->cp_id, $date);
		}
		if ($user->hasRight('dolitrace', 'dolitracetreatment', 'read')) {
			$history['treatments'] = $this->getTreatments((int) $obj->cp_id, (int) $obj->fk_plot, $cropstart, $date, $history['cropplan']['crop_eppo']);
			$history['treatment_summary'] = $this->getTreatmentSummary($history['treatments'], $date);
			$drafts = $this->countDraftTreatments((int) $obj->cp_id, $date);
			if ($drafts > 0) {
				$history['warnings'][] = $outputlangs->transnoentities('TraceWarningDraftTreatments', $drafts);
			}
			foreach ($history['treatments'] as $line) {
				if ($line['safety_ok'] === false) {
					$history['warnings'][] = $outputlangs->transnoentities('TraceWarningSafety', $line['product_label'], dol_print_date($line['date'], 'day', 'tzserver', $outputlangs), $line['safety_days'], $line['days_before']);
				}
			}
		}
		if (isModEnabled('stock') && $user->hasRight('stock', 'lire') && (int) $obj->fk_product_out > 0 && (string) $obj->batch_number !== '') {
			$history['stock'] = $this->getStock((int) $obj->fk_product_out, (string) $obj->batch_number);
			$movements = $register->getMovements(array(), '', '', '', (int) $obj->fk_product_out);
			$history['movements'] = array();
			if (is_array($movements)) {
				foreach ($movements as $line) {
					if ($line['batch'] === (string) $obj->batch_number) {
						$history['movements'][] = $line;
					}
				}
			}
			$customers = array();
			foreach ($history['movements'] as $line) {
				if ($line['origin'] == 'SHIPPING' && $line['thirdparty'] !== '') {
					$customers[$line['thirdparty']] = ($customers[$line['thirdparty']] ?? 0) + (float) $line['qty_out'] - (float) $line['qty_in'];
				}
			}
			$history['customers'] = $customers;
		}
		if ($history['harvest']['status'] != 1) {
			$history['warnings'][] = $outputlangs->transnoentities('TraceWarningNotValidated');
		}
		if ($history['harvest']['uuid'] === '') {
			$history['warnings'][] = $outputlangs->transnoentities('TraceWarningNoUuid');
		}
		$statuses = array();
		foreach (array_merge(array($history['harvest']), $history['lot']) as $line) {
			if ($line['bio_status'] !== '') {
				$statuses[$line['bio_status']] = $line['bio_status'];
			}
		}
		if (count($statuses) > 1) {
			$history['warnings'][] = $outputlangs->transnoentities('TraceWarningMixedLot', implode(', ', $statuses));
		}
		return $history;
	}

	/**
	 * Other harvests of the same lot (same product and lot number), not canceled
	 *
	 * @param	int		$productid	Product
	 * @param	string	$batch		Lot
	 * @param	int		$exclude	Harvest to leave out
	 * @return	array<int,array<string,mixed>>
	 */
	private function getLotHarvests($productid, $batch, $exclude)
	{
		$lines = array();
		if ($productid <= 0 || $batch === '') {
			return $lines;
		}
		$prefix = $this->db->prefix();
		$sql = "SELECT h.rowid, h.ref, h.uuid, h.date_harvest, h.qty_harvested, h.qty_unit, h.bio_status, h.status, pl.ref as plot_ref";
		$sql .= " FROM ".$prefix."dolitrace_harvest as h";
		$sql .= " LEFT JOIN ".$prefix."dolitrace_cropplan as cp ON cp.rowid = h.fk_cropplan";
		$sql .= " LEFT JOIN ".$prefix."dolifarm_plot as pl ON pl.rowid = cp.fk_plot";
		$sql .= " WHERE h.fk_product_out = ".((int) $productid)." AND h.batch_number = '".$this->db->escape($batch)."'";
		$sql .= " AND h.rowid <> ".((int) $exclude)." AND (h.status IS NULL OR h.status <> 9)";
		$sql .= " AND h.entity IN (".getEntity('dolitraceharvest').")";
		$sql .= " ORDER BY h.date_harvest, h.rowid";
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' '.$this->db->lasterror(), LOG_ERR);
			return $lines;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$lines[] = array(
				'id' => (int) $obj->rowid, 'ref' => (string) $obj->ref, 'uuid' => (string) $obj->uuid, 'date' => $this->db->jdate($obj->date_harvest),
				'qty' => $obj->qty_harvested !== null ? (float) $obj->qty_harvested : null, 'unit' => (string) $obj->qty_unit,
				'bio_status' => (string) $obj->bio_status, 'status' => (int) $obj->status, 'plot' => (string) $obj->plot_ref,
			);
		}
		$this->db->free($resql);
		return $lines;
	}

	/**
	 * Operations of the crop plan up to the day of the harvest (not canceled), with the material used and its lot
	 *
	 * @param	int		$cropplanid		Crop plan
	 * @param	int		$date			Day of the harvest
	 * @return	array<int,array<string,mixed>>
	 */
	private function getOperations($cropplanid, $date)
	{
		dol_include_once('/dolitrace/class/dolitraceoperation.class.php');
		$prefix = $this->db->prefix();
		$lines = array();
		$sql = "SELECT o.rowid, o.ref, o.date_op, o.fk_operation_type, o.label, o.batch_input, o.qty_used, o.seed_status, o.bdsb_ref,";
		$sql .= " p.ref as product_ref, p.label as product_label";
		$sql .= " FROM ".$prefix."dolitrace_operation as o";
		$sql .= " LEFT JOIN ".$prefix."product as p ON p.rowid = o.fk_product";
		$sql .= " WHERE o.fk_cropplan = ".((int) $cropplanid)." AND (o.status IS NULL OR o.status <> 9)";
		$sql .= " AND o.date_op <= '".$this->db->idate(dol_mktime(23, 59, 59, (int) dol_print_date($date, '%m'), (int) dol_print_date($date, '%d'), (int) dol_print_date($date, '%Y')))."'";
		$sql .= " ORDER BY o.date_op, o.rowid";
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' '.$this->db->lasterror(), LOG_ERR);
			return $lines;
		}
		$seeding = class_exists('DoliTraceOperation') ? DoliTraceOperation::$seedingTypes : array();
		while ($obj = $this->db->fetch_object($resql)) {
			$lines[] = array(
				'id' => (int) $obj->rowid, 'ref' => (string) $obj->ref, 'date' => $this->db->jdate($obj->date_op), 'type' => (string) $obj->fk_operation_type,
				'seeding' => in_array((string) $obj->fk_operation_type, $seeding), 'label' => (string) $obj->label,
				'product' => trim($obj->product_ref.' - '.$obj->product_label, ' -'), 'batch' => (string) $obj->batch_input,
				'qty' => $obj->qty_used !== null ? (float) $obj->qty_used : null, 'seed_status' => (string) $obj->seed_status, 'bdsb_ref' => (string) $obj->bdsb_ref,
			);
		}
		$this->db->free($resql);
		return $lines;
	}

	/**
	 * Validated treatments of the crop plan up to the harvest, one line per product: those recorded on the crop plan,
	 * and those on the plot without crop plan from the start of the crop. For the plant protection products, the
	 * pre-harvest interval of the authorised use on the crop (otherwise the one of the product) and whether it was kept.
	 *
	 * @param	int		$cropplanid		Crop plan
	 * @param	int		$plotid			Plot
	 * @param	int		$start			Start of the crop
	 * @param	int		$date			Day of the harvest
	 * @param	string	$cropeppo		EPPO code of the crop
	 * @return	array<int,array<string,mixed>>
	 */
	private function getTreatments($cropplanid, $plotid, $start, $date, $cropeppo)
	{
		$prefix = $this->db->prefix();
		$lines = array();
		$end = dol_mktime(23, 59, 59, (int) dol_print_date($date, '%m'), (int) dol_print_date($date, '%d'), (int) dol_print_date($date, '%Y'));
		$sql = "SELECT DISTINCT t.rowid, t.ref, t.kind, t.date_start, t.date_end,";
		$sql .= " tp.rowid as line_id, tp.fk_agrodrug, tp.fk_fertilizer, tp.product_label, tp.reg_number, tp.adversity_label, tp.dose, tp.dose_unit_label,";
		$sql .= " tp.qty_total, tp.qty_unit, tp.batch, tp.n_applied, a.active_substance, a.safety_days_default";
		$sql .= " FROM ".$prefix."dolitrace_treatment as t";
		$sql .= " INNER JOIN ".$prefix."dolitrace_treatment_target as tt ON tt.fk_treatment = t.rowid";
		$sql .= " INNER JOIN ".$prefix."dolitrace_treatment_product as tp ON tp.fk_treatment = t.rowid";
		$sql .= " LEFT JOIN ".$prefix."dolifarm_agrodrug as a ON a.rowid = tp.fk_agrodrug";
		$sql .= " WHERE t.status = 1 AND t.entity IN (".getEntity('dolitracetreatment').")";
		$sql .= " AND t.date_start <= '".$this->db->idate($end)."'";
		$sql .= " AND (tt.fk_cropplan = ".((int) $cropplanid);
		if ($plotid > 0) {
			$sql .= " OR (tt.fk_cropplan IS NULL AND tt.fk_plot = ".((int) $plotid)." AND t.date_start >= '".$this->db->idate($start)."')";
		}
		$sql .= ")";
		$sql .= " ORDER BY t.date_start, t.rowid, tp.rowid";
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' '.$this->db->lasterror(), LOG_ERR);
			return $lines;
		}
		$rows = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$rows[] = $obj;
		}
		$this->db->free($resql);

		$organic = dol_include_once('/dolifarm/lib/dolifarm_organic.lib.php');
		$harvestday = (int) floor($end / 86400);
		foreach ($rows as $obj) {
			$tdate = $this->db->jdate($obj->date_start);
			$tend = $obj->date_end ? $this->db->jdate($obj->date_end) : $tdate;
			$days = $harvestday - (int) floor($tend / 86400);
			$safety = null;
			$organicresult = '';
			if ((int) $obj->fk_agrodrug > 0) {
				$safety = $this->getSafetyDays((int) $obj->fk_agrodrug, $cropeppo);
				if ($safety === null && $obj->safety_days_default !== null) {
					$safety = (int) $obj->safety_days_default;
				}
				if ($organic && function_exists('dolifarmOrganicAgroDrugCheck')) {
					$check = dolifarmOrganicAgroDrugCheck($this->db, (int) $obj->fk_agrodrug, $tdate);
					$organicresult = (string) $check['result'];
				}
			} elseif ((int) $obj->fk_fertilizer > 0 && $organic && function_exists('dolifarmOrganicFertilizerCheck')) {
				$check = dolifarmOrganicFertilizerCheck($this->db, (int) $obj->fk_fertilizer);
				$organicresult = (string) $check['result'];
			}
			$lines[] = array(
				'id' => (int) $obj->rowid, 'ref' => (string) $obj->ref, 'kind' => (int) $obj->kind, 'date' => $tdate, 'date_end' => $tend,
				'fk_agrodrug' => (int) $obj->fk_agrodrug, 'fk_fertilizer' => (int) $obj->fk_fertilizer,
				'product_label' => (string) $obj->product_label, 'reg_number' => (string) $obj->reg_number, 'substance' => trim((string) $obj->active_substance),
				'adversity' => (string) $obj->adversity_label, 'dose' => $obj->dose !== null ? (float) $obj->dose : null, 'dose_unit' => (string) $obj->dose_unit_label,
				'qty' => $obj->qty_total !== null ? (float) $obj->qty_total : null, 'unit' => (string) $obj->qty_unit, 'batch' => (string) $obj->batch,
				'n_applied' => $obj->n_applied !== null ? (float) $obj->n_applied : null,
				'days_before' => $days, 'safety_days' => $safety, 'safety_ok' => ($safety !== null && $safety > 0 ? $days >= $safety : null),
				'organic_result' => $organicresult,
			);
		}
		return $lines;
	}

	/**
	 * Pre-harvest interval of a plant protection product on a crop: the longest among the authorised uses on the crop
	 * or on a group of the EPPO taxonomy that contains it
	 *
	 * @param	int		$agrodrugid		Plant protection product
	 * @param	string	$cropeppo		EPPO code of the crop
	 * @return	int|null				Days, null when no use of the crop gives it
	 */
	private function getSafetyDays($agrodrugid, $cropeppo)
	{
		if ($cropeppo === '') {
			return null;
		}
		$sql = "SELECT crop_eppo, safety_days FROM ".$this->db->prefix()."dolifarm_agrodrug_use";
		$sql .= " WHERE fk_agrodrug = ".((int) $agrodrugid)." AND safety_days IS NOT NULL";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return null;
		}
		$hastaxonomy = dol_include_once('/dolifarm/lib/dolifarm_eppo.lib.php') && function_exists('dolifarmEppoCovers');
		$days = null;
		while ($obj = $this->db->fetch_object($resql)) {
			$code = strtoupper((string) $obj->crop_eppo);
			if ($code === $cropeppo || ($hastaxonomy && dolifarmEppoCovers($this->db, $code, $cropeppo))) {
				$days = max((int) $days, (int) $obj->safety_days);
			}
		}
		$this->db->free($resql);
		return $days;
	}

	/**
	 * Summary of the treatments: number of plant protection treatments and of fertilizations, last plant protection
	 * treatment and days before the harvest, whether all the products are allowed in organic production
	 *
	 * @param	array<int,array<string,mixed>>	$treatments		Lines of getTreatments
	 * @param	int								$date			Day of the harvest
	 * @return	array<string,mixed>
	 */
	public function getTreatmentSummary($treatments, $date)
	{
		$protection = array();
		$fertilization = array();
		$last = null;
		$lastdays = null;
		$allorganic = true;
		$substances = array();
		foreach ($treatments as $line) {
			if ($line['kind'] == 2) {
				$fertilization[$line['id']] = true;
			} else {
				$protection[$line['id']] = true;
				if ($last === null || $line['date_end'] > $last) {
					$last = $line['date_end'];
					$lastdays = $line['days_before'];
				}
				if ($line['substance'] !== '') {
					$substances[$line['substance']] = $line['substance'];
				}
			}
			if ($line['organic_result'] !== 'yes') {
				$allorganic = false;
			}
		}
		return array(
			'protection' => count($protection), 'fertilization' => count($fertilization), 'last_protection' => $last, 'last_days' => $lastdays,
			'all_organic' => (count($treatments) > 0 && $allorganic), 'substances' => array_values($substances),
		);
	}

	/**
	 * Treatments still in draft on the crop plan before the harvest: they are not in the history
	 *
	 * @param	int		$cropplanid		Crop plan
	 * @param	int		$date			Day of the harvest
	 * @return	int
	 */
	private function countDraftTreatments($cropplanid, $date)
	{
		$end = dol_mktime(23, 59, 59, (int) dol_print_date($date, '%m'), (int) dol_print_date($date, '%d'), (int) dol_print_date($date, '%Y'));
		$sql = "SELECT COUNT(DISTINCT t.rowid) as nb FROM ".$this->db->prefix()."dolitrace_treatment as t";
		$sql .= " INNER JOIN ".$this->db->prefix()."dolitrace_treatment_target as tt ON tt.fk_treatment = t.rowid";
		$sql .= " WHERE t.status = 0 AND tt.fk_cropplan = ".((int) $cropplanid)." AND t.date_start <= '".$this->db->idate($end)."'";
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : null;
		return $obj ? (int) $obj->nb : 0;
	}

	/**
	 * Current stock of a lot by warehouse
	 *
	 * @param	int		$productid	Product
	 * @param	string	$batch		Lot
	 * @return	array<int,array<string,mixed>>
	 */
	private function getStock($productid, $batch)
	{
		$prefix = $this->db->prefix();
		$lines = array();
		$sql = "SELECT e.ref as warehouse, pb.qty FROM ".$prefix."product_batch as pb";
		$sql .= " INNER JOIN ".$prefix."product_stock as ps ON ps.rowid = pb.fk_product_stock";
		$sql .= " INNER JOIN ".$prefix."entrepot as e ON e.rowid = ps.fk_entrepot";
		$sql .= " WHERE ps.fk_product = ".((int) $productid)." AND pb.batch = '".$this->db->escape($batch)."'";
		$sql .= " AND e.entity IN (".getEntity('stock').") AND pb.qty <> 0";
		$sql .= " ORDER BY e.ref";
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' '.$this->db->lasterror(), LOG_ERR);
			return $lines;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$lines[] = array('warehouse' => (string) $obj->warehouse, 'qty' => (float) $obj->qty);
		}
		$this->db->free($resql);
		return $lines;
	}

	/**
	 * Traceability sheet in PDF (portrait): the history of the harvest, to give to the control body or to a customer
	 *
	 * @param	array<string,mixed>	$history		History (getHistory)
	 * @param	Translate			$outputlangs	Language
	 * @return	string								Content of the PDF
	 */
	public function toPdf($history, Translate $outputlangs)
	{
		global $mysoc;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
		$outputlangs->loadLangs(array('main', 'products', 'productbatch', 'stocks', 'dolitrace@dolitrace', 'dolifarm@dolifarm'));
		$h = $history['harvest'];
		$title = $outputlangs->transnoentities('TraceSheetTitle', $h['ref']);
		$format = pdf_getFormat($outputlangs);
		$pdf = pdf_getInstance(array($format['width'], $format['height']), 'mm', 'P');
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetTitle($outputlangs->convToOutputCharset($title));
		$pdf->SetAuthor($outputlangs->convToOutputCharset($mysoc->name));
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(true);
		$pdf->SetMargins(10, 10, 10);
		$pdf->SetAutoPageBreak(true, 12);
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 8);
		$pdf->AddPage();

		$esc = function ($text) {
			return dol_escape_htmltag((string) $text);
		};
		$day = function ($date) use ($outputlangs) {
			return $date ? dol_print_date($date, 'day', 'tzserver', $outputlangs) : '';
		};
		$num = function ($value) use ($outputlangs) {
			return $value === null ? '' : price(price2num($value, 'MS'), 0, $outputlangs);
		};
		$table = function ($headers, $rows) use ($esc, $outputlangs) {
			$html = '<table border="0.3" cellpadding="2"><tr style="background-color:#e8e8e8; font-weight:bold;">';
			foreach ($headers as $header) {
				$html .= '<th width="'.$header[1].'%">'.$esc($outputlangs->transnoentities($header[0])).'</th>';
			}
			$html .= '</tr>';
			if (empty($rows)) {
				$html .= '<tr><td width="100%">'.$esc($outputlangs->transnoentities('None')).'</td></tr>';
			}
			foreach ($rows as $row) {
				$html .= '<tr nobr="true">';
				foreach ($row as $i => $cell) {
					$html .= '<td width="'.$headers[$i][1].'%">'.$esc($cell).'</td>';
				}
				$html .= '</tr>';
			}
			return $html.'</table>';
		};
		$pairs = function ($rows) use ($esc, $outputlangs) {
			$html = '<table cellpadding="1">';
			foreach ($rows as $row) {
				if ((string) $row[1] !== '') {
					$html .= '<tr><td width="30%"><b>'.$esc($outputlangs->transnoentities($row[0])).'</b></td><td width="70%">'.$esc($row[1]).'</td></tr>';
				}
			}
			return $html.'</table>';
		};

		$html = '<h2>'.$esc($title).'</h2>';
		foreach ($history['farm']['header'] as $line) {
			$html .= $esc($line).'<br>';
		}
		$html .= $esc($outputlangs->transnoentities('TreatmentPdfGenerated', dol_print_date(dol_now(), 'dayhour', 'tzuser', $outputlangs)));

		$html .= '<h3>'.$esc($outputlangs->transnoentities('TraceSectionHarvest')).'</h3>';
		$html .= $pairs(array(
			array('HarvestProduct', $h['product']), array('HarvestBatch', $h['batch']), array('HarvestDate', $day($h['date'])),
			array('HarvestQty', $h['qty'] !== null ? $num($h['qty']).' '.$h['unit'] : ''), array('HarvestBioStatus', $outputlangs->transnoentities($h['bio_status'] !== '' ? 'BioRegisterFilter'.$h['bio_status'] : '')),
			array('TraceUuid', $h['uuid']), array('TraceShortCode', $h['short']), array('NotePublic', $h['note_public']),
		));
		if (count($history['lot'])) {
			$rows = array();
			foreach ($history['lot'] as $line) {
				$rows[] = array($line['ref'], $day($line['date']), $line['plot'], $line['qty'] !== null ? $num($line['qty']).' '.$line['unit'] : '', $line['bio_status']);
			}
			$html .= '<h4>'.$esc($outputlangs->transnoentities('TraceSectionLot')).'</h4>';
			$html .= $table(array(array('Ref', 25), array('Date', 15), array('BioRegisterPlot', 25), array('Qty', 20), array('BioRegisterStatus', 15)), $rows);
		}

		$p = $history['plot'];
		$cp = $history['cropplan'];
		$html .= '<h3>'.$esc($outputlangs->transnoentities('TraceSectionOrigin')).'</h3>';
		$html .= $pairs(array(
			array('TraceDossier', $history['farm']['dossier']), array('BioRegisterPlot', trim($p['ref'].' - '.$p['label'], ' -')),
			array('TraceCadastral', trim($p['comune'].' '.($p['sheet'] !== '' ? $outputlangs->transnoentities('TraceSheetParcel', $p['sheet'], $p['parcel']) : ''))),
			array('BioRegisterSurface', $p['surface'] !== null ? $num($p['surface']).' ha' : ''), array('BioRegisterPlotStatus', $p['bio_label']),
			array('BioRegisterCropPlan', trim($cp['ref'].' - '.$cp['label'], ' -')), array('BioRegisterCrop', $cp['crop'].($cp['crop_eppo'] !== '' ? ' ('.$cp['crop_eppo'].')' : '')),
			array('TraceCropPeriod', trim($day($cp['date_start']).' - '.$day($cp['date_end']), ' -')),
		));

		if (isset($history['operations'])) {
			$rows = array();
			foreach ($history['operations'] as $line) {
				$rows[] = array($day($line['date']), $line['ref'], $outputlangs->transnoentities($line['type']), $line['product'], $line['batch'], $line['seed_status'].($line['bdsb_ref'] !== '' ? ' '.$line['bdsb_ref'] : ''));
			}
			$html .= '<h3>'.$esc($outputlangs->transnoentities('TraceSectionOperations')).'</h3>';
			$html .= $table(array(array('Date', 12), array('Ref', 16), array('Type', 20), array('Product', 26), array('Batch', 14), array('BioRegisterStatus', 12)), $rows);
		}

		if (isset($history['treatments'])) {
			$rows = array();
			foreach ($history['treatments'] as $line) {
				$rows[] = array(
					$day($line['date']), $line['ref'], $line['product_label'].($line['reg_number'] !== '' ? ' ('.$line['reg_number'].')' : ''), $line['adversity'],
					$line['qty'] !== null ? $num($line['qty']).' '.$line['unit'] : '', $line['batch'],
					$line['safety_days'] !== null ? $line['days_before'].' / '.$line['safety_days'].($line['safety_ok'] === false ? ' !' : '') : (string) $line['days_before'],
				);
			}
			$html .= '<h3>'.$esc($outputlangs->transnoentities('TraceSectionTreatments')).'</h3>';
			$html .= $table(array(array('Date', 11), array('Ref', 14), array('Product', 27), array('TraceAdversity', 16), array('Qty', 11), array('Batch', 11), array('TraceDaysSafety', 10)), $rows);
		}

		if (isset($history['movements'])) {
			$rows = array();
			foreach ($history['movements'] as $line) {
				$rows[] = array($day($line['date']), $outputlangs->transnoentities('BioRegisterOrigin'.ucfirst(strtolower($line['origin']))), $line['document'], $line['thirdparty'], $line['warehouse'],
					$line['qty_in'] !== null ? $num($line['qty_in']) : '', $line['qty_out'] !== null ? $num($line['qty_out']) : '');
			}
			$html .= '<h3>'.$esc($outputlangs->transnoentities('TraceSectionDownstream')).'</h3>';
			$html .= $table(array(array('Date', 11), array('BioRegisterOrigin', 14), array('BioRegisterDocument', 16), array('ThirdParty', 22), array('Warehouse', 15), array('BioRegisterIn', 11), array('BioRegisterOut', 11)), $rows);
			$stock = array();
			foreach ($history['stock'] as $line) {
				$stock[] = $line['warehouse'].': '.$num($line['qty']);
			}
			$html .= '<p>'.$esc($outputlangs->transnoentities('TraceStockNow')).': '.$esc(count($stock) ? implode(', ', $stock) : '0').'</p>';
		}

		if (count($history['warnings'])) {
			$html .= '<h3>'.$esc($outputlangs->transnoentities('TraceSectionWarnings')).'</h3><p>';
			foreach ($history['warnings'] as $warning) {
				$html .= $esc($warning).'<br>';
			}
			$html .= '</p>';
		}
		$pdf->writeHTML($html, true, false, true, false, '');
		return $pdf->Output('', 'S');
	}
}
