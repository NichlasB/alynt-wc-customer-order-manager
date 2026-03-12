jQuery(function($) {
    window.awcomOrderInterface = window.awcomOrderInterface || {};
    var api = window.awcomOrderInterface;
    api.shippingRequest = null;
    api.shippingRequestId = 0;
    api.shippingUpdateTimer = null;

    function escapeHtml(value) {
        return String(value === null || typeof value === 'undefined' ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getFormattedCostText(method) {
        if (method && method.formatted_cost_text !== null && typeof method.formatted_cost_text !== 'undefined') {
            return String(method.formatted_cost_text);
        }

        if (method && method.formatted_cost !== null && typeof method.formatted_cost !== 'undefined') {
            return String(method.formatted_cost).replace(/<[^>]*>/g, '');
        }

        return '';
    }

    api.renderShippingMessage = function(message, type, retryable) {
        var cssClass = type === 'warning' ? 'no-shipping' : 'error';
        var html = '<p class="' + escapeHtml(cssClass) + '">' + escapeHtml(message) + '</p>';

        if (retryable) {
            html += '<p><button type="button" class="button retry-shipping">' + escapeHtml(awcomOrderVars.i18n.retry_shipping) + '</button></p>';
        }

        $('#shipping-methods').attr('aria-busy', 'false').html(html);

        if (typeof api.updateOrderTotals === 'function') {
            api.updateOrderTotals();
        }
    };

    api.queueShippingMethodsUpdate = function(delay) {
        window.clearTimeout(api.shippingUpdateTimer);
        api.shippingUpdateTimer = window.setTimeout(function() {
            api.updateShippingMethods();
        }, typeof delay === 'number' ? delay : 300);
    };

    api.updateShippingMethods = function() {
        var items = [];
        $('.awcom-order-items tbody tr').each(function() {
            items.push({
                product_id: $(this).data('product-id'),
                quantity: $(this).find('input.quantity').val(),
            });
        });

        var previouslySelected = $('input[name="shipping_method"]:checked').val();
        $('#shipping-methods').attr('aria-busy', 'true').html('<p class="loading">' + escapeHtml(awcomOrderVars.i18n.calculating) + '</p>');

        if (api.shippingRequest && api.shippingRequest.readyState !== 4) {
            api.shippingRequest.abort();
        }

        api.shippingRequestId += 1;
        var requestId = api.shippingRequestId;

        api.shippingRequest = $.ajax({
            url: awcomOrderVars.ajaxurl,
            type: 'POST',
            timeout: 15000,
            data: {
                action: 'awcom_get_shipping_methods',
                nonce: awcomOrderVars.nonce,
                customer_id: $('input[name="customer_id"]').val(),
                items: items,
            },
            success: function(response) {
                if (requestId !== api.shippingRequestId) {
                    return;
                }

                if (!response || !response.success || !response.data) {
                    api.renderShippingMessage(awcomOrderVars.i18n.shipping_error, 'error', true);
                    return;
                }

                var methods = Array.isArray(response.data.methods) ? response.data.methods : [];
                var html = '';

                if (methods.length > 0) {
                    html += '<ul class="shipping-method-list">';
                    methods.forEach(function(method) {
                        var methodLabel = escapeHtml(method.label);
                        var methodCost = escapeHtml(getFormattedCostText(method));

                        html += '<li>';
                        html += '<label>';
                        html += '<input type="radio" name="shipping_method" value="' + escapeHtml(method.id) + '" data-cost="' + escapeHtml(method.cost) + '">';
                        html += methodLabel + ' (' + methodCost + ')';
                        html += '</label>';
                        html += '</li>';
                    });
                    html += '</ul>';
                } else {
                    api.renderShippingMessage(response.data.message || awcomOrderVars.i18n.no_shipping, 'warning', !!response.data.retryable);
                    return;
                }

                $('#shipping-methods').attr('aria-busy', 'false').html(html);

                var methodRestored = false;
                if (previouslySelected) {
                    var matchingMethod = $('#shipping-methods input[name="shipping_method"]').filter(function() {
                        return $(this).val() === previouslySelected;
                    });
                    if (matchingMethod.length > 0) {
                        matchingMethod.prop('checked', true);
                        methodRestored = true;
                    }
                }

                if (!methodRestored) {
                    $('#shipping-methods input[type="radio"]:first').prop('checked', true);
                }

                if (typeof api.updateOrderTotals === 'function') {
                    api.updateOrderTotals();
                }
            },
            error: function(xhr, status) {
                if (status === 'abort' || requestId !== api.shippingRequestId) {
                    return;
                }

                var message = awcomOrderVars.i18n.shipping_error;
                var retryable = true;

                if (!navigator.onLine) {
                    message = awcomOrderVars.i18n.offline_error;
                } else if (status === 'timeout') {
                    message = awcomOrderVars.i18n.shipping_timeout;
                }

                if (xhr && xhr.responseJSON && xhr.responseJSON.data) {
                    if (xhr.responseJSON.data.message) {
                        message = xhr.responseJSON.data.message;
                    }
                    retryable = !!xhr.responseJSON.data.retryable;
                }

                api.renderShippingMessage(message, 'error', retryable);
            },
            complete: function(xhr, status) {
                if (status !== 'abort' && requestId === api.shippingRequestId) {
                    api.shippingRequest = null;
                }
            },
        });
    };

    $(document).on('click', '.retry-shipping', function(e) {
        e.preventDefault();
        api.updateShippingMethods();
    });

    if ($('.awcom-order-items tbody tr').length > 0) {
        api.updateShippingMethods();
    }
});
