<?php

use Civi\Payment\PropertyBag;

class CRM_Core_Payment_IciciEnach extends CRM_Core_Payment {

  use CRM_Core_Payment_IciciTrait;

  private $_frequencies = [
    'day' => 'DAIL',
    'week' => 'WEEK',
    'month' => 'MNTH',
    'quaterly' => 'QURT',
    'semiannualy' => 'MIAN',
    'year' => 'YEAR',
    'bi_month' => 'BIMN',
    'adho' => 'ADHO',
  ];

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

  private $_paymentUrl = 'https://www.paynimo.com/api/paynimoV2.req';

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

    $paymentURL = $this->storeTransparentRedirectFormData();

    // Allow each CMS to do a pre-flight check before redirecting to Payment gateway.
    CRM_Core_Config::singleton()->userSystem->prePostRedirect();
    $url = CRM_Utils_System::url('civicrm/icicipayment/details', [
      'key' => $params['qfKey'],
    ]);

    $this->log('success_redirect', ['url' => $url]);
    CRM_Utils_System::redirect($url);

    return $result;
  }

  /**
   * Store the data required on the payment form.
   *
   */
  private function storeTransparentRedirectFormData(): void {
    $data = $this->getPaymentRedirectData();
    CRM_Core_Session::singleton()->set(
      'transparent_redirect_data' . $this->_paymentData['qfKey'],
      json_encode($data)
    );

    $this->log('success_storeTransparentRedirectFormData', ['data' => $data]);
  }

  private function getRecurDetails(): array {
    return (array) \Civi\Api4\ContributionRecur::get(FALSE)
      ->addWhere('id', '=', $this->_paymentData['contributionRecurID'])
      ->execute()
      ->first();
  }

  private function getContactDetails(): array {
    return (array) \Civi\Api4\Contact::get(FALSE)
      ->addSelect('email_primary.email')
      ->addWhere('id', '=', $this->_paymentData['contactID'])
      ->execute()
      ->first();
  }

  private function getOrderDetails(): array {
    return [
      'itemId' => $this->_paymentProcessor['subject'],
      'amount' => $this->getFormatedAmount($this->_paymentData),
      'comAmt' => 0,
    ];
  }

  private function generateHashToken(array $paymentData, array $keys): string {
    $hashData = [];

    foreach ($keys as $k) {
      $hashData[] = $paymentData[$k] ?? NULL;
    }

    $hashData[] = $this->_paymentProcessor['password'];

    $hashData = implode('|', $hashData);

    return hash('sha512', $hashData);
  }

  /**
   * Store the data required on the payment form.
   *
   * @return array
   */
  private function getPaymentRedirectData(): array {
    $recurDetails = $this->getRecurDetails();
    $contactDetails = $this->getContactDetails();

    $query = [
      'ccid' => $this->_paymentData['contributionID'],
    ];

    $paymentData = [
      'deviceId' => 'WEBSH2',
      'merchantId' => $this->_paymentProcessor['user_name'],
      'consumerId' => $this->_paymentData['contactID'],
      'txnType' => 'SALE',
      'txnSubType' => 'DEBIT',
      'paymentMode' => 'all',
      'amountType' => 'F',
      'maxAmount' => $this->getFormatedAmount($this->_paymentData),
      'returnUrl' => $this->getPaymentNotifyUrl($this->_paymentData['qfKey'], FALSE, $query),
      'redirectOnClose' => $this->getPaymentNotifyUrl($this->_paymentData['qfKey'], TRUE, $query),
      'txnId' => $recurDetails['trxn_id'],
      'debitStartDate' => date('d-m-Y'),
      'debitEndDate' => date('d-m-Y', strtotime('+30 years')),
      'responseHandler' => 'handleResponse',
      'frequency' => $this->_frequencies[$recurDetails['frequency_unit']],
      'merchantLogoUrl' => $this->getWebsiteLogo(),
      'consumerEmailId' => $contactDetails['email_primary.email'],
      'items' => [$this->getOrderDetails()],
      'totalamount' => $this->getFormatedAmount($this->_paymentData),
      'cartDescription' => substr($this->_propertyBag->getDescription(), 0, 32),
      'payment_processor_id' => $this->_paymentProcessor['id'],
      'contact_id' => $this->_paymentData['contactID'],
    ];

    $paymentData['token'] = $this->generateHashToken($paymentData, [
      'merchantId', 'txnId', 'totalamount', 'accountNo', 'consumerId',
      'consumerMobileNo', 'consumerEmailId', 'debitStartDate', 'debitEndDate',
      'maxAmount', 'amountType', 'frequency', 'cardNumber', 'expMonth',
      'expYear', 'cvvCode',
    ]);

    // Allow further manipulation of the arguments via custom hooks ..
    CRM_Utils_Hook::alterPaymentProcessorParams(
      $this, $this->_paymentData, $paymentData
    );

    $requiredParams = $this->getRequiredPaymentParams();

    if (count(array_diff_key(
      $requiredParams, array_filter($paymentData)
    )) != 0) {
      $this->log('enach-missing_payment_params-error', [
        'missingParam' => array_diff_key(
          $requiredParams, array_filter($paymentData)
        ),
      ]);

      throw new CRM_Core_Exception(ts('Missing required payment params.'));
    }

    return $paymentData;
  }

  private function getWebsiteLogo(): string {
    $config = CRM_Core_Config::singleton();
    $imageURL = '';
    switch (strtolower($config->userFramework)) {
      case 'joomla':
        break;

      case 'wordpress':
        $logo = get_theme_mod('custom_logo');
        $image = wp_get_attachment_image_src($logo, 'full');
        $imageURL = $image[0];
        break;

      case 'backdrop':
      case 'drupal':
      case 'drupal6':
        global $base_url;
        $imageURL = theme_get_setting('logo');
        break;

      case 'drupal8':
        $imageURL = \Drupal::theme()->getActiveTheme()->getLogo();
        break;
    }

    return $imageURL;
  }

  /**
   * Get required params for cpay payment gateway.
   *
   * @return array
   */
  public function getRequiredPaymentParams(): array {
    return [
      'deviceId' => 1,
      'token' => 1,
      'returnUrl' => 1,
      'responseHandler' => 1,
      'paymentMode' => 1,
      'merchantId' => 1,
      'consumerId' => 1,
      'txnId' => 1,
      'txnType' => 1,
      'txnSubType' => 1,
      'items' => 1,
      'amountType' => 1,
      'frequency' => 1,
      'maxAmount' => 1,
    ];
  }

  private function extractMsg() {
    $this->_reponseData['msg'] = explode('|', $this->_reponseData['msg']);
    $string = 'txn_status|txn_msg|txn_err_msg|clnt_txn_ref|tpsl_bank_cd|tpsl_txn_id|txn_amt|clnt_rqst_meta|tpsl_txn_time|bal_amt|card_id|alias_name|BankTransactionID|mandate_reg_no|token|hash';
    $this->_reponseData['response'] = [];
    foreach (explode('|', $string) as $k => $key) {
      $this->_reponseData['response'][$key] = $this->_reponseData['msg'][$k];
    }
  }

  /**
  * Store input array on the class.
  */
 public function setNotificationParameters(): void {
   $this->_reponseData = $_REQUEST;
   $this->_component = $_REQUEST['component'] ?? 'contribute';
   $this->_reponseData['processor_id'] = $this->_paymentProcessor['id'];
   $this->_contributionId = $this->_reponseData['ccid'] ?? NULL;
   if (!empty($this->_reponseData['msg'])) {
     $this->extractMsg();
   }

  // log response
  self::logPaymentNotification($this->_reponseData);

  if (!empty($this->_reponseData['response']['txn_status'])) {
    if ($this->_reponseData['response']['txn_status'] == '0300') {
      $this->_successResponse = TRUE;
    }
    else if ($this->_reponseData['response']['txn_status'] == '0398') {
      $this->_successResponse = TRUE;
    }
    else if ($this->_reponseData['response']['txn_status'] == '0399') {
      $this->_errorMessage = 'Failed: ' . $this->_reponseData['response']['txn_err_msg'];
    }
    else if ($this->_reponseData['response']['txn_status'] == '0392') {
      $this->_errorMessage = 'Cancelled : ' . $this->_reponseData['response']['txn_err_msg'];
    }
    else if ($this->_reponseData['response']['txn_status'] == '0396') {
      $this->_successResponse = TRUE;
    }

  }

  $this->_contributionStatuses = CRM_Contribute_BAO_Contribution::buildOptions(
    'contribution_status_id', 'validate'
  );

  $this->log('ipn_requestBody', ['data' => $this->_reponseData]);

   // Differentiate browser submit v/s push notification.
   if (!empty($_SERVER['HTTP_USER_AGENT'])) {
     $this->setBounceSuccessURL();
   }
   else {
     http_response_code(200);
   }

 }

  /**
   * Process incoming payment notification (IPN).
   *
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public function handlePaymentNotification() {
    $this->setNotificationParameters();

    CRM_Core_Error::debug_log_message(
      'IPN Notification:' . print_r($this->_reponseData, 1),
      FALSE, 'IciciEnach', PEAR_LOG_INFO
    );

    $this->processPaymentNotification();

    if (!empty($this->_bounceSuccessURL)) {
      if (!$this->_successResponse) {
        $errorMessage = ts('Your payment was not successful. Please try again.');
        if (!empty($this->_errorMessage)) {
           $errorMessage .= ' (' . $this->_errorMessage . ')';
        }
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
      if ($this->verifyResponse()) {
        $obj = new CRM_IciciPayment_Utils_MandateVerification();
        $obj->createMandate(
          $this->_contributionData['contribution_recur_id'],
          $this->_reponseData['response']['mandate_reg_no'] ?? ''
        );
      }
    }
    else if ($this->verifyResponse()) {
      $this->failContribution();
      if (!empty($this->_bounceSuccessURL)) {
        $this->failContributionRecur(ts('user aborted/cancelled.'));
      }
    }
  }

  private function verifyResponse() {
    if (empty($this->_reponseData['response'])) {
      return FALSE;
    }
    $hashValue = $this->generateHashToken($this->_reponseData['response'], [
      'txn_status', 'txn_msg', 'txn_err_msg', 'clnt_txn_ref',
      'tpsl_bank_cd', 'tpsl_txn_id', 'txn_amt', 'clnt_rqst_meta',
      'tpsl_txn_time', 'bal_amt', 'card_id', 'alias_name',
      'BankTransactionID', 'mandate_reg_no', 'token',
    ]);

    if ($hashValue == $this->_reponseData['response']['hash']) {
      return TRUE;
    }

    return FALSE;
  }

  public function getResponse($params) {

    $ch = curl_init();

    // Set the curl URL option
    curl_setopt($ch, CURLOPT_URL, $this->_paymentUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json',
    'Content-Type: application/json',]);
    CRM_Core_Error::debug_log_message(
      'CurlParmas' . print_r($params, 1),
      FALSE, 'IciciEnach', PEAR_LOG_INFO
    );
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

    // This option will return data as a string instead of direct output
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    // Execute curl & store data in a variable
    $output = curl_exec($ch);
    curl_close($ch);

    CRM_Core_Error::debug_log_message(
      'CurlResponse' . print_r($output, 1),
      FALSE, 'IciciEnach', PEAR_LOG_INFO
    );

    $response = json_decode($output, TRUE);
    return $response;
  }

  /**
   * Set contribution status to failed.
   *
   */
  private function failContributionRecur(string $msg = ''): void {
    \Civi\Api4\ContributionRecur::update(FALSE)
      ->addValue('contribution_status_id:name', 'Cancelled')
      ->addValue('cancel_reason', $msg)
      ->addWhere('id', '=', $this->_contributionData['contribution_recur_id'])
      ->execute();
  }

  public function cancelTransaction(int $recurId, string $msg) {
    if (!empty($recurId)) {
      $this->_contributionData['contribution_recur_id'] = $recurId;
      $this->_errorMessage = $msg;
      $contribution = \Civi\Api4\Contribution::get(FALSE)
        ->addSelect('id', 'contribution_status_id:name')
        ->addWhere('contribution_recur_id', '=', $recurId)
        ->addWhere('contribution_status_id:name', '=', 'Pending')
        ->addWhere('is_template', '=', FALSE)
        ->setLimit(1)
        ->execute()
        ->first();
      $this->_contributionId = $contribution['id'];
      $this->_contributionStatusName = $contribution['contribution_status_id:name'];

      $this->failContributionRecur($msg);
      if (!empty($this->_contributionId)) {
        $this->failContribution();
      }
    }
  }

  public function completeContribution(int $contributionId, int $recurId, $trxnId, $date) {
    civicrm_api3('Contribution', 'completetransaction', [
      'id' => $contributionId,
      'trxn_id' => $trxnId,
      'trxn_date' => $date,
    ]);

    \Civi\Api4\ContributionRecur::update(FALSE)
      ->addWhere('id', '=', $recurId)
      ->addValue('failure_count', 0)
      ->execute();

  }

  public function updateRecurring(int $recurId, bool $failed) {
    $recurDetails = CRM_IciciPayment_BAO_IciciPaymentMandate::getRecurDetails(
      $recurId
    );
    $nextScheduledContributionDate = $this->calculateNextScheduledDate($recurDetails);
    $recur = \Civi\Api4\ContributionRecur::update(FALSE)
      ->addWhere('id', '=', $recurId)
      ->addValue('auto_renew', 1);

    if ($failed === FALSE) {
      $recur->addValue('failure_count', ($recurDetails['failure_count']++));
    }
    else {
      $recur->addValue('next_sched_contribution_date', $nextScheduledContributionDate);
      $recur->addValue('cycle_day', date('d', strtotime($nextScheduledContributionDate)));
    }

    $recur->execute();
  }

  /**
   * Calculate the end_date for a recurring contribution based on the number of installments
   * @param $params
   *
   * @return string
   * @throws \CRM_Core_Exception
   */
  public function calculateNextScheduledDate($params) {
    $startDate = date('YmdHis');
    $nextScheduleDate = $params['next_sched_contribution_date'] ?? NULL;
    if (!empty($nextScheduleDate)
      && (date('YmdHis', strtotime($nextScheduleDate)) < date('YmdHis'))
    ) {
      $startDate = $params['next_sched_contribution_date'];
    }

    switch ($params['frequency_unit']) {
      case 'day':
        $frequencyUnit = 'D';
        break;

      case 'week':
        $frequencyUnit = 'W';
        break;

      case 'month':
        $frequencyUnit = 'M';
        break;

      case 'year':
        $frequencyUnit = 'Y';
        break;
    }

    $numberOfUnits = $params['frequency_interval'];
    $endDate = new DateTime($startDate);
    $endDate->add(new DateInterval("P{$numberOfUnits}{$frequencyUnit}"));
    return $endDate->format('Ymd');
  }

}
