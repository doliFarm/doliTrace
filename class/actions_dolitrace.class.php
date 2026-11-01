<?php
/* Copyright (C) 2026		Luigi Grillo - doliFarm.com	<info@dolifarm.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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
 * \file    class/actions_dolitrace.class.php
 * \ingroup dolitrace
 * \brief   Hooks of doliTrace (home page of Dolibarr: group of the field register in the dashboard of open elements)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';

/**
 * Hooks of doliTrace
 */
class ActionsDolitrace extends CommonHookActions
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error
	 */
	public $error = '';

	/**
	 * @var string[] Errors
	 */
	public $errors = array();

	/**
	 * @var mixed[] Hook results, propagated to $hookmanager->resArray
	 */
	public $results = array();

	/**
	 * @var ?string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db		Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Home page of Dolibarr: lines of the draft records and harvests in the dashboard
	 *
	 * A draft record is late when the operation ended more than 30 days ago (D.Lgs. 150/2012 and Reg. EU 2023/564).
	 * Only the farms the user can see are counted.
	 *
	 * @param	array<string,mixed>	$parameters		Parameters
	 * @param	object				$object			Not used
	 * @param	string				$action			Not used
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int										0
	 */
	public function addOpenElementsDashboardLine($parameters, &$object, &$action, $hookmanager)
	{
		global $user, $langs;

		$this->results = array();
		if (!isModEnabled('dolitrace') || !in_array('index', explode(':', $parameters['context']))) {
			return 0;
		}
		require_once DOL_DOCUMENT_ROOT.'/core/class/workboardresponse.class.php';
		dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');
		dol_include_once('/dolitrace/lib/dolitrace_access.lib.php');
		$langs->load('dolitrace@dolitrace');
		$prefix = $this->db->prefix();

		if ($user->hasRight('dolitrace', 'dolitracetreatment', 'read')) {
			$limit = dol_time_plus_duree(dol_now(), -30, 'd');
			$sql = "SELECT COUNT(t.rowid) as nb, SUM(CASE WHEN COALESCE(t.date_end, t.date_start) < '".$this->db->idate($limit)."' THEN 1 ELSE 0 END) as nblate";
			$sql .= " FROM ".$prefix."dolitrace_treatment as t";
			$sql .= " WHERE t.entity IN (".getEntity('dolitracetreatment').") AND t.status = 0";
			$sql .= dolifarmSqlSocFilter($user, 't.fk_soc');
			$board = new WorkboardResponse();
			$board->img = img_picto('', 'fa-clipboard-list');
			$board->label = $langs->trans('DashboardTreatmentsDraft');
			$board->labelShort = $langs->trans('DashboardTreatmentsDraftShort');
			$board->url = dol_buildpath('/dolitrace/dolitracetreatment_list.php', 1).'?mainmenu=dolitrace&search_status=0';
			$board->warning_delay = 30;
			$this->countInto($board, $sql);
			$this->results['dolitrace_treatment_draft'] = $board;
		}

		if ($user->hasRight('dolitrace', 'dolitraceharvest', 'read')) {
			$sql = "SELECT COUNT(t.rowid) as nb, 0 as nblate";
			$sql .= " FROM ".$prefix."dolitrace_harvest as t";
			$sql .= " WHERE t.entity IN (".getEntity('dolitraceharvest').") AND t.status = 0";
			$sql .= dolitraceSqlCropplanSocFilter($user, 't.fk_cropplan');
			$board = new WorkboardResponse();
			$board->img = img_picto('', 'fa-tractor');
			$board->label = $langs->trans('DashboardHarvestsDraft');
			$board->url = dol_buildpath('/dolitrace/dolitraceharvest_list.php', 1).'?mainmenu=dolitrace&search_status=0';
			$this->countInto($board, $sql);
			$this->results['dolitrace_harvest_draft'] = $board;
		}
		return 0;
	}

	/**
	 * Home page of Dolibarr: group "Field register" of the dashboard of open elements
	 *
	 * @param	array<string,mixed>	$parameters		Parameters
	 * @param	object				$object			Not used
	 * @param	string				$action			Not used
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int										0
	 */
	public function addOpenElementsDashboardGroup($parameters, &$object, &$action, $hookmanager)
	{
		$this->results = array();
		if (!isModEnabled('dolitrace') || !in_array('index', explode(':', $parameters['context']))) {
			return 0;
		}
		$this->results = array(
			'dolitrace' => array(
				'lang' => 'dolitrace@dolitrace',
				'groupName' => 'DashboardFieldRegister',
				'stats' => array('dolitrace_treatment_draft', 'dolitrace_harvest_draft'),
			),
		);
		return 0;
	}

	/**
	 * Fill the counters of a line of the dashboard
	 *
	 * @param	WorkboardResponse	$board	Line
	 * @param	string				$sql	Query returning nb and nblate
	 * @return	void
	 */
	private function countInto($board, $sql)
	{
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' '.$this->db->lasterror(), LOG_ERR);
			return;
		}
		$obj = $this->db->fetch_object($resql);
		if ($obj) {
			$board->nbtodo = (int) $obj->nb;
			$board->nbtodolate = (int) $obj->nblate;
		}
		$this->db->free($resql);
	}
}
