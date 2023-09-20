<?php

class CRM_IciciPayment_Utils_TransactionVerification {

  public function __construct() {
    $this->_progressStatusId = (int) CRM_Core_PseudoConstant::getKey(
      'CRM_Contribute_BAO_ContributionRecur', 'contribution_status_id', 'In Progress'
    );

    $this->_pendingContStatusId = (int) CRM_Core_PseudoConstant::getKey(
      'CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending'
    );
  }

  public function processTransactions() {
    $query = "
      SELECT
        cr.id recur_id, cc.id contribution_id,
        cc.receive_date payment_date, cc.trxn_id trxn_id,
        cc.currency currency
      FROM civicrm_contribution_recur cr
        INNER JOIN civicrm_payment_processor cp
          ON cp.id = cr.payment_processor_id
        INNER JOIN civicrm_payment_processor_type cpt
          ON cpt.id = cp.payment_processor_type_id
            AND cpt.name = 'icici_e_nach'
        INNER JOIN civicrm_contribution cc
          ON cc.contribution_recur_id = cr.id
            AND cc.contribution_status_id = %2
        INNER JOIN civicrm_icici_enach_details cied
          ON cied.entity_id = cc.id
            AND cied.initiated_request = 1
      WHERE cr.contribution_status_id = %1
      ORDER BY cc.receive_date
      LIMIT 30;
    ";
    $results = CRM_Core_DAO::executeQuery(
      $query, [
        1 => [$this->_progressStatusId, 'Integer'],
        2 => [$this->_pendingContStatusId, 'Integer'],
      ]
    )->fetchAll();

    foreach ($results as $result) {
      $response = $this->verifyPayment($result);
    }
  }

  private function verifyPayment($params) {
    $paymentProcessorObj = CRM_Financial_BAO_PaymentProcessor::getProcessorForEntity(
      $params['recur_id'], 'recur', 'obj'
    );
    $paymentParams = [
      'merchant' => [
        'identifier' => $paymentProcessorObj->getPaymentProcessor()['user_name'],
      ],
      'payment' => [
        'instruction' => (object)[],
      ],
      'transaction' => [
        'deviceIdentifier' => 'S',
        'type' => '002',
        'subType' => '004',
        'requestType' => 'TSI',
        'currency' => $params['currency'] ?? 'INR',
        'identifier' => $params['trxn_id'],
        'dateTime' => date('d-m-Y', strtotime($params['payment_date'])),
      ],
    ];
    CRM_Core_Error::debug('$paymentParams', $paymentParams);
    $response = $paymentProcessorObj->getResponse($paymentParams);
    $failed = FALSE;
    CRM_Core_Error::debug('test', $response);exit;
    if (!empty($response['paymentMethod'])) {
      $code = $response['paymentMethod']['paymentTransaction']['statusCode'] ?? '';
      if ($code === '0300') {
        // FIXME check status and call completed
      }
      else if ($code === '0399') {
        $failed = TRUE;
        $msg = $response['paymentMethod']['paymentTransaction']['statusMessage'] ?? '';
        // FIXME set contribution to failed.
      }
    }
  }

}
