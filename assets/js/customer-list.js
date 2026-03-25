jQuery(function($) {
    var i18n = window.awcomCustomerListVars && window.awcomCustomerListVars.i18n
        ? window.awcomCustomerListVars.i18n
        : {};
    var $form = $('.awcom-customer-list-form');
    var $spinner = $form.find('.awcom-customer-list-spinner');
    var dialogId = 'awcom-customer-delete-dialog';

    function formatString(template, value) {
        return String(template || '')
            .replace('%d', value)
            .replace('%s', value);
    }

    function getDeleteDialog() {
        var $dialog = $('#' + dialogId);

        if ($dialog.length) {
            return $dialog;
        }

        $dialog = $('<div></div>', {
            id: dialogId,
            class: 'awcom-delete-dialog'
        }).append($('<p></p>'));

        $('body').append($dialog);

        return $dialog;
    }

    function setBusyState(isBusy) {
        var busy = Boolean(isBusy);
        var disabledValue = busy ? 'true' : null;

        $form.attr('aria-busy', busy ? 'true' : 'false');
        $form.find('button[type="submit"], input[type="submit"], select[name="action"], select[name="action2"]')
            .prop('disabled', busy)
            .attr('aria-disabled', disabledValue);

        if ($spinner.length) {
            $spinner.toggleClass('is-active', busy);
        }
    }

    function openDeleteDialog(title, message, actionLabel, onConfirm) {
        var $dialog = getDeleteDialog();

        if (typeof $.fn.dialog !== 'function') {
            if (window.confirm(message)) {
                onConfirm();
            }

            return;
        }

        $dialog.find('p').text(message);
        $dialog.dialog({
            modal: true,
            width: 420,
            minHeight: 0,
            dialogClass: 'wp-dialog awcom-confirm-dialog',
            title: title,
            resizable: false,
            draggable: false,
            closeOnEscape: true,
            buttons: [
                {
                    text: i18n.cancel_label,
                    click: function() {
                        $(this).dialog('close');
                    }
                },
                {
                    text: actionLabel,
                    click: function() {
                        $(this).dialog('close');
                        onConfirm();
                    }
                }
            ],
            open: function() {
                var $buttons = $(this).closest('.ui-dialog').find('.ui-dialog-buttonpane button');

                $buttons.eq(0).addClass('button');
                $buttons.eq(1).addClass('button-link-delete');
                $buttons.eq(0).trigger('focus');
            },
            close: function() {
                $(this).dialog('destroy');
            }
        });
    }

    function getSelectedAction() {
        var primaryAction = $form.find('select[name="action"]').val();
        var secondaryAction = $form.find('select[name="action2"]').val();

        if (primaryAction && primaryAction !== '-1') {
            return primaryAction;
        }

        if (secondaryAction && secondaryAction !== '-1') {
            return secondaryAction;
        }

        return '';
    }

    if ($form.length === 0) {
        return;
    }

    setBusyState(false);

    $form.on('submit', function(e) {
        var selectedAction = getSelectedAction();
        var selectedCount = $form.find('input[name="customers[]"]:checked').length;

        if (selectedAction !== 'delete' || selectedCount < 1) {
            return;
        }

        if ($form.data('confirmedDelete')) {
            $form.removeData('confirmedDelete');
            setBusyState(true);
            return;
        }

        e.preventDefault();
        openDeleteDialog(
            i18n.delete_bulk_title,
            formatString(i18n.delete_bulk_message, selectedCount),
            i18n.delete_bulk_action,
            function() {
                $form.data('confirmedDelete', true);
                $form.trigger('submit');
            }
        );
    });

    $(document).on('click', '.delete-customer', function(e) {
        var customerId;
        var customerName;

        e.preventDefault();
        customerId = $(this).data('id');
        customerName = String($(this).data('name') || '').trim();

        if (!customerId) {
            return;
        }

        if (!customerName) {
            customerName = $(this).closest('tr').find('.column-customer_name strong').text().trim();
        }

        openDeleteDialog(
            i18n.delete_single_title,
            formatString(i18n.delete_single_message, customerName || customerId),
            i18n.delete_single_action,
            function() {
                $form.find('input[name="customers[]"]').prop('checked', false);
                $form.find('input[name="customers[]"][value="' + customerId + '"]').prop('checked', true);
                $form.find('select[name="action"]').val('delete');
                $form.data('confirmedDelete', true);
                $form.trigger('submit');
            }
        );
    });
});
