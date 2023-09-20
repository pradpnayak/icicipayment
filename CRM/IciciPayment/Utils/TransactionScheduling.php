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
    $this->_errorMessage = '';
    $this->_contributionId = $params['contribution_id'];
    $this->_reponseData['Unique_Ref_Number'] = $params['trxn_id'];
    $date = $response['paymentMethod']['paymentTransaction']['dateTime'];
    if (empty($date)) {
      $date = $params['payment_date'];
    }
    $this->_reponseData['Transaction_Date'] = $date;
    if (!empty($response['paymentMethod'])) {
      $code = $response['paymentMethod']['paymentTransaction']['statusCode'] ?? '';
      if ($code === '0300') {
        $this->completeContribution(
          $params['contribution_id'],  $params['recur_id'],
          $params['trxn_id'], date('YmdHis', strtotime($date));
        );
      }
      else if ($code === '0398') {
        \Civi\Api4\Contribution::update(TRUE)
          ->addValue('receive_date', date('YmdHis', strtotime($date)))
          ->addValue('trxn_id', $params['trxn_id'])
          ->addWhere('id', '=', $params['contribution_id'])
          ->addValue('ICICI_Enach_Details.Trxn_request_date', date('YmdHis'))
          ->addValue('ICICI_Enach_Details.Initiated_Request', 1)
          ->execute();
      }
      else if ($code === '0399') {
        $failed = TRUE;
        $this->_errorMessage = 'Failed: ' . ($response['paymentMethod']['paymentTransaction']['statusMessage'] ?? '');

        if (stripos($this->_errorMessage, 'Technical Problem') !== FALSE) {
          return;
        }
        $this->failContribution();
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
      CRM_Core_Error::debug_log_message(
        'Schedule-sunsequent_payment:' . print_r($result, 1),
        FALSE, 'IciciEnach', PEAR_LOG_INFO
      );
      $response = $this->schedulePayment($result);
    }
  }

  private function buildTrxnId($recurId, $contactId) {
    $trxnId = [$recurId, 'icici', $contactId, date('YmdHis')];
    return implode('', $trxnId);
  }

  private function createContribution(&$params) {
    $trxnId = $this->buildTrxnId($params['recur_id'], $params['contact_id']);
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
      $params['contribution_id'] = $contribution['id'];
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
        MAX(cc.trxn_id) trxn_id,
        MAX(ci.mandate) mandate, MAX(cr.amount) amount,
        MAX(cr.currency) currency,
        MAX(cr.contact_id) contact_id
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
        LEFT JOIN civicrm_icici_enach_details cied
          ON cied.entity_id = cc.id
      WHERE cr.contribution_status_id = %1
        AND cr.create_date < DATE_SUB(now(), INTERVAL 26 HOUR)
        AND (cied.initiated_request IS NULL OR cied.initiated_request = 0)
      GROUP BY cr.id
      HAVING count(cc.id) = 1
      LIMIT 10;
    ";
    $results = CRM_Core_DAO::executeQuery(
      $query, [1 => [$this->_progressStatusId, 'Integer']]
    )->fetchAll();

    foreach ($results as $result) {
      if (empty($result['trxn_id'])) {
        $result['trxn_id'] = $this->buildTrxnId($result['recur_id'], $result['contact_id']);
      }
      $result['payment_date'] = date('YmdHis');
      CRM_Core_Error::debug_log_message(
        'Schedule-First_payment:' . print_r($result, 1),
        FALSE, 'IciciEnach', PEAR_LOG_INFO
      );
      $response = $this->schedulePayment($result);
    }
  }

}
