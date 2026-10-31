<?php
/* Copyright (C) 2017       Laurent Destailleur      <eldy@users.sourceforge.net>
 * Copyright (C) 2023-2025  Frédéric France          <frederic.france@free.fr>
 * Copyright (C) 2026      SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file        htdocs/dolitrace/class/dolitracetreatment.class.php
 * \ingroup     dolitrace
 * \brief       Register of the treatments and fertilizations (Reg. EU 2023/564): record, products and areas
 */

// Put here all includes required by your class file
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
dol_include_once('/dolitrace/lib/dolitrace_treatment.lib.php');
//require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
//require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

/**
 * Record of the register: a plant protection treatment or a fertilization, with its products and its areas
 */
class DoliTraceTreatment extends CommonObject
{
	/**
	 * @var string 		ID of module.
	 */
	public $module = 'dolitrace';

	/**
	 * @var string 		ID to identify managed object.
	 */
	public $element = 'dolitracetreatment';

	/**
	 * @var string		Prefix to check for any trigger code of any business class to prevent bad value for trigger code.
	 * @see CommonTrigger::call_trigger()
	 */
	public $TRIGGER_PREFIX = 'DOLITRACE_TREATMENT';	// Will be used to build trgiger keys 'DOLITRACE_TREATMENT_MODIFY', ...

	/**
	 * @var string 		Name of table without prefix where object is stored. This is also the key used for extrafields management (so extrafields know the link to the parent table).
	 */
	public $table_element = 'dolitrace_treatment';

	/**
	 * @var string 		If permission must be checked with hasRight('dolitrace', 'read') and not hasright('dolitrace', 'dolitracetreatment', 'read'), you can uncomment this line
	 */
	//public $element_for_permission = 'dolitrace';

	/**
	 * @var string 		String with name of icon for dolitracetreatment. Must be a 'fa-xxx' fontawesome code (or 'fa-xxx_fa_color_size') or 'dolitracetreatment@dolitrace' if picto is file 'img/object_dolitracetreatment.png'.
	 */
	public $picto = 'fa-spray-can';

	/**
	 * @var int<0,1>	Does object support extrafields ? 0=No, 1=Yes
	 */
	public $isextrafieldmanaged = 1;

	/**
	 * @var int<0,1>|string		Does this object support multicompany module ?
	 * 							0=No test on entity, 1=Test with field entity in local table, 'field@table'=Test entity into the field@table (example 'fk_soc@societe')
	 */
	public $ismultientitymanaged = 1;


	const STATUS_DRAFT = 0;
	const STATUS_VALIDATED = 1;
	const STATUS_REPLACED = 8;
	const STATUS_CANCELED = 9;

	const KIND_PLANTPROTECTION = 1;
	const KIND_FERTILIZATION = 2;

	const TECHNICAL_SURFACES = 1;
	const TECHNICAL_CLOSED = 2;
	const TECHNICAL_SEEDS = 3;

	/**
	 * @var string[]	Types of fertilizer of livestock origin, counted for the limit of nitrogen in vulnerable zones
	 */
	public static $livestockFertTypes = array('MANURE', 'SLURRY', 'DIGEST');

	/**
	 *  'type' field format:
	 *  	'integer', 'integer:ObjectClass:PathToClass[:AddCreateButtonOrNot[:Filter[:Sortfield]]]',
	 *  	'select' (list of values are in 'options'. for integer list of values are in 'arrayofkeyval'),
	 *  	'sellist:TableName:LabelFieldName[:KeyFieldName[:KeyFieldParent[:Filter[:CategoryIdType[:CategoryIdList[:SortField]]]]]]',
	 *  	'chkbxlst:...',
	 *  	'varchar(x)',
	 *  	'text', 'text:none', 'html',
	 *   	'double(24,8)', 'real', 'price', 'stock',
	 *  	'date', 'datetime', 'timestamp', 'duration',
	 *  	'boolean', 'checkbox', 'radio', 'array',
	 *  	'email', 'phone', 'url', 'password', 'ip'
	 *		Note: Filter must be a Dolibarr Universal Filter syntax string. Example: "(t.ref:like:'SO-%') or (t.date_creation:>:'20160101') or (t.status:!=:0) or (t.nature:is:NULL)"
	 *  'length' the length of field. Example: 255, '24,8'
	 *  'label' the translation key.
	 *  'langfile' the key of the language file for translation.
	 *  'alias' the alias used into some old hard coded SQL requests
	 *  'picto' is code of a picto to show before value in forms
	 *  'enabled' is a condition when the field must be managed (Example: 1 or 'getDolGlobalInt("MY_SETUP_PARAM")' or 'isModEnabled("multicurrency")' ...)
	 *  'position' is the sort order of field.
	 *  'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty ('' or 0).
	 *  'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form (not create). 5=Visible on list and view form (not create/not update). 6=visible on list and update/view form (not update). Using a negative value means field is not shown by default on list but can be selected for viewing)
	 *  'noteditable' says if field is not editable (1 or 0)
	 *  'alwayseditable' says if field can be modified also when status is not draft ('1' or '0')
	 *  'default' is a default value for creation (can still be overwritten by the Setup of Default Values if the field is editable in creation form). Note: If default is set to '(PROV)' and field is 'ref', the default value will be set to '(PROVid)' where id is rowid when a new record is created.
	 *  'index' if we want an index in database.
	 *  'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommended to name the field fk_...).
	 *  'searchall' is 1 if we want to search in this field when making a search from the quick search button.
	 *  'isameasure' must be set to 1 or 2 if field can be used for measure. Field type must be summable like integer or double(24,8). Use 1 in most cases, or 2 if you don't want to see the column total into list (for example for percentage)
	 *  'css' and 'cssview' and 'csslist' is the CSS style to use on field. 'css' is used in creation and update. 'cssview' is used in view mode. 'csslist' is used for columns in lists. For example: 'css'=>'minwidth300 maxwidth500 widthcentpercentminusx', 'cssview'=>'wordbreak', 'csslist'=>'tdoverflowmax200'
	 *  'placeholder' to set the placeholder of a varchar field.
	 *  'help' and 'helplist' is a 'TranslationString' to use to show a tooltip on field. You can also use 'TranslationString:keyfortooltiponlick' for a tooltip on click.
	 *  'showoncombobox' if value of the field must be visible into the label of the combobox that list record
	 *  'disabled' is 1 if we want to have the field locked by a 'disabled' attribute. In most cases, this is never set into the definition of $fields into class, but is set dynamically by some part of code like the constructor of the class.
	 *  'arrayofkeyval' to set a list of values if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel"). Note that type can be 'integer' or 'varchar'
	 *  'autofocusoncreate' to have field having the focus on a create form. Only 1 field should have this property set to 1.
	 *  'comment' is not used. You can store here any text of your choice. It is not used by application.
	 *	'validate' is 1 if you need to validate the field with $this->validateField(). Need MAIN_ACTIVATE_VALIDATION_RESULT.
	 *  'copytoclipboard' is 1 or 2 to allow to add a picto to copy value into clipboard (1=picto after label, 2=picto after value)
	 *  'description' is a description of the field. Can be used to help the MCP server.
	 *
	 *  Note: To have value dynamic, you can set value to 0 in definition and edit the value on the fly into the constructor.
	 */

	// BEGIN MODULEBUILDER PROPERTIES
	/**
	 * @inheritdoc
	 * Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1, 'css' => 'left'),
		'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 4, 'noteditable' => 1, 'default' => '(PROV)', 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'csslist' => 'tdoverflowmax150'),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'default' => '1', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'position' => 11, 'index' => 1),
		'kind' => array('type' => 'integer', 'label' => 'TreatmentKind', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'default' => '1', 'arrayofkeyval' => array('1' => 'TreatmentKindPlantProtection', '2' => 'TreatmentKindFertilization'), 'css' => 'minwidth200'),
		'technical_type' => array('type' => 'integer', 'label' => 'TreatmentTechnicalType', 'enabled' => 1, 'position' => 22, 'notnull' => 1, 'visible' => 1, 'default' => '1', 'arrayofkeyval' => array('1' => 'TreatmentTechnicalSurfaces', '2' => 'TreatmentTechnicalClosed', '3' => 'TreatmentTechnicalSeeds'), 'help' => 'TreatmentTechnicalTypeHelp', 'css' => 'minwidth200'),
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php:1:((status:=:1) AND (entity:IN:__SHARED_ENTITIES__))', 'label' => 'TreatmentFarm', 'picto' => 'company', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'csslist' => 'tdoverflowmax150'),
		'fk_dossier' => array('type' => 'integer:DoliFarmFarmDossier:dolifarm/class/dolifarmfarmdossier.class.php', 'label' => 'TreatmentDossier', 'enabled' => 1, 'position' => 32, 'notnull' => 0, 'visible' => -1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'csslist' => 'tdoverflowmax150'),
		'date_start' => array('type' => 'datetime', 'label' => 'TreatmentDateStart', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'date_end' => array('type' => 'datetime', 'label' => 'TreatmentDateEnd', 'enabled' => 1, 'position' => 41, 'notnull' => 0, 'visible' => -1),
		'reason' => array('type' => 'varchar(255)', 'label' => 'TreatmentReason', 'enabled' => 1, 'position' => 45, 'notnull' => 0, 'visible' => -1, 'css' => 'minwidth300', 'help' => 'TreatmentReasonHelp'),
		'fk_operator' => array('type' => 'integer:DoliFarmOperator:dolifarm/class/dolifarmoperator.class.php:0:(status:=:1)', 'label' => 'TreatmentOperator', 'picto' => 'user', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1, 'index' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'csslist' => 'tdoverflowmax150'),
		'operator_name' => array('type' => 'varchar(255)', 'label' => 'TreatmentOperatorName', 'enabled' => 1, 'position' => 51, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1),
		'operator_license' => array('type' => 'varchar(64)', 'label' => 'TreatmentOperatorLicense', 'enabled' => 1, 'position' => 52, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1),
		'operator_license_end' => array('type' => 'date', 'label' => 'TreatmentOperatorLicenseEnd', 'enabled' => 1, 'position' => 53, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1),
		'fk_soc_contractor' => array('type' => 'integer:Societe:societe/class/societe.class.php:1:((status:=:1) AND (entity:IN:__SHARED_ENTITIES__))', 'label' => 'TreatmentContractor', 'picto' => 'company', 'enabled' => 1, 'position' => 55, 'notnull' => 0, 'visible' => -1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'csslist' => 'tdoverflowmax150', 'help' => 'TreatmentContractorHelp'),
		'contractor_mode' => array('type' => 'integer', 'label' => 'TreatmentContractorMode', 'enabled' => 1, 'position' => 56, 'notnull' => 0, 'visible' => -1, 'arrayofkeyval' => array('1' => 'TreatmentContractorModeCountersign', '2' => 'TreatmentContractorModeForm')),
		'contractor_signed' => array('type' => 'boolean', 'label' => 'TreatmentContractorSigned', 'enabled' => 1, 'position' => 57, 'notnull' => 0, 'visible' => -1, 'default' => '0'),
		'date_contractor_signed' => array('type' => 'datetime', 'label' => 'TreatmentContractorSignedDate', 'enabled' => 1, 'position' => 58, 'notnull' => 0, 'visible' => -1),
		'delegation_ref' => array('type' => 'varchar(128)', 'label' => 'TreatmentDelegationRef', 'enabled' => 1, 'position' => 59, 'notnull' => 0, 'visible' => -1, 'help' => 'TreatmentDelegationRefHelp'),
		'fk_machine' => array('type' => 'integer:DoliFarmMachine:dolifarm/class/dolifarmmachine.class.php', 'label' => 'TreatmentMachine', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => -1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'csslist' => 'tdoverflowmax150'),
		'machine_label' => array('type' => 'varchar(255)', 'label' => 'TreatmentMachineLabel', 'enabled' => 1, 'position' => 61, 'notnull' => 0, 'visible' => 0, 'noteditable' => 1),
		'machine_check_end' => array('type' => 'date', 'label' => 'TreatmentMachineCheckEnd', 'enabled' => 1, 'position' => 62, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1),
		'water_volume' => array('type' => 'double(24,8)', 'label' => 'TreatmentWaterVolume', 'enabled' => 1, 'position' => 65, 'notnull' => 0, 'visible' => -1, 'css' => 'maxwidth100', 'help' => 'TreatmentWaterVolumeHelp'),
		'weather' => array('type' => 'varchar(255)', 'label' => 'TreatmentWeather', 'enabled' => 1, 'position' => 66, 'notnull' => 0, 'visible' => -1, 'css' => 'minwidth300'),
		'version' => array('type' => 'integer', 'label' => 'TreatmentVersion', 'enabled' => 1, 'position' => 80, 'notnull' => 1, 'visible' => 5, 'default' => '1', 'noteditable' => 1, 'csslist' => 'right'),
		'fk_previous' => array('type' => 'integer:DoliTraceTreatment:dolitrace/class/dolitracetreatment.class.php', 'label' => 'TreatmentPrevious', 'enabled' => 1, 'position' => 81, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1),
		'late' => array('type' => 'boolean', 'label' => 'TreatmentLate', 'enabled' => 1, 'position' => 82, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1, 'help' => 'TreatmentLateHelp'),
		'content_hash' => array('type' => 'varchar(64)', 'label' => 'TreatmentContentHash', 'enabled' => 1, 'position' => 83, 'notnull' => 0, 'visible' => 0, 'noteditable' => 1),
		'cancel_reason' => array('type' => 'text', 'label' => 'TreatmentCancelReason', 'enabled' => 1, 'position' => 84, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1),
		'note_public' => array('type' => 'html', 'label' => 'NotePublic', 'enabled' => 1, 'position' => 90, 'notnull' => 0, 'visible' => 0, 'cssview' => 'wordbreak'),
		'note_private' => array('type' => 'html', 'label' => 'NotePrivate', 'enabled' => 1, 'position' => 91, 'notnull' => 0, 'visible' => 0, 'cssview' => 'wordbreak'),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'date_valid' => array('type' => 'datetime', 'label' => 'DateValidation', 'enabled' => 1, 'position' => 502, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'picto' => 'user', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2, 'foreignkey' => '0', 'csslist' => 'tdoverflowmax150'),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'picto' => 'user', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2, 'csslist' => 'tdoverflowmax150'),
		'fk_user_valid' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserValidation', 'picto' => 'user', 'enabled' => 1, 'position' => 512, 'notnull' => -1, 'visible' => -2, 'csslist' => 'tdoverflowmax150'),
		'last_main_doc' => array('type' => 'varchar(255)', 'label' => 'LastMainDoc', 'enabled' => 1, 'position' => 600, 'notnull' => 0, 'visible' => 0),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => -1, 'visible' => -2),
		'model_pdf' => array('type' => 'varchar(255)', 'label' => 'Model pdf', 'enabled' => 1, 'position' => 1010, 'notnull' => -1, 'visible' => 0),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'position' => 2000, 'notnull' => 1, 'visible' => 5, 'default' => '0', 'index' => 1, 'arrayofkeyval' => array('0' => 'Draft', '1' => 'Validated', '8' => 'TreatmentReplaced', '9' => 'Canceled')),
	);

	/**
	 * @var int
	 */
	public $kind;

