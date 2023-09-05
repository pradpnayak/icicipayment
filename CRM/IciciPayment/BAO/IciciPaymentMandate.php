<?php


class CRM_IciciPayment_BAO_IciciPaymentMandate extends CRM_IciciPayment_DAO_IciciPaymentMandate {

  /**
   * Create the event.
   *
   * @param array $params
   *   Reference array contains the values submitted by the form.
   *
   * @return object
   */
  public static function add(&$params) {
    $dao = new CRM_IciciPayment_BAO_IciciPaymentMandate();
    $dao->contribution_recur_id = $params['contribution_recur_id'];
    $dao->mandate = $params['mandate'];
    if (empty($params['id'])) {
      $dao->find(TRUE);
      return $dao;
    }
    $dao->save(TRUE);

    return $dao;
  }

}
