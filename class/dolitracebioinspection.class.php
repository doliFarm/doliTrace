<?php
/* Copyright (C) 2026		Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file        class/dolitracebioinspection.class.php
 * \ingroup     dolitrace
 * \brief       Inspection of the organic control body
 *
 * Closing an inspection freezes the registers of the farm up to its date (see lib/dolitrace_inspection.lib.php) and
 * saves a read only copy of the organic registers of the year of the inspection and of the year before (one row per
 * register and year, with its SHA-256) and the PDF dossiers in the documents of the inspection. Only a user with the
 * permission "reopen" can reopen it, with a reason; the copies of the earlier closures are kept.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
dol_include_once('/dolitrace/lib/dolitrace_inspection.lib.php');

/**
 * Inspection of the organic control body
 */
class DoliTraceBioInspection extends CommonObject
{
	/**
	 * @var string
	 */
	public $module = 'dolitrace';

	/**
	 * @var string
	 */
	public $element = 'dolitracebioinspection';

	/**
	 * @var string
	 */
	public $table_element = 'dolitrace_bioinspection';

	/**
	 * @var string
	 */
	public $TRIGGER_PREFIX = 'DOLITRACE_BIOINSPECTION';

	/**
	 * @var string
	 */
	public $picto = 'fa-clipboard-check';

	/**
	 * @var int
	 */
	public $isextrafieldmanaged = 0;

	/**
	 * @var int<0,1>|string
	 */
	public $ismultientitymanaged = 1;

	const STATUS_DRAFT = 0;
	const STATUS_CLOSED = 1;

	/**
	 * @var array<string,array<string,mixed>>
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => 0, 'default' => '1'),
		'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 4, 'noteditable' => 1, 'showoncombobox' => 1, 'csslist' => 'tdoverflowmax150'),
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php:1', 'label' => 'TreatmentFarm', 'picto' => 'company', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'csslist' => 'tdoverflowmax150'),
		'date_inspection' => array('type' => 'date', 'label' => 'BioInspectionDate', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1, 'help' => 'BioInspectionDateHelp'),
		'fk_soc_body' => array('type' => 'integer:Societe:societe/class/societe.class.php:1', 'label' => 'BioInspectionBody', 'picto' => 'company', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'csslist' => 'tdoverflowmax150', 'help' => 'BioInspectionBodyHelp'),
		'inspector' => array('type' => 'varchar(255)', 'label' => 'BioInspectionInspector', 'enabled' => 1, 'position' => 45, 'notnull' => 0, 'visible' => 1, 'css' => 'minwidth300'),
		'inspection_type' => array('type' => 'select', 'label' => 'BioInspectionType', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1, 'default' => 'ANNUAL',
			'arrayofkeyval' => array('ANNUAL' => 'BioInspectionTypeANNUAL', 'ADDITIONAL' => 'BioInspectionTypeADDITIONAL', 'UNANNOUNCED' => 'BioInspectionTypeUNANNOUNCED')),
		'samples' => array('type' => 'boolean', 'label' => 'BioInspectionSamples', 'enabled' => 1, 'position' => 55, 'notnull' => 0, 'visible' => 1, 'default' => '0'),
		'outcome' => array('type' => 'select', 'label' => 'BioInspectionOutcome', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1, 'default' => 'PENDING',
			'arrayofkeyval' => array('PENDING' => 'BioInspectionOutcomePENDING', 'COMPLIANT' => 'BioInspectionOutcomeCOMPLIANT', 'NONCONFORM' => 'BioInspectionOutcomeNONCONFORM')),
		'report_ref' => array('type' => 'varchar(128)', 'label' => 'BioInspectionReport', 'enabled' => 1, 'position' => 65, 'notnull' => 0, 'visible' => 1, 'help' => 'BioInspectionReportHelp'),
		'note_public' => array('type' => 'html', 'label' => 'NotePublic', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => 0),
		'note_private' => array('type' => 'html', 'label' => 'NotePrivate', 'enabled' => 1, 'position' => 71, 'notnull' => 0, 'visible' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => -2),
		'date_close' => array('type' => 'datetime', 'label' => 'BioInspectionDateClose', 'enabled' => 1, 'position' => 505, 'notnull' => 0, 'visible' => -2, 'noteditable' => 1),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'fk_user_close' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'BioInspectionUserClose', 'enabled' => 1, 'position' => 512, 'notnull' => 0, 'visible' => -2, 'noteditable' => 1),
		'last_main_doc' => array('type' => 'varchar(255)', 'label' => 'LastMainDoc', 'enabled' => 1, 'position' => 600, 'notnull' => 0, 'visible' => 0),
		'model_pdf' => array('type' => 'varchar(255)', 'label' => 'Model pdf', 'enabled' => 1, 'position' => 1010, 'notnull' => 0, 'visible' => 0),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => 0, 'visible' => -2),
		'status' => array('type' => 'smallint', 'label' => 'Status', 'enabled' => 1, 'position' => 1000, 'notnull' => 1, 'visible' => 5, 'default' => '0', 'index' => 1, 'noteditable' => 1,
			'arrayofkeyval' => array('0' => 'BioInspectionStatusDraft', '1' => 'BioInspectionStatusClosed')),
	);

	/**
	 * @var int
	 */
	public $rowid;

	/**
	 * @var string
	 */
	public $ref;

	/**
	 * @var int
	 */
	public $fk_soc;

	/**
	 * @var int|string
	 */
	public $date_inspection;

	/**
	 * @var ?int
	 */
	public $fk_soc_body;

	/**
	 * @var ?string
	 */
	public $inspector;

	/**
	 * @var ?string
	 */
	public $inspection_type;

	/**
	 * @var int
	 */
	public $samples;

	/**
	 * @var ?string
	 */
	public $outcome;

	/**
	 * @var ?string
	 */
	public $report_ref;

	/**
	 * @var int|string|null
	 */
	public $date_close;

	/**
	 * @var ?int
	 */
	public $fk_user_close;

	/**
	 * @var int
	 */
	public $status;

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db		Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $langs;

		$this->db = $db;
		if (!getDolGlobalInt('MAIN_SHOW_TECHNICAL_ID') && isset($this->fields['rowid'])) {
			$this->fields['rowid']['visible'] = 0;
		}
		foreach ($this->fields as $key => $val) {
			if (isset($val['enabled']) && empty($val['enabled'])) {
				unset($this->fields[$key]);
			}
		}
		if (is_object($langs)) {
			foreach ($this->fields as $key => $val) {
				if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
					foreach ($val['arrayofkeyval'] as $key2 => $val2) {
						$this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
					}
				}
			}
		}
		// Farms: the managed farms only
		if (dol_include_once('/dolifarm/lib/dolifarm_access.lib.php') && function_exists('dolifarmRestrictLinkFields')) {
			dolifarmRestrictLinkFields($this, array());
		}
	}

	/**
	 * Create the inspection (draft) with its reference ISP-YYYY-NNN
	 *
	 * @param	User		$user		User
	 * @param	int<0,1>	$notrigger	1=no triggers
	 * @return	int						Id if OK, <0 if KO
	 */
	public function create(User $user, $notrigger = 0)
	{
		global $conf;

		if (empty($this->entity)) {
			$this->entity = $conf->entity;
		}
		$this->status = self::STATUS_DRAFT;
		$this->samples = (int) $this->samples;
		$ts = dolitraceFrozenTimestamp($this->date_inspection);
		$year = (int) dol_print_date($ts ?: dol_now(), '%Y');
		$prefix = 'ISP-'.$year.'-';
		$num = 0;
		$sql = "SELECT ref FROM ".$this->db->prefix().$this->table_element." WHERE ref LIKE '".$this->db->escape($prefix)."%' AND entity = ".((int) $this->entity);
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$num = max($num, (int) substr((string) $obj->ref, strlen($prefix)));
			}
			$this->db->free($resql);
		}
		$this->ref = $prefix.sprintf('%03d', $num + 1);
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Load the inspection
	 *
	 * @param	int		$id		Id
	 * @param	?string	$ref	Ref
	 * @return	int				>0 if OK, 0 if not found, <0 if KO
	 */
	public function fetch($id, $ref = null)
	{
		return $this->fetchCommon($id, $ref);
	}

	/**
	 * Update the inspection: only a draft
	 *
	 * @param	User		$user		User
	 * @param	int<0,1>	$notrigger	1=no triggers
	 * @return	int						>0 if OK, <0 if KO
	 */
	public function update(User $user, $notrigger = 0)
	{
		global $langs;

		if ((int) $this->status != self::STATUS_DRAFT) {
			$this->error = $langs->trans('ErrorBioInspectionClosed');
			return -1;
		}
		$this->samples = (int) $this->samples;
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete the inspection: only a draft never closed (the copies of the registers are kept with the inspection)
	 *
	 * @param	User		$user		User
	 * @param	int<0,1>	$notrigger	1=no triggers
	 * @return	int						>0 if OK, <0 if KO
	 */
	public function delete(User $user, $notrigger = 0)
	{
		global $langs;

		if ((int) $this->status != self::STATUS_DRAFT || $this->getClosureCount() > 0) {
			$this->error = $langs->trans('ErrorBioInspectionDeleteClosed');
			return -1;
		}
		return $this->deleteCommon($user, $notrigger);
	}

	/**
	 * Number of closures of the inspection
	 *
	 * @return	int
	 */
	public function getClosureCount()
	{
		$nb = 0;
		$resql = $this->db->query("SELECT MAX(closure) as nb FROM ".$this->db->prefix()."dolitrace_bioinspection_snapshot WHERE fk_inspection = ".((int) $this->id));
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			$nb = ($obj ? (int) $obj->nb : 0);
			$this->db->free($resql);
		}
		return $nb;
	}

	/**
	 * Years copied at the closure: the year of the inspection and the year before
	 *
	 * @return	int[]
	 */
	public function getSnapshotYears()
	{
		$year = (int) dol_print_date(dolitraceFrozenTimestamp($this->date_inspection), '%Y');
		return array($year - 1, $year);
	}

	/**
	 * Draft records dated on or before the inspection, which the closure would freeze as drafts
	 *
	 * @return	array<string,int>	Number of drafts by kind (treatments, operations, harvests)
	 */
	public function countDraftsToFreeze()
	{
		$prefix = $this->db->prefix();
		$day = (int) dolitraceFrozenTimestamp($this->date_inspection);
		$date = $this->db->idate(dol_mktime(23, 59, 59, (int) dol_print_date($day, '%m'), (int) dol_print_date($day, '%d'), (int) dol_print_date($day, '%Y')));
		$queries = array(
			'treatments' => "SELECT COUNT(rowid) as nb FROM ".$prefix."dolitrace_treatment WHERE fk_soc = ".((int) $this->fk_soc)." AND status = 0 AND (fk_previous IS NULL OR fk_previous = 0) AND date_start <= '".$date."'",
			'operations' => "SELECT COUNT(o.rowid) as nb FROM ".$prefix."dolitrace_operation as o INNER JOIN ".$prefix."dolitrace_cropplan as cp ON cp.rowid = o.fk_cropplan WHERE cp.fk_soc = ".((int) $this->fk_soc)." AND (o.status = 0 OR o.status IS NULL) AND o.date_op <= '".$date."'",
			'harvests' => "SELECT COUNT(h.rowid) as nb FROM ".$prefix."dolitrace_harvest as h INNER JOIN ".$prefix."dolitrace_cropplan as cp ON cp.rowid = h.fk_cropplan WHERE cp.fk_soc = ".((int) $this->fk_soc)." AND h.status = 0 AND h.date_harvest <= '".$date."'",
		);
		$counts = array();
		foreach ($queries as $key => $sql) {
			$counts[$key] = 0;
			$resql = $this->db->query($sql);
			if ($resql) {
				$obj = $this->db->fetch_object($resql);
				$counts[$key] = ($obj ? (int) $obj->nb : 0);
				$this->db->free($resql);
			}
		}
		return $counts;
	}

	/**
	 * Close the inspection: freeze the registers of the farm up to its date, copy the organic registers and save the
	 * PDF dossiers in the documents of the inspection
	 *
	 * @param	User		$user		User (the copy contains the registers this user can read)
	 * @param	Translate	$outputlangs	Languages of the copy
	 * @return	int						>0 if OK, <0 if KO
	 */
	public function close(User $user, Translate $outputlangs)
	{
		global $conf, $langs;

		if ((int) $this->status != self::STATUS_DRAFT) {
			$this->error = $langs->trans('ErrorBioInspectionClosed');
			return -1;
		}
		dol_include_once('/dolitrace/class/dolitracebioregister.class.php');
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

		$register = new DoliTraceBioRegister($this->db);
		$closure = $this->getClosureCount() + 1;
		$now = dol_now();
		$header = $register->getFarmHeader((int) $this->fk_soc, $outputlangs);
		$header[] = $outputlangs->transnoentities('BioInspectionSnapshotHeader', $this->ref, dol_print_date(dolitraceFrozenTimestamp($this->date_inspection), 'day', 'tzserver', $outputlangs), dol_print_date($now, 'dayhour', 'tzserver', $outputlangs));

		$this->db->begin();
		$error = 0;
		$copies = array();
		foreach ($this->getSnapshotYears() as $year) {
			foreach (DoliTraceBioRegister::getAllowedTabs($user) as $tab) {
				$lines = $register->getLines($tab, $user, (int) $this->fk_soc, $year);
				if (!is_array($lines)) {
					$this->error = $register->error;
					$error++;
					break 2;
				}
				$copies[$year][$tab] = $lines;
				$jsonheader = json_encode($header, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
				$jsoncontent = json_encode(array_values($lines), JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_PARTIAL_OUTPUT_ON_ERROR);
				$sql = "INSERT INTO ".$this->db->prefix()."dolitrace_bioinspection_snapshot (fk_inspection, closure, tab, year, header, content, hash, datec) VALUES (";
				$sql .= ((int) $this->id).", ".((int) $closure).", '".$this->db->escape($tab)."', ".((int) $year).", '".$this->db->escape($jsonheader)."', '".$this->db->escape($jsoncontent)."',";
				$sql .= " '".$this->db->escape(hash('sha256', $jsonheader.$jsoncontent))."', '".$this->db->idate($now)."')";
				if (!$this->db->query($sql)) {
					$this->error = $this->db->lasterror();
					$error++;
					break 2;
				}
			}
		}
		if (!$error) {
			$sql = "UPDATE ".$this->db->prefix().$this->table_element." SET status = ".self::STATUS_CLOSED.", date_close = '".$this->db->idate($now)."', fk_user_close = ".((int) $user->id);
			$sql .= " WHERE rowid = ".((int) $this->id)." AND status = ".self::STATUS_DRAFT;
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$error++;
			}
		}
		if (!$error && $this->addLog($user, 'CLOSE', $closure, '') < 0) {
			$error++;
		}
		if (!$error) {
			$this->status = self::STATUS_CLOSED;
			$this->date_close = $now;
			$this->fk_user_close = $user->id;
			if ($this->call_trigger($this->TRIGGER_PREFIX.'_CLOSE', $user) < 0) {
				$error++;
			}
		}
		if ($error) {
			$this->db->rollback();
			$this->status = self::STATUS_DRAFT;
			return -1;
		}
		$this->db->commit();
		dolitraceFrozenInspection($this->db, (int) $this->fk_soc, true);

		// PDF dossiers of the copy in the documents of the inspection (not blocking)
		$dir = $conf->dolitrace->dir_output.'/'.$this->element.'/'.dol_sanitizeFileName($this->ref);
		if (dol_mkdir($dir) >= 0) {
			foreach ($copies as $year => $tabs) {
				$content = $register->snapshotDossierPdf($year, $header, $tabs, $outputlangs);
				$file = $dir.'/'.dol_sanitizeFileName('registri_bio_'.$year.'_chiusura_'.$closure.'.pdf');
				if (is_string($content) && file_put_contents($file, $content) !== false) {
					dolChmod($file);
				} else {
					dol_syslog(__METHOD__.' PDF of the copy not saved: '.$file, LOG_ERR);
				}
			}
		}
		return 1;
	}

	/**
	 * Reopen a closed inspection, with its reason: the registers are no more frozen by it, the copies stay
	 *
	 * @param	User	$user		User (permission dolitracebioinspection reopen, checked by the caller)
	 * @param	string	$reason		Reason
	 * @return	int					>0 if OK, <0 if KO
	 */
	public function reopenWithReason(User $user, $reason)
	{
		global $langs;

		if ((int) $this->status != self::STATUS_CLOSED) {
			return 0;
		}
		if (trim($reason) === '') {
			$langs->load('dolitrace@dolitrace');
			$this->error = $langs->trans('ErrorTreatmentReasonRequired');
			return -1;
		}
		$this->db->begin();
		$error = 0;
		$sql = "UPDATE ".$this->db->prefix().$this->table_element." SET status = ".self::STATUS_DRAFT.", fk_user_modif = ".((int) $user->id);
		$sql .= " WHERE rowid = ".((int) $this->id)." AND status = ".self::STATUS_CLOSED;
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$error++;
		}
		if (!$error && $this->addLog($user, 'REOPEN', $this->getClosureCount(), $reason) < 0) {
			$error++;
		}
		if (!$error) {
			$this->status = self::STATUS_DRAFT;
			if ($this->call_trigger($this->TRIGGER_PREFIX.'_REOPEN', $user) < 0) {
				$error++;
			}
		}
		if ($error) {
			$this->db->rollback();
			$this->status = self::STATUS_CLOSED;
			return -1;
		}
		$this->db->commit();
		dolitraceFrozenInspection($this->db, (int) $this->fk_soc, true);
		return 1;
	}

	/**
	 * Add a line to the log of the inspection
	 *
	 * @param	User	$user		User
	 * @param	string	$action		CLOSE or REOPEN
	 * @param	int		$closure	Number of the closure
	 * @param	string	$reason		Reason
	 * @return	int					>0 if OK, <0 if KO
	 */
	private function addLog(User $user, $action, $closure, $reason)
	{
		$sql = "INSERT INTO ".$this->db->prefix()."dolitrace_bioinspection_log (fk_inspection, action, closure, reason, fk_user, datec) VALUES (";
		$sql .= ((int) $this->id).", '".$this->db->escape($action)."', ".((int) $closure).", ".($reason !== '' ? "'".$this->db->escape($reason)."'" : 'NULL').", ".((int) $user->id).", '".$this->db->idate(dol_now())."')";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Log of the closures and reopenings
	 *
	 * @return	array<int,stdClass>
	 */
	public function getLog()
	{
		$rows = array();
		$resql = $this->db->query("SELECT action, closure, reason, fk_user, datec FROM ".$this->db->prefix()."dolitrace_bioinspection_log WHERE fk_inspection = ".((int) $this->id)." ORDER BY datec, rowid");
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$obj->datec = $this->db->jdate($obj->datec);
				$rows[] = $obj;
			}
			$this->db->free($resql);
		}
		return $rows;
	}

	/**
	 * Copies of the registers: by closure, the years and the registers with their hash
	 *
	 * @return	array<int,array<int,array<string,string>>>	closure => year => tab => hash
	 */
	public function getSnapshots()
	{
		$copies = array();
		$resql = $this->db->query("SELECT closure, year, tab, hash FROM ".$this->db->prefix()."dolitrace_bioinspection_snapshot WHERE fk_inspection = ".((int) $this->id)." ORDER BY closure, year, rowid");
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$copies[(int) $obj->closure][(int) $obj->year][(string) $obj->tab] = (string) $obj->hash;
			}
			$this->db->free($resql);
		}
		return $copies;
	}

	/**
	 * One register of a copy, with the check of its hash
	 *
	 * @param	int		$closure	Number of the closure
	 * @param	string	$tab		Register
	 * @param	int		$year		Year
	 * @return	?array{header:string[],lines:array<int,array<string,mixed>>,hash_ok:bool}	Null if not found
	 */
	public function getSnapshot($closure, $tab, $year)
	{
		$sql = "SELECT header, content, hash FROM ".$this->db->prefix()."dolitrace_bioinspection_snapshot";
		$sql .= " WHERE fk_inspection = ".((int) $this->id)." AND closure = ".((int) $closure)." AND tab = '".$this->db->escape($tab)."' AND year = ".((int) $year);
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : null;
		if (!$obj) {
			return null;
		}
		$header = json_decode((string) $obj->header, true);
		$lines = json_decode((string) $obj->content, true);
		return array(
			'header' => is_array($header) ? $header : array(),
			'lines' => is_array($lines) ? $lines : array(),
			'hash_ok' => hash_equals((string) $obj->hash, hash('sha256', (string) $obj->header.(string) $obj->content)),
		);
	}

	/**
	 * Clickable name of the inspection
	 *
	 * @param	int		$withpicto		Picto
	 * @param	string	$option			Not used
	 * @param	int		$notooltip		Not used
	 * @param	string	$morecss		CSS
	 * @param	int		$save_lastsearch_value	Not used
	 * @return	string
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		$url = dol_buildpath('/dolitrace/dolitracebioinspection_card.php', 1).'?id='.$this->id;
		$result = '<a href="'.$url.'" class="classfortooltip'.($morecss ? ' '.$morecss : '').'">';
		if ($withpicto) {
			$result .= img_picto('', $this->picto, 'class="pictofixedwidth"');
		}
		if ($withpicto != 2) {
			$result .= dol_escape_htmltag($this->ref);
		}
		$result .= '</a>';
		return $result;
	}

	/**
	 * Label of the status
	 *
	 * @param	int		$mode	Mode
	 * @return	string
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut((int) $this->status, $mode);
	}

	/**
	 * Label of a status
	 *
	 * @param	int		$status		Status
	 * @param	int		$mode		Mode
	 * @return	string
	 */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;

		$langs->load('dolitrace@dolitrace');
		$label = $langs->transnoentitiesnoconv($status == self::STATUS_CLOSED ? 'BioInspectionStatusClosed' : 'BioInspectionStatusDraft');
		return dolGetStatus($label, $label, '', $status == self::STATUS_CLOSED ? 'status6' : 'status0', $mode);
	}
}
