// <!--
if (window.MOLLIE_APPLEPAY_CHECK_URL) {
    if (window.jQuery) {
        $(function () {
            const setApplePayStatus = function (status) {

                $.ajax(window.MOLLIE_APPLEPAY_CHECK_URL, {
                    method: 'POST',
                    contentType: "application/x-www-form-urlencoded; charset=UTF-8",
                    data: {
                        available: status
                    }
                });

            }
            setApplePayStatus(window.ApplePaySession && window.ApplePaySession.canMakePayments() ? 1 : 0);
        });
    } else if (window.console.warn) {
        console.warn('jQuery not loaded as yet, ApplePay not available!');
    }
} else if (window.console.info) {
    console.info('MOLLIE_APPLEPAY_CHECK_URL not loaded, do nothing!');
}
// -->