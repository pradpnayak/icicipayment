<?php
/**
 * Payment page class.
 *
 * This had to be done as a page to 'keep quickform from tampering'
 * (e.g with the submit).
 *
 * But this means we lose a lot of quickform's html generation.
 */
class CRM_IciciPayment_Page_PaymentPage extends CRM_Core_Page {

  /**
   * Page run function.
   *
   * @return string
   * @throws \CiviCRM_API3_Exception
   */
  public function run() {
    $paymentData = $this->getTransparentRedirectFormData(
      CRM_Utils_Request::retrieve('key', 'String', CRM_Core_DAO::$_nullObject, TRUE)
    );

    Civi::resources()->addScriptUrl('https://www.paynimo.com/PaynimoCDN/lib/jquery.min.js', 10, 'html-header');
    Civi::resources()->addScriptUrl('https://www.paynimo.com/paynimocheckout/server/lib/checkout.js', 10, 'html-header');
    CRM_Core_Error::debug_var('paymentData', json_encode($paymentData));
    $this->assign('paymentData', json_encode($paymentData));
    return parent::run();
  }

  /**
   * Get the data required on the payment form.
   *
   * @param string $key
   *
   * @return array
   */
  private function getTransparentRedirectFormData(string $key): array {
    return json_decode(CRM_Core_Session::singleton()->get('transparent_redirect_data' . $key), TRUE);
  }

}
