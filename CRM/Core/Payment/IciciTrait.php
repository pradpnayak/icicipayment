<?php

use Civi\Payment\PropertyBag;

trait CRM_Core_Payment_IciciTrait {

  /**
   * We can use the cpay processor on the backend
   * @return bool
   */
  public function supportsBackOffice() {
    return FALSE;
  }

  /**
   * Does this payment processor support refund?
   *
   * @return bool
   */
  public function supportsRefund() {
    return FALSE;
  }

  /**
   * Does this processor support cancelling recurring contributions through code.
   *
   * If the processor returns true it must be possible to take action from
   * within CiviCRM that will result in no further payments being processed.
   *
   * @return bool
   */
  protected function supportsCancelRecurring() {
    return FALSE;
  }

  /**
   * Does the processor support the user having a choice as to whether to cancel
   * the recurring with the processor?
   *
   * If this returns TRUE then there will be an option to send a cancellation
   * request in the cancellation form.
   *
   * This would normally be false for processors where CiviCRM maintains the
   * schedule.
   *
   * @return bool
   */
  protected function supportsCancelRecurringNotifyOptional() {
    return FALSE;
  }

  /**
   * Override CRM_Core_Payment function
   *
   * @return array
   */
  public function getPaymentFormFields() {
    return [];
  }

  /**
   * Return an array of all the details about the fields potentially required
   * for payment fields.
   *
   * Only those determined by getPaymentFormFields will actually be assigned
   * to the form
   *
   * @return array
   *   field metadata
   */
  public function getPaymentFormFieldsMetadata() {
    return [];
  }

  /**
   * Get billing fields required for this processor.
   *
   * We apply the existing default of returning fields only for payment
   * processor type 1. Processors can override to alter.
   *
   * @param int $billingLocationID
   *
   * @return array
   */
  public function getBillingAddressFields($billingLocationID = NULL) {
    return [];
  }

  /**
   * Get form metadata for billing address fields.
   *
   * @param int $billingLocationID
   *
   * @return array
   *   Array of metadata for address fields.
   */
  public function getBillingAddressFieldsMetadata($billingLocationID = NULL) {
    return parent::getBillingAddressFieldsMetadata($billingLocationID);
  }

  /**
   * Set default values when loading the (payment) form
   *
   * @param \CRM_Core_Form $form
   */
  public function buildForm(&$form) {
    parent::buildForm($form);
  }

  /**
   * This function checks to see if we have the right config values.
   *
   * @return string
   *   the error message if any
   */
  public function checkConfig() {
    return NULL;
  }

  /**
   * Log debugging data.
   *
   * @param string $type
   * @param array $details
   */
  private function log(string $type, array $details): void {
    if (\Civi::settings()->get('icicieasypay_debug_mode')) {
      \Civi::log()->error("Icici gateway: Type: {$type}\n" . print_r($details, 1));
    }
  }

  /**
   * Get the currency for the transaction.
   *
   * Handle any inconsistency about how it is passed in here.
   *
   * @param array|PropertyBag $params
   *
   * @return string
   */
  public function getFormatedAmount(array $params = []): string {
    $amount = number_format(
      (float) $params['amount'] ?? 0.0,
      CRM_Utils_Money::getCurrencyPrecision($this->getCurrency($params)),
      '.', ''
    );

    return $amount;
  }


  /**
   * Set contribution status to completed.
   *
   */
  private function completeContribution(): void {
    if ($this->checkStatusAlreadyHandled('Completed')) {
      return;
    }

    // CiviCRM does not (currently) allow changing contribution_status=Failed to anything else.
    // But we need to go from Failed to Completed if payment succeeds following a failure.
    // So we check for Failed status and update to Pending so it will transition to Completed.
    // Fixed in 5.29 with https://github.com/civicrm/civicrm-core/pull/17943
    // Also set cancel_date to NULL because we are setting it for failed payments and UI will still display "greyed
    // out" if it is set.
    if ($this->_contributionStatusName == 'Failed') {
      $sql = 'UPDATE civicrm_contribution
        SET contribution_status_id = %1, cancel_date = NULL
        WHERE id = %2
      ';
      $queryParams = [
        1 => [array_search('Pending', $this->_contributionStatuses), 'Positive'],
        2 => [$this->_contributionId, 'Positive'],
      ];
      CRM_Core_DAO::executeQuery($sql, $queryParams);
    }

    try {
      // update contribution with details.
      $params = [
        'id' => $this->_contributionId,
        'trxn_id' => $this->_reponseData['Unique_Ref_Number'],
        'payment_processor_id' => $this->_paymentProcessor['id'],
        'fee_amount' => ($this->_reponseData['Total_Amount'] - $this->_reponseData['Transaction_Amount']),
        'total_amount' => $this->_reponseData['Total_Amount'],
      ];

      civicrm_api3('contribution', 'create', $params);

      if (!in_array($this->_reponseData['Payment_Mode'], ['CASH', 'CHEQUE', 'NEFT_RTGS'])) {
        civicrm_api3('contribution', 'completetransaction', [
          'id' => $this->_contributionId,
          'trxn_id' => $this->_reponseData['Unique_Ref_Number'],
          'trxn_date' => $this->getDate(($this->_reponseData['Transaction_Date'] ?? NULL)),
          'receive_date' => $this->getDate(($this->_reponseData['Transaction_Date'] ?? NULL)),
        ]);
      }
    }
    catch (CiviCRM_API3_Exception $e) {
      $this->handleError($e->getMessage(), [
        'contribution_id' => $this->_contributionId,
        'failedAt' => 'completeContribution',
      ]);
      if (stripos($e->getMessage(), 'Contribution already completed') === FALSE) {
        $this->handleError($e->getMessage(), [
          'contribution_id' => $this->_contributionId,
          'failedAt' => 'completeContribution',
        ]);
      }
    }
  }

  /**
   * Handle an error and notify the user
   *
   * @param string $errorMessage
   * @param array $data
   *
   */
  private function handleError(string $errorMessage = NULL, array $data = []): void {
    $this->log('ipn_error', [
      'data' => $data,
      'errorMessage' => ts($errorMessage),
    ]);

    if (!empty($this->_bounceSuccessURL)) {
      CRM_Core_Error::statusBounce(
        ts($errorMessage), $this->_bounceSuccessURL, 'Error'
      );
    }

    echo ts($errorMessage);
    exit();
  }