	/**
	 * @var int
	 */
	public $technical_type;

	/**
	 * @var int
	 */
	public $fk_soc;

	/**
	 * @var ?int
	 */
	public $fk_dossier;

	/**
	 * @var int|string
	 */
	public $date_start;

	/**
	 * @var int|string|null
	 */
	public $date_end;

	/**
	 * @var ?string
	 */
	public $reason;

	/**
	 * @var ?int
	 */
	public $fk_operator;

	/**
	 * @var ?string
	 */
	public $operator_name;

	/**
	 * @var ?string
	 */
	public $operator_license;

	/**
	 * @var int|string|null
	 */
	public $operator_license_end;

	/**
	 * @var ?int
	 */
	public $fk_soc_contractor;

	/**
	 * @var ?int
	 */
	public $contractor_mode;

	/**
	 * @var int
	 */
	public $contractor_signed;

	/**
	 * @var int|string|null
	 */
	public $date_contractor_signed;

	/**
	 * @var ?string
	 */
	public $delegation_ref;

	/**
	 * @var ?int
	 */
	public $fk_machine;

	/**
	 * @var ?string
	 */
	public $machine_label;

	/**
	 * @var int|string|null
	 */
	public $machine_check_end;

	/**
	 * @var float|string|null
	 */
	public $water_volume;

	/**
	 * @var ?string
	 */
	public $weather;

	/**
	 * @var int
	 */
	public $version;

	/**
	 * @var ?int
	 */
	public $fk_previous;

	/**
	 * @var int
	 */
	public $late;

	/**
	 * @var ?string
	 */
	public $content_hash;

	/**
	 * @var ?string
	 */
	public $cancel_reason;

	/**
	 * @var int|string|null
	 */
	public $date_valid;

	/**
	 * @var int Thirdparty ID
	 */
	public $socid;

	/**
	 * @var ?int ID of the user who validated
	 */
	public $fk_user_valid;
	// END MODULEBUILDER PROPERTIES

	//BEGIN MODULEBUILDER LINES
	/**
	 * @var string    Field with ID of parent key in the tables of the lines
	 */
	public $fk_element = 'fk_treatment';

	/**
	 * @var array<array<string>|string>		List of child tables. To test if we can delete object.
	 */
	protected $childtables = array();

	/**
	 * @var string[]	Tables of the lines, deleted with the record (only drafts can be deleted)
	 */
	protected $childtablesoncascade = array('dolitrace_treatment_product', 'dolitrace_treatment_target', 'dolitrace_treatment_log');

	/**
	 * @var DoliTraceTreatmentProduct[]	Products of the record
	 */
	public $products = array();

	/**
	 * @var DoliTraceTreatmentTarget[]	Areas of the record
	 */
	public $targets = array();
	//END MODULEBUILDER LINES

	/**
	 * Constructor
	 *
	 * @param	DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $langs;

		$this->db = $db;

		if (!getDolGlobalInt('MAIN_SHOW_TECHNICAL_ID') && isset($this->fields['rowid']) && !empty($this->fields['ref'])) {
			$this->fields['rowid']['visible'] = 0;
		}
		if (!isModEnabled('multicompany') && isset($this->fields['entity'])) {
			$this->fields['entity']['enabled'] = 0;
		}

		// Example to show how to set values of fields definition dynamically
		/*if ($user->hasRight('dolitrace', 'dolitracetreatment', 'read')) {
			$this->fields['myfield']['visible'] = 1;
			$this->fields['myfield']['noteditable'] = 0;
		}*/

		// Unset fields that are disabled
		foreach ($this->fields as $key => $val) {
			if (isset($val['enabled']) && empty($val['enabled'])) {
				unset($this->fields[$key]);
			}
		}

