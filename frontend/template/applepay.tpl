<script>
    if (window.jQuery) {
        const applepayStatus = window.ApplePaySession && window.ApplePaySession.canMakePayments() ? 1 : 0;
        if (applepayStatus !== {$currentApplePayStatus}) {
            $.ajax('{$wsMollieApplePayUrl}', {
                method: 'POST',
                contentType: "application/x-www-form-urlencoded; charset=UTF-8",
                data: {
                    available: applepayStatus
                }
            });
        }
    } else if (window.console.warn) {
        console.warn('jQuery not loaded as yet, ApplePay not available!');
    }

</script>