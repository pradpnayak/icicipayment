<?php

use Civi\Payment\PropertyBag;

class CRM_Core_Payment_IciciEasyPay extends CRM_Core_Payment {

  use CRM_Core_Payment_IciciTrait;

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

}
