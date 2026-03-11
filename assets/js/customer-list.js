jQuery(function($) {
    var i18n = window.awcomCustomerListVars && window.awcomCustomerListVars.i18n
        ? window.awcomCustomerListVars.i18n
        : {};
    var $form = $('.wrap form');

    function formatString(template, value) {
        return String(template || '').replace('%d', value);
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

    function confirmDelete(count) {
        if (count < 1) {
            return true;
        }

        var message = count === 1
            ? i18n.confirm_delete_single
            : formatString(i18n.confirm_delete_bulk, count);

        return window.confirm(message);
    }

    if ($form.length === 0) {
        return;
    }

    $form.on('submit', function(e) {
        var selectedAction = getSelectedAction();
        var selectedCount = $form.find('input[name="customers[]"]:checked').length;

        if (selectedAction !== 'delete' || selectedCount < 1) {
            return;
        }

        if ($form.data('confirmedDelete')) {
            $form.removeData('confirmedDelete');
            $form.attr('aria-busy', 'true');
            return;
        }

        if (!confirmDelete(selectedCount)) {
            e.preventDefault();
            return;
        }

        $form.attr('aria-busy', 'true');
    });

    $(document).on('click', '.delete-customer', function(e) {
        var customerId;

        e.preventDefault();
        customerId = $(this).data('id');

        if (!customerId || !confirmDelete(1)) {
            return;
        }

        $form.find('input[name="customers[]"]').prop('checked', false);
        $form.find('input[name="customers[]"][value="' + customerId + '"]').prop('checked', true);
        $form.find('select[name="action"]').val('delete');
        $form.data('confirmedDelete', true);
        $form.trigger('submit');
    });
});