  /**
   * Check Status if already handled.
   *
   * @param string $name
   *
   * @return bool
   */
  private function checkStatusAlreadyHandled(string $name): bool {
    if ($this->_contributionStatusName == $name) {
      $this->log('ipn_alreadyhandled', [
        'contribution_id' => $this->_contributionId,
        'message' => ts('Contribution status already set to %1.', [1 => $name]),
      ]);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Set contribution status to failed.
   *
   */
  private function failContribution(): void {
    $errorMessage = $this->_errorMessage;
    if (stripos($errorMessage, 'cancelled') !== FALSE) {
      if (!$this->checkStatusAlreadyHandled('Cancelled')) {
        $this->updateContribution('Cancelled', [
          'cancel_date' => $this->getDate(),
          'cancel_reason' => $errorMessage,
        ]);
      }
    }
    elseif (!$this->checkStatusAlreadyHandled('Failed')) {
      $params = [
        'cancel_date' => $this->getDate(($this->_reponseData['Transaction_Date'] ?? NULL)),
        'cancel_reason' => $errorMessage,
      ];

      if (!empty($this->_reponseData['Unique_Ref_Number'])) {
        $params['trxn_id'] = $this->_reponseData['Unique_Ref_Number'];
      }
      $this->updateContribution('Failed', $params);
    }
  }

  /**
   * Update contribution.
   *
   * @param string $statusName
   * @param array $params
   *
   */
  private function updateContribution(string $statusName, array $params): void {
    if (!empty($this->_contributionId)) {
      civicrm_api3('Contribution', 'create', [
        'contribution_status_id' => $statusName,
        'id' => $this->_contributionId,
      ] + $params);
    }
  }

  /**
   * Encrypt value.
   *
   * @param string|int $data
   */
  private function getEncryptValue($data) {
    if ($data == '' || is_null($data)) {
      return $data;
    }
    // Generate an initialization vector
    // $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    // Encrypt the data using AES 128 encryption in ecb mode using our encryption key and initialization vector.
    $encrypted = openssl_encrypt(
      $data, 'aes-128-ecb',
      $this->_paymentProcessor['password'], OPENSSL_RAW_DATA
    );
    // The $iv is just as important as the key for decrypting, so save it with our encrypted data using a unique separator (::)
    return base64_encode($encrypted);
  }

  /**
  * Store input array on the class.
  */
 public function setNotificationParameters(): void {
   $this->_reponseData = $_REQUEST;
   $this->_component = $_REQUEST['component'] ?? 'contribute';
   $this->_reponseData['processor_id'] = $this->_paymentProcessor['id'];
   $this->_contributionId = $this->_reponseData['ReferenceNo'] ?? NULL;

  // log response
  self::logPaymentNotification($this->_reponseData);

  if (!empty($this->_reponseData['Response_Code'])
    && $this->_reponseData['Response_Code'] == 'E000'
  ) {
    $this->_successResponse = TRUE;
  }

  $this->_contributionStatuses = CRM_Contribute_BAO_Contribution::buildOptions(
    'contribution_status_id', 'validate'
  );

  $this->log('ipn_requestBody', ['data' => $this->_reponseData]);

  //echo '<pre>' . print_r( $this->_reponseData) . '</pre>';

   // Differentiate browser submit v/s push notification.
   if (!empty($_SERVER['HTTP_USER_AGENT'])) {
     $this->setBounceSuccessURL();
   }
   else {
     http_response_code(200);
   }

 }

 /**
   * Set Success or Failure URL.
   *
   */
  private function setBounceSuccessURL(): void {
    if ($this->_successResponse) {
      $this->_bounceSuccessURL = $this->getReturnSuccessUrl(
        $this->_reponseData['qfKey']
      );
    }
    else {
      $participantId = $eventId = NULL;
      if ($this->_component == 'event') {
        list($participantId, $eventId) = $this->participantId();
      }
      $this->_bounceSuccessURL = $this->getReturnFailUrl(
        $this->_reponseData['qfKey'], $participantId, $eventId
      );
    }
  }

  /**
   * Get Participant ID from Contribution ID.
   *
   */
  private function participantId(): ?array {
    try {
      $result = civicrm_api3('ParticipantPayment', 'getsingle', [
        'return' => ['participant_id', 'participant_id.event_id'],
        'contribution_id' => $this->_contributionId,
        'options' => ['limit' => 1, 'sort' => 'id ASC'],
      ]);
      return [$result['participant_id'], $result['participant_id.event_id']];
    }
    catch (Exception $e) {
      $this->log('no_participantid', [
        'contribution_id' => $this->_contributionId,
        'errorMessage' => $e->getMessage(),
      ]);
    }
    return NULL;
  }

  /**
   * Get the notify (aka ipn, web hook or silent post) url.
   *
   * If there is no '.' in it we assume that we are dealing with localhost or
   * similar and it is unreachable from the web & hence invalid.
   *
   * @param string $qfKey
   * @param bool $failURL
   *
   * @return string
   *   URL to notify outcome of transaction.
   */
  private function getPaymentNotifyUrl(string $qfKey, bool $failURL = FALSE, array $query = []): string {
    $query['qfKey'] = $qfKey;
    $query['component'] = $this->_component;

    if ($failURL) {
      $query['failed'] = 1;
    }

    // Since 5.38 getNotifyUrl() is used to build the notify url as it fixes
    // issue regarding wordpress
    // See PR https://github.com/civicrm/civicrm-core/pull/20063
    if (method_exists('CRM_Utils_System', 'getNotifyUrl')) {
      $functionName = 'getNotifyUrl';
    }
    else {
      $functionName = 'url';
    }

    $url = CRM_Utils_System::$functionName(
      "civicrm/payment/ipn/{$this->_paymentProcessor['id']}",
      $query, TRUE, NULL, FALSE, TRUE
    );

    return (stristr($url, '.')) ? $url : '';
  }

  /**
   * Get equivalent payment method from Civi.
   *
   * @param string $name
   *
   * @return string|int
   */
  private function getPaymentInstrumentIDFromName($name) {
    $overRiddenPM = [
      'DEBIT_CARD' => 'Debit Card',
      'CREDIT_CARD' => 'Credit Card',
      'CASH' => 'ICICI_CASH',
      'CHEQUE' => 'ICICI_CHEQUE',
    ];

    $name = $overRiddenPM[$name] ?? $name;
    try {
      return civicrm_api3('OptionValue', 'getvalue', [
        'name' => $name,
        'option_group_id' => 'payment_instrument',
        'return' => 'value',
      ]);
    }
    catch (Exception $e) {
    }

    return NULL;
  }

  /**
   * Get date.
   *
   * @return string
   */
  private function getDate($date = NULL) {
    if (!empty($date)) {
      $date = date('YmdHis', strtotime($date));
    }
    else {
      $date = date('YmdHis');
    }

    return $date;
  }

}
