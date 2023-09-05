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
      'subject' => ts('Scheme Code'),
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

}

function icicipayment_civicrm_buildForm($formName, &$form) {
  if (in_array($formName, [
    'CRM_Contribute_Form_Contribution_Main', 'CRM_Event_Form_Registration_Register'
  ])) {
  }
}
