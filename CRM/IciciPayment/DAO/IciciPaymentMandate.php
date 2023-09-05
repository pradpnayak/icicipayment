<?php

/**
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 *
 * Generated from icicipayment/xml/schema/CRM/IciciPayment/IciciPayment.xml
 * DO NOT EDIT.  Generated by CRM_Core_CodeGen
 * (GenCodeChecksum:dd4c388f397ddf76ad83bc3bc5ef7149)
 */
use CRM_IciciPayment_ExtensionUtil as E;

/**
 * Database access object for the IciciPaymentMandate entity.
 */
class CRM_IciciPayment_DAO_IciciPaymentMandate extends CRM_Core_DAO {
  const EXT = E::LONG_NAME;
  const TABLE_ADDED = '1.0';

  /**
   * Static instance to hold the table name.
   *
   * @var string
   */
  public static $_tableName = 'civicrm_icici_mandates';

  /**
   * Should CiviCRM log any modifications to this table in the civicrm_log table.
   *
   * @var bool
   */
  public static $_log = TRUE;

  /**
   * Unique ID
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $id;

  /**
   * FK to Contribution Recur ID.
   *
   * @var int|string
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $contribution_recur_id;

  /**
   * Mandate
   *
   * @var string
   *   (SQL type: varchar(255))
   *   Note that values will be retrieved from the database as a string.
   */
  public $mandate;

  /**
   * When the data was created.
   *
   * @var string
   *   (SQL type: timestamp)
   *   Note that values will be retrieved from the database as a string.
   */
  public $created_date;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->__table = 'civicrm_icici_mandates';
    parent::__construct();
  }

  /**
   * Returns localized title of this entity.
   *
   * @param bool $plural
   *   Whether to return the plural version of the title.
   */
  public static function getEntityTitle($plural = FALSE) {
    return $plural ? E::ts('Icici Payment Mandates') : E::ts('Icici Payment Mandate');
  }

  /**
   * Returns foreign keys and entity references.
   *
   * @return array
   *   [CRM_Core_Reference_Interface]
   */
  public static function getReferenceColumns() {
    if (!isset(Civi::$statics[__CLASS__]['links'])) {
      Civi::$statics[__CLASS__]['links'] = static::createReferenceColumns(__CLASS__);
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'contribution_recur_id', 'civicrm_contribution_recur', 'id');
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'links_callback', Civi::$statics[__CLASS__]['links']);
    }
    return Civi::$statics[__CLASS__]['links'];
  }

  /**
   * Returns all the column names of this table
   *
   * @return array
   */
  public static function &fields() {
    if (!isset(Civi::$statics[__CLASS__]['fields'])) {
      Civi::$statics[__CLASS__]['fields'] = [
        'id' => [
          'name' => 'id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('ID'),
          'description' => E::ts('Unique ID'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_icici_mandates.id',
          'table_name' => 'civicrm_icici_mandates',
          'entity' => 'IciciPaymentMandate',
          'bao' => 'CRM_IciciPayment_DAO_IciciPaymentMandate',
          'localizable' => 0,
          'readonly' => TRUE,
          'add' => '1.0',
        ],
        'icici_contribution_recur_id' => [
          'name' => 'contribution_recur_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Contribution Recur Id'),
          'description' => E::ts('FK to Contribution Recur ID.'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_icici_mandates.contribution_recur_id',
          'table_name' => 'civicrm_icici_mandates',
          'entity' => 'IciciPaymentMandate',
          'bao' => 'CRM_IciciPayment_DAO_IciciPaymentMandate',
          'localizable' => 0,
          'FKClassName' => 'CRM_Contribute_DAO_ContributionRecur',
          'add' => '1.0',
        ],
        'icici_mandate' => [
          'name' => 'mandate',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('Mandate'),
          'description' => E::ts('Mandate'),
          'required' => TRUE,
          'maxlength' => 255,
          'size' => CRM_Utils_Type::HUGE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_icici_mandates.mandate',
          'table_name' => 'civicrm_icici_mandates',
          'entity' => 'IciciPaymentMandate',
          'bao' => 'CRM_IciciPayment_DAO_IciciPaymentMandate',
          'localizable' => 0,
          'serialize' => self::SERIALIZE_JSON,
          'add' => '1.0',
        ],
        'icici_created_date' => [
          'name' => 'created_date',
          'type' => CRM_Utils_Type::T_TIMESTAMP,
          'title' => E::ts('Created Date'),
          'description' => E::ts('When the data was created.'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_icici_mandates.created_date',
          'default' => 'CURRENT_TIMESTAMP',
          'table_name' => 'civicrm_icici_mandates',
          'entity' => 'IciciPaymentMandate',
          'bao' => 'CRM_IciciPayment_DAO_IciciPaymentMandate',
          'localizable' => 0,
          'html' => [
            'label' => E::ts("Created Date"),
          ],
          'add' => '1.0',
        ],
      ];
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'fields_callback', Civi::$statics[__CLASS__]['fields']);
    }
    return Civi::$statics[__CLASS__]['fields'];
  }

  /**
   * Return a mapping from field-name to the corresponding key (as used in fields()).
   *
   * @return array
   *   Array(string $name => string $uniqueName).
   */
  public static function &fieldKeys() {
    if (!isset(Civi::$statics[__CLASS__]['fieldKeys'])) {
      Civi::$statics[__CLASS__]['fieldKeys'] = array_flip(CRM_Utils_Array::collect('name', self::fields()));
    }
    return Civi::$statics[__CLASS__]['fieldKeys'];
  }

  /**
   * Returns the names of this table
   *
   * @return string
   */
  public static function getTableName() {
    return self::$_tableName;
  }

  /**
   * Returns if this table needs to be logged
   *
   * @return bool
   */
  public function getLog() {
    return self::$_log;
  }

  /**
   * Returns the list of fields that can be imported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &import($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getImports(__CLASS__, 'icici_mandates', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of fields that can be exported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &export($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getExports(__CLASS__, 'icici_mandates', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of indices
   *
   * @param bool $localize
   *
   * @return array
   */
  public static function indices($localize = TRUE) {
    $indices = [
      'UI_icici_contribution_recur_id' => [
        'name' => 'UI_icici_contribution_recur_id',
        'field' => [
          0 => 'contribution_recur_id',
        ],
        'localizable' => FALSE,
        'unique' => TRUE,
        'sig' => 'civicrm_icici_mandates::1::contribution_recur_id',
      ],
    ];
    return ($localize && !empty($indices)) ? CRM_Core_DAO_AllCoreTables::multilingualize(__CLASS__, $indices) : $indices;
  }

}