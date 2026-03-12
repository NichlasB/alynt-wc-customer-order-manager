jQuery(function($) {
    window.awcomOrderInterface = window.awcomOrderInterface || {};
    var api = window.awcomOrderInterface;
    api.productSearchErrorMessage = '';
    api.lowStockThreshold = 5;

    api.formatPrice = function(price) {
        var normalizedPrice = Number(price);

        if (!Number.isFinite(normalizedPrice)) {
            normalizedPrice = 0;
        }

        if (typeof window.accounting !== 'undefined' && typeof window.accounting.formatMoney === 'function') {
            return window.accounting.formatMoney(normalizedPrice, {
                symbol: awcomOrderVars.currency_symbol,
                decimal: '.',
                thousand: ',',
                precision: 2,
                format: '%s%v',
            });
        }

        return awcomOrderVars.currency_symbol + normalizedPrice.toFixed(2);
    };

    api.updateLineTotal = function(row) {
        var quantity = parseFloat(row.find('input.quantity').val()) || 0;
        var price = parseFloat(row.attr('data-price')) || 0;
        var total = quantity * price;
        row.find('.total').text(api.formatPrice(total));
        return total;
    };

    api.updateOrderTotals = function() {
        var subtotal = 0;
        $('.awcom-order-items tbody tr').each(function() {
            subtotal += api.updateLineTotal($(this));
        });

        var shippingCost = 0;
        var selectedShipping = $('input[name="shipping_method"]:checked');
        if (selectedShipping.length) {
            shippingCost = parseFloat(selectedShipping.data('cost')) || 0;
        }

        $('.awcom-order-items .subtotal').text(api.formatPrice(subtotal));
        $('.shipping-total').text(api.formatPrice(shippingCost));
        $('.order-total').text(api.formatPrice(subtotal + shippingCost));
    };

    api.showOrderNotice = function(type, message) {
        var $notice = $('#awcom-order-interface-notice');

        if ($notice.length === 0) {
            $notice = $('<div id="awcom-order-interface-notice" class="notice"><p></p></div>');
            $('#awcom-create-order-form').before($notice);
        }

        $notice
            .removeClass('notice-error notice-warning notice-success')
            .addClass(type === 'warning' ? 'notice-warning' : 'notice-error')
            .attr('role', type === 'warning' ? 'status' : 'alert')
            .find('p')
            .text(message);
    };

    api.clearOrderNotice = function() {
        $('#awcom-order-interface-notice').remove();
    };

    api.requestShippingMethodsUpdate = function(delay) {
        if (typeof api.queueShippingMethodsUpdate === 'function') {
            api.queueShippingMethodsUpdate(delay);
        } else if (typeof api.updateShippingMethods === 'function') {
            api.updateShippingMethods();
        }
    };

    api.getInvalidQuantityField = function() {
        var $invalidField = $();

        $('.awcom-order-items input.quantity').each(function() {
            var quantity = parseFloat($(this).val());
            if (!Number.isFinite(quantity) || quantity < 1 || quantity !== Math.floor(quantity)) {
                $invalidField = $(this);
                return false;
            }
        });

        return $invalidField;
    };

    api.findProductRow = function(productId) {
        var normalizedProductId = parseInt(productId, 10);

        return $('.awcom-order-items tbody tr').filter(function() {
            return parseInt($(this).attr('data-product-id'), 10) === normalizedProductId;
        }).first();
    };

    api.addProductToOrder = function(product) {
        var existingRow = api.findProductRow(product.id);

        if (existingRow.length) {
            var $quantityField = existingRow.find('input.quantity');
            var currentQuantity = parseInt($quantityField.val(), 10);

            if (!Number.isFinite(currentQuantity) || currentQuantity < 1) {
                currentQuantity = 1;
            }

            $quantityField.val(currentQuantity + 1).trigger('change').trigger('focus');
            return;
        }

        var row = $('<tr></tr>');
        row.attr('data-product-id', product.id);
        row.attr('data-price', product.price);

        var priceDisplay = product.has_discount
            ? '<span class="original-price"><del>' + product.formatted_original_price + '</del></span> ' + product.formatted_price
            : product.formatted_price;

        var cleanProductName = product.text;
        if (product.stock_display) {
            var stockPattern = ' \\(' + product.stock_display.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\)';
            cleanProductName = cleanProductName.replace(new RegExp(stockPattern + '$'), '');
        }

        var productNameWithStock = cleanProductName;
        if (product.stock_display) {
            var stockClass = '';
            if (product.stock_status === 'outofstock' || (product.manage_stock && product.stock_quantity === 0)) {
                stockClass = 'awcom-stock-out';
            } else if (product.manage_stock && product.stock_quantity !== null && product.stock_quantity <= api.lowStockThreshold) {
                stockClass = 'awcom-stock-low';
            }
            productNameWithStock += '<br><small class="awcom-stock-info ' + stockClass + '">' + product.stock_display + '</small>';
        }

        row.append('<td class="product-name">' + productNameWithStock + '</td>');
        row.append('<td class="quantity"><input type="number" name="items[' + product.id + '][quantity]" class="quantity" value="1" min="1"></td>');
        row.append('<td class="price">' + priceDisplay + '</td>');
        row.append('<td class="total">' + product.formatted_price + '</td>');
        row.append('<td class="actions"><a href="#" class="remove-item" aria-label="' + awcomOrderVars.i18n.remove_item + '"><span aria-hidden="true">×</span></a></td>');

        $('.awcom-order-items tbody').append(row);
        api.updateOrderTotals();
        api.clearOrderNotice();
        api.requestShippingMethodsUpdate(0);
    };

    api.initProductSelect = function() {
        if (typeof $.fn.select2 !== 'function') {
            $('#awcom-add-product').prop('disabled', true);
            api.showOrderNotice('error', awcomOrderVars.i18n.product_search_unavailable);
            return;
        }

        var $productSelect = $('#awcom-add-product');

        $productSelect.select2({
            ajax: {
                url: awcomOrderVars.ajaxurl,
                dataType: 'json',
                delay: 250,
                timeout: 10000,
                data: function(params) {
                    return {
                        term: params.term,
                        action: 'awcom_search_products',
                        nonce: awcomOrderVars.nonce,
                        customer_id: $('input[name="customer_id"]').val(),
                    };
                },
                transport: function(params, success, failure) {
                    api.productSearchErrorMessage = '';

                    var request = $.ajax(params);
                    request.done(success);
                    request.fail(function(xhr, status) {
                        if (status === 'abort') {
                            return;
                        }

                        api.productSearchErrorMessage = awcomOrderVars.i18n.product_search_error;
                        if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                            api.productSearchErrorMessage = xhr.responseJSON.data.message;
                        }

                        failure(xhr);
                    });

                    return request;
                },
                processResults: function(response) {
                    var products = response && response.success && response.data && Array.isArray(response.data.products)
                        ? response.data.products
                        : [];

                    var results = products.map(function(item) {
                        var stockText = item.stock_display ? ' (' + item.stock_display + ')' : '';
                        var stockClass = '';

                        if (item.stock_status === 'outofstock' || (item.manage_stock && item.stock_quantity === 0)) {
                            stockClass = 'awcom-out-of-stock';
                        } else if (item.manage_stock && item.stock_quantity !== null && item.stock_quantity <= api.lowStockThreshold) {
                            stockClass = 'awcom-low-stock';
                        }

                        return {
                            id: item.id,
                            text: item.text + stockText,
                            price: item.price,
                            formatted_price: item.formatted_price,
                            original_price: item.original_price,
                            formatted_original_price: item.formatted_original_price,
                            has_discount: item.has_discount,
                            stock_quantity: item.stock_quantity,
                            stock_status: item.stock_status,
                            stock_display: item.stock_display,
                            manage_stock: item.manage_stock,
                            element: $('<option></option>').addClass(stockClass),
                        };
                    });

                    return { results: results };
                },
                cache: true,
            },
            minimumInputLength: 2,
            placeholder: awcomOrderVars.i18n.search_products,
            language: {
                noResults: function() {
                    return awcomOrderVars.i18n.no_products;
                },
                errorLoading: function() {
                    return api.productSearchErrorMessage || awcomOrderVars.i18n.product_search_error;
                },
            },
            allowClear: true,
            width: '100%',
        });

        $productSelect.on('select2:select', function(e) {
            var product = e.params.data;
            api.addProductToOrder(product);
            $(this).val(null).trigger('change');
        });
    };

    api.bindEvents = function() {
        $(document).on('change keyup', '.awcom-order-items input.quantity', function() {
            api.clearOrderNotice();
            api.updateLineTotal($(this).closest('tr'));
            api.updateOrderTotals();
            api.requestShippingMethodsUpdate(300);
        });

        $(document).on('click', '.awcom-order-items .remove-item', function(e) {
            e.preventDefault();
            $(this).closest('tr').remove();
            api.updateOrderTotals();
            api.requestShippingMethodsUpdate(0);
        });

        $(document).on('change', 'input[name="shipping_method"]', function() {
            api.clearOrderNotice();
            api.updateOrderTotals();
        });

        $('#awcom-create-order-form').on('submit', function(e) {
            var $form = $(this);
            var $submitButton = $form.find('button[type="submit"], input[type="submit"]').first();
            var $invalidQuantityField = api.getInvalidQuantityField();

            api.clearOrderNotice();

            if ($('.awcom-order-items tbody tr').length === 0) {
                e.preventDefault();
                api.showOrderNotice('error', awcomOrderVars.i18n.no_items);
                return false;
            }

            if ($invalidQuantityField.length) {
                e.preventDefault();
                api.showOrderNotice('error', awcomOrderVars.i18n.invalid_quantity);
                $invalidQuantityField.trigger('focus');
                return false;
            }

            if ($('input[name="shipping_method"]:checked').length === 0) {
                e.preventDefault();
                api.showOrderNotice('error', awcomOrderVars.i18n.no_shipping_selected);
                return false;
            }

            if ($submitButton.data('submitting')) {
                e.preventDefault();
                return false;
            }

            $submitButton.data('submitting', true);
            $form.attr('aria-busy', 'true');
            $submitButton.prop('disabled', true).attr('aria-busy', 'true');

            if ($submitButton.is('input')) {
                $submitButton.data('original-text', $submitButton.val());
                $submitButton.val(awcomOrderVars.i18n.creating_order);
            } else {
                $submitButton.data('original-text', $submitButton.text());
                $submitButton.text(awcomOrderVars.i18n.creating_order);
            }
        });
    };

    api.initProductSelect();
    api.bindEvents();
});
