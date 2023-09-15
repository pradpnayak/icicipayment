<?php

/**
 * IciciPaymentMandate.verifymandate API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_icici_payment_mandate_verifymandate($params) {
  $obj = new CRM_IciciPayment_Utils_MandateVerification();
  $obj->processTransactions();
  return civicrm_api3_create_success([], $params, 'IciciPaymentMandate', 'verifymandate');
}

/**
 * IciciPaymentMandate.scheduletransaction API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_icici_payment_mandate_scheduletransaction($params) {
  $obj = new CRM_IciciPayment_Utils_TransactionScheduling();
  $obj->processTransactions();
  return civicrm_api3_create_success([], $params, 'IciciPaymentMandate', 'scheduletransaction');
}

/**
 * IciciPaymentMandate.verifytransaction API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_icici_payment_mandate_verifytransaction($params) {
  $obj = new CRM_IciciPayment_Utils_TransactionVerification();
  $obj->processTransactions();
  return civicrm_api3_create_success([], $params, 'IciciPaymentMandate', 'verifytransaction');
}
