<?php

use Civi\Payment\PropertyBag;

class CRM_Core_Payment_IciciEasyPay extends CRM_Core_Payment {

  /**
   * @var bool
   */
  private $_successResponse = FALSE;

  /**
   * @var string
   */
  private $_bounceSuccessURL;

  /**
   * @var int
   *  Contribution Id.
   */
  private $_contributionId;

  /**
   * @var array
   *   Response data from cpay gateway
   */
  private $_reponseData = [];

  private $_paymentUrl = 'https://eazypay.icicibank.com/EazyPG';

  private $_iciciPaymentMode = 9;

  private $_mandatoryFields = [
    'reference_no' => 1,
    'contact_id' => 1,
    'amount' => 1,
    'label' => 1,
  ];

  private $_optionalFields = [];

  /**
   * Constructor
   *
   * @param string $mode
   *   (deprecated) The mode of operation: live or test.
   * @param array $paymentProcessor
   */
  public function __construct($mode, $paymentProcessor) {
    $this->_paymentProcessor = $paymentProcessor;
    $this->_paymentMode = $mode;
  }

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
   * Process payment
   * Payment processors should set payment_status_id.
   *
   * @param array|PropertyBag $params
   *   Assoc array of input parameters for this transaction.
   * @param string $component
   *
   * @return array
   *   Result array
   *
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function doPayment(&$params, $component = 'contribute') {
    $this->_component = $component;
    $result = [];

    $propertyBag = PropertyBag::cast($params);
    // If we have a $0 amount, skip call to processor and set payment_status
    // to Completed. Conceivably a processor might override this - perhaps for
    // setting up a token - but we don't have an example of that at the mome.
    if ($propertyBag->getAmount() == 0) {
      $result['payment_status_id'] = CRM_Core_PseudoConstant::getKey(
        'CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed'
      );
      $result['payment_status'] = 'Completed';
      return $result;
    }
    $this->_propertyBag = $propertyBag;
    $this->_paymentData = $params;

    $paymentURL = $this->getPaymentRedirectURL();

    // Allow each CMS to do a pre-flight check before redirecting to Payment gateway.
    CRM_Core_Config::singleton()->userSystem->prePostRedirect();
    CRM_Utils_System::redirect($paymentURL);

    return $result;
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
  private function getPaymentNotifyUrl(string $qfKey, bool $failURL = FALSE): string {
    $query = ['qfKey' => $qfKey, 'component' => $this->_component];

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
   * The URL the form will POST to.
   *
   * @return string
   */
  private function getPaymentRedirectURL(): string {
    $paymentData = $this->getPaymentData();
    $amount = $this->getFormatedAmount($this->_paymentData);

    $mandatoryData = array_intersect_key($paymentData, $this->_mandatoryFields);
    $mandatoryData = implode('|', $mandatoryData);

    $optionalData = array_intersect_key($paymentData, $this->_optionalFields);
    $optionalData = implode('|', $optionalData);

    $returnUrl = $this->getPaymentNotifyUrl($this->_paymentData['qfKey']);

    $paymentUrl = $this->_paymentUrl . "?merchantid={$this->_paymentProcessor['user_name']}";
    foreach ([
      'mandatory fields' => $mandatoryData,
      'optional fields' => $optionalData,
      'returnurl' => $returnUrl,
      'Reference No' => $this->_paymentData['contributionID'],
      'submerchantid' => $this->_paymentData['contactID'],
      'transaction amount' => $amount,
      'paymode' => $this->_iciciPaymentMode,
    ] as $key => $value) {
      $value = $this->getEncryptValue($value);
      $paymentUrl .= "&{$key}={$value}";
    }

    return $paymentUrl;
  }

  /**
   * Log debugging data.
   *
   * @param string $type
   * @param array $details
   */
  private function log(string $type, array $details): void {
    if (\Civi::settings()->get('icicieasypay_debug_mode')) {
      \Civi::log()->error("Icici Easy Pay gateway: Type: {$type}\n" . print_r($details, 1));
    }
  }

  /**
   * Store the data required on the payment form.
   *
   * @return array
   */
  private function getPaymentData(): array {
    $paymentData = [
      'reference_no' => $this->_paymentData['contributionID'],
      'contact_id' => (int) $this->_paymentData['contactID'],
      'amount' => $this->getFormatedAmount($this->_paymentData),
      'label' => substr($this->_propertyBag->getDescription(), 0, 32),
    ];

    // Allow further manipulation of the arguments via custom hooks ..
    CRM_Utils_Hook::alterPaymentProcessorParams(
      $this, $this->_paymentData, $paymentData
    );

    if (count(array_diff_key(
      $this->_mandatoryFields, array_filter($paymentData)
    )) != 0) {
      $this->log('missing_payment_params-error', [
        'missingParam' => array_diff_key(
          $this->_mandatoryFields, array_filter($paymentData)
        ),
      ]);
      throw new CRM_Core_Exception(ts('Missing required payment params.'));
    }

    return $paymentData;
  }

  /**
   * Set mandatory fields.
   *
   * @param array $fields
   */
  public function setMandatoryFields(array $fields) {
    if (!empty($fields)) {
      $this->_mandatoryFields = $fields;
    }
  }

  /**
   * Set optional fields.
   *
   * @param array $fields
   */
  public function setOptionalFields(array $fields) {
    if (!empty($fields)) {
      $this->_optionalFields = $fields;
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
   * Process incoming payment notification (IPN).
   *
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public function handlePaymentNotification() {
    $this->setNotificationParameters();

    $this->processPaymentNotification();

    if (!empty($this->_bounceSuccessURL)) {
      if (!$this->_successResponse) {
        $errorMessage = CRM_Core_Payment_IciciErrorCodes::response_code($this->_reponseData['Response_Code']);
        $errorMessage = ts('Your payment was not successful. Please try again. <br>Error(' . $this->_reponseData['Response_Code'] . ') - ' . $errorMessage);
        $this->handleError($errorMessage, [
          'contribution_id' => $this->_contributionId,
        ]);
      }
      else {
        CRM_Utils_System::redirect($this->_bounceSuccessURL);
      }
    }

    CRM_Utils_System::civiExit();
  }

  /**
   * Update Transaction based on outcome of the API.
   *
   * @throws CRM_Core_Exception
   * @throws CiviCRM_API3_Exception
   */
  public function processPaymentNotification(): void {
    $this->_contributionData = civicrm_api3('Contribution', 'getsingle', [
      'id' => $this->_contributionId,
    ]);

    $this->_contributionStatusId = $this->_contributionData['contribution_status_id'];
    $this->_contributionStatusName = CRM_Utils_Array::value(
      $this->_contributionStatusId, $this->_contributionStatuses
    );

    if ($this->_successResponse) {
      // validate params
      if ($this->validateNotificationParameters()) {
        $this->completeContribution();
      }
    }
    else {
      $this->failContribution();
    }
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

      $pid = $this->getPaymentInstrumentIDFromName($this->_reponseData['Payment_Mode']);
      if (!empty($pid)) {
        $params['payment_instrument_id'] = $pid;
      }
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
   * validate Notification Parameters.
   */
  private function validateNotificationParameters() {

    // if Unique_Ref_Number is empty do not proceed.
    if (empty($this->_reponseData['ReferenceNo'])) {
      $this->handleError(
        'Reference No field is empty, so we cannot proceed further.',
        $this->_reponseData
      );
    }

    if (!$this->validateResponseData()) {
      $this->handleError(
        'Invalid response data, failed to validate.',
        $this->_reponseData
      );
    }

    return TRUE;
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
   * Validate response data
   *
   * @return bool
   *
   */
  private function validateResponseData(): bool {
    foreach ([
      'ID', 'Response_Code', 'Unique_Ref_Number', 'Service_Tax_Amount',
      'Processing_Fee_Amount', 'Total_Amount', 'Transaction_Amount',
      'Transaction_Date', 'Interchange_Value', 'TDR',
      'Payment_Mode', 'SubMerchantId', 'ReferenceNo', 'TPS',
    ] as $fieldName) {
      $data[] = $this->_reponseData[$fieldName];
    }

    $data[] = $this->_paymentProcessor['password'];

    $verificationKey = implode('|', $data);
    $encryptedMessage = hash('sha512', $verificationKey);

    if ($encryptedMessage == $this->_reponseData['RS']) {
      return TRUE;
    }

    return FALSE;
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
    $errorMessage = CRM_Core_Payment_IciciErrorCodes::response_code(
      $this->_reponseData['Response_Code']
    );
    if (stripos($errorMessage, 'cancelled') !== FALSE) {
      if (!$this->checkStatusAlreadyHandled('Cancelled')) {
        $this->updateContribution('Cancelled', [
          'cancel_date' => $this->getDate(),
          'cancel_reason' => $errorMessage,
        ]);
      }
    }
    elseif (!$this->checkStatusAlreadyHandled('Failed')) {
      $this->updateContribution('Failed', [
        'trxn_id' => $this->_reponseData['Unique_Ref_Number'] ?? '',
        'cancel_date' => $this->getDate(($this->_reponseData['Transaction_Date'] ?? NULL)),
        'cancel_reason' => $errorMessage,
      ]);
    }
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
  * Store input array on the class.
  */
 public function setNotificationParameters(): void {
   $this->_reponseData = $_REQUEST;
   $this->_component = $_REQUEST['component'] ?? 'contribute';
   $this->_reponseData['processor_id'] = $this->_paymentProcessor['id'];
   $this->_contributionId = $this->_reponseData['ReferenceNo'] ?? NULL;

  // log response
  self::logPaymentNotification($params);

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
}