		// Translate some data of arrayofkeyval
		if (is_object($langs)) {
			foreach ($this->fields as $key => $val) {
				if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
					foreach ($val['arrayofkeyval'] as $key2 => $val2) {
						$this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
					}
				}
			}
		}
	}

	/**
	 * Create object into database
	 *
	 * @param	User		$user		User that creates
	 * @param	int<0,1> 	$notrigger	0=launch triggers after, 1=disable triggers
	 * @return	int<-1,max>				Return integer <0 if KO, Id of created object if OK
	 */
	public function create(User $user, $notrigger = 0)
	{
		if (empty($this->version)) {
			$this->version = 1;
		}
		$result = $this->createCommon($user, $notrigger);
		if ($result > 0 && $this->addLog($user, 'CREATE') < 0) {
			return -1;
		}

		return $result;
	}

	/**
	 * Clone an object into another one
	 *
	 * @param	User 	$user		User that creates
	 * @param	int 	$fromid		Id of object to clone
	 * @return	self|int<-1,-1>		New object created, <0 if KO
	 */
	public function createFromClone(User $user, $fromid)
	{
		global $langs, $extrafields;
		$error = 0;

		dol_syslog(__METHOD__, LOG_DEBUG);

		$object = new self($this->db);

		$this->db->begin();

		// Load source object
		$result = $object->fetchCommon($fromid);
		if ($result > 0) {
			$object->fetchLines();
		}
		// Reset some properties
		unset($object->id);
		unset($object->fk_user_creat);
		unset($object->import_key);

		// Clear fields
		if (property_exists($object, 'ref')) {
			$object->ref = empty($this->fields['ref']['default']) ? "Copy_Of_".$object->ref : $this->fields['ref']['default'];
		}
		if (property_exists($object, 'label')) {
			$object->label = empty($this->fields['label']['default']) ? $langs->trans("CopyOf")." ".$object->label : $this->fields['label']['default'];
		}
		if (property_exists($object, 'status')) {
			$object->status = self::STATUS_DRAFT;
		}
		if (property_exists($object, 'date_creation')) {
			$object->date_creation = dol_now();
		}
		if (property_exists($object, 'date_modification')) {
			$object->date_modification = null;
		}
		// ...
		// Clear extrafields that are unique
		if (is_array($object->array_options) && count($object->array_options) > 0) {
			$extrafields->fetch_name_optionals_label($this->table_element);
			foreach ($object->array_options as $key => $option) {
				$shortkey = preg_replace('/options_/', '', $key);
				if (!empty($extrafields->attributes[$this->table_element]['unique'][$shortkey])) {
					//var_dump($key);
					//var_dump($clonedObj->array_options[$key]); exit;
					unset($object->array_options[$key]);
				}
			}
		}

		// Create clone
		$object->context['createfromclone'] = 'createfromclone';
		$result = $object->createCommon($user);
		if ($result < 0) {
			$error++;
			$this->setErrorsFromObject($object);
		}

		if (!$error) {
			// copy internal contacts
			if ($this->copy_linked_contact($object, 'internal') < 0) {
				$error++;
			}
		}

		if (!$error) {
			// copy external contacts if same company
			if (!empty($object->socid) && ((property_exists($this, 'fk_soc') && ($this->fk_soc == $object->socid)) || (property_exists($this, 'socid') && ($this->socid == $object->socid)))) {	// @phpstan-ignore-line
				if ($this->copy_linked_contact($object, 'external') < 0) {
					$error++;
				}
			}
		}

		unset($object->context['createfromclone']);

		// End
		if (!$error) {
			$this->db->commit();
			return $object;
		} else {
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param	int    		$id   			Id object
	 * @param	string 		$ref  			Ref
	 * @param	int<0,1>	$noextrafields	0=Default to load extrafields, 1=No extrafields
	 * @param	int<0,1>	$nolines		0=Default to load lines, 1=No lines
	 * @return	int<-1,1>					Return integer <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null, $noextrafields = 0, $nolines = 0)
	{
		$result = $this->fetchCommon($id, $ref, '', $noextrafields);
		if ($result > 0) {
			$this->socid = $this->fk_soc;
			if (empty($nolines)) {
				$this->fetchLines($noextrafields);
			}
		}
		return $result;
	}

	/**
	 * Load the products and the areas of the record
	 *
	 * @param	int<0,1>	$noextrafields	Not used (the lines have no extrafields)
	 * @return 	int<-1,1>					Return integer <0 if KO, >0 if OK
	 */
	public function fetchLines($noextrafields = 0)
	{
		$this->products = array();
		$this->targets = array();
		if (empty($this->id)) {
			return 1;
		}
		$product = new DoliTraceTreatmentProduct($this->db);
		$result = $product->fetchByTreatment($this->id);
		if (!is_array($result)) {
			$this->setErrorsFromObject($product);
			return -1;
		}
		$this->products = $result;
		$target = new DoliTraceTreatmentTarget($this->db);
		$result = $target->fetchByTreatment($this->id);
		if (!is_array($result)) {
			$this->setErrorsFromObject($target);
			return -1;
		}
		$this->targets = $result;
		return 1;
	}

	/**
	 * Load list of objects in memory from the database.
	 * Using a fetchAll() with limit = 0 is a very bad practice. Instead try to forge yourself an optimized SQL request with
	 * your own loop with start and stop pagination.
	 *
	 * @param	string		$sortorder	Sort Order
	 * @param	string		$sortfield	Sort field
	 * @param	int<0,max>	$limit		Limit the number of lines returned
	 * @param	int<0,max>	$offset		Offset
	 * @param	string		$filter		Filter as an Universal Search string.
	 *                                  Example: '((client:=:1) OR ((client:>=:2) AND (client:<=:3))) AND (client:!=:8) AND (nom:like:'a%')'
	 * @param	string		$filtermode	No longer used
	 * @return	array<int,self>|int<-1,-1>	 <0 if KO, array of pages if OK
	 */
	public function fetchAll($sortorder = '', $sortfield = '', $limit = 1000, $offset = 0, string $filter = '', $filtermode = 'AND')
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$records = array();

		$sql = "SELECT ";
		$sql .= $this->getFieldList('t');
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		if (!empty($this->isextrafieldmanaged) && $this->isextrafieldmanaged == 1) {
			$sql .= " LEFT JOIN ".$this->db->prefix().$this->table_element."_extrafields as te ON te.fk_object = t.rowid";
		}
		if (!empty($this->ismultientitymanaged) && (int) $this->ismultientitymanaged == 1) {
			$sql .= " WHERE t.entity IN (".getEntity($this->element).")";
		} elseif (preg_match('/^\w+@\w+$/', (string) $this->ismultientitymanaged)) {
			$tmparray = explode('@', (string) $this->ismultientitymanaged);
			$sql .= " LEFT JOIN ".$this->db->prefix().$this->db->sanitize($tmparray[1])." as pt ON t.".$this->db->sanitize($tmparray[0])." = pt.rowid";
			$sql .= " WHERE pt.entity IN (".getEntity($this->element).")";
		} else {
			$sql .= " WHERE 1 = 1";
		}

		// Manage filter
		$errormessage = '';
		$sql .= forgeSQLFromUniversalSearchCriteria($filter, $errormessage);
		if ($errormessage) {
			$this->errors[] = $errormessage;
			dol_syslog(__METHOD__.' '.implode(',', $this->errors), LOG_ERR);
			return -1;
		}

		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}
		if (!empty($limit)) {
			$sql .= $this->db->plimit($limit, $offset);
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < ($limit ? min($limit, $num) : $num)) {
				$obj = $this->db->fetch_object($resql);

				$record = new self($this->db);
				$record->setVarsFromFetchObj($obj);

				if (!empty($record->isextrafieldmanaged)) {
					$record->fetch_optionals();
				}

				$records[$record->id] = $record;

				$i++;
			}
			$this->db->free($resql);

			return $records;
		} else {
			$this->errors[] = 'Error '.$this->db->lasterror();
			dol_syslog(__METHOD__.' '.implode(',', $this->errors), LOG_ERR);

			return -1;
		}
	}

	/**
	 * Update object into database
	 *
	 * @param	User		$user		User that modifies
	 * @param	int<0,1>	$notrigger	0=launch triggers after, 1=disable triggers
	 * @return	int<-1,1>				Return integer <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = 0)
	{
		if ($this->status != self::STATUS_DRAFT) {
			$this->error = 'ErrorTreatmentValidatedLocked';
			return -1;
		}
		$result = $this->updateCommon($user, $notrigger);
		if ($result > 0 && ($this->fetchLines() < 0 || $this->updateAutoQuantities($user) < 0)) {
			return -1;
		}
		return $result;
	}

	/**
	 * Delete object in database
	 *
	 * @param	User		$user		User that deletes
	 * @param	int<0,1> 	$notrigger	0=launch triggers, 1=disable triggers
	 * @return	int<-1,1>				Return integer <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		if ($this->status != self::STATUS_DRAFT) {
			$this->error = 'ErrorTreatmentOnlyDraftDeleted';
			return -1;
		}
		return $this->deleteCommon($user, $notrigger);
	}



	/**
	 *	Validate object
	 *
	 *	@param	User		$user		User making status change
	 *  @param	int<0,1>	$notrigger	1=Does not execute triggers, 0= execute triggers
	 *	@return	int<-1,1>				Return integer <=0 if OK, 0=Nothing done, >0 if KO
	 */
	public function validate($user, $notrigger = 0)
	{
		global $conf;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

		$error = 0;

		// Protection
		if ($this->status != self::STATUS_DRAFT) {
			dol_syslog(get_class($this)."::validate action abandoned: not a draft", LOG_DEBUG);
			return 0;
		}
		if ($this->fetchLines() < 0) {
			return -1;
		}

		$now = dol_now();
		$oldref = $this->ref;
		$this->error = '';
		$this->errors = array();

		$this->db->begin();

		// Copies of the registries and quantities, then the checks on what is recorded
		if ($this->refreshCopies($user) < 0 || $this->updateAutoQuantities($user) < 0) {
			$error++;
		}
		if (!$error) {
			$errors = $this->checkForValidation();
			if (count($errors)) {
				$this->error = '';
				$this->errors = $errors;
				$error++;
			}
		}

		// Define new ref: a correction keeps the ref of the first version with the number of the version
		$previous = null;
		if (!$error) {
			if ($this->fk_previous > 0) {
				$previous = new self($this->db);
				if ($previous->fetch($this->fk_previous, null, 1, 1) <= 0 || $previous->status != self::STATUS_VALIDATED) {
					$this->error = 'ErrorTreatmentPreviousNotValidated';
					$error++;
				} else {
					$num = preg_replace('/-V[0-9]+$/', '', $previous->ref).'-V'.((int) $this->version);
				}
			} elseif (preg_match('/^[\(]?PROV/i', $this->ref) || empty($this->ref)) {
				$num = $this->getNextNumRef();
			} else {
				$num = (string) $this->ref;
			}
			if (!$error && empty($num)) {
				$error++;
			}
		}

		if (!$error) {
			$this->newref = $num;
			$this->ref = $num;
			// Late: recorded more than 30 days after the end of the operation (Reg. EU 2023/564, art. 3)
			$this->late = ($now - (!empty($this->date_end) ? $this->date_end : $this->date_start) > 30 * 86400 ? 1 : 0);
			$this->date_valid = $now;
			$this->fk_user_valid = $user->id;
			$this->content_hash = $this->computeHash();

			$sql = "UPDATE ".$this->db->prefix().$this->table_element;
			$sql .= " SET ref = '".$this->db->escape($num)."'";
			$sql .= ", status = ".self::STATUS_VALIDATED;
			$sql .= ", date_valid = '".$this->db->idate($now)."'";
			$sql .= ", fk_user_valid = ".((int) $user->id);
			$sql .= ", late = ".((int) $this->late);
			$sql .= ", content_hash = '".$this->db->escape($this->content_hash)."'";
			$sql .= ", operator_name = ".($this->operator_name !== null ? "'".$this->db->escape($this->operator_name)."'" : "NULL");
			$sql .= ", operator_license = ".($this->operator_license !== null ? "'".$this->db->escape($this->operator_license)."'" : "NULL");
			$sql .= ", operator_license_end = ".(!empty($this->operator_license_end) ? "'".$this->db->idate($this->operator_license_end)."'" : "NULL");
			$sql .= ", machine_label = ".($this->machine_label !== null ? "'".$this->db->escape($this->machine_label)."'" : "NULL");
			$sql .= ", machine_check_end = ".(!empty($this->machine_check_end) ? "'".$this->db->idate($this->machine_check_end)."'" : "NULL");
			$sql .= " WHERE rowid = ".((int) $this->id)." AND status = ".self::STATUS_DRAFT;

			dol_syslog(get_class($this)."::validate()", LOG_DEBUG);
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				$error++;
			}
		}

		// The previous version is replaced by this one
		if (!$error && $previous) {
			$sql = "UPDATE ".$this->db->prefix().$this->table_element." SET status = ".self::STATUS_REPLACED;
			$sql .= " WHERE rowid = ".((int) $previous->id)." AND status = ".self::STATUS_VALIDATED;
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$error++;
			} elseif ($this->addLog($user, 'REPLACE', '', array('replaced_by' => (int) $this->id, 'replaced_by_ref' => $num), $previous->id, $previous->version) < 0) {
				$error++;
			}
		}

		if (!$error && $this->addLog($user, 'VALIDATE', '', $this->getRegisterContent()) < 0) {
			$error++;
		}

		if (!$error && !$notrigger) {
			// Call trigger
			$result = $this->call_trigger($this->TRIGGER_PREFIX.'_VALIDATE', $user);
			if ($result < 0) {
				$error++;
			}
			// End call triggers
		}

		// Rename directory if dir was a temporary ref
		if (!$error && preg_match('/^[\(]?PROV/i', $oldref)) {
			// Now we rename also files into index
			$sql = 'UPDATE '.$this->db->prefix()."ecm_files set filename = CONCAT('".$this->db->escape($this->newref)."', SUBSTR(filename, ".(strlen($oldref) + 1).")), filepath = 'dolitracetreatment/".$this->db->escape($this->newref)."'";
			$sql .= " WHERE filename LIKE '".$this->db->escape($oldref)."%' AND filepath = 'dolitracetreatment/".$this->db->escape($oldref)."' and entity = ".((int) $conf->entity);
			if (!$this->db->query($sql)) {
				$error++;
				$this->error = $this->db->lasterror();
			}
			$sql = 'UPDATE '.$this->db->prefix()."ecm_files set filepath = 'dolitracetreatment/".$this->db->escape($this->newref)."'";
			$sql .= " WHERE filepath = 'dolitracetreatment/".$this->db->escape($oldref)."' and entity = ".((int) $conf->entity);
			if (!$error && !$this->db->query($sql)) {
				$error++;
				$this->error = $this->db->lasterror();
			}

			// We rename directory ($oldref = old ref, $num = new ref) in order not to lose the attachments
			$oldrefdir = dol_sanitizeFileName($oldref);
			$newrefdir = dol_sanitizeFileName($this->newref);
			$dirsource = $conf->dolitrace->dir_output.'/dolitracetreatment/'.$oldrefdir;
			$dirdest = $conf->dolitrace->dir_output.'/dolitracetreatment/'.$newrefdir;
			if (!$error && file_exists($dirsource)) {
				dol_syslog(get_class($this)."::validate() rename dir ".$dirsource." into ".$dirdest);
				if (@rename($dirsource, $dirdest)) {
					// Rename docs starting with $oldref with $newref
					$listoffiles = dol_dir_list($dirdest, 'files', 1, '^'.preg_quote($oldrefdir, '/'));
					foreach ($listoffiles as $fileentry) {
						$filesource = $fileentry['path'].'/'.$fileentry['name'];
						$filedest = $fileentry['path'].'/'.preg_replace('/^'.preg_quote($oldrefdir, '/').'/', $newrefdir, $fileentry['name']);
						@rename($filesource, $filedest);
					}
				}
			}
		}

		if (!$error) {
			$this->oldref = $oldref;
			$this->status = self::STATUS_VALIDATED;
			$this->db->commit();

			// Stock: never blocks the register (products to regularize are shown on the card)
			if ($previous) {
				$previous->status = self::STATUS_REPLACED;
				$previous->reverseStock($user);
			}
			$this->moveStock($user);
			return 1;
		} else {
			$this->ref = $oldref;
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Recompute the automatic total quantities and the nitrogen applied of the products, and save them
	 *
	 * The total quantity is dose x treated quantity in the basis of the unit x factor of the unit. A quantity entered by the user is kept.
	 *
	 * @param	User	$user	User that modifies
	 * @return	int				Return integer <0 if KO, >0 if OK
	 */
	public function updateAutoQuantities(User $user)
	{
		$units = dolitraceTreatmentDoseUnits($this->db, (int) $this->kind, (int) $this->technical_type);
		$hectares = dolitraceTreatmentQtyInBasis($this, 'HA');
		foreach ($this->products as $product) {
			$unit = isset($units[$product->fk_dose_unit]) ? $units[$product->fk_dose_unit] : null;
			$qty = $product->qty_total;
			$n_applied = null;
			if ($product->qty_auto) {
				$basisqty = $unit ? dolitraceTreatmentQtyInBasis($this, $unit->basis) : null;
				$qty = ($basisqty !== null && $product->dose > 0) ? price2num($product->dose * $basisqty * $unit->factor, 'MS') : null;
			}
			if ($product->n_content > 0 && $qty > 0 && $hectares > 0 && in_array($product->qty_unit, array('kg', 't'))) {
				$kg = $qty * ($product->qty_unit == 't' ? 1000 : 1);
				$n_applied = price2num($kg * $product->n_content / 100 / $hectares, 'MS');
			}
			if ((string) $qty === (string) $product->qty_total && (string) $n_applied === (string) $product->n_applied) {
				continue;
			}
			$product->qty_total = $qty;
			$product->n_applied = $n_applied;
			if ($product->update($user) < 0) {
				$this->setErrorsFromObject($product);
				return -1;
			}
		}
		return 1;
	}

	/**
	 * Copy into the record the data of the registries (operator, machine, products, adversities, units, crop plans)
	 *
	 * The copies keep the register as it was at the validation, even if the registries change later.
	 *
	 * @param	User	$user	User that validates
	 * @return	int				Return integer <0 if KO, >0 if OK
	 */
	public function refreshCopies(User $user)
	{
		global $langs;

		$langs->load('dolifarm@dolifarm');

		$this->operator_name = null;
		$this->operator_license = null;
		$this->operator_license_end = null;
		if ($this->fk_operator > 0) {
			$rows = dolitraceTreatmentFetchRows($this->db, "SELECT rowid, label, license_number, license_end FROM ".$this->db->prefix()."dolifarm_operator WHERE rowid = ".((int) $this->fk_operator));
			if (isset($rows[$this->fk_operator])) {
				$this->operator_name = $rows[$this->fk_operator]->label;
				$this->operator_license = $rows[$this->fk_operator]->license_number;
				$this->operator_license_end = $this->db->jdate($rows[$this->fk_operator]->license_end);
			}
		}
		$this->machine_label = null;
		$this->machine_check_end = null;
		if ($this->fk_machine > 0) {
			$rows = dolitraceTreatmentFetchRows($this->db, "SELECT rowid, ref, label, date_sprayer_check_end FROM ".$this->db->prefix()."dolifarm_machine WHERE rowid = ".((int) $this->fk_machine));
			if (isset($rows[$this->fk_machine])) {
				$this->machine_label = trim($rows[$this->fk_machine]->ref.' - '.$rows[$this->fk_machine]->label, ' -');
				$this->machine_check_end = $this->db->jdate($rows[$this->fk_machine]->date_sprayer_check_end);
			}
		}

		foreach ($this->products as $product) {
			if ($product->fk_agrodrug > 0) {
				$rows = dolitraceTreatmentFetchRows($this->db, "SELECT rowid, label, reg_number, fk_product FROM ".$this->db->prefix()."dolifarm_agrodrug WHERE rowid = ".((int) $product->fk_agrodrug));
				if (isset($rows[$product->fk_agrodrug])) {
					$product->product_label = $rows[$product->fk_agrodrug]->label;
					$product->reg_number = $rows[$product->fk_agrodrug]->reg_number;
					$product->fk_product = ($rows[$product->fk_agrodrug]->fk_product > 0 ? (int) $rows[$product->fk_agrodrug]->fk_product : null);
				}
			}
			if ($product->fk_fertilizer > 0) {
				$rows = dolitraceTreatmentFetchRows($this->db, "SELECT rowid, label, registration_number, fk_product, n_content, p2o5_content, k2o_content FROM ".$this->db->prefix()."dolifarm_fertilizer WHERE rowid = ".((int) $product->fk_fertilizer));
				if (isset($rows[$product->fk_fertilizer])) {
					$fertilizer = $rows[$product->fk_fertilizer];
					$product->product_label = $fertilizer->label;
					$product->reg_number = $fertilizer->registration_number;
					$product->fk_product = ($fertilizer->fk_product > 0 ? (int) $fertilizer->fk_product : null);
					$product->n_content = $fertilizer->n_content;
					$product->p2o5_content = $fertilizer->p2o5_content;
					$product->k2o_content = $fertilizer->k2o_content;
				}
			}
			if ($product->fk_adversity > 0) {
				$rows = dolitraceTreatmentFetchRows($this->db, "SELECT rowid, code, label FROM ".$this->db->prefix()."dolitrace_c_adversity WHERE rowid = ".((int) $product->fk_adversity));
				if (isset($rows[$product->fk_adversity])) {
					$product->adversity_code = $rows[$product->fk_adversity]->code;
					$product->adversity_label = $rows[$product->fk_adversity]->label;
				}
			}
			if ($product->fk_dose_unit > 0) {
				$rows = dolitraceTreatmentFetchRows($this->db, "SELECT rowid, label, qty_unit FROM ".$this->db->prefix()."dolitrace_c_dose_unit WHERE rowid = ".((int) $product->fk_dose_unit));
				if (isset($rows[$product->fk_dose_unit])) {
					$product->dose_unit_label = $rows[$product->fk_dose_unit]->label;
					$product->qty_unit = $rows[$product->fk_dose_unit]->qty_unit;
				}
			}
			if ($product->update($user) < 0) {
				$this->setErrorsFromObject($product);
				return -1;
			}
		}

		$cropplans = dolitraceTreatmentCropPlans($this->db, $this);
		foreach ($this->targets as $target) {
			if ($target->target_type != DoliTraceTreatmentTarget::TYPE_PLOT || !isset($cropplans[$target->fk_cropplan])) {
				continue;
			}
			$cropplan = $cropplans[$target->fk_cropplan];
			$target->fk_plot = (int) $cropplan->fk_plot;
			$target->fk_crop = (int) $cropplan->fk_crop;
			$target->crop_label = $langs->transnoentitiesnoconv($cropplan->crop_label);
			$target->crop_eppo = $cropplan->eppo_code;
			$target->location_ref = $cropplan->iacs_parcel ? $cropplan->iacs_parcel : ($cropplan->cadastral_reference ? $cropplan->cadastral_reference : $cropplan->plot_ref);
			$target->zone_zvn = (int) $cropplan->zone_zvn;
			if ($target->update($user) < 0) {
				$this->setErrorsFromObject($target);
				return -1;
			}
		}
		return 1;
	}

	/**
	 * Blocking checks before the validation
	 *
	 * @return	string[]	Translated errors, empty if the record can be validated
	 */
	public function checkForValidation()
	{
		global $langs;

		$langs->loadLangs(array('errors', 'dolitrace@dolitrace'));
		$errors = array();

		if (empty($this->date_start)) {
			$errors[] = $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('TreatmentDateStart'));
		} elseif ($this->date_start > dol_now() + 3600) {
			$errors[] = $langs->trans('ErrorTreatmentDateFuture');
		}
		if (!empty($this->date_end) && !empty($this->date_start) && $this->date_end < $this->date_start) {
			$errors[] = $langs->trans('ErrorTreatmentDateEnd');
		}
		if ($this->kind == self::KIND_PLANTPROTECTION && empty($this->fk_operator)) {
			$errors[] = $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('TreatmentOperator'));
		}
		if (count($this->targets) == 0 || count($this->products) == 0) {
			$errors[] = $langs->trans('ErrorTreatmentNeedsAreaAndProduct');
		}

		$cropplans = dolitraceTreatmentCropPlans($this->db, $this);
		foreach ($this->targets as $target) {
			$label = $target->location_ref ? $target->location_ref : $target->seed_batch;
			if ($target->target_type != $this->technical_type) {
				$errors[] = $langs->trans('ErrorTreatmentTargetType', $label);
			} elseif ($target->target_type == DoliTraceTreatmentTarget::TYPE_PLOT && !isset($cropplans[$target->fk_cropplan])) {
				$errors[] = $langs->trans('ErrorTreatmentCropPlanNotInProgress', $label, dol_print_date($this->date_start, 'day'));
			}
			if (!($target->treated_qty > 0)) {
				$errors[] = $langs->trans('ErrorTreatmentTreatedQty', $label);
			}
		}

		$units = dolitraceTreatmentDoseUnits($this->db, (int) $this->kind, (int) $this->technical_type);
		foreach ($this->products as $product) {
			$label = $product->product_label;
			if ($this->kind == self::KIND_PLANTPROTECTION) {
				if (empty($product->fk_agrodrug)) {
					$errors[] = $langs->trans('ErrorTreatmentProductKind', $label);
				} else {
					if (empty($product->reg_number)) {
						$errors[] = $langs->trans('ErrorTreatmentNoRegNumber', $label);
					}
					if (empty($product->fk_adversity)) {
						$errors[] = $langs->trans('ErrorTreatmentNoAdversity', $label);
					}
				}
			} elseif (empty($product->fk_fertilizer)) {
				$errors[] = $langs->trans('ErrorTreatmentProductKind', $label);
			}
			if (!($product->dose > 0) || !isset($units[$product->fk_dose_unit])) {
				$errors[] = $langs->trans('ErrorTreatmentDoseUnit', $label);
			} elseif (!($product->qty_total > 0)) {
				$errors[] = $langs->trans('ErrorTreatmentQtyTotal', $label);
			}
		}
		return $errors;
	}

	/**
	 * Warnings on the record: they do not block the validation, the user checks them
	 *
	 * Dose above the maximum of the product, authorization of the product, license of the operator or functional check
	 * of the machine expired at the date, record more than 30 days late, harvests within the safety interval,
	 * nitrogen of livestock origin above the limit of the year in vulnerable zones (ZVN).
	 * For drafts operator and machine are read from the registries, for validated records from the copies.
	 *
	 * @return	string[]	Warnings, translated
	 */
	public function getWarnings()
	{
		global $langs;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

		$langs->loadLangs(array('dolitrace@dolitrace', 'dolifarm@dolifarm'));
		$warnings = array();
		if (empty($this->date_start)) {
			return $warnings;
		}
		$day = $this->db->jdate(dol_print_date($this->date_start, '%Y-%m-%d').' 00:00:00');
		$dateend = !empty($this->date_end) ? $this->date_end : $this->date_start;

		// Operator and machine
		$licenseend = $this->operator_license_end;
		$checkend = $this->machine_check_end;
		if ($this->status == self::STATUS_DRAFT) {
			$licenseend = null;
			$checkend = null;
			if ($this->fk_operator > 0) {
				$rows = dolitraceTreatmentFetchRows($this->db, "SELECT rowid, license_end FROM ".$this->db->prefix()."dolifarm_operator WHERE rowid = ".((int) $this->fk_operator));
				$licenseend = isset($rows[$this->fk_operator]) ? $this->db->jdate($rows[$this->fk_operator]->license_end) : null;
			}
			if ($this->fk_machine > 0) {
				$rows = dolitraceTreatmentFetchRows($this->db, "SELECT rowid, date_sprayer_check_end FROM ".$this->db->prefix()."dolifarm_machine WHERE rowid = ".((int) $this->fk_machine));
				$checkend = isset($rows[$this->fk_machine]) ? $this->db->jdate($rows[$this->fk_machine]->date_sprayer_check_end) : null;
			}
		}
		if ($this->kind == self::KIND_PLANTPROTECTION && !empty($licenseend) && $licenseend < $day) {
			$warnings[] = $langs->transnoentities('WarningTreatmentLicenseExpired', dol_print_date($licenseend, 'day'));
		}
		if (!empty($checkend) && $checkend < $day) {
			$warnings[] = $langs->transnoentities('WarningTreatmentMachineCheckExpired', dol_print_date($checkend, 'day'));
		}

		// Late record: more than 30 days after the end of the operation (Reg. EU 2023/564, art. 3)
		if ($this->status == self::STATUS_DRAFT && dol_now() - $dateend > 30 * 86400) {
			$warnings[] = $langs->transnoentities('WarningTreatmentWillBeLate');
		}

		$hectares = ($this->technical_type == self::TECHNICAL_SURFACES ? dolitraceTreatmentQtyInBasis($this, 'HA') : null);
		$safetydays = 0;
		$livestockn = 0;
		foreach ($this->products as $product) {
			if ($product->fk_agrodrug > 0) {
				$rows = dolitraceTreatmentFetchRows($this->db, "SELECT rowid, auth_expiration_date, max_dose_ha, safety_days_default FROM ".$this->db->prefix()."dolifarm_agrodrug WHERE rowid = ".((int) $product->fk_agrodrug));
				$agrodrug = isset($rows[$product->fk_agrodrug]) ? $rows[$product->fk_agrodrug] : null;
				if (!$agrodrug) {
					continue;
				}
				$authend = $this->db->jdate($agrodrug->auth_expiration_date);
				if (!empty($authend) && $authend < $day) {
					$warnings[] = $langs->transnoentities('WarningTreatmentAuthExpired', $product->product_label, dol_print_date($authend, 'day'));
				}
				// Dose by hectare in kg or l, compared with the maximum of the product
				if ($agrodrug->max_dose_ha > 0 && $hectares > 0 && $product->qty_total > 0 && in_array($product->qty_unit, array('kg', 'l', 't'))) {
					$doseha = $product->qty_total * ($product->qty_unit == 't' ? 1000 : 1) / $hectares;
					if ($doseha > $agrodrug->max_dose_ha * 1.0001) {
						$unit = ($product->qty_unit == 'l' ? 'l' : 'kg');
						$warnings[] = $langs->transnoentities('WarningTreatmentDoseAboveMax', $product->product_label, price(price2num($doseha, 'MS')).' '.$unit.'/ha', price(price2num($agrodrug->max_dose_ha, 'MS')).' '.$unit.'/ha');
					}
				}
				$safetydays = max($safetydays, (int) $agrodrug->safety_days_default);
			}
			if ($product->fk_fertilizer > 0 && $product->n_applied > 0) {
				$rows = dolitraceTreatmentFetchRows($this->db, "SELECT rowid, fk_fert_type FROM ".$this->db->prefix()."dolifarm_fertilizer WHERE rowid = ".((int) $product->fk_fertilizer));
				if (isset($rows[$product->fk_fertilizer]) && in_array($rows[$product->fk_fertilizer]->fk_fert_type, self::$livestockFertTypes)) {
					$livestockn += $product->n_applied;
				}
			}
		}

		// Harvests of the crop plans before the end of the safety interval
		if ($safetydays > 0) {
			$safetyend = dol_time_plus_duree($dateend, $safetydays, 'd');
			$cropplans = array();
			foreach ($this->targets as $target) {
				if ($target->fk_cropplan > 0) {
					$cropplans[] = (int) $target->fk_cropplan;
				}
			}
			if (count($cropplans)) {
				$sql = "SELECT h.rowid, h.ref, h.date_harvest FROM ".$this->db->prefix()."dolitrace_harvest as h";
				$sql .= " WHERE h.fk_cropplan IN (".$this->db->sanitize(implode(',', $cropplans)).") AND (h.status IS NULL OR h.status <> 9)";
				$sql .= " AND h.date_harvest >= '".$this->db->escape(dol_print_date($this->date_start, '%Y-%m-%d'))."'";
				$sql .= " AND h.date_harvest < '".$this->db->escape(dol_print_date($safetyend, '%Y-%m-%d'))."'";
				foreach (dolitraceTreatmentFetchRows($this->db, $sql) as $harvest) {
					$warnings[] = $langs->transnoentities('WarningTreatmentHarvestInSafety', $harvest->ref, dol_print_date($this->db->jdate($harvest->date_harvest), 'day'), dol_print_date($safetyend, 'day'));
				}
			}
		}

		// Nitrogen of livestock origin in vulnerable zones: kg N/ha of the calendar year on each plot, this record included
		$limit = getDolGlobalInt('DOLITRACE_ZVN_N_LIVESTOCK_LIMIT', 170);
		if ($this->kind == self::KIND_FERTILIZATION && $livestockn > 0 && $limit > 0) {
			$year = (int) dol_print_date($this->date_start, '%Y');
			foreach ($this->targets as $target) {
				if (empty($target->zone_zvn) || empty($target->fk_plot)) {
					continue;
				}
				$sql = "SELECT SUM(p.n_applied) as total FROM ".$this->db->prefix()."dolitrace_treatment_product as p";
				$sql .= " INNER JOIN ".$this->db->prefix()."dolitrace_treatment as t ON t.rowid = p.fk_treatment";
				$sql .= " INNER JOIN ".$this->db->prefix()."dolifarm_fertilizer as f ON f.rowid = p.fk_fertilizer";
				$sql .= " WHERE t.status = ".self::STATUS_VALIDATED." AND t.kind = ".self::KIND_FERTILIZATION;
				$sql .= " AND t.rowid <> ".((int) $this->id).($this->fk_previous > 0 ? " AND t.rowid <> ".((int) $this->fk_previous) : "");
				$sql .= " AND t.date_start >= '".$this->db->idate(dol_mktime(0, 0, 0, 1, 1, $year))."' AND t.date_start <= '".$this->db->idate(dol_mktime(23, 59, 59, 12, 31, $year))."'";
				$sql .= " AND f.fk_fert_type IN ('".implode("','", array_map(array($this->db, 'escape'), self::$livestockFertTypes))."')";
				$sql .= " AND EXISTS (SELECT 1 FROM ".$this->db->prefix()."dolitrace_treatment_target as g WHERE g.fk_treatment = t.rowid AND g.fk_plot = ".((int) $target->fk_plot).")";
				$resql = $this->db->query($sql);
				$obj = $resql ? $this->db->fetch_object($resql) : null;
				$total = ($obj ? (float) $obj->total : 0) + $livestockn;
				if ($total > $limit) {
					$warnings[] = $langs->transnoentities('WarningTreatmentZvnNitrogen', $target->location_ref, price(price2num($total, 'MS')), $year, $limit);
				}
			}
		}

		return $warnings;
	}

	/**
	 * Content of the record that the register certifies: header, products and areas, without technical fields and notes
	 *
	 * @return	array<string,mixed>		Content with normalized values
	 */
	public function getRegisterContent()
	{
		$num = function ($value) {
			if ($value === null || $value === '') {
				return '';
			}
			return rtrim(rtrim(sprintf('%.8F', (float) $value), '0'), '.');
		};
		$datetime = function ($value) {
			return empty($value) ? '' : dol_print_date($value, '%Y-%m-%d %H:%M:%S', 'gmt');
		};
		$date = function ($value) {
			return empty($value) ? '' : dol_print_date($value, '%Y-%m-%d', 'tzserver');
		};
		$content = array(
			'ref' => (string) $this->ref,
			'version' => (int) $this->version,
			'fk_previous' => (int) $this->fk_previous,
			'kind' => (int) $this->kind,
			'technical_type' => (int) $this->technical_type,
			'fk_soc' => (int) $this->fk_soc,
			'fk_dossier' => (int) $this->fk_dossier,
			'date_start' => $datetime($this->date_start),
			'date_end' => $datetime($this->date_end),
			'reason' => (string) $this->reason,
			'fk_operator' => (int) $this->fk_operator,
			'operator_name' => (string) $this->operator_name,
			'operator_license' => (string) $this->operator_license,
			'operator_license_end' => $date($this->operator_license_end),
			'fk_soc_contractor' => (int) $this->fk_soc_contractor,
			'contractor_mode' => (int) $this->contractor_mode,
			'delegation_ref' => (string) $this->delegation_ref,
			'fk_machine' => (int) $this->fk_machine,
			'machine_label' => (string) $this->machine_label,
			'machine_check_end' => $date($this->machine_check_end),
			'water_volume' => $num($this->water_volume),
			'weather' => (string) $this->weather,
			'targets' => array(),
			'products' => array(),
		);
		foreach ($this->targets as $target) {
			$content['targets'][] = array(
				'target_type' => (int) $target->target_type,
				'fk_plot' => (int) $target->fk_plot,
				'fk_building' => (int) $target->fk_building,
				'fk_cropplan' => (int) $target->fk_cropplan,
				'location_ref' => (string) $target->location_ref,
				'zone_zvn' => (int) $target->zone_zvn,
				'fk_crop' => (int) $target->fk_crop,
				'crop_eppo' => (string) $target->crop_eppo,
				'crop_label' => (string) $target->crop_label,
				'bbch' => (string) $target->bbch,
				'treated_qty' => $num($target->treated_qty),
				'treated_unit' => (string) $target->treated_unit,
				'seed_batch' => (string) $target->seed_batch,
			);
		}
		foreach ($this->products as $product) {
			$content['products'][] = array(
				'fk_agrodrug' => (int) $product->fk_agrodrug,
				'fk_fertilizer' => (int) $product->fk_fertilizer,
				'product_label' => (string) $product->product_label,
				'reg_number' => (string) $product->reg_number,
				'n_content' => $num($product->n_content),
				'p2o5_content' => $num($product->p2o5_content),
				'k2o_content' => $num($product->k2o_content),
				'adversity_code' => (string) $product->adversity_code,
				'adversity_label' => (string) $product->adversity_label,
				'dose' => $num($product->dose),
				'dose_unit_label' => (string) $product->dose_unit_label,
				'qty_total' => $num($product->qty_total),
				'qty_unit' => (string) $product->qty_unit,
				'n_applied' => $num($product->n_applied),
				'batch' => (string) $product->batch,
			);
		}
		return $content;
	}

	/**
	 * Fingerprint (SHA-256) of the content of the record
	 *
	 * @return	string		Hash
	 */
	public function computeHash()
	{
		return hash('sha256', json_encode($this->getRegisterContent()));
	}

	/**
	 * Check that the content of a validated record was not changed in the database
	 *
	 * @return	int		1 if the content matches its fingerprint, 0 if not, -1 if the record has no fingerprint
	 */
	public function checkHash()
	{
		if (empty($this->content_hash)) {
			return -1;
		}
		return (hash_equals((string) $this->content_hash, $this->computeHash()) ? 1 : 0);
	}

	/**
	 * Add a line to the history of the record
	 *
	 * @param	User					$user			User of the action
	 * @param	string					$action			CREATE, VALIDATE, CORRECT, REPLACE, CANCEL or STOCK
	 * @param	string					$reason			Reason given by the user
	 * @param	array<string,mixed>|null	$changes		Data of the action, saved as JSON
	 * @param	int						$fk_treatment	Record, this one if 0
	 * @param	int						$version		Version of the record, this one if 0
	 * @return	int										Return integer <0 if KO, >0 if OK
	 */
	public function addLog(User $user, $action, $reason = '', $changes = null, $fk_treatment = 0, $version = 0)
	{
		$sql = "INSERT INTO ".$this->db->prefix()."dolitrace_treatment_log (fk_treatment, version, action, changes, reason, content_hash, fk_user, datec)";
		$sql .= " VALUES (".((int) ($fk_treatment > 0 ? $fk_treatment : $this->id)).", ".((int) ($version > 0 ? $version : $this->version)).",";
		$sql .= " '".$this->db->escape($action)."',";
		$sql .= " ".($changes !== null ? "'".$this->db->escape(json_encode($changes))."'" : "NULL").",";
		$sql .= " ".($reason !== '' ? "'".$this->db->escape($reason)."'" : "NULL").",";
		$sql .= " ".($fk_treatment > 0 || empty($this->content_hash) ? "NULL" : "'".$this->db->escape($this->content_hash)."'").",";
		$sql .= " ".((int) $user->id).", '".$this->db->idate(dol_now())."')";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * History of the record
	 *
	 * @return	array<int,stdClass>		Lines of the history, the oldest first
	 */
	public function getHistory()
	{
		$sql = "SELECT l.rowid, l.version, l.action, l.changes, l.reason, l.content_hash, l.fk_user, l.datec";
		$sql .= " FROM ".$this->db->prefix()."dolitrace_treatment_log as l";
		$sql .= " WHERE l.fk_treatment = ".((int) $this->id);
		$sql .= " ORDER BY l.datec, l.rowid";
		return dolitraceTreatmentFetchRows($this->db, $sql);
	}

	/**
	 * Id of the record that corrects this one (draft or validated), 0 if none
	 *
	 * @return	int		Id of the correction
	 */
	public function getCorrectionId()
	{
		$sql = "SELECT t.rowid FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.fk_previous = ".((int) $this->id)." AND t.status IN (".self::STATUS_DRAFT.", ".self::STATUS_VALIDATED.", ".self::STATUS_REPLACED.")";
		$sql .= " ORDER BY t.rowid DESC";
		$rows = dolitraceTreatmentFetchRows($this->db, $sql);
		return count($rows) ? (int) key($rows) : 0;
	}

	/**
	 * Start the correction of a validated record: a new draft version with the same content
	 *
	 * The record stays valid until the new version is validated, then it becomes "replaced".
	 *
	 * @param	User	$user		User that corrects
	 * @param	string	$reason		Reason of the correction
	 * @return	int					Id of the new version, <0 if KO
	 */
	public function createCorrection(User $user, $reason)
	{
		global $langs;

		if ($this->status != self::STATUS_VALIDATED) {
			$this->error = $langs->trans('ErrorTreatmentValidatedLocked');
			return -1;
		}
		if (trim($reason) === '') {
			$this->error = $langs->trans('ErrorTreatmentReasonRequired');
			return -1;
		}
		if ($this->getCorrectionId() > 0) {
			$this->error = $langs->trans('ErrorTreatmentCorrectionExists');
			return -1;
		}
		$this->fetchLines();

		$this->db->begin();
		$new = new self($this->db);
		foreach (array('entity', 'kind', 'technical_type', 'fk_soc', 'fk_dossier', 'date_start', 'date_end', 'reason', 'fk_operator', 'fk_soc_contractor', 'contractor_mode',
			'contractor_signed', 'date_contractor_signed', 'delegation_ref', 'fk_machine', 'water_volume', 'weather', 'note_public', 'note_private') as $key) {
			$new->$key = $this->$key;
		}
		$new->ref = '(PROV)';
		$new->version = (int) $this->version + 1;
		$new->fk_previous = $this->id;
		$new->status = self::STATUS_DRAFT;
		$new->context['createcorrection'] = 'createcorrection';
		$result = $new->createCommon($user);
		if ($result > 0) {
			foreach (array_merge($this->targets, $this->products) as $row) {
				$copy = clone $row;
				$copy->id = 0;
				$copy->rowid = 0;
				$copy->fk_treatment = $new->id;
				if ($row instanceof DoliTraceTreatmentProduct) {
					$copy->stock_status = DoliTraceTreatmentProduct::STOCK_NONE;
					$copy->fk_stock_movement = null;
				}
				if ($copy->create($user) < 0) {
					$this->setErrorsFromObject($copy);
					$result = -1;
					break;
				}
			}
		} else {
			$this->setErrorsFromObject($new);
		}
		if ($result > 0 && $new->addLog($user, 'CORRECT', $reason, array('previous' => (int) $this->id, 'previous_ref' => $this->ref)) < 0) {
			$this->setErrorsFromObject($new);
			$result = -1;
		}
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return $new->id;
	}

	/**
	 * Cancel a validated record, with its reason. The record stays in the register as canceled.
	 *
	 * @param	User	$user		User that cancels
	 * @param	string	$reason		Reason of the cancellation
	 * @return	int					Return integer <0 if KO, >0 if OK
	 */
	public function cancelRecord(User $user, $reason)
	{
		global $langs;

		if ($this->status != self::STATUS_VALIDATED) {
			$this->error = $langs->trans('ErrorTreatmentValidatedLocked');
			return -1;
		}
		if (trim($reason) === '') {
			$this->error = $langs->trans('ErrorTreatmentReasonRequired');
			return -1;
		}
		$this->db->begin();
		$sql = "UPDATE ".$this->db->prefix().$this->table_element;
		$sql .= " SET status = ".self::STATUS_CANCELED.", cancel_reason = '".$this->db->escape($reason)."', fk_user_modif = ".((int) $user->id);
		$sql .= " WHERE rowid = ".((int) $this->id)." AND status = ".self::STATUS_VALIDATED;
		$error = 0;
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$error++;
		}
		if (!$error && $this->addLog($user, 'CANCEL', $reason) < 0) {
			$error++;
		}
		if (!$error) {
			$this->status = self::STATUS_CANCELED;
			$this->cancel_reason = $reason;
			if ($this->call_trigger($this->TRIGGER_PREFIX.'_CANCEL', $user) < 0) {
				$error++;
			}
		}
		if ($error) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		$this->reverseStock($user);
		return 1;
	}

	/**
	 * Take the products of a validated record out of the inputs warehouse of the farm
	 *
	 * Each product is moved in its own transaction, after the validation: an error of the stock never blocks the register,
	 * the product stays "to regularize" with the reason and the movement can be tried again.
	 *
	 * @param	User	$user	User that validates
	 * @return	int				Number of products moved, <0 if at least one product is to regularize
	 */
	public function moveStock(User $user)
	{
		return $this->stockMovements($user, false);
	}

	/**
	 * Put back in stock the products taken out by a record that is canceled or replaced
	 *
	 * @param	User	$user	User that cancels or replaces
	 * @return	int				Number of products put back, <0 if KO
	 */
	public function reverseStock(User $user)
	{
		return $this->stockMovements($user, true);
	}

	/**
	 * Stock movements of the products of the record (MouvementStock, never SQL on the stock tables)
	 *
	 * @param	User	$user		User of the action
	 * @param	bool	$reverse	False to take the products out, true to put back what was taken out
	 * @return	int					Number of products moved, <0 if at least one movement failed
	 */
	private function stockMovements(User $user, $reverse)
	{
		global $langs;

		if (!isModEnabled('stock') || $this->fetchLines() < 0) {
			return 0;
		}
		require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		dol_include_once('/dolifarm/lib/dolifarm_warehouse.lib.php');
		$langs->load('dolitrace@dolitrace');

		$warehouseid = ($reverse ? 0 : dolifarmGetFarmWarehouse($this->db, (int) $this->fk_soc, 'inputs'));
		$moved = 0;
		$failed = 0;
		$changes = array();
		foreach ($this->products as $product) {
			$status = (int) $product->stock_status;
			$movementid = $product->fk_stock_movement;
			$rowwarehouse = $product->fk_warehouse;
			$error = '';
			if ($reverse) {
				// Only what was taken out goes back; nothing is left to regularize on a canceled record
				if ($status == DoliTraceTreatmentProduct::STOCK_TO_REGULARIZE) {
					$status = DoliTraceTreatmentProduct::STOCK_NONE;
				}
				if ($status != DoliTraceTreatmentProduct::STOCK_MOVED) {
					if ($status != $product->stock_status) {
						$this->saveStockStatus($product, $status, '', $rowwarehouse, $movementid);
					}
					continue;
				}
			} elseif ($status == DoliTraceTreatmentProduct::STOCK_MOVED || $status == DoliTraceTreatmentProduct::STOCK_REVERSED) {
				continue;
			}

			$prod = new Product($this->db);
			if (empty($product->fk_product) || $prod->fetch($product->fk_product) <= 0 || $prod->type != Product::TYPE_PRODUCT) {
				// No product of the stock: nothing to move
				if ($status != DoliTraceTreatmentProduct::STOCK_NONE || (string) $product->stock_error !== '') {
					$this->saveStockStatus($product, DoliTraceTreatmentProduct::STOCK_NONE, '', null, null);
				}
				continue;
			}
			$batch = ($prod->hasbatch() ? (string) $product->batch : '');
			if (!$reverse) {
				$rowwarehouse = $warehouseid;
				if ($warehouseid <= 0) {
					$error = $langs->trans('ErrorTreatmentNoWarehouse');
				} elseif (!($product->qty_total > 0)) {
					$error = $langs->trans('ErrorTreatmentQtyTotal', $product->product_label);
				} elseif ($prod->hasbatch() && $batch === '') {
					$error = $langs->trans('ErrorTreatmentBatchRequired');
				}
			}

			if ($error === '') {
				$this->db->begin();
				$mouv = new MouvementStock($this->db);
				$mouv->setOrigin($this->element.'@dolitrace', $this->id);
				if ($reverse) {
					$label = $langs->trans('TreatmentStockReversal', $this->ref);
					$result = $mouv->reception($user, $prod->id, $rowwarehouse, (float) $product->qty_total, (float) $prod->pmp, $label, '', '', $batch, dol_now());
				} else {
					$label = $langs->trans('TreatmentStockTaken', $this->ref);
					$result = $mouv->livraison($user, $prod->id, $rowwarehouse, (float) $product->qty_total, (float) $prod->pmp, $label, $this->date_start, '', '', $batch);
				}
				if ($result > 0) {
					$this->db->commit();
					$status = ($reverse ? DoliTraceTreatmentProduct::STOCK_REVERSED : DoliTraceTreatmentProduct::STOCK_MOVED);
					$movementid = ($reverse ? $movementid : (int) $result);
					$moved++;
				} else {
					$this->db->rollback();
					$error = dol_string_nohtmltag(dol_html_entity_decode($mouv->error ? $mouv->error : implode(', ', $mouv->errors), ENT_QUOTES | ENT_HTML5));
					if ($error === '') {
						$error = $langs->trans('ErrorTreatmentStockMovement');
					}
				}
			}
			if ($error !== '') {
				$failed++;
				if (!$reverse) {
					$status = DoliTraceTreatmentProduct::STOCK_TO_REGULARIZE;
				}
				dol_syslog(__METHOD__.' record '.$this->id.' product '.$product->id.': '.$error, LOG_WARNING);
			}
			$this->saveStockStatus($product, $status, $error, $rowwarehouse, $movementid);
			$changes[] = array('product' => $product->product_label, 'qty' => $product->qty_total, 'unit' => $product->qty_unit, 'batch' => $batch,
				'warehouse' => (int) $rowwarehouse, 'movement' => ($error === '' && !$reverse ? (int) $movementid : 0), 'reverse' => $reverse ? 1 : 0, 'error' => $error);
		}
		if (count($changes)) {
			$this->addLog($user, 'STOCK', '', $changes);
		}
		return ($failed ? -1 : $moved);
	}

	/**
	 * Save the state of the stock of a product of the record (these columns are not part of the content of the register)
	 *
	 * @param	DoliTraceTreatmentProduct	$product		Product of the record
	 * @param	int							$status			State of the stock
	 * @param	string						$error			Reason of a movement to regularize
	 * @param	int|null					$warehouseid	Warehouse
	 * @param	int|null					$movementid		Movement that took the product out
	 * @return	void
	 */
	private function saveStockStatus($product, $status, $error, $warehouseid, $movementid)
	{
		$sql = "UPDATE ".$this->db->prefix()."dolitrace_treatment_product SET";
		$sql .= " stock_status = ".((int) $status);
		$sql .= ", stock_error = ".($error !== '' ? "'".$this->db->escape(dol_trunc($error, 250, 'right', 'UTF-8', 1))."'" : "NULL");
		$sql .= ", fk_warehouse = ".($warehouseid > 0 ? ((int) $warehouseid) : "NULL");
		$sql .= ", fk_stock_movement = ".($movementid > 0 ? ((int) $movementid) : "NULL");
		$sql .= " WHERE rowid = ".((int) $product->id);
		if (!$this->db->query($sql)) {
			dol_syslog(__METHOD__.' '.$this->db->lasterror(), LOG_ERR);
			return;
		}
		$product->stock_status = $status;
		$product->stock_error = ($error !== '' ? $error : null);
		$product->fk_warehouse = $warehouseid;
		$product->fk_stock_movement = $movementid;
	}

	/**
	 *	Set draft status
	 *
	 *	@param	User		$user		Object user that modify
	 *  @param	int<0,1>	$notrigger	1=Does not execute triggers, 0=Execute triggers
	 *	@return	int<0,1>				Return integer <0 if KO, >0 if OK
	 */
	public function setDraft($user, $notrigger = 0)
	{
		// Protection
		if ($this->status <= self::STATUS_DRAFT) {
			return 0;
		}
		// A validated record of the register is never brought back to draft
		$this->error = 'ErrorTreatmentValidatedLocked';
		return -1;
	}

	/**
	 *	Set cancel status
	 *
	 *	@param	User		$user		Object user that modify
	 *  @param	int<0,1>	$notrigger	1=Does not execute triggers, 0=Execute triggers
	 *	@return	int<-1,1>				Return integer <0 if KO, 0=Nothing done, >0 if OK
	 */
	public function cancel($user, $notrigger = 0)
	{
		// A record of the register is canceled with its reason: see cancelRecord()
		$this->error = 'ErrorTreatmentReasonRequired';
		return -1;
	}

	/**
	 *	Set back to validated status
	 *
	 *	@param	User		$user			Object user that modify
	 *  @param	int<0,1>	$notrigger		1=Does not execute triggers, 0=Execute triggers
	 *	@return	int<-1,1>					Return integer <0 if KO, 0=Nothing done, >0 if OK
	 */
	public function reopen($user, $notrigger = 0)
	{
		// Protection
		if ($this->status == self::STATUS_VALIDATED) {
			return 0;
		}
		// A replaced or canceled record of the register stays as it is
		$this->error = 'ErrorTreatmentValidatedLocked';
		return -1;
	}

	/**
	 * getTooltipContentArray
	 *
	 * @param	array<string,string> 	$params 	Params to construct tooltip data
	 * @since 	v18
	 * @return	array{optimize?:string,picto?:string,ref?:string}
	 */
	public function getTooltipContentArray($params)
	{
		global $langs;

		$datas = [];

		if (getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER')) {
			return ['optimize' => $langs->trans("ShowDoliTraceTreatment")];
		}
		$datas['picto'] = img_picto('', $this->picto).' <u>'.$langs->trans("DoliTraceTreatment").'</u>';
		if (isset($this->status)) {
			$datas['picto'] .= ' '.$this->getLibStatut(5);
		}
		if (property_exists($this, 'ref')) {
			$datas['ref'] = '<br><b>'.$langs->trans('Ref').':</b> '.$this->ref;
		}
		if (property_exists($this, 'label')) {
			$datas['label'] = '<br>'.$langs->trans('Label').':</b> '.$this->label;
		}

		return $datas;
	}

	/**
	 *  Return a link to the object card (with optionally the picto)
	 *
	 *  @param	int     $withpicto                  Include picto in link (0=No picto, 1=Include picto into link, 2=Only picto)
	 *  @param	string  $option                     On what the link point to ('nolink', ...)
	 *  @param	int     $notooltip                  1=Disable tooltip
	 *  @param	string  $morecss                    Add more css on link
	 *  @param	int     $save_lastsearch_value      -1=Auto, 0=No save of lastsearch_values when clicking, 1=Save lastsearch_values whenclicking
	 *  @return	string                              String with URL
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		global $conf, $langs, $hookmanager;

		if (!empty($conf->dol_no_mouse_hover)) {
			$notooltip = 1; // Force disable tooltips
		}

		$result = '';
		$params = [
			'id' => (string) $this->id,
			'objecttype' => $this->element.($this->module ? '@'.$this->module : ''),
			'option' => $option,
		];
		$classfortooltip = 'classfortooltip';
		$dataparams = '';
		if (getDolGlobalInt('MAIN_ENABLE_AJAX_TOOLTIP')) {
			$classfortooltip = 'classforajaxtooltip';
			$dataparams = ' data-params="'.dol_escape_htmltag(json_encode($params)).'"';
			$label = '';
		} else {
			$label = implode($this->getTooltipContentArray($params));
		}

		$baseurl = dol_buildpath('/dolitrace/dolitracetreatment_card.php', 1);
		$query = ['id' => $this->id];
		if ($option !== 'nolink') {
			// Add param to save lastsearch_values or not
			$add_save_lastsearch_values = ($save_lastsearch_value == 1 ? 1 : 0);
			if ($save_lastsearch_value == -1 && isset($_SERVER["PHP_SELF"]) && preg_match('/list\.php/', $_SERVER["PHP_SELF"])) {
				$add_save_lastsearch_values = 1;
			}
			if ($add_save_lastsearch_values) {
				$query = array_merge($query, ['save_lastsearch_values' => 1]);
			}
		}
		$url = dolBuildUrl($baseurl, $query);

		$linkclose = '';
		if (empty($notooltip)) {
			if (getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER')) {
				$label = $langs->trans("ShowDoliTraceTreatment");
				$linkclose .= ' alt="'.dolPrintHTMLForAttribute($label).'"';
			}
			$linkclose .= ($label ? ' title="'.dolPrintHTMLForAttribute($label).'"' : ' title="tocomplete"');
			$linkclose .= $dataparams.' class="'.$classfortooltip.($morecss ? ' '.$morecss : '').'"';
		} else {
			$linkclose = ($morecss ? ' class="'.$morecss.'"' : '');
		}

		if ($option == 'nolink') {
			$linkstart = '<span';
		} else {
			$linkstart = '<a href="'.$url.'"';
		}
		$linkstart .= $linkclose.'>';
		if ($option == 'nolink') {
			$linkend = '</span>';
		} else {
			$linkend = '</a>';
		}

		$result .= $linkstart;

		if (empty($this->showphoto_on_popup)) {
			if ($withpicto) {
				$result .= img_object(($notooltip ? '' : $label), ($this->picto ? $this->picto : 'generic'), (($withpicto != 2) ? 'class="paddingright"' : ''), 0, 0, $notooltip ? 0 : 1);
			}
		} else {
			if ($withpicto) {
				require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

				list($class, $module) = explode('@', $this->picto);
				$upload_dir = $conf->$module->multidir_output[$conf->entity]."/$class/".dol_sanitizeFileName($this->ref);
				$filearray = dol_dir_list($upload_dir, "files");
				$filename = $filearray[0]['name'];
				if (!empty($filename)) {
					$pospoint = strpos($filearray[0]['name'], '.');

					$pathtophoto = $class.'/'.$this->ref.'/thumbs/'.substr($filename, 0, $pospoint).'_mini'.substr($filename, $pospoint);
					if (!getDolGlobalString(strtoupper($module.'_'.$class).'_FORMATLISTPHOTOSASUSERS')) {
						$result .= '<div class="floatleft inline-block valignmiddle divphotoref"><div class="photoref"><img class="photo'.$module.'" alt="No photo" border="0" src="'.DOL_URL_ROOT.'/viewimage.php?modulepart='.$module.'&entity='.$conf->entity.'&file='.urlencode($pathtophoto).'"></div></div>';
					} else {
						$result .= '<div class="floatleft inline-block valignmiddle divphotoref"><img class="photouserphoto userphoto" alt="No photo" border="0" src="'.DOL_URL_ROOT.'/viewimage.php?modulepart='.$module.'&entity='.$conf->entity.'&file='.urlencode($pathtophoto).'"></div>';
					}

					$result .= '</div>';
				} else {
					$result .= img_object(($notooltip ? '' : $label), ($this->picto ? $this->picto : 'generic'), ($notooltip ? (($withpicto != 2) ? 'class="paddingright"' : '') : 'class="'.(($withpicto != 2) ? 'paddingright ' : '').'"'), 0, 0, $notooltip ? 0 : 1);
				}
			}
		}

		if ($withpicto != 2) {
			$result .= $this->ref;
		}

		$result .= $linkend;
		//if ($withpicto != 2) $result.=(($addlabel && $this->label) ? $sep . dol_trunc($this->label, ($addlabel > 1 ? $addlabel : 0)) : '');

		global $action, $hookmanager;
		$hookmanager->initHooks(array($this->element.'dao'));
		$parameters = array('id' => $this->id, 'getnomurl' => &$result);
		$reshook = $hookmanager->executeHooks('getNomUrl', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
		if ($reshook > 0) {
			$result = $hookmanager->resPrint;
		} else {
			$result .= $hookmanager->resPrint;
		}

		return $result;
	}

	/**
	 *	Return a thumb for kanban views
	 *
	 *	@param	string	    			$option		Where point the link (0=> main card, 1,2 => shipment, 'nolink'=>No link)
	 *  @param	?array<string,mixed>	$arraydata	Array of data
	 *  @return	string								HTML Code for Kanban thumb.
	 */
	public function getKanbanView($option = '', $arraydata = null)
	{
		global $conf, $langs;

		$selected = (empty($arraydata['selected']) ? 0 : $arraydata['selected']);

		$return = '<div class="box-flex-item box-flex-grow-zero">';
		$return .= '<div class="info-box info-box-sm">';
		$return .= '<span class="info-box-icon bg-infobox-action">';
		$return .= img_picto('', $this->picto);
		$return .= '</span>';
		$return .= '<div class="info-box-content">';
		$return .= '<span class="info-box-ref inline-block tdoverflowmax150 valignmiddle">'.(method_exists($this, 'getNomUrl') ? $this->getNomUrl() : $this->ref).'</span>';
		if ($selected >= 0) {
			$return .= '<input id="cb'.$this->id.'" class="flat checkforselect fright" type="checkbox" name="toselect[]" value="'.$this->id.'"'.($selected ? ' checked="checked"' : '').'>';
		}
		if (property_exists($this, 'label')) {
			$return .= ' <div class="inline-block opacitymedium valignmiddle tdoverflowmax100">'.$this->label.'</div>';
		}
		if (property_exists($this, 'thirdparty') && is_object($this->thirdparty)) {
			$return .= '<br><div class="info-box-ref tdoverflowmax150">'.$this->thirdparty->getNomUrl(1).'</div>';
		}
		if (property_exists($this, 'amount')) {
			$return .= '<br>';
			$return .= '<span class="info-box-label amount">'.price($this->amount, 0, $langs, 1, -1, -1, $conf->currency).'</span>';
		}
		if (method_exists($this, 'getLibStatut')) {
			$return .= '<br><div class="info-box-status">'.$this->getLibStatut(3).'</div>';
		}
		$return .= '</div>';
		$return .= '</div>';
		$return .= '</div>';

		return $return;
	}

	/**
	 *  Return the label of the status
	 *
	 *  @param	int<0,6>	$mode          0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return	string 			       Label of status
	 */
	public function getLabelStatus($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	/**
	 *  Return the label of the status
	 *
	 *  @param	int<0,6>	$mode	0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return	string				Label of status
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Return the label of a given status
	 *
	 *  @param	int			$status		Id status
	 *  @param	int<0,6>	$mode		0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return	string					Label of status
	 */
	public function LibStatut($status, $mode = 0)
	{
		// phpcs:enable
		if (is_null($status)) {
			return '';
		}

		$paramsBadge = array('badgeParams' => array('attr' => array(
			'data-status-element' => $this->element,
			'data-status' => (int) $status
		)));


		if (empty($this->labelStatus) || empty($this->labelStatusShort)) {
			global $langs;
			//$langs->load("dolitrace@dolitrace");
			$langs->load("dolitrace@dolitrace");
			$this->labelStatus[self::STATUS_DRAFT] = $langs->transnoentitiesnoconv('Draft');
			$this->labelStatus[self::STATUS_VALIDATED] = $langs->transnoentitiesnoconv('Validated');
			$this->labelStatus[self::STATUS_REPLACED] = $langs->transnoentitiesnoconv('TreatmentReplaced');
			$this->labelStatus[self::STATUS_CANCELED] = $langs->transnoentitiesnoconv('Canceled');
			$this->labelStatusShort[self::STATUS_DRAFT] = $langs->transnoentitiesnoconv('Draft');
			$this->labelStatusShort[self::STATUS_VALIDATED] = $langs->transnoentitiesnoconv('Validated');
			$this->labelStatusShort[self::STATUS_REPLACED] = $langs->transnoentitiesnoconv('TreatmentReplaced');
			$this->labelStatusShort[self::STATUS_CANCELED] = $langs->transnoentitiesnoconv('Canceled');
		}

		$statusType = 'status'.$status;
		//if ($status == self::STATUS_VALIDATED) $statusType = 'status1';
		if ($status == self::STATUS_VALIDATED) {
			$statusType = 'status4';
		} elseif ($status == self::STATUS_REPLACED) {
			$statusType = 'status5';
		} elseif ($status == self::STATUS_CANCELED) {
			$statusType = 'status9';
		}

		return dolGetStatus($this->labelStatus[$status], $this->labelStatusShort[$status], '', $statusType, $mode, '', $paramsBadge);
	}

	/**
	 *	Load the info information in the object
	 *
	 *	@param	int		$id       Id of object
	 *	@return	void
	 */
	public function info($id)
	{
		$sql = "SELECT t.rowid, t.date_creation as datec";
		if (!empty($this->isextrafieldmanaged) && $this->isextrafieldmanaged == 1) {
			$sql .= ", GREATEST(t.tms, COALESCE(te.tms, t.tms)) as datem";
		} else {
			$sql .= ", t.tms as datem";
		}
		if (!empty($this->fields['date_validation'])) {
			$sql .= ", t.date_validation as datev";
		}
		if (!empty($this->fields['fk_user_creat'])) {
			$sql .= ", t.fk_user_creat";
		}
		if (!empty($this->fields['fk_user_modif'])) {
			$sql .= ", t.fk_user_modif";
		}
		if (!empty($this->fields['fk_user_valid'])) {
			$sql .= ", t.fk_user_valid";
		}
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		if (!empty($this->isextrafieldmanaged) && $this->isextrafieldmanaged == 1) {
			$sql .= " LEFT JOIN ".$this->db->prefix().$this->table_element."_extrafields as te ON te.fk_object = t.rowid";
		}
		$sql .= " WHERE t.rowid = ".((int) $id);

		$result = $this->db->query($sql);
		if ($result) {
			if ($this->db->num_rows($result)) {
				$obj = $this->db->fetch_object($result);

				$this->id = $obj->rowid;

				if (!empty($this->fields['fk_user_creat'])) {
					$this->user_creation_id = $obj->fk_user_creat;
				}
				if (!empty($this->fields['fk_user_modif'])) {
					$this->user_modification_id = $obj->fk_user_modif;
				}
				if (!empty($this->fields['fk_user_valid'])) {
					$this->user_validation_id = $obj->fk_user_valid;
				}
				$this->date_creation = $this->db->jdate($obj->datec);
				$this->date_modification = empty($obj->datem) ? '' : $this->db->jdate($obj->datem);
				if (!empty($obj->datev)) {
					$this->date_validation = empty($obj->datev) ? '' : $this->db->jdate($obj->datev);
				}
			}

			$this->db->free($result);
		} else {
			dol_print_error($this->db);
		}
	}

	/**
	 * Initialize object with example values
	 * Id must be 0 if object instance is a specimen
	 *
	 * @return	int
	 */
	public function initAsSpecimen()
	{
		// Set here init that are not commonf fields
		// $this->property1 = ...
		// $this->property2 = ...

		return $this->initAsSpecimenCommon();
	}


	/**
	 *  Returns the reference to the following non used object depending on the active numbering module.
	 *
	 *  @return	string      		Object free reference
	 */
	public function getNextNumRef()
	{
		global $langs, $conf;
		$langs->load("dolitrace@dolitrace");

		if (!getDolGlobalString('DOLITRACE_TREATMENT_ADDON')) {
			$conf->global->DOLITRACE_TREATMENT_ADDON = 'mod_dolitracetreatment_standard';
		}

		if (getDolGlobalString('DOLITRACE_TREATMENT_ADDON')) {
			$mybool = false;

			$file = getDolGlobalString('DOLITRACE_TREATMENT_ADDON').".php";
			$classname = getDolGlobalString('DOLITRACE_TREATMENT_ADDON');

			// Include file with class
			$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
			foreach ($dirmodels as $reldir) {
				$dir = dol_buildpath($reldir."core/modules/dolitrace/");

				// Load file with numbering class (if found)
				$mybool = $mybool || @include_once $dir.$file;
			}

			if (!$mybool) {
				dol_print_error(null, "Failed to include file ".$file);
				return '';
			}

			if (class_exists($classname)) {
				$obj = new $classname();
				'@phan-var-force ModeleNumRefDoliTraceTreatment $obj';
				$numref = $obj->getNextValue($this);

				if ($numref != '' && $numref != '-1') {
					return $numref;
				} else {
					$this->error = $obj->error;
					//dol_print_error($this->db,get_class($this)."::getNextNumRef ".$obj->error);
					return "";
				}
			} else {
				print $langs->trans("Error")." ".$langs->trans("ClassNotFound").' '.$classname;
				return "";
			}
		} else {
			print $langs->trans("ErrorNumberingModuleNotSetup", $this->element);
			return "";
		}
	}

	/**
	 *  Create a document onto disk according to template module.
	 *
	 *  @param	string		$modele			Force template to use ('' to not force)
	 *  @param	Translate	$outputlangs	object lang a utiliser pour traduction
	 *  @param	int<0,1>	$hidedetails    Hide details of lines
	 *  @param	int<0,1>	$hidedesc       Hide description
	 *  @param	int<0,1>	$hideref        Hide ref
	 *  @param	?array<string,string>  $moreparams     Array to provide more information
	 *  @return	int         				0 if KO, 1 if OK
	 */
	public function generateDocument($modele, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = null)
	{
		global $langs;

		$result = 0;
		$includedocgeneration = 0;

		$langs->load("dolitrace@dolitrace");

		if (!dol_strlen($modele)) {
			if (!empty($this->model_pdf)) {
				$modele = $this->model_pdf;
			} else {
				$modele = getDolGlobalString('DOLITRACETREATMENT_ADDON_PDF', 'standard_dolitracetreatment');
			}
		}

		$modelpath = "core/modules/dolitrace/doc/";

		if ($includedocgeneration && !empty($modele)) {
			$result = $this->commonGenerateDocument($modelpath, $modele, $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams);
		}

		return $result;
	}

	/**
	 * Return validation test result for a field.
	 * Need MAIN_ACTIVATE_VALIDATION_RESULT to be called.
	 *
	 * @param   array<string,array{type:string,label:string,enabled:int<0,2>|string,position:int,notnull?:int,visible:int<-2,5>|string,noteditable?:int<0,1>,default?:int<0,1>|string,index?:int,foreignkey?:string,searchall?:int<0,1>,isameasure?:int<0,1>,css?:string,csslist?:string,help?:string,showoncombobox?:int<0,2>,disabled?:int<0,1>,arrayofkeyval?:array<int|string,string>,comment?:string,validate?:int<0,1>}>  $fields Array of properties of field to show
	 * @param	string  $fieldKey            Key of attribute
	 * @param	string  $fieldValue          value of attribute
	 * @return	bool 						Return false if fail, true on success, set $this->error for error message
	 */
	public function validateField($fields, $fieldKey, $fieldValue)
	{
		// Add your own validation rules here.
		// ...

		return parent::validateField($fields, $fieldKey, $fieldValue);
	}

	/**
	 * Action executed by scheduler
	 * CAN BE A CRON TASK. In such a case, parameters come from the schedule job setup field 'Parameters'
	 * Use public function doScheduledJob($param1, $param2, ...) to get parameters
	 *
	 * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
	 */
	public function doScheduledJob()
	{
		//global $conf, $langs;

		//$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_mydedicatedlogfile.log';

		$error = 0;
		$this->output = '';
		$this->error = '';

		dol_syslog(__METHOD__." start", LOG_INFO);

		//$now = dol_now();

		$this->db->begin();

		// ...

		$this->db->commit();

		dol_syslog(__METHOD__." end", LOG_INFO);

		return $error;
	}
}


