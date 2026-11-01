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
 * \file        class/dolitracebioregister.class.php
 * \ingroup     dolitrace
 * \brief       Registers of organic production (Reg. EU 2018/848, Reg. EU 2021/771): stock in and out register,
 *              annual mass balance by crop and status, annual summary by plot
 *
 * Everything is read only: the stock movements and the lots of Dolibarr are read, never written.
 */

dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_bio.lib.php');

/**
 * Registers of organic production
 */
class DoliTraceBioRegister
{
	/** Origins of the movements: sales (out to customers) */
	const SALE_TYPES = array('shipping', 'facture', 'commande');

	/** Origins of the movements: purchases (in from suppliers) */
	const PURCHASE_TYPES = array('reception', 'order_supplier', 'invoice_supplier');

	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Last error
	 */
	public $error = '';

	/**
	 * @var array<string,string> Status of the lots read by getMovements: "product id|batch" => BIO, INCONV, NONBIO
	 */
	public $lotstatus = array();

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
	 * Origin of a stock movement
	 *
	 * @param	string	$origintype		Origin type of the movement
	 * @return	string					HARVEST, TREATMENT, SHIPPING, RECEPTION or ADJUSTMENT (inventories, transfers, manual)
	 */
	public static function originOf($origintype)
	{
		$origintype = (string) $origintype;
		if ($origintype == 'dolitraceharvest@dolitrace') {
			return 'HARVEST';
		}
		if ($origintype == 'dolitracetreatment@dolitrace') {
			return 'TREATMENT';
		}
		if (in_array($origintype, self::SALE_TYPES)) {
			return 'SHIPPING';
		}
		if (in_array($origintype, self::PURCHASE_TYPES)) {
			return 'RECEPTION';
		}
		return 'ADJUSTMENT';
	}

	/**
	 * Status of a lot from its suffix
	 *
	 * @param	string	$batch	Lot
	 * @return	string			BIO, INCONV or '' when the suffix says nothing
	 */
	public static function statusFromBatch($batch)
	{
		$batch = (string) $batch;
		if (str_ends_with($batch, '-BIO')) {
			return 'BIO';
		}
		if (str_ends_with($batch, '-INCONV')) {
			return 'INCONV';
		}
		return '';
	}

