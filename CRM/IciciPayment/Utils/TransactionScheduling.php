<?php

class CRM_IciciPayment_Utils_TransactionScheduling {

  public function __construct() {

  }

  public function processTransactions() {
    $query = "";
    $results = CRM_Core_DAO::executeQuery($query)->fetchAll();
    foreach ($results as $result) {
      $response = $this->schedulePayment($result);
    }
  }

  private function schedulePayment($params) {
    $pid = $params['ppid'];
    $params = [
      'merchant' => [
        'identifier' => $pParams['user_name'],
      ],
      'payment' => [
        'instrument' => [
          'identifier' => $pParams['subject'],
        ],
        'instruction' => [
          'amount' => ,
          'endDateTime' => date('dmY', strtotime($params['payment_date'])),
          '' => ,
        ],
      ],
      'transaction' => [
        'deviceIdentifier' => 'S',
        'type' => '002',
        'subType' => '003',
        'requestType' => 'TSI',
        'currency' => $params['currency'],
        'identifier' => $params['trxn_id'],
      ],
    ];

    $responce = $this->_paymentProcessor[$pid]->getResponse();
  }

}