require_once DOL_DOCUMENT_ROOT.'/core/class/commonobjectline.class.php';
/**
 * Common code of the rows of a record of the register (products and areas)
 */
abstract class DoliTraceTreatmentRow extends CommonObjectLine
{
	/**
	 * @var string	Name of the parent element
	 */
	public $parent_element = 'dolitracetreatment';

	/**
	 * @var string	Field with the ID of the parent record
	 */
	public $fk_parent_attribute = 'fk_treatment';

	/**
	 * @var int<0,1>	Does object support extrafields ? 0=No, 1=Yes
	 */
	public $isextrafieldmanaged = 0;

	/**
	 * @var int<0,1>|string|null	The entity is the one of the record
	 */
	public $ismultientitymanaged = 0;

	/**
	 * @var int
	 */
	public $rowid;

	/**
	 * @var int
	 */
	public $fk_treatment;

	/**
	 * @var int
	 */
	public $rang;

	/**
	 * Constructor
	 *
	 * @param	DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Create the row in database
	 *
	 * @param	User		$user		User that creates
	 * @param	int<0,1>	$notrigger	Not used: the rows have no triggers
	 * @return	int						Return integer <0 if KO, Id of created row if OK
	 */
	public function create(User $user, $notrigger = 1)
	{
		if (!$this->parentIsDraft()) {
			return -1;
		}
		return $this->createCommon($user, 1);
	}