	/**
	 * Warehouses of the register: the warehouse chosen, otherwise the warehouses of the farm dossiers (technical inputs
	 * and products), always with their sub-warehouses
	 *
	 * @param	User	$user			User
	 * @param	int		$socid			Farm, 0 for all the farms of the user
	 * @param	int		$warehouseid	Warehouse chosen, 0 for none
	 * @return	int[]					Warehouses, empty for all the warehouses of the entity
	 */
	public function getWarehouses(User $user, $socid, $warehouseid = 0)
	{
		$prefix = $this->db->prefix();
		$ids = array();
		if ($warehouseid > 0) {
			$ids[(int) $warehouseid] = (int) $warehouseid;
		} else {
			$sql = "SELECT d.fk_warehouse_inputs, d.fk_warehouse_products FROM ".$prefix."dolifarm_farmdossier as d";
			$sql .= " WHERE d.entity IN (".getEntity('dolifarmfarmdossier').") AND d.status IN (0, 1)";
			if ($socid > 0) {
				$sql .= " AND d.fk_soc = ".((int) $socid);
			}
			$sql .= dolifarmSqlSocFilter($user, 'd.fk_soc');
			$resql = $this->db->query($sql);
			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
					foreach (array($obj->fk_warehouse_inputs, $obj->fk_warehouse_products) as $id) {
						if ((int) $id > 0) {
							$ids[(int) $id] = (int) $id;
						}
					}
				}
				$this->db->free($resql);
			}
			if (empty($ids)) {
				foreach (array('DOLIFARM_WAREHOUSE_INPUTS', 'DOLIFARM_WAREHOUSE_PRODUCTS') as $const) {
					if (getDolGlobalInt($const) > 0 && $socid <= 0) {
						$ids[getDolGlobalInt($const)] = getDolGlobalInt($const);
					}
				}
			}
		}
		if (empty($ids)) {
			return array();
		}
		// Sub-warehouses, at any level
		$todo = $ids;
		for ($level = 0; $level < 10 && count($todo); $level++) {
			$sql = "SELECT rowid FROM ".$prefix."entrepot WHERE fk_parent IN (".$this->db->sanitize(implode(',', $todo)).")";
			$sql .= " AND entity IN (".getEntity('stock').")";
			$todo = array();
			$resql = $this->db->query($sql);
			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
					if (!isset($ids[(int) $obj->rowid])) {
						$ids[(int) $obj->rowid] = (int) $obj->rowid;
						$todo[] = (int) $obj->rowid;
					}
				}
				$this->db->free($resql);
			}
		}
		return array_values($ids);
	}

	/**
	 * Stock in and out register: the movements of the period, with the opening balance of each product and lot and the
	 * running balance. The status of a lot comes from its suffix, from the harvest that made it, otherwise from the
	 * status of the product (extrafield dolifarm_seed_status).
	 *
	 * @param	int[]		$warehouses		Warehouses, empty for all
	 * @param	int|string	$datefrom		First day (timestamp), '' for no limit
	 * @param	int|string	$dateto			Last day (timestamp), '' for today
	 * @param	string		$biostatus		BIO, INCONV, NONBIO, NONE (status unknown) or '' for all
	 * @param	int			$productid		Product, 0 for all
	 * @return	array<int,array<string,mixed>>|int	Lines, <0 if KO
	 */
	public function getMovements($warehouses, $datefrom, $dateto, $biostatus = '', $productid = 0)
	{
		if (!isModEnabled('stock')) {
			return array();
		}
		$prefix = $this->db->prefix();
		$start = ($datefrom !== '' && $datefrom !== null) ? (int) $datefrom : null;
		$end = ($dateto !== '' && $dateto !== null) ? (int) $dateto : dol_now();

		$sql = "SELECT m.rowid, m.datem, m.fk_product, m.batch, m.fk_entrepot, m.value, m.label, m.origintype, m.fk_origin,";
		$sql .= " p.ref as product_ref, p.label as product_label, e.ref as warehouse_ref";
		$sql .= " FROM ".$prefix."stock_mouvement as m";
		$sql .= " INNER JOIN ".$prefix."product as p ON p.rowid = m.fk_product";
		$sql .= " INNER JOIN ".$prefix."entrepot as e ON e.rowid = m.fk_entrepot";
		$sql .= " WHERE e.entity IN (".getEntity('stock').")";
		$sql .= " AND m.datem <= '".$this->db->idate($end)."'";
		if (count($warehouses)) {
			$sql .= " AND m.fk_entrepot IN (".$this->db->sanitize(implode(',', array_map('intval', $warehouses))).")";
		}
		if ($productid > 0) {
			$sql .= " AND m.fk_product = ".((int) $productid);
		}
		$sql .= " ORDER BY m.datem ASC, m.rowid ASC";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$movements = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$movements[] = $obj;
		}
		$this->db->free($resql);

		$products = array();
		$origins = array();
		foreach ($movements as $obj) {
			$products[(int) $obj->fk_product] = (int) $obj->fk_product;
			if ((int) $obj->fk_origin > 0 && (string) $obj->origintype !== '') {
				$origins[(string) $obj->origintype][(int) $obj->fk_origin] = (int) $obj->fk_origin;
			}
		}
		$productstatus = $this->getProductStatuses($products);
		$harveststatus = $this->getHarvestLotStatuses($products);
		$documents = $this->getOriginDocuments($origins);

		$lines = array();
		$balance = array();
		$opening = array();
		foreach ($movements as $obj) {
			$key = (int) $obj->fk_product.'|'.(string) $obj->batch;
			$value = (float) $obj->value;
			$date = $this->db->jdate($obj->datem);
			if (!isset($balance[$key])) {
				$balance[$key] = 0.0;
			}
			if (!isset($this->lotstatus[$key])) {
				$status = self::statusFromBatch($obj->batch);
				if ($status === '' && isset($harveststatus[$key])) {
					$status = $harveststatus[$key];
				}
				if ($status === '' && isset($productstatus[(int) $obj->fk_product])) {
					$status = $productstatus[(int) $obj->fk_product];
				}
				$this->lotstatus[$key] = $status;
			}
			$status = $this->lotstatus[$key];
			if ($start !== null && $date < $start) {
				$balance[$key] += $value;
				$opening[$key] = $obj;
				continue;
			}
			if (!$this->statusMatches($status, $biostatus)) {
				$balance[$key] += $value;
				continue;
			}
			// Opening balance of the lot, before its first movement of the period
			if (isset($opening[$key])) {
				if (abs($balance[$key]) > 0.0000001) {
					$first = $opening[$key];
					$lines[] = array(
						'date' => $start, 'product_id' => (int) $first->fk_product, 'product_ref' => (string) $first->product_ref,
						'product_label' => (string) $first->product_label, 'batch' => (string) $first->batch, 'bio_status' => $status,
						'warehouse' => '', 'qty_in' => null, 'qty_out' => null, 'balance' => $balance[$key], 'origin' => 'OPENING',
						'document' => '', 'label' => '', 'thirdparty' => '', 'thirdparty_cert' => '', 'thirdparty_body' => '',
					);
				}
				unset($opening[$key]);
			}
			$balance[$key] += $value;
			$doc = $documents[(string) $obj->origintype][(int) $obj->fk_origin] ?? null;
			$lines[] = array(
				'date' => $date,
				'product_id' => (int) $obj->fk_product,
				'product_ref' => (string) $obj->product_ref,
				'product_label' => (string) $obj->product_label,
				'batch' => (string) $obj->batch,
				'bio_status' => $status,
				'warehouse' => (string) $obj->warehouse_ref,
				'qty_in' => $value > 0 ? $value : null,
				'qty_out' => $value < 0 ? -$value : null,
				'balance' => $balance[$key],
				'origin' => self::originOf($obj->origintype),
				'document' => $doc ? $doc['ref'] : '',
				'label' => (string) $obj->label,
				'thirdparty' => $doc ? $doc['thirdparty'] : '',
				'thirdparty_cert' => $doc ? $doc['cert'] : '',
				'thirdparty_body' => $doc ? $doc['body'] : '',
			);
		}
		// Lots with an opening balance and no movement in the period
		foreach ($opening as $key => $first) {
			$status = $this->lotstatus[$key];
			if (abs($balance[$key]) > 0.0000001 && $this->statusMatches($status, $biostatus)) {
				$lines[] = array(
					'date' => $start, 'product_id' => (int) $first->fk_product, 'product_ref' => (string) $first->product_ref,
					'product_label' => (string) $first->product_label, 'batch' => (string) $first->batch, 'bio_status' => $status,
					'warehouse' => '', 'qty_in' => null, 'qty_out' => null, 'balance' => $balance[$key], 'origin' => 'OPENING',
					'document' => '', 'label' => '', 'thirdparty' => '', 'thirdparty_cert' => '', 'thirdparty_body' => '',
				);
			}
		}
		// By product and lot, then by date: the running balance reads top down
		usort($lines, function ($a, $b) {
			return array($a['product_ref'], $a['batch'], $a['origin'] == 'OPENING' ? 0 : 1, $a['date']) <=> array($b['product_ref'], $b['batch'], $b['origin'] == 'OPENING' ? 0 : 1, $b['date']);
		});
		return $lines;
	}

	/**
	 * True when the status of a lot matches the filter
	 *
	 * @param	string	$status		BIO, INCONV, NONBIO or ''
	 * @param	string	$filter		BIO, INCONV, NONBIO, NONE or ''
	 * @return	bool
	 */
	private function statusMatches($status, $filter)
	{
		if ($filter === '') {
			return true;
		}
		return ($filter == 'NONE' ? $status === '' : $status === $filter);
	}

	/**
	 * Organic status of products (extrafield dolifarm_seed_status of doliFarm)
	 *
	 * @param	int[]	$products	Products
	 * @return	array<int,string>	Product id => BIO, INCONV, NONBIO
	 */
	private function getProductStatuses($products)
	{
		$statuses = array();
		if (empty($products)) {
			return $statuses;
		}
		$sql = "SELECT fk_object, dolifarm_seed_status FROM ".$this->db->prefix()."product_extrafields";
		$sql .= " WHERE fk_object IN (".$this->db->sanitize(implode(',', array_map('intval', $products))).")";
		$resql = $this->db->query($sql);
		if (!$resql) {
			// The extrafield does not exist when doliFarm was not activated again after its creation
			dol_syslog(__METHOD__.' '.$this->db->lasterror(), LOG_DEBUG);
			return $statuses;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			if ((string) $obj->dolifarm_seed_status !== '') {
				$statuses[(int) $obj->fk_object] = (string) $obj->dolifarm_seed_status;
			}
		}
		$this->db->free($resql);
		return $statuses;
	}

	/**
	 * Organic status of the lots made by validated harvests
	 *
	 * @param	int[]	$products	Products
	 * @return	array<string,string>	"product id|batch" => BIO, INCONV, NONBIO
	 */
	private function getHarvestLotStatuses($products)
	{
		$statuses = array();
		if (empty($products)) {
			return $statuses;
		}
		$sql = "SELECT fk_product_out, batch_number, bio_status FROM ".$this->db->prefix()."dolitrace_harvest";
		$sql .= " WHERE status = 1 AND bio_status IS NOT NULL AND bio_status <> ''";
		$sql .= " AND fk_product_out IN (".$this->db->sanitize(implode(',', array_map('intval', $products))).")";
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$statuses[(int) $obj->fk_product_out.'|'.(string) $obj->batch_number] = (string) $obj->bio_status;
			}
			$this->db->free($resql);
		}
		return $statuses;
	}

	/**
	 * Documents at the origin of the movements, with their third party and its organic certificate
	 *
	 * @param	array<string,int[]>	$origins	Origin type => ids
	 * @return	array<string,array<int,array<string,string>>>	Origin type => id => ref, thirdparty, cert, body
	 */
	private function getOriginDocuments($origins)
	{
		$tables = array(
			'shipping' => 'expedition', 'commande' => 'commande', 'facture' => 'facture',
			'reception' => 'reception', 'order_supplier' => 'commande_fournisseur', 'invoice_supplier' => 'facture_fourn',
			'dolitraceharvest@dolitrace' => 'dolitrace_harvest', 'dolitracetreatment@dolitrace' => 'dolitrace_treatment',
		);
		$prefix = $this->db->prefix();
		$documents = array();
		foreach ($origins as $type => $ids) {
			if (!isset($tables[$type]) || empty($ids)) {
				continue;
			}
			$table = $tables[$type];
			$withsoc = !in_array($type, array('dolitraceharvest@dolitrace', 'dolitracetreatment@dolitrace'));
			$sql = "SELECT d.rowid, d.ref";
			if ($withsoc) {
				$sql .= ", s.nom as thirdparty, se.dolifarm_bio_cert, se.dolifarm_bio_body";
			}
			$sql .= " FROM ".$prefix.$table." as d";
			if ($withsoc) {
				$sql .= " LEFT JOIN ".$prefix."societe as s ON s.rowid = d.fk_soc";
				$sql .= " LEFT JOIN ".$prefix."societe_extrafields as se ON se.fk_object = s.rowid";
			}
			$sql .= " WHERE d.rowid IN (".$this->db->sanitize(implode(',', array_map('intval', $ids))).")";
			$resql = $this->db->query($sql);
			if (!$resql && $withsoc) {
				// Extrafields of the certificate not created yet: third party only
				$sql = "SELECT d.rowid, d.ref, s.nom as thirdparty, '' as dolifarm_bio_cert, '' as dolifarm_bio_body FROM ".$prefix.$table." as d";
				$sql .= " LEFT JOIN ".$prefix."societe as s ON s.rowid = d.fk_soc";
				$sql .= " WHERE d.rowid IN (".$this->db->sanitize(implode(',', array_map('intval', $ids))).")";
				$resql = $this->db->query($sql);
			}
			if (!$resql) {
				dol_syslog(__METHOD__.' '.$this->db->lasterror(), LOG_ERR);
				continue;
			}
			while ($obj = $this->db->fetch_object($resql)) {
				$documents[$type][(int) $obj->rowid] = array(
					'ref' => (string) $obj->ref,
					'thirdparty' => $withsoc ? (string) $obj->thirdparty : '',
					'cert' => $withsoc ? (string) $obj->dolifarm_bio_cert : '',
					'body' => $withsoc ? (string) $obj->dolifarm_bio_body : '',
				);
			}
			$this->db->free($resql);
		}
		return $documents;
	}

	/**
	 * Annual mass balance by crop and organic status (Reg. EU 2021/771 art. 1.5): estimated yield of the crop plans
	 * of the season, validated harvests of the year, and for their lots the quantity sold (shipments, invoices, orders),
	 * the stock at the end of the year and the rest (losses and other outputs); with the harvest of the previous year.
	 * Harvests without lot, or whose lot has no stock movement, are counted apart (untracked) and not in the losses.
	 *
	 * @param	User	$user		User
	 * @param	int		$socid		Farm, 0 for all the farms of the user
	 * @param	int		$year		Year
	 * @return	array<int,array<string,mixed>>|int	Lines, <0 if KO
	 */
	public function getMassBalance(User $user, $socid, $year)
	{
		$current = $this->getYearBalance($user, $socid, (int) $year);
		$previous = $this->getYearBalance($user, $socid, (int) $year - 1);
		if (!is_array($current) || !is_array($previous)) {
			return -1;
		}
		foreach ($previous as $key => $line) {
			if (!isset($current[$key])) {
				$current[$key] = array_merge($line, array('estimated' => 0.0, 'harvested' => 0.0, 'untracked' => 0.0, 'sold' => 0.0, 'stock' => 0.0, 'losses' => 0.0, 'lots' => array()));
			}
		}
		$lines = array();
		foreach ($current as $key => $line) {
			$prev = isset($previous[$key]) ? $previous[$key]['harvested'] : 0.0;
			$line['harvested_previous'] = $prev;
			$line['change_percent'] = ($prev > 0 ? round(($line['harvested'] - $prev) / $prev * 100, 1) : null);
			$line['yield_percent'] = ($line['estimated'] > 0 ? round($line['harvested'] / $line['estimated'] * 100, 1) : null);
			$line['lots'] = array_values($line['lots']);
			$lines[] = $line;
		}
		usort($lines, function ($a, $b) {
			return array($a['crop'], $a['bio_status']) <=> array($b['crop'], $b['bio_status']);
		});
		return $lines;
	}

	/**
	 * Mass balance of one year, by crop, status and unit
	 *
	 * @param	User	$user		User
	 * @param	int		$socid		Farm, 0 for all
	 * @param	int		$year		Year
	 * @return	array<string,array<string,mixed>>|int	"crop|status|unit" => line, <0 if KO
	 */
	private function getYearBalance(User $user, $socid, $year)
	{
		$prefix = $this->db->prefix();
		$endofyear = dol_mktime(23, 59, 59, 12, 31, $year);
		$lines = array();
		$add = function ($cropid, $crop, $status, $unit) use (&$lines) {
			$key = $cropid.'|'.$status.'|'.$unit;
			if (!isset($lines[$key])) {
				$lines[$key] = array('crop_id' => (int) $cropid, 'crop' => (string) $crop, 'bio_status' => (string) $status, 'unit' => (string) $unit,
					'estimated' => 0.0, 'harvested' => 0.0, 'untracked' => 0.0, 'sold' => 0.0, 'stock' => 0.0, 'losses' => 0.0, 'lots' => array());
			}
			return $key;
		};

		// Harvests of the year
		$sql = "SELECT h.rowid, h.qty_harvested, h.qty_unit, h.fk_product_out, h.batch_number, h.bio_status, h.date_harvest,";
		$sql .= " cp.fk_crop, cp.fk_plot, c.label as crop_label";
		$sql .= " FROM ".$prefix."dolitrace_harvest as h";
		$sql .= " INNER JOIN ".$prefix."dolitrace_cropplan as cp ON cp.rowid = h.fk_cropplan";
		$sql .= " LEFT JOIN ".$prefix."dolifarm_crops as c ON c.rowid = cp.fk_crop";
		$sql .= " WHERE h.status = 1 AND h.entity IN (".getEntity('dolitraceharvest').")";
		$sql .= " AND h.date_harvest BETWEEN '".$this->db->idate(dol_mktime(0, 0, 0, 1, 1, $year))."' AND '".$this->db->idate($endofyear)."'";
		if ($socid > 0) {
			$sql .= " AND cp.fk_soc = ".((int) $socid);
		}
		$sql .= dolifarmSqlSocFilter($user, 'cp.fk_soc');
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$units = array();
		$lots = array();
		$lotqty = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$status = (string) $obj->bio_status;
			if ($status === '' && (int) $obj->fk_plot > 0) {
				$status = dolifarmBioProductStatus(dolifarmBioStatusAt($this->db, (int) $obj->fk_plot, $this->db->jdate($obj->date_harvest)));
			}
			$unit = (string) $obj->qty_unit;
			$key = $add((int) $obj->fk_crop, $obj->crop_label, $status, $unit);
			$units[(int) $obj->fk_crop.'|'.$status][$unit] = $unit;
			$lines[$key]['harvested'] += (float) $obj->qty_harvested;
			if ((int) $obj->fk_product_out > 0 && (string) $obj->batch_number !== '') {
				$lotkey = (int) $obj->fk_product_out.'|'.(string) $obj->batch_number;
				$lines[$key]['lots'][$lotkey] = (string) $obj->batch_number;
				$lots[$lotkey] = $key;
				$lotqty[$lotkey] = ($lotqty[$lotkey] ?? 0.0) + (float) $obj->qty_harvested;
			} else {
				// Harvest without lot: it cannot be followed in the stock
				$lines[$key]['untracked'] += (float) $obj->qty_harvested;
			}
		}
		$this->db->free($resql);

		// Estimated yield of the crop plans of the season, in the unit of the harvests of the same crop and status
		$sql = "SELECT cp.fk_crop, cp.fk_plot, cp.estimated_yield, cp.date_start, cp.date_end, c.label as crop_label";
		$sql .= " FROM ".$prefix."dolitrace_cropplan as cp";
		$sql .= " LEFT JOIN ".$prefix."dolifarm_crops as c ON c.rowid = cp.fk_crop";
		$sql .= " WHERE cp.season = ".((int) $year)." AND cp.status IN (1, 2) AND cp.entity IN (".getEntity('dolitracecropplan').")";
		if ($socid > 0) {
			$sql .= " AND cp.fk_soc = ".((int) $socid);
		}
		$sql .= dolifarmSqlSocFilter($user, 'cp.fk_soc');
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$date = $obj->date_end ? $this->db->jdate($obj->date_end) : $endofyear;
			$status = ((int) $obj->fk_plot > 0 ? dolifarmBioProductStatus(dolifarmBioStatusAt($this->db, (int) $obj->fk_plot, $date)) : '');
			$unitsofcrop = $units[(int) $obj->fk_crop.'|'.$status] ?? array();
			$key = $add((int) $obj->fk_crop, $obj->crop_label, $status, count($unitsofcrop) ? reset($unitsofcrop) : '');
			$lines[$key]['estimated'] += (float) $obj->estimated_yield;
		}
		$this->db->free($resql);

		// Movements of the lots of the harvests until the end of the year: sold and stock, the rest are losses
		if (count($lots) && isModEnabled('stock')) {
			$conditions = array();
			foreach (array_keys($lots) as $lotkey) {
				list($productid, $batch) = explode('|', $lotkey, 2);
				$conditions[] = "(fk_product = ".((int) $productid)." AND batch = '".$this->db->escape($batch)."')";
			}
			foreach (array_chunk($conditions, 200) as $chunk) {
				$sql = "SELECT fk_product, batch,";
				$sql .= " SUM(CASE WHEN origintype IN ('".implode("','", self::SALE_TYPES)."') THEN -value ELSE 0 END) as sold,";
				$sql .= " SUM(value) as stock";
				$sql .= " FROM ".$prefix."stock_mouvement";
				$sql .= " WHERE datem <= '".$this->db->idate($endofyear)."' AND (".implode(' OR ', $chunk).")";
				$sql .= " GROUP BY fk_product, batch";
				$resql = $this->db->query($sql);
				if (!$resql) {
					$this->error = $this->db->lasterror();
					return -1;
				}
				while ($obj = $this->db->fetch_object($resql)) {
					$lotkey = (int) $obj->fk_product.'|'.(string) $obj->batch;
					if (isset($lots[$lotkey])) {
						$lines[$lots[$lotkey]]['sold'] += (float) $obj->sold;
						$lines[$lots[$lotkey]]['stock'] += (float) $obj->stock;
						unset($lotqty[$lotkey]);
					}
				}
				$this->db->free($resql);
			}
		}
		// Lots without stock movements (stock not used at the harvest): not followed either
		foreach ($lotqty as $lotkey => $qty) {
			$lines[$lots[$lotkey]]['untracked'] += $qty;
		}
		foreach ($lines as $key => $line) {
			$lines[$key]['losses'] = round($line['harvested'] - $line['untracked'] - $line['sold'] - $line['stock'], 6);
		}
		return $lines;
	}

	/**
	 * Annual summary by plot, in place of the annual production programme (PAP, abrogated in 2025): organic status of
	 * the plot at the end of the year, crop plans of the season with their surface, estimated yield and harvest
	 *
	 * @param	User	$user		User
	 * @param	int		$socid		Farm, 0 for all
	 * @param	int		$year		Year
	 * @return	array<int,array<string,mixed>>|int	Lines, <0 if KO
	 */
	public function getPlotSummary(User $user, $socid, $year)
	{
		$prefix = $this->db->prefix();
		$endofyear = dol_mktime(23, 59, 59, 12, 31, (int) $year);
		$labels = $this->getBioStatusLabels();

		// Crop plans of the season, with their validated harvests
		$plans = array();
		$sql = "SELECT cp.rowid, cp.ref, cp.fk_plot, cp.estimated_yield, c.label as crop_label,";
		$sql .= " (SELECT SUM(h.qty_harvested) FROM ".$prefix."dolitrace_harvest as h WHERE h.fk_cropplan = cp.rowid AND h.status = 1) as harvested,";
		$sql .= " (SELECT MAX(h.qty_unit) FROM ".$prefix."dolitrace_harvest as h WHERE h.fk_cropplan = cp.rowid AND h.status = 1) as unit";
		$sql .= " FROM ".$prefix."dolitrace_cropplan as cp";
		$sql .= " LEFT JOIN ".$prefix."dolifarm_crops as c ON c.rowid = cp.fk_crop";
		$sql .= " WHERE cp.season = ".((int) $year)." AND cp.status IN (1, 2) AND cp.entity IN (".getEntity('dolitracecropplan').")";
		if ($socid > 0) {
			$sql .= " AND cp.fk_soc = ".((int) $socid);
		}
		$sql .= dolifarmSqlSocFilter($user, 'cp.fk_soc');
		$sql .= " ORDER BY cp.ref";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$plans[(int) $obj->fk_plot][] = $obj;
		}
		$this->db->free($resql);

		// Active plots of the farm, and the plots of the crop plans
		$sql = "SELECT p.rowid, p.ref, p.label, p.fk_soc, p.fk_status_bio, p.size_sau, p.size_conducted, p.size_total, s.nom as farm_name";
		$sql .= " FROM ".$prefix."dolifarm_plot as p";
		$sql .= " LEFT JOIN ".$prefix."societe as s ON s.rowid = p.fk_soc";
		$sql .= " WHERE p.entity IN (".getEntity('dolifarmplot').")";
		$sql .= " AND (p.status = 1".(count($plans) ? " OR p.rowid IN (".$this->db->sanitize(implode(',', array_map('intval', array_keys($plans)))).")" : "").")";
		if ($socid > 0) {
			$sql .= " AND p.fk_soc = ".((int) $socid);
		}
		$sql .= dolifarmSqlSocFilter($user, 'p.fk_soc');
		$sql .= " ORDER BY s.nom, p.ref";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$lines = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$code = dolifarmBioStatusAt($this->db, (int) $obj->rowid, $endofyear, (string) $obj->fk_status_bio);
			$surface = (float) $obj->size_sau > 0 ? (float) $obj->size_sau : ((float) $obj->size_conducted > 0 ? (float) $obj->size_conducted : (float) $obj->size_total);
			$base = array(
				'plot_id' => (int) $obj->rowid, 'plot_ref' => (string) $obj->ref, 'plot_label' => (string) $obj->label,
				'farm' => (string) $obj->farm_name, 'bio_code' => $code, 'bio_label' => $labels[$code] ?? $code,
				'bio_status' => dolifarmBioProductStatus($code), 'surface' => $surface,
				'cropplan' => '', 'crop' => '', 'estimated' => null, 'harvested' => null, 'unit' => '',
			);
			if (empty($plans[(int) $obj->rowid])) {
				$lines[] = $base;
				continue;
			}
			foreach ($plans[(int) $obj->rowid] as $plan) {
				$lines[] = array_merge($base, array(
					'cropplan' => (string) $plan->ref, 'crop' => (string) $plan->crop_label,
					'estimated' => $plan->estimated_yield !== null ? (float) $plan->estimated_yield : null,
					'harvested' => $plan->harvested !== null ? (float) $plan->harvested : null, 'unit' => (string) $plan->unit,
				));
			}
		}
		$this->db->free($resql);
		return $lines;
	}

	/**
	 * Labels of the dictionary of the organic statuses of the plots
	 *
	 * @return	array<string,string>	Code => label
	 */
	public function getBioStatusLabels()
	{
		$labels = array();
		$resql = $this->db->query("SELECT code, label FROM ".$this->db->prefix()."dolifarm_c_biostatus");
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$labels[(string) $obj->code] = (string) $obj->label;
			}
			$this->db->free($resql);
		}
		return $labels;
	}

	/**
	 * Section "organic" of the JSON export of the register (format 1.4): status and history of the plots at the end of
	 * the period, status of the lots moved in the period, mass balance and summary by plot of the year of the end
	 *
	 * @param	User		$user		User
	 * @param	int			$socid		Farm, 0 for all
	 * @param	int|string	$datefrom	First day (timestamp), '' for no limit
	 * @param	int|string	$dateto		Last day (timestamp), '' for today
	 * @return	array<string,mixed>|int	Section, <0 if KO
	 */
	public function getOrganicSection(User $user, $socid, $datefrom, $dateto)
	{
		$end = ($dateto !== '' && $dateto !== null) ? (int) $dateto : dol_now();
		$year = (int) dol_print_date($end, '%Y');
		$iso = function ($date) {
			return $date ? dol_print_date($date, '%Y-%m-%d') : '';
		};
		$summary = $this->getPlotSummary($user, $socid, $year);
		$balance = $this->getMassBalance($user, $socid, $year);
		if (!is_array($summary) || !is_array($balance)) {
			return -1;
		}
		$plots = array();
		foreach ($summary as $line) {
			if (isset($plots[$line['plot_id']])) {
				continue;
			}
			$history = array();
			foreach (dolifarmBioHistory($this->db, $line['plot_id']) as $period) {
				$history[] = array(
					'status' => (string) $period->status, 'date_start' => $iso($period->date_start), 'date_end' => $iso($period->date_end),
					'conversion_years' => $period->conversion_years !== null ? (int) $period->conversion_years : null,
					'reason' => (string) $period->reason, 'reference' => (string) $period->reference,
				);
			}
			$plots[$line['plot_id']] = array(
				'plot_ref' => $line['plot_ref'], 'plot_label' => $line['plot_label'],
				'bio_status' => dolifarmBioStatusAt($this->db, $line['plot_id'], $end), 'history' => $history,
			);
		}
		$lots = array();
		$movements = ($user->hasRight('stock', 'lire') ? $this->getMovements($this->getWarehouses($user, $socid), $datefrom, $dateto) : array());
		if (is_array($movements)) {
			foreach ($movements as $line) {
				if ($line['batch'] !== '') {
					$lots[$line['product_ref'].'|'.$line['batch']] = array('product_ref' => $line['product_ref'], 'batch' => $line['batch'], 'bio_status' => $line['bio_status']);
				}
			}
		}
		return array(
			'date' => $iso($end),
			'year' => $year,
			'plots' => array_values($plots),
			'lots' => array_values($lots),
			'mass_balance' => $balance,
			'plot_summary' => $summary,
		);
	}

	/**
	 * CSV of a register (separator ";", UTF-8 with BOM for spreadsheets)
	 *
	 * @param	string		$tab		movements, balance or plots
	 * @param	array<int,array<string,mixed>>	$lines	Lines of the register
	 * @param	Translate	$outputlangs	Language
	 * @return	string
	 */
	public function toCsv($tab, $lines, Translate $outputlangs)
	{
		$outputlangs->loadLangs(array('main', 'products', 'companies', 'productbatch', 'stocks', 'dolitrace@dolitrace', 'dolifarm@dolifarm'));
		$columns = $this->getColumns($tab);
		$fh = fopen('php://memory', 'w+');
		fwrite($fh, "\xEF\xBB\xBF");
		fputcsv($fh, array_map(function ($c) use ($outputlangs) {
			return $outputlangs->transnoentitiesnoconv($c[1]);
		}, $columns), ';', '"', '');
		foreach ($lines as $line) {
			$row = array();
			foreach ($columns as $column) {
				$row[] = $this->formatValue($column[0], $line, $outputlangs, true);
			}
			fputcsv($fh, $row, ';', '"', '');
		}
		rewind($fh);
		$csv = stream_get_contents($fh);
		fclose($fh);
		return (string) $csv;
	}

	/**
	 * PDF of a register, in landscape
	 *
	 * @param	string		$tab		movements, balance or plots
	 * @param	array<int,array<string,mixed>>	$lines	Lines of the register
	 * @param	array<string,string>	$header	Lines of the heading: farm, period, filters
	 * @param	Translate	$outputlangs	Language
	 * @return	string					Content of the PDF
	 */
	public function toPdf($tab, $lines, $header, Translate $outputlangs)
	{
		global $mysoc;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
		$outputlangs->loadLangs(array('main', 'products', 'companies', 'productbatch', 'stocks', 'dolitrace@dolitrace', 'dolifarm@dolifarm'));
		$titles = array('movements' => 'BioRegisterMovements', 'balance' => 'BioRegisterBalance', 'plots' => 'BioRegisterPlots');
		$title = $outputlangs->transnoentities($titles[$tab] ?? 'BioRegister');

		$format = pdf_getFormat($outputlangs);
		$pdf = pdf_getInstance(array($format['width'], $format['height']), 'mm', 'L');
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetTitle($outputlangs->convToOutputCharset($title));
		$pdf->SetAuthor($outputlangs->convToOutputCharset($mysoc->name));
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(true);
		$pdf->SetMargins(8, 8, 8);
		$pdf->SetAutoPageBreak(true, 12);
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 7);
		$pdf->AddPage();

		$html = '<h3>'.dol_escape_htmltag($title).'</h3><p>';
		foreach ($header as $text) {
			if ((string) $text !== '') {
				$html .= dol_escape_htmltag($text).'<br>';
			}
		}
		$html .= dol_escape_htmltag($outputlangs->transnoentities('TreatmentPdfGenerated', dol_print_date(dol_now(), 'dayhour', 'tzuser', $outputlangs))).'</p>';

		$columns = $this->getColumns($tab);
		$html .= '<table border="0.3" cellpadding="2"><thead><tr style="background-color:#e8e8e8; font-weight:bold;">';
		foreach ($columns as $column) {
			$html .= '<th width="'.$column[2].'%"'.($column[3] ? ' align="right"' : '').'>'.dol_escape_htmltag($outputlangs->transnoentities($column[1])).'</th>';
		}
		$html .= '</tr></thead><tbody>';
		if (empty($lines)) {
			$html .= '<tr><td width="100%">'.dol_escape_htmltag($outputlangs->transnoentities('None')).'</td></tr>';
		}
		foreach ($lines as $line) {
			$html .= '<tr nobr="true">';
			foreach ($columns as $column) {
				$html .= '<td width="'.$column[2].'%"'.($column[3] ? ' align="right"' : '').'>'.dol_escape_htmltag($this->formatValue($column[0], $line, $outputlangs, false)).'</td>';
			}
			$html .= '</tr>';
		}
		$html .= '</tbody></table>';
		$pdf->writeHTML($html, true, false, true, false, '');
		return $pdf->Output('', 'S');
	}

	/**
	 * Columns of a register: key, translation key, width in the PDF (%), numeric
	 *
	 * @param	string	$tab	movements, balance or plots
	 * @return	array<int,array{0:string,1:string,2:int,3:bool}>
	 */
	public function getColumns($tab)
	{
		if ($tab == 'balance') {
			return array(
				array('crop', 'BioRegisterCrop', 16, false), array('bio_status', 'BioRegisterStatus', 9, false), array('unit', 'Unit', 5, false),
				array('estimated', 'BioRegisterEstimated', 9, true), array('harvested', 'BioRegisterHarvested', 9, true),
				array('yield_percent', 'BioRegisterYieldPercent', 7, true), array('untracked', 'BioRegisterUntracked', 8, true),
				array('sold', 'BioRegisterSold', 8, true), array('stock', 'BioRegisterStock', 8, true), array('losses', 'BioRegisterLosses', 8, true),
				array('harvested_previous', 'BioRegisterHarvestedPrevious', 8, true), array('change_percent', 'BioRegisterChangePercent', 8, true),
			);
		}
		if ($tab == 'plots') {
			return array(
				array('plot_ref', 'BioRegisterPlot', 10, false), array('plot_label', 'Label', 16, false), array('bio_label', 'BioRegisterPlotStatus', 14, false),
				array('surface', 'BioRegisterSurface', 8, true), array('cropplan', 'BioRegisterCropPlan', 12, false), array('crop', 'BioRegisterCrop', 14, false),
				array('estimated', 'BioRegisterEstimated', 9, true), array('harvested', 'BioRegisterHarvested', 9, true), array('unit', 'Unit', 8, false),
			);
		}
		return array(
			array('date', 'Date', 7, false), array('product', 'Product', 15, false), array('batch', 'Batch', 9, false),
			array('bio_status', 'BioRegisterStatus', 7, false), array('warehouse', 'Warehouse', 8, false), array('qty_in', 'BioRegisterIn', 6, true),
			array('qty_out', 'BioRegisterOut', 6, true), array('balance', 'BioRegisterBalanceQty', 6, true), array('origin', 'BioRegisterOrigin', 8, false),
			array('document', 'BioRegisterDocument', 9, false), array('thirdparty', 'ThirdParty', 11, false), array('thirdparty_cert', 'BioRegisterCertificate', 8, false),
		);
	}

	/**
	 * Value of a cell, for the page, the CSV (raw numbers, ISO dates) and the PDF
	 *
	 * @param	string		$key			Column
	 * @param	array<string,mixed>	$line	Line
	 * @param	Translate	$outputlangs	Language
	 * @param	bool		$raw			True for the CSV
	 * @return	string
	 */
	public function formatValue($key, $line, Translate $outputlangs, $raw)
	{
		$statuses = array('BIO' => 'SeedStatusBio', 'INCONV' => 'SeedStatusInConversion', 'NONBIO' => 'SeedStatusNonBio');
		switch ($key) {
			case 'date':
				return $line['date'] ? dol_print_date($line['date'], $raw ? '%Y-%m-%d' : 'day', 'tzserver', $outputlangs) : '';
			case 'product':
				return trim($line['product_ref'].' - '.$line['product_label'], ' -');
			case 'bio_status':
				return $line['bio_status'] === '' ? '' : ($raw ? $line['bio_status'] : $outputlangs->transnoentities($statuses[$line['bio_status']] ?? $line['bio_status']));
			case 'origin':
				return $raw ? $line['origin'] : $outputlangs->transnoentities('BioRegisterOrigin'.ucfirst(strtolower($line['origin'])));
			case 'thirdparty_cert':
				return trim($line['thirdparty_cert'].($line['thirdparty_body'] !== '' ? ' ('.$line['thirdparty_body'].')' : ''));
			case 'qty_in':
			case 'qty_out':
			case 'balance':
			case 'estimated':
			case 'harvested':
			case 'untracked':
			case 'sold':
			case 'stock':
			case 'losses':
			case 'harvested_previous':
			case 'surface':
			case 'yield_percent':
			case 'change_percent':
				if (!isset($line[$key]) || $line[$key] === null) {
					return '';
				}
				return $raw ? (string) round((float) $line[$key], 6) : price((float) $line[$key], 0, $outputlangs, 0, 0, -1).(str_ends_with($key, '_percent') ? ' %' : '');
			default:
				return (string) ($line[$key] ?? '');
		}
	}
}
