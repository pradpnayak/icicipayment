<?php

class CRM_IciciPayment_Utils_TransactionScheduling {

  public function __construct() {
    $this->_progressStatusId = (int) CRM_Core_PseudoConstant::getKey(
      'CRM_Contribute_BAO_ContributionRecur', 'contribution_status_id', 'In Progress'
    );

    $this->_pendingContStatusId = (int) CRM_Core_PseudoConstant::getKey(
      'CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending'
    );
  }

  public function processTransactions() {
    $this->processFirstPayment();
    $this->processSubsequentPayment();
  }

  private function schedulePayment($params) {
    $paymentProcessorObj = CRM_Financial_BAO_PaymentProcessor::getProcessorForEntity(
      $params['recur_id'], 'recur', 'obj'
    );
    $paymentParams = [
      'merchant' => [
        'identifier' => $paymentProcessorObj->getPaymentProcessor()['user_name'],
      ],
      'payment' => [
        'instrument' => [
          'identifier' => $paymentProcessorObj->getPaymentProcessor()['subject'],
        ],
        'instruction' => [
          'amount' => $params['amount'],
          'endDateTime' => date('dmY', strtotime($params['payment_date'])),
          'identifier' => $params['mandate'],
        ],
      ],
      'transaction' => [
        'deviceIdentifier' => 'S',
        'type' => '002',
        'subType' => '003',
        'requestType' => 'TSI',
        'currency' => $params['currency'] ?? 'INR',
        'identifier' => $params['trxn_id'],
      ],
    ];

    $response = $paymentProcessorObj->getResponse($paymentParams);
    $failed = FALSE;
    //CRM_Core_Error::debug($response);exit;
    if (!empty($response['paymentMethod'])) {
      $code = $response['paymentMethod']['paymentTransaction']['statusCode'] ?? '';
      if ($code === '0300') {
        // FIXME check status and call completed
      }
      else if ($code === '0398') {
        // FIXME check status and call completed
      }
      else if ($code === '0399') {
        $failed = TRUE;
        $msg = $response['paymentMethod']['paymentTransaction']['statusMessage'] ?? '';
        // FIXME set contribution to failed.
      }
    }

    $paymentProcessorObj->updateRecurring($params['recur_id'], $failed);
  }

  private function processSubsequentPayment() {
    $query = "
      SELECT
        cr.id recur_id, cr.contact_id, ci.mandate, cr.amount
      FROM civicrm_contribution_recur cr
        INNER JOIN civicrm_payment_processor cp
          ON cp.id = cr.payment_processor_id
        INNER JOIN civicrm_payment_processor_type cpt
          ON cpt.id = cp.payment_processor_type_id
            AND cpt.name = 'icici_e_nach'
        INNER JOIN civicrm_icici_mandates ci
          ON ci.contribution_recur_id = cr.id
      WHERE cr.contribution_status_id = %1
        AND cr.next_sched_contribution_date IS NOT NULL
        AND cr.next_sched_contribution_date > DATE_SUB(CURDATE(), INTERVAL 2 DAY)
          AND cr.next_sched_contribution_date <= CURDATE()
      GROUP BY cr.id
      LIMIT 10;
    ";
    $results = CRM_Core_DAO::executeQuery(
      $query, [1 => [$this->_progressStatusId, 'Integer']]
    )->fetchAll();

    foreach ($results as $result) {
      $this->createContribution($result);
      $response = $this->schedulePayment($result);
    }
  }

  private function createContribution(&$params) {
    $trxnId = ['icici_', $params['recur_id'], $params['contact_id'], date('YmdHis')];
    $trxnId =  implode('_', $trxnId);
    $contributionParams = [
      'contribution_recur_id' => $params['recur_id'],
      'contribution_status_id' => $this->_pendingContStatusId,
      'receive_date' => date('YmdHis'),
      'order_reference' => $trxnId,
      'trxn_id' => $trxnId,
    ];

    try {
      $contribution = reset(civicrm_api3(
        'Contribution', 'repeattransaction', $contributionParams
      )['values']);
      $params['currency'] = $contribution['currency'];
      $params['trxn_id'] = $contribution['trxn_id'];
      $params['payment_date'] = $contribution['receive_date'];
    }
    catch (Exception $e) {
      // We catch, log, throw again so we have debug details in the logs
      $message = 'Icici-Enach call to repeattransaction failed: ' . $e->getMessage() . '; params: ' . print_r($contributionParams, TRUE);
      \Civi::log()->error($message);
      throw new Exception($message);
    }
  }

  private function processFirstPayment() {
    $query = "
      SELECT
        cr.id recur_id, MAX(cc.id) contribution_id,
        MAX(cc.receive_date) payment_date, MAX(cc.trxn_id) trxn_id,
        MAX(ci.mandate) mandate, MAX(cr.amount) amount,
        MAX(cr.currency) currency
      FROM civicrm_contribution_recur cr
        INNER JOIN civicrm_payment_processor cp
          ON cp.id = cr.payment_processor_id
        INNER JOIN civicrm_payment_processor_type cpt
          ON cpt.id = cp.payment_processor_type_id
            AND cpt.name = 'icici_e_nach'
        INNER JOIN civicrm_icici_mandates ci
          ON ci.contribution_recur_id = cr.id
        INNER JOIN civicrm_contribution cc
          ON cc.contribution_recur_id = cr.id
      WHERE cr.contribution_status_id = %1
        AND cr.create_date < DATE_SUB(now(), INTERVAL 26 HOUR)
      GROUP BY cr.id
      HAVING count(cc.id) = 1
      LIMIT 10;
    ";
    $results = CRM_Core_DAO::executeQuery(
      $query, [1 => [$this->_progressStatusId, 'Integer']]
    )->fetchAll();

    foreach ($results as $result) {
      $response = $this->schedulePayment($result);
    }
  }

}