	/**
	 * Load the row in memory from the database
	 *
	 * @param	int		$id		Id of the row
	 * @return	int				Return integer <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id)
	{
		return $this->fetchCommon($id);
	}

	/**
	 * Update the row in database
	 *
	 * @param	User		$user		User that modifies
	 * @param	int<0,1>	$notrigger	Not used: the rows have no triggers
	 * @return	int						Return integer <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = 1)
	{
		if (!$this->parentIsDraft()) {
			return -1;
		}
		return $this->updateCommon($user, 1);
	}

	/**
	 * Delete the row in database
	 *
	 * @param	User		$user		User that deletes
	 * @param	int<0,1>	$notrigger	Not used: the rows have no triggers
	 * @return	int						Return integer <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 1)
	{
		if (!$this->parentIsDraft()) {
			return -1;
		}
		return $this->deleteCommon($user, 1);
	}

	/**
	 * Check that the record of the row is a draft: the rows of a validated record are never changed
	 *
	 * @return	bool	True if the record is a draft
	 */
	public function parentIsDraft()
	{
		$sql = "SELECT status FROM ".$this->db->prefix()."dolitrace_treatment WHERE rowid = ".((int) $this->fk_treatment);
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : null;
		if (!$obj || (int) $obj->status != DoliTraceTreatment::STATUS_DRAFT) {
			$this->error = 'ErrorTreatmentValidatedLocked';
			return false;
		}
		return true;
	}

	/**
	 * Load the rows of a record, in their order
	 *
	 * @param	int		$fk_treatment	Id of the record
	 * @return	static[]|int			Array of rows if OK, <0 if KO
	 */
	public function fetchByTreatment($fk_treatment)
	{
		$rows = array();
		$sql = "SELECT ".$this->getFieldList('t');
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.fk_treatment = ".((int) $fk_treatment);
		$sql .= " ORDER BY t.rang, t.rowid";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$row = new static($this->db);
			$row->setVarsFromFetchObj($obj);
			$row->id = (int) $obj->rowid;
			$rows[] = $row;
		}
		$this->db->free($resql);
		return $rows;
	}
}

/**
 * Product of a record: plant protection product with its adversity, or fertilizer, with dose and quantity
 */
class DoliTraceTreatmentProduct extends DoliTraceTreatmentRow
{
	/**
	 * @var string	ID to identify managed object
	 */
	public $element = 'dolitracetreatmentproduct';

	/**
	 * @var string	Name of table without prefix where object is stored
	 */
	public $table_element = 'dolitrace_treatment_product';

	const STOCK_NONE = 0;
	const STOCK_MOVED = 1;
	const STOCK_TO_REGULARIZE = 2;
	const STOCK_REVERSED = 3;

	/**
	 * @inheritdoc
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'fk_treatment' => array('type' => 'integer', 'label' => 'Treatment', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => 0),
		'rang' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 6, 'notnull' => 0, 'visible' => 0, 'default' => '0'),
		'fk_agrodrug' => array('type' => 'integer', 'label' => 'TreatmentAgroDrug', 'enabled' => 1, 'position' => 10, 'notnull' => 0, 'visible' => 1),
		'fk_fertilizer' => array('type' => 'integer', 'label' => 'TreatmentFertilizer', 'enabled' => 1, 'position' => 11, 'notnull' => 0, 'visible' => 1),
		'fk_product' => array('type' => 'integer', 'label' => 'Product', 'enabled' => 1, 'position' => 12, 'notnull' => 0, 'visible' => 0),
		'product_label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 13, 'notnull' => 0, 'visible' => 1),
		'reg_number' => array('type' => 'varchar(64)', 'label' => 'TreatmentRegNumber', 'enabled' => 1, 'position' => 14, 'notnull' => 0, 'visible' => 1),
		'n_content' => array('type' => 'double(24,8)', 'label' => 'TreatmentNContent', 'enabled' => 1, 'position' => 15, 'notnull' => 0, 'visible' => 1),
		'p2o5_content' => array('type' => 'double(24,8)', 'label' => 'TreatmentP2O5Content', 'enabled' => 1, 'position' => 16, 'notnull' => 0, 'visible' => 1),
		'k2o_content' => array('type' => 'double(24,8)', 'label' => 'TreatmentK2OContent', 'enabled' => 1, 'position' => 17, 'notnull' => 0, 'visible' => 1),
		'fk_adversity' => array('type' => 'integer', 'label' => 'TreatmentAdversity', 'enabled' => 1, 'position' => 20, 'notnull' => 0, 'visible' => 1),
		'adversity_code' => array('type' => 'varchar(10)', 'label' => 'TreatmentAdversityCode', 'enabled' => 1, 'position' => 21, 'notnull' => 0, 'visible' => 1),
		'adversity_label' => array('type' => 'varchar(255)', 'label' => 'TreatmentAdversity', 'enabled' => 1, 'position' => 22, 'notnull' => 0, 'visible' => 1),
		'dose' => array('type' => 'double(24,8)', 'label' => 'TreatmentDose', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1),
		'fk_dose_unit' => array('type' => 'integer', 'label' => 'TreatmentDoseUnit', 'enabled' => 1, 'position' => 31, 'notnull' => 0, 'visible' => 1),
		'dose_unit_label' => array('type' => 'varchar(64)', 'label' => 'TreatmentDoseUnit', 'enabled' => 1, 'position' => 32, 'notnull' => 0, 'visible' => 1),
		'qty_total' => array('type' => 'double(24,8)', 'label' => 'TreatmentQtyTotal', 'enabled' => 1, 'position' => 33, 'notnull' => 0, 'visible' => 1),
		'qty_unit' => array('type' => 'varchar(8)', 'label' => 'Unit', 'enabled' => 1, 'position' => 34, 'notnull' => 0, 'visible' => 1),
		'qty_auto' => array('type' => 'integer', 'label' => 'TreatmentQtyAuto', 'enabled' => 1, 'position' => 35, 'notnull' => 1, 'visible' => 0, 'default' => '1'),
		'n_applied' => array('type' => 'double(24,8)', 'label' => 'TreatmentNApplied', 'enabled' => 1, 'position' => 35, 'notnull' => 0, 'visible' => 1),
		'batch' => array('type' => 'varchar(128)', 'label' => 'Batch', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'stock_status' => array('type' => 'integer', 'label' => 'TreatmentStockStatus', 'enabled' => 1, 'position' => 50, 'notnull' => 1, 'visible' => 0, 'default' => '0', 'arrayofkeyval' => array('0' => 'TreatmentStockNone', '1' => 'TreatmentStockMoved', '2' => 'TreatmentStockToRegularize', '3' => 'TreatmentStockReversed')),
		'stock_error' => array('type' => 'varchar(255)', 'label' => 'TreatmentStockError', 'enabled' => 1, 'position' => 53, 'notnull' => 0, 'visible' => 0),
		'fk_warehouse' => array('type' => 'integer', 'label' => 'Warehouse', 'enabled' => 1, 'position' => 51, 'notnull' => 0, 'visible' => 0),
		'fk_stock_movement' => array('type' => 'integer', 'label' => 'StockMovement', 'enabled' => 1, 'position' => 52, 'notnull' => 0, 'visible' => 0),
	);

	/**
	 * @var ?int
	 */
	public $fk_agrodrug;

