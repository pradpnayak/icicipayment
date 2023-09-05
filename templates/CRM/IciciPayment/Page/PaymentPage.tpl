<div>{ts}Processing payment.....{/ts}</div>
{literal}

<script type="text/javascript">
  CRM.$(function($) {
    function handleResponse(res) {
      console.log('res', res);
      if (typeof res != 'undefined' && typeof res.paymentMethod != 'undefined' && typeof res.paymentMethod.paymentTransaction != 'undefined' && typeof res.paymentMethod.paymentTransaction.statusCode != 'undefined' && res.paymentMethod.paymentTransaction.statusCode == '0300') {
        // success block
      }
      else if (typeof res != 'undefined' && typeof res.paymentMethod != 'undefined' && typeof res.paymentMethod.paymentTransaction != 'undefined' && typeof res.paymentMethod.paymentTransaction.statusCode != 'undefined' && res.paymentMethod.paymentTransaction.statusCode == '0398') {
        // initiated block
      }
      else {
        // error block
      }
    };

    loadPaymentBlock();

    function loadPaymentBlock() {
      var configJson = {
        'tarCall': false,
        'features': {
          'showPGResponseMsg': true,
          'enableAbortResponse': true,
          'enableNewWindowFlow': true,
          'enableExpressPay':true,
          'siDetailsAtMerchantEnd':true,
          'enableSI':true
        },
        'consumerData': {/literal}{$paymentData}{literal}
      };

      //console.log('configJson', configJson);

      $.pnCheckout(configJson);
      if (configJson.features.enableNewWindowFlow) {
        pnCheckoutShared.openNewWindow();
      }
    };
  });
</script>

{/literal}
