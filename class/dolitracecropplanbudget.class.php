<?php
/* Copyright (C) 2026      Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
 * \file    class/dolitracecropplanbudget.class.php
 * \ingroup dolitrace
 * \brief   Line of the budget of a production plan: a planned operation with its costs per hectare
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Planned operation of a production plan
 */
class DoliTraceCropPlanBudget extends CommonObject
{
	/**
	 * @var string Module
	 */
	public $module = 'dolitrace';

	/**
	 * @var string Element
	 */
	public $element = 'dolitracecropplanbudget';

	/**
	 * @var string Table without prefix
	 */
	public $table_element = 'dolitrace_cropplan_budget';

	/**
	 * @var int<0,1>|string Multicompany
	 */
	public $ismultientitymanaged = 1;

	/**
	 * @var int<0,1> Extrafields
	 */
	public $isextrafieldmanaged = 0;

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => 0, 'default' => '1'),
		'fk_cropplan' => array('type' => 'integer', 'label' => 'CropPlan', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 0),
		'rang' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 15, 'notnull' => 0, 'visible' => 0),
		'fk_operation_type' => array('type' => 'varchar(32)', 'label' => 'OperationType', 'enabled' => 1, 'position' => 20, 'notnull' => 0, 'visible' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 25, 'notnull' => 0, 'visible' => 1),
		'passes' => array('type' => 'double(24,8)', 'label' => 'BudgetPasses', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1),
		'area_ha' => array('type' => 'double(24,8)', 'label' => 'OperationAreaHa', 'enabled' => 1, 'position' => 35, 'notnull' => 0, 'visible' => 1),
		'cost_manpower_ha' => array('type' => 'double(24,8)', 'label' => 'BudgetManpowerHa', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'cost_machine_ha' => array('type' => 'double(24,8)', 'label' => 'BudgetMachineHa', 'enabled' => 1, 'position' => 45, 'notnull' => 0, 'visible' => 1),
		'cost_service_ha' => array('type' => 'double(24,8)', 'label' => 'BudgetServiceHa', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'cost_material_ha' => array('type' => 'double(24,8)', 'label' => 'BudgetMaterialHa', 'enabled' => 1, 'position' => 55, 'notnull' => 0, 'visible' => 1),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => 0),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => 0),
		'fk_user_creat' => array('type' => 'integer', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => 0),
		'fk_user_modif' => array('type' => 'integer', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => 0),
	);

	/**
	 * @var int Id
	 */
	public $rowid;
	/**
	 * @var int Production plan
	 */
	public $fk_cropplan;
	/**
	 * @var int Order
	 */
	public $rang;
	/**
	 * @var string|null Type of operation
	 */
	public $fk_operation_type;
	/**
	 * @var float|string|null Number of passes
	 */
	public $passes;
	/**
	 * @var float|string|null Area in hectares
	 */
	public $area_ha;
	/**
	 * @var float|string|null Labour per hectare
	 */
	public $cost_manpower_ha;
	/**
	 * @var float|string|null Machines and fuel per hectare
	 */
	public $cost_machine_ha;
	/**
	 * @var float|string|null Services of third parties per hectare
	 */
	public $cost_service_ha;
	/**
	 * @var float|string|null Products per hectare
	 */
	public $cost_material_ha;
	/**
	 * @var int|null Author
	 */
	public $fk_user_creat;
	/**
	 * @var int|null Last modifier
	 */
	public $fk_user_modif;

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
	 * Create the line
	 *
	 * @param	User		$user		User
	 * @param	int<0,1>	$notrigger	1 = no trigger
	 * @return	int						Id if OK, <0 if KO
	 */
	public function create(User $user, $notrigger = 1)
	{
		global $conf;

		if (empty($this->entity)) {
			$this->entity = $conf->entity;
		}
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Load the line
	 *
	 * @param	int		$id		Id
	 * @return	int				>0 if OK, 0 if not found, <0 if KO
	 */
	public function fetch($id)
	{
		return $this->fetchCommon($id);
	}

	/**
	 * Update the line
	 *
	 * @param	User		$user		User
	 * @param	int<0,1>	$notrigger	1 = no trigger
	 * @return	int						>0 if OK, <0 if KO
	 */
	public function update(User $user, $notrigger = 1)
	{
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete the line
	 *
	 * @param	User		$user		User
	 * @param	int<0,1>	$notrigger	1 = no trigger
	 * @return	int						>0 if OK, <0 if KO
	 */
	public function delete(User $user, $notrigger = 1)
	{
		return $this->deleteCommon($user, $notrigger);
	}

	/**
	 * Copy the standard costs per hectare of the type of operation (doliFarm)
	 *
	 * @param	int		$socid		Farm of the plan
	 * @param	?int	$date		Date of reference (start of the plan)
	 * @return	bool				True when a standard cost was found
	 */
	public function setStandardCosts($socid, ?int $date = null)
	{
		dol_include_once('/dolitrace/lib/dolitrace_costs.lib.php');
		$rate = dolitraceStandardCost($this->db, (string) $this->fk_operation_type, (int) $socid, $date);
		if (!$rate) {
			return false;
		}
		$this->cost_manpower_ha = $rate['manpower'];
		$this->cost_machine_ha = $rate['machine'];
		$this->cost_service_ha = $rate['service'];
		return true;
	}
}