	/**
	 * @var ?int
	 */
	public $fk_fertilizer;

	/**
	 * @var ?int
	 */
	public $fk_product;

	/**
	 * @var ?string
	 */
	public $product_label;

	/**
	 * @var ?string
	 */
	public $reg_number;

	/**
	 * @var float|string|null
	 */
	public $n_content;

	/**
	 * @var float|string|null
	 */
	public $p2o5_content;

	/**
	 * @var float|string|null
	 */
	public $k2o_content;

	/**
	 * @var ?int
	 */
	public $fk_adversity;

	/**
	 * @var ?string
	 */
	public $adversity_code;

	/**
	 * @var ?string
	 */
	public $adversity_label;

	/**
	 * @var float|string|null
	 */
	public $dose;

	/**
	 * @var ?int
	 */
	public $fk_dose_unit;

	/**
	 * @var ?string
	 */
	public $dose_unit_label;

	/**
	 * @var float|string|null
	 */
	public $qty_total;

	/**
	 * @var ?string
	 */
	public $qty_unit;

	/**
	 * @var int		1 if the total quantity is computed, 0 if entered by the user
	 */
	public $qty_auto;

	/**
	 * @var float|string|null
	 */
	public $n_applied;

	/**
	 * @var ?string
	 */
	public $batch;

	/**
	 * @var int
	 */
	public $stock_status;

	/**
	 * @var ?string	Reason of a movement to regularize
	 */
	public $stock_error;

	/**
	 * @var ?int
	 */
	public $fk_warehouse;

	/**
	 * @var ?int
	 */
	public $fk_stock_movement;
}

/**
 * Area of a record: a plot with the crop of its crop plan, a building or a seed lot
 */
class DoliTraceTreatmentTarget extends DoliTraceTreatmentRow
{
	/**
	 * @var string	ID to identify managed object
	 */
	public $element = 'dolitracetreatmenttarget';

	/**
	 * @var string	Name of table without prefix where object is stored
	 */
	public $table_element = 'dolitrace_treatment_target';

	const TYPE_PLOT = 1;
	const TYPE_BUILDING = 2;
	const TYPE_SEEDS = 3;

	/**
	 * @inheritdoc
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'fk_treatment' => array('type' => 'integer', 'label' => 'Treatment', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => 0),
		'rang' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 6, 'notnull' => 0, 'visible' => 0, 'default' => '0'),
		'target_type' => array('type' => 'integer', 'label' => 'TreatmentTargetType', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'default' => '1', 'arrayofkeyval' => array('1' => 'TreatmentTargetPlot', '2' => 'TreatmentTargetBuilding', '3' => 'TreatmentTargetSeeds')),
		'fk_plot' => array('type' => 'integer', 'label' => 'TreatmentPlot', 'enabled' => 1, 'position' => 11, 'notnull' => 0, 'visible' => 1),
		'fk_building' => array('type' => 'integer', 'label' => 'TreatmentBuilding', 'enabled' => 1, 'position' => 12, 'notnull' => 0, 'visible' => 1),
		'fk_cropplan' => array('type' => 'integer', 'label' => 'TreatmentCropPlan', 'enabled' => 1, 'position' => 13, 'notnull' => 0, 'visible' => 1),
		'location_ref' => array('type' => 'varchar(128)', 'label' => 'TreatmentLocationRef', 'enabled' => 1, 'position' => 14, 'notnull' => 0, 'visible' => 1),
		'zone_zvn' => array('type' => 'integer', 'label' => 'TreatmentZoneZvn', 'enabled' => 1, 'position' => 15, 'notnull' => 0, 'visible' => 1, 'default' => '0'),
		'fk_crop' => array('type' => 'integer', 'label' => 'TreatmentCrop', 'enabled' => 1, 'position' => 20, 'notnull' => 0, 'visible' => 1),
		'crop_eppo' => array('type' => 'varchar(10)', 'label' => 'TreatmentCropEppo', 'enabled' => 1, 'position' => 21, 'notnull' => 0, 'visible' => 1),
		'crop_label' => array('type' => 'varchar(255)', 'label' => 'TreatmentCrop', 'enabled' => 1, 'position' => 22, 'notnull' => 0, 'visible' => 1),
		'bbch' => array('type' => 'varchar(3)', 'label' => 'TreatmentBbch', 'enabled' => 1, 'position' => 23, 'notnull' => 0, 'visible' => 1),
		'treated_qty' => array('type' => 'double(24,8)', 'label' => 'TreatmentTreatedQty', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1),
		'treated_unit' => array('type' => 'varchar(8)', 'label' => 'Unit', 'enabled' => 1, 'position' => 31, 'notnull' => 0, 'visible' => 1),
		'seed_batch' => array('type' => 'varchar(128)', 'label' => 'TreatmentSeedBatch', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
	);

	/**
	 * @var int
	 */
	public $target_type;

	/**
	 * @var ?int
	 */
	public $fk_plot;

	/**
	 * @var ?int
	 */
	public $fk_building;

	/**
	 * @var ?int
	 */
	public $fk_cropplan;

	/**
	 * @var ?string
	 */
	public $location_ref;

	/**
	 * @var int
	 */
	public $zone_zvn;

	/**
	 * @var ?int
	 */
	public $fk_crop;

	/**
	 * @var ?string
	 */
	public $crop_eppo;

	/**
	 * @var ?string
	 */
	public $crop_label;

	/**
	 * @var ?string
	 */
	public $bbch;

	/**
	 * @var float|string|null
	 */
	public $treated_qty;

	/**
	 * @var ?string
	 */
	public $treated_unit;

	/**
	 * @var ?string
	 */
	public $seed_batch;
}
