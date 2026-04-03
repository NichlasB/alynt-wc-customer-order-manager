(function() {
    var i18n = window.awcomPaymentLinkVars && window.awcomPaymentLinkVars.i18n
        ? window.awcomPaymentLinkVars.i18n
        : {};

    function formatString(template, value) {
        return String(template || '').replace('%s', value);
    }

    function getFeedbackElement(button) {
        var actions = button.closest('.payment-link-actions');

        return actions ? actions.querySelector('.awcom-payment-link-feedback') : null;
    }

    function getActionRegion(button) {
        return button.closest('.payment-link-actions');
    }

    function getButtonLabel(button) {
        return button.querySelector('.awcom-payment-action-label');
    }

    function getButtonSpinner(button) {
        return button.querySelector('.awcom-payment-action-spinner');
    }

    function clearFeedback(button) {
        var feedback = getFeedbackElement(button);

        if (!feedback) {
            return;
        }

        feedback.hidden = true;
        feedback.className = 'awcom-payment-link-feedback';
        feedback.removeAttribute('role');
        feedback.removeAttribute('tabindex');
        feedback.innerHTML = '';
    }

    function showFeedback(button, type, message) {
        var feedback = getFeedbackElement(button);
        var paragraph;

        if (!feedback) {
            return;
        }

        feedback.hidden = false;
        feedback.className = 'awcom-payment-link-feedback notice inline ' + (type === 'success' ? 'notice-success' : 'notice-error');
        feedback.setAttribute('role', type === 'success' ? 'status' : 'alert');
        feedback.setAttribute('tabindex', '-1');
        feedback.innerHTML = '';

        paragraph = document.createElement('p');
        paragraph.textContent = message;
        feedback.appendChild(paragraph);

        if (type === 'error') {
            feedback.focus();
        }
    }

    function setBusyState(button, isBusy) {
        var region = getActionRegion(button);
        var label = getButtonLabel(button);
        var spinner = getButtonSpinner(button);

        button.disabled = Boolean(isBusy);

        if (isBusy) {
            button.classList.add('is-busy');
            button.setAttribute('aria-disabled', 'true');
            button.setAttribute('aria-busy', 'true');

            if (region) {
                region.setAttribute('aria-busy', 'true');
            }

            if (label && i18n.copying) {
                label.textContent = i18n.copying;
            }

            if (spinner) {
                spinner.classList.add('is-active');
            }

            return;
        }

        button.classList.remove('is-busy');
        button.removeAttribute('aria-disabled');
        button.removeAttribute('aria-busy');

        if (region) {
            region.removeAttribute('aria-busy');
        }

        if (label && i18n.copy_label) {
            label.textContent = i18n.copy_label;
        }

        if (spinner) {
            spinner.classList.remove('is-active');
        }
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
            return document.execCommand('copy');
        } catch (err) {
            return false;
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

        clearFeedback(button);

        if (!link) {
            showFeedback(button, 'error', i18n.no_payment_link);
            return;
        }

        setBusyState(button, true);

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(link).then(function() {
                showFeedback(button, 'success', i18n.copied);
            }).catch(function() {
                if (fallbackCopy(link)) {
                    showFeedback(button, 'success', i18n.copied);
                    return;
                }

                showFeedback(button, 'error', formatString(i18n.copy_failed, link));
            }).finally(function() {
                setBusyState(button, false);
            });
        } else {
            if (fallbackCopy(link)) {
                showFeedback(button, 'success', i18n.copied);
            } else {
                showFeedback(button, 'error', formatString(i18n.copy_failed, link));
            }

            setBusyState(button, false);
        }
    });
})();
