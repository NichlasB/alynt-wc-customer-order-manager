(function() {
    var i18n = window.awcomPaymentLinkVars && window.awcomPaymentLinkVars.i18n
        ? window.awcomPaymentLinkVars.i18n
        : {};

    function formatString(template, value) {
        return String(template || '').replace('%s', value);
    }

    function showCopyFailedMessage(text) {
        alert(formatString(i18n.copy_failed, text));
    }

    function fallbackCopy(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'absolute';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();

        try {
            if (document.execCommand('copy')) {
                alert(i18n.copied);
            } else {
                showCopyFailedMessage(text);
            }
        } catch (err) {
            showCopyFailedMessage(text);
        } finally {
            document.body.removeChild(textarea);
        }
    }

    document.addEventListener('click', function(event) {
        var button = event.target.closest('.awcom-copy-payment-link');

        if (!button) {
            return;
        }

        event.preventDefault();

        var link = button.getAttribute('data-payment-link');

        if (!link) {
            alert(i18n.no_payment_link);
            return;
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(link).then(function() {
                alert(i18n.copied);
            }).catch(function() {
                fallbackCopy(link);
            });
        } else {
            fallbackCopy(link);
        }
    });
})();
