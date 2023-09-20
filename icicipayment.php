<?php

require_once 'icicipayment.civix.php';
// phpcs:disable
use CRM_Icicipayment_ExtensionUtil as E;
// phpcs:enable

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function icicipayment_civicrm_config(&$config): void {
  _icicipayment_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function icicipayment_civicrm_install(): void {
  _icicipayment_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function icicipayment_civicrm_enable(): void {
  _icicipayment_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_managed().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
 */
function icicipayment_civicrm_managed(&$entities) {
  $entities[] = [
    'name' => 'ICICI Payment Easy Pay',
    'entity' => 'PaymentProcessorType',
    'module' => 'icicipayment',
    'params' => [
      'version' => 3,
      'title' => ts('ICICI (Easy pay)'),
      'name' => 'icici_easy_pay',
      'description' => ts('ICICI Payment Processor'),
      'user_name_label' => ts('ICID'),
      'password_label' => ts('AES key'),
      'class_name' => 'Payment_IciciEasyPay',
      'site_url' => '',
      'billing_mode' => 4,
      'payment_type' => 1,
      'is_recur' => FALSE,
      'is_active' => 1,
    ],
  ];

  $entities[] = [
    'name' => 'ICICI Payment E-nach',
    'entity' => 'PaymentProcessorType',
    'module' => 'icicipayment',
    'params' => [
      'version' => 3,
      'title' => ts('ICICI (E-NACH)'),
      'name' => 'icici_e_nach',
      'description' => ts('ICICI Payment Processor'),
      'user_name_label' => ts('Merchant Code'),
      'password_label' => ts('Salt'),
      'site_url' => 'https://www.paynimo.com/api/paynimoV2.req',
      'class_name' => 'Payment_IciciEnach',
      'billing_mode' => 4,
      'subject_label' => ts('Scheme Code'),
      'payment_type' => CRM_Core_Payment::PAYMENT_TYPE_DIRECT_DEBIT,
      'is_recur' => TRUE,
      'is_active' => 1,
    ],
  ];

  $ppFa = civicrm_api3('FinancialAccount', 'getvalue', [
    'return' => 'id',
    'name' => 'Payment Processor Account',
  ]);

  foreach ([
    'NEFT_RTGS' => ts('RTGS and NEFT'),
    'NET_BANKING_ICICI' => ts('Netbanking (ICICI Bank)'),
    'NET_BANKING' => ts('Netbanking (Other Banks)'),
    'ICICI_CASH' => ts('Cash ICICI'),
    'ICICI_CHEQUE' => ts('Cheque ICICI'),
    'UPI_ICICI' => ts('UPI ICICI'),
    'Direct Debit' => ts('Direct Debit'),
  ] as $name => $label) {
    $entities[] = [
      'name' => 'ICICI Payment methods ' . $name,
      'entity' => 'OptionValue',
      'module' => 'icicipayment',
      'update' => 'never',
      'params' => [
        'label' => $label,
        'name' => $name,
        'option_group_id' => 'payment_instrument',
        'options' => ['match' => ['option_group_id', 'name']],
        'is_active' => 1,
        'is_reserved' => 1,
        'financial_account_id' => $ppFa,
        'version' => 3,
      ],
    ];
  }

  $entities[] = [
    'name' => 'CustomGroup_ICICI_Enach_Details',
    'entity' => 'CustomGroup',
    'update' => 'never',
    'module' => 'icicipayment',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'ICICI_Enach_Details',
        'title' => E::ts('ICICI Enach Details'),
        'extends' => 'Contribution',
        'extends_entity_column_id' => NULL,
        'extends_entity_column_value' => NULL,
        'style' => 'Inline',
        'collapse_display' => FALSE,
        'is_active' => TRUE,
        'is_multiple' => FALSE,
        'min_multiple' => NULL,
        'max_multiple' => NULL,
        'collapse_adv_display' => TRUE,
        'table_name' => 'civicrm_icici_enach_details',
        'is_reserved' => TRUE,
        'is_public' => FALSE,
      ],
      'match' => ['name'],
    ],
  ];

  $entities[] = [
    'name' => 'CustomGroup_ICICI_Enach_Details_CustomField_Initiated_Request',
    'entity' => 'CustomField',
    'update' => 'never',
    'module' => 'icicipayment',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'ICICI_Enach_Details',
        'name' => 'Initiated_Request',
        'label' => E::ts('Initiated Request'),
        'data_type' => 'Boolean',
        'html_type' => 'Radio',
        'default_value' => NULL,
        'is_required' => FALSE,
        'is_searchable' => FALSE,
        'is_search_range' => FALSE,
        'is_active' => TRUE,
        'is_view' => TRUE,
        'options_per_line' => NULL,
        'text_length' => 255,
        'note_columns' => 60,
        'note_rows' => 4,
        'column_name' => 'initiated_request',
        'option_group_id' => NULL,
        'in_selector' => FALSE,
      ],
      'match' => [
        'name', 'custom_group_id'],
    ],
  ];

  $entities[] = [
    'name' => 'CustomGroup_ICICI_Enach_Details_CustomField_Trxn_request_date',
    'entity' => 'CustomField',
    'update' => 'never',
    'module' => 'icicipayment',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'ICICI_Enach_Details',
        'name' => 'Trxn_request_date',
        'label' => E::ts('Trxn request date'),
        'data_type' => 'Date',
        'html_type' => 'Select Date',
        'is_required' => FALSE,
        'is_active' => TRUE,
        'is_view' => TRUE,
        'options_per_line' => NULL,
        'text_length' => 255,
        'date_format' => 'mm/dd/yy',
        'time_format' => 2,
        'note_columns' => 60,
        'note_rows' => 4,
        'column_name' => 'trxn_request_date',
        'in_selector' => FALSE,
      ],
      'match' => ['name','custom_group_id'],
    ],
  ];

}

function icicipayment_civicrm_buildForm($formName, &$form) {
  if (in_array($formName, ['CRM_Contribute_Form_Contribution'])) {

  }
}

function icicipayment_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  if (in_array($formName, ['CRM_Contribute_Form_Contribution_Main'])) {
    if (empty($fields['is_recur'])
      && !empty($fields['payment_processor_id'])
    ) {
      $count = $paymentProcessors = \Civi\Api4\PaymentProcessor::get(FALSE)
        ->selectRowCount()
        ->addWhere('id', '=', $fields['payment_processor_id'])
        ->addWhere('payment_processor_type_id:name', '=', 'icici_e_nach')
        ->execute()->rowCount;
      if ($count > 0) {
        $errors['is_recur'] = ts('For the selected payment method, the payment should be recurring.');
      }
    }
  }
}
