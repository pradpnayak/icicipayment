<?php

class CRM_IciciPayment_Utils_MandateVerification {

  public function __construct() {

  }

  public function processTransactions() {
    $query = "
      SELECT
        cr.id recur_id
      FROM civicrm_contribution_recur cr
        INNER JOIN civicrm_payment_processor cp
          ON cp.id = cr.payment_processor_id
        INNER JOIN civicrm_payment_processor_type cpt
          ON cpt.id = cp.payment_processor_type_id
            AND cpt.name = 'icici_e_nach'
        LEFT JOIN civicrm_icici_mandates ci
          ON ci.contribution_recur_id = cr.id
      WHERE cr.contribution_status_id = 2 AND ci.id IS NULL
      GROUP BY cr.id
      LIMIT 10;
    ";
    $results = CRM_Core_DAO::executeQuery($query)->fetchAll();
    foreach ($results as $result) {
      $this->createMandate($result['recur_id']);
    }
  }

  public function createMandate(int $recurId, $mandate = NULL) {
    $recurDetails = CRM_IciciPayment_BAO_IciciPaymentMandate::getRecurDetails(
      $recurId
    );
    $mandateId = $this->verifyMandate([
      'recur_id' => $recurId,
      'date' => $recurDetails['start_date'],
      'currency' => $recurDetails['currency'],
      'trxn_id' => $recurDetails['trxn_id'],
      'contact_id' => $recurDetails['contact_id'],
      'recur_id' => $recurId,
    ]);

    if ((!empty($mandate) && $mandate != $mandateId) || empty($mandateId)) {
      return;
    }

    \Civi\Api4\IciciPaymentMandate::create(FALSE)
      ->addValue('contribution_recur_id', $recurId)
      ->addValue('mandate', $mandateId)
      ->execute();

    \Civi\Api4\ContributionRecur::update(FALSE)
      ->addValue('contribution_status_id:name', 'In Progress')
      ->addValue('start_date', date('YmdHis'))
      ->addWhere('id', '=', $recurId)
      ->execute();

  }

  private function verifyMandate($params) {
    $paymentProcessorObj = CRM_Financial_BAO_PaymentProcessor::getProcessorForEntity(
      $params['recur_id'], 'recur', 'obj'
    );

    $params = [
      'merchant' => [
        'identifier' => $paymentProcessorObj->getPaymentProcessor()['user_name'],
      ],
      'payment' => [
        'instruction' => (object)[],
      ],
      'transaction' => [
        'deviceIdentifier' => 'S',
        'type' => '002',
        'subType' => '002',
        'requestType' => 'TSI',
        'dateTime' => date('d-m-Y', strtotime($params['date'])),
        'currency' => $params['currency'],
        'identifier' => $params['trxn_id'],
      ],
      'consumer' => [
        'identifier' => $params['contact_id'],
      ],
    ];

    $response = $paymentProcessorObj->getResponse($params);
    if (empty($response['error']) && !empty($response['paymentMethod'])) {
      $token = $response['paymentMethod']['token'] ?? NULL;
      $code = $response['paymentMethod']['paymentTransaction']['statusCode'] ?? '';
      if ($token && $code === '0300') {
        return $token;
      }
      else if ($code === '0399') {
        $msg = $response['paymentMethod']['paymentTransaction']['statusMessage'] ?? '';
        $paymentProcessorObj->cancelTransaction($params['recur_id'], $msg);
      }
    }

    return NULL;
  }

}