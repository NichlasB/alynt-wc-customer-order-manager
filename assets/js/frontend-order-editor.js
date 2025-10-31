jQuery(function($) {
    // ---------------
    // Initialization
    // ---------------

    // Add dynamic styles for stock indicators
    $('<style>')
    .text(`
        .awcom-frontend-order-editor .awcom-stock-info {
            color: #666;
            font-weight: normal;
        }
        .awcom-frontend-order-editor .awcom-stock-low {
            color: #ff9800;
            font-weight: bold;
        }
        .awcom-frontend-order-editor .awcom-stock-out {
            color: #f44336;
            font-weight: bold;
        }
        .select2-results__option .awcom-out-of-stock {
            color: #f44336;
        }
        .select2-results__option .awcom-low-stock {
            color: #ff9800;
        }
        .awcom-order-edit-notices {
            margin: 20px 0;
        }
        .awcom-notice {
            padding: 12px;
            margin: 10px 0;
            border-left: 4px solid;
            background: #fff;
        }
        .awcom-notice.success {
            border-left-color: #46b450;
            background: #f7fcf0;
            color: #5b9025;
        }
        .awcom-notice.error {
            border-left-color: #dc3232;
            background: #fbeaea;
            color: #a00;
        }
        .awcom-loading {
            opacity: 0.6;
            pointer-events: none;
        }
        `)
    .appendTo('head');

    // Get order data from form
    const $form = $('#awcom-frontend-order-form');
    const orderId = $form.attr('data-order-id');
    const orderKey = $form.attr('data-order-key');
    const isPaidOrder = $('#awcom-create-additional-order').length > 0;
    const hasAdditionalItemsSection = $('.awcom-additional-items-section').length > 0;

    // Initialize Select2 for product search
    $('#awcom-add-product').select2({
        ajax: {
            url: awcomFrontendVars.ajaxurl,
            dataType: 'json',
            type: 'POST',
            delay: 250,
            data: function(params) {
                return {
                    term: params.term,
                    action: 'awcom_frontend_search_products',
                    nonce: awcomFrontendVars.nonce,
                    order_id: orderId,
                    order_key: orderKey
                };
            },
            processResults: function(data) {
                // Handle WordPress AJAX error response format
                if (data && data.success === false) {
                    return { results: [] };
                }
                
                // Ensure data is an array
                if (!Array.isArray(data)) {
                    return { results: [] };
                }
                
                // Add stock information to the display text
                var results = data.map(function(item) {
                    var stockText = item.stock_display ? ' (' + item.stock_display + ')' : '';
                    var stockClass = '';
                    
                    // Add CSS class based on stock status
                    if (item.stock_status === 'outofstock' || (item.manage_stock && item.stock_quantity === 0)) {
                        stockClass = 'awcom-out-of-stock';
                    } else if (item.manage_stock && item.stock_quantity !== null && item.stock_quantity <= 5) {
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
                        element: $('<option></option>').addClass(stockClass)
                    };
                });
                
                return {
                    results: results
                };
            },
            cache: true
        },
        minimumInputLength: 2,
        placeholder: awcomFrontendVars.i18n.search_products,
        allowClear: true,
        width: '100%'
    });

    // ---------------
    // Event Handlers
    // ---------------

    // Handle product selection
    let processingProduct = false;
    
    $('#awcom-add-product').on('select2:select', function(e) {
        // Immediate blocking to prevent any duplicate processing
        if (processingProduct) {
            return false;
        }
        
        processingProduct = true;
        
        var product = e.params.data;
        
        // Clear the select immediately to prevent re-triggering
        $(this).val(null).trigger('change');
        
        // Add the product
        if (isPaidOrder && hasAdditionalItemsSection) {
            addProductToAdditionalItems(product);
            updateAdditionalItemsTotal();
            updateShippingMethods();
        } else {
            addProductToOrder(product);
            updateOrderTotals();
            updateShippingMethods();
        }
        
        // Reset processing flag after DOM updates
        setTimeout(function() {
            processingProduct = false;
        }, 50);
        
        return false; // Prevent event bubbling
    });

    // Handle quantity changes
    $(document).on('change keyup', '.awcom-order-items input.quantity', function() {
        updateLineTotal($(this).closest('tr'));
        updateOrderTotals();
        updateShippingMethods();
    });

    // Handle remove item
    $(document).on('click', '.awcom-order-items .remove-item', function(e) {
        e.preventDefault();
        $(this).closest('tr').remove();
        updateOrderTotals();
        updateShippingMethods();
    });

    // Handle shipping method selection
    $(document).on('change', 'input[name="shipping_method"]', function() {
        updateOrderTotals();
    });

    // Handle quantity changes for additional items
    $(document).on('change keyup', '.awcom-additional-items input.quantity', function() {
        updateAdditionalLineTotal($(this).closest('tr'));
        updateAdditionalItemsTotal();
        updateShippingMethods();
        checkAdditionalItemsButton();
    });

    // Handle remove additional item
    $(document).on('click', '.awcom-additional-items .remove-item', function(e) {
        e.preventDefault();
        $(this).closest('tr').remove();
        updateAdditionalItemsTotal();
        updateShippingMethods();
        checkAdditionalItemsButton();
        
        // Show "no items" message if table is empty
        if ($('#awcom-additional-items-body tr:not(.no-items)').length === 0) {
            $('#awcom-additional-items-body').html('<tr class="no-items"><td colspan="5">' + awcomFrontendVars.i18n.no_additional_items + '</td></tr>');
        }
    });

    // Handle update order button
    $('#awcom-update-order').on('click', function() {
        updateOrder();
    });

    // Handle proceed to payment button
    $('#awcom-proceed-payment').on('click', function() {
        var paymentUrl = $(this).data('payment-url');
        if (paymentUrl) {
            window.location.href = paymentUrl;
        }
    });

    // Handle create additional order button
    $('#awcom-create-additional-order').on('click', function() {
        createAdditionalOrder();
    });

    // ---------------
    // Product Functions
    // ---------------

    function addProductToOrder(product) {
        // Check if product already exists in order
        const existingRow = $('.awcom-order-items tbody tr[data-product-id="' + product.id + '"]');
        if (existingRow.length) {
            // Increase quantity instead of adding duplicate
            const quantityInput = existingRow.find('input.quantity');
            const currentQty = parseInt(quantityInput.val()) || 1;
            quantityInput.val(currentQty + 1).trigger('change');
            return;
        }

        var row = $('<tr></tr>');
        row.attr('data-product-id', product.id);
        row.attr('data-price', product.price);

        var priceDisplay = product.has_discount 
            ? '<span class="original-price"><del>' + product.formatted_original_price + '</del></span> ' + product.formatted_price
            : product.formatted_price;

        // Strip stock info from product title for the order items table
        var cleanProductName = product.text;
        if (product.stock_display) {
            var stockPattern = ' \\(' + product.stock_display.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\)';
            cleanProductName = cleanProductName.replace(new RegExp(stockPattern + '$'), '');
        }

        // Create product name with stock info as separate line
        var productNameWithStock = cleanProductName;
        if (product.stock_display) {
            var stockClass = '';
            if (product.stock_status === 'outofstock' || (product.manage_stock && product.stock_quantity === 0)) {
                stockClass = 'awcom-stock-out';
            } else if (product.manage_stock && product.stock_quantity !== null && product.stock_quantity <= 5) {
                stockClass = 'awcom-stock-low';
            } else {
                stockClass = 'awcom-stock-info';
            }
            productNameWithStock += '<br><small class="' + stockClass + '">' + product.stock_display + '</small>';
        }

        // Set max quantity based on stock
        var maxQty = 999;
        if (product.manage_stock && product.stock_quantity !== null) {
            maxQty = product.stock_quantity;
        }

        row.append('<td class="product-name">' + productNameWithStock + '</td>');
        row.append('<td class="product-quantity"><input type="number" class="quantity" value="1" min="1" max="' + maxQty + '"></td>');
        row.append('<td class="product-price">' + priceDisplay + '</td>');
        row.append('<td class="product-total">' + product.formatted_price + '</td>');
        row.append('<td class="product-remove"><a href="#" class="remove-item" title="' + awcomFrontendVars.i18n.remove_item + '">×</a></td>');

        $('.awcom-order-items tbody').append(row);
    }

    // ---------------
    // Calculation Functions
    // ---------------

    function updateLineTotal(row) {
        var quantity = parseFloat(row.find('input.quantity').val()) || 0;
        var price = parseFloat(row.attr('data-price')) || 0;
        var total = quantity * price;

        row.find('.product-total').text(formatPrice(total));
        return total;
    }

    function updateOrderTotals() {
        var subtotal = 0;
        $('.awcom-order-items tbody tr').each(function() {
            subtotal += updateLineTotal($(this));
        });

        // Get selected shipping cost
        var shippingCost = 0;
        var selectedShipping = $('input[name="shipping_method"]:checked');
        if (selectedShipping.length) {
            shippingCost = parseFloat(selectedShipping.data('cost')) || 0;
        }

        // Update displayed totals
        $('.awcom-order-items .subtotal').text(formatPrice(subtotal));
        $('.shipping-total').text(formatPrice(shippingCost));
        $('.order-total-amount').text(formatPrice(subtotal + shippingCost));
    }

    function formatPrice(price) {
        return accounting.formatMoney(price, {
            symbol: awcomFrontendVars.currency_symbol,
            decimal: ".",
            thousand: ",",
            precision: 2,
            format: "%s%v"
        });
    }

    // ---------------
    // Shipping Functions
    // ---------------

    function updateShippingMethods() {
        var items = [];
        
        // For paid orders, collect items from additional items table
        if (isPaidOrder && hasAdditionalItemsSection) {
            $('#awcom-additional-items-body tr:not(.no-items)').each(function() {
                const productId = $(this).data('product-id');
                const quantity = $(this).find('input.quantity').val();
                if (productId && quantity > 0) {
                    items.push({
                        product_id: productId,
                        quantity: quantity
                    });
                }
            });
        } else {
            // For pending orders, collect items from main order table
            $('.awcom-order-items tbody tr').each(function() {
                const productId = $(this).data('product-id');
                const quantity = $(this).find('input.quantity').val();
                if (productId && quantity > 0) {
                    items.push({
                        product_id: productId,
                        quantity: quantity
                    });
                }
            });
        }

        if (items.length === 0) {
            $('#awcom-shipping-methods').html('<p class="no-shipping">' + awcomFrontendVars.i18n.no_shipping + '</p>');
            return;
        }

        $('#awcom-shipping-methods').html('<p class="loading">' + awcomFrontendVars.i18n.calculating + '</p>');

        $.ajax({
            url: awcomFrontendVars.ajaxurl,
            type: 'POST',
            data: {
                action: 'awcom_frontend_get_shipping_methods',
                nonce: awcomFrontendVars.nonce,
                order_id: orderId,
                order_key: orderKey,
                items: items
            },
            success: function(response) {
                var html = '';

                if (response && response.success && response.data && response.data.length > 0) {
                    html += '<ul class="shipping-method-list">';
                    response.data.forEach(function(method) {
                        var formattedCost = accounting.formatMoney(method.cost, {
                            symbol: awcomFrontendVars.currency_symbol,
                            decimal: ".",
                            thousand: ",",
                            precision: 2,
                            format: "%s%v"
                        });
                        html += '<li>';
                        html += '<label>';
                        html += '<input type="radio" name="shipping_method" value="' + method.id + '" data-cost="' + method.cost + '">';
                        html += ' ' + method.label + ' (' + formattedCost + ')';
                        html += '</label>';
                        html += '</li>';
                    });
                    html += '</ul>';
                } else {
                    html = '<p class="no-shipping">' + awcomFrontendVars.i18n.no_shipping + '</p>';
                }

                $('#awcom-shipping-methods').html(html);

                // Select first shipping method by default if available
                if (response && response.success && response.data && response.data.length > 0) {
                    $('#awcom-shipping-methods input[type="radio"]:first').prop('checked', true);
                    updateOrderTotals();
                }
            },
            error: function() {
                $('#awcom-shipping-methods').html('<p class="error">' + awcomFrontendVars.i18n.shipping_error + '</p>');
            }
        });
    }

    // ---------------
    // Order Update Functions
    // ---------------

    function updateOrder() {
        // Validate order
        if ($('.awcom-order-items tbody tr').length === 0) {
            showNotice('error', awcomFrontendVars.i18n.no_items);
            return;
        }

        if ($('input[name="shipping_method"]:checked').length === 0) {
            showNotice('error', awcomFrontendVars.i18n.no_shipping_selected);
            return;
        }

        // Collect order data
        var items = [];
        $('.awcom-order-items tbody tr').each(function() {
            const productId = $(this).data('product-id');
            const quantity = $(this).find('input.quantity').val();
            if (productId && quantity > 0) {
                items.push({
                    product_id: productId,
                    quantity: quantity
                });
            }
        });

        var shippingMethod = $('input[name="shipping_method"]:checked').val();
        var orderNotes = $('#order-notes').val();

        // Show loading state
        $('.awcom-frontend-order-editor').addClass('awcom-loading');
        $('#awcom-update-order').prop('disabled', true).text(awcomFrontendVars.i18n.calculating);

        $.ajax({
            url: awcomFrontendVars.ajaxurl,
            type: 'POST',
            data: {
                action: 'awcom_frontend_update_order',
                nonce: awcomFrontendVars.nonce,
                order_id: orderId,
                order_key: orderKey,
                items: items,
                shipping_method: shippingMethod,
                order_notes: orderNotes
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    
                    // Keep the update button visible and add proceed to payment option
                    $('#awcom-proceed-payment')
                        .data('payment-url', response.data.payment_url)
                        .show();
                    
                    // Change update button text to indicate it can be used again
                    $('#awcom-update-order').text('Update Order Again').show();
                } else {
                    showNotice('error', response.data || awcomFrontendVars.i18n.update_error);
                }
            },
            error: function() {
                showNotice('error', awcomFrontendVars.i18n.update_error);
            },
            complete: function() {
                // Remove loading state
                $('.awcom-frontend-order-editor').removeClass('awcom-loading');
                $('#awcom-update-order').prop('disabled', false).text(awcomFrontendVars.i18n.update_order);
            }
        });
    }

    function showNotice(type, message) {
        const noticeHtml = '<div class="awcom-notice ' + type + '">' + message + '</div>';
        $('.awcom-order-edit-notices').html(noticeHtml);
        
        // Scroll to notice
        $('html, body').animate({
            scrollTop: $('.awcom-order-edit-notices').offset().top - 100
        }, 500);

        // Auto-hide success notices after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                $('.awcom-order-edit-notices .awcom-notice.success').fadeOut();
            }, 5000);
        }
    }

    // ---------------
    // Additional Items Functions (for paid orders)
    // ---------------

    function addProductToAdditionalItems(product) {
        // Check if product already exists in additional items
        const existingRow = $('.awcom-additional-items tbody tr[data-product-id="' + product.id + '"]');
        
        if (existingRow.length) {
            // DON'T auto-increment for existing products - just return
            return;
        }

        // Remove "no items" message
        $('#awcom-additional-items-body .no-items').remove();

        var row = $('<tr></tr>');
        row.attr('data-product-id', product.id);
        row.attr('data-price', product.price);

        var priceDisplay = product.has_discount 
            ? '<span class="original-price"><del>' + product.formatted_original_price + '</del></span> ' + product.formatted_price
            : product.formatted_price;

        // Strip stock info from product title
        var cleanProductName = product.text;
        if (product.stock_display) {
            var stockPattern = ' \\(' + product.stock_display.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\)';
            cleanProductName = cleanProductName.replace(new RegExp(stockPattern + '$'), '');
        }

        // Create product name with stock info
        var productNameWithStock = cleanProductName;
        if (product.stock_display) {
            var stockClass = '';
            if (product.stock_status === 'outofstock' || (product.manage_stock && product.stock_quantity === 0)) {
                stockClass = 'awcom-stock-out';
            } else if (product.manage_stock && product.stock_quantity !== null && product.stock_quantity <= 5) {
                stockClass = 'awcom-stock-low';
            } else {
                stockClass = 'awcom-stock-info';
            }
            productNameWithStock += '<br><small class="' + stockClass + '">' + product.stock_display + '</small>';
        }

        // Set max quantity based on stock
        var maxQty = 999;
        if (product.manage_stock && product.stock_quantity !== null) {
            maxQty = product.stock_quantity;
        }

        row.append('<td class="product-name">' + productNameWithStock + '</td>');
        row.append('<td class="product-quantity"><input type="number" class="quantity" value="1" min="1" max="' + maxQty + '"></td>');
        row.append('<td class="product-price">' + priceDisplay + '</td>');
        row.append('<td class="product-total">' + product.formatted_price + '</td>');
        row.append('<td class="product-remove"><a href="#" class="remove-item" title="' + awcomFrontendVars.i18n.remove_item + '">×</a></td>');

        $('#awcom-additional-items-body').append(row);
        checkAdditionalItemsButton();
    }

    function updateAdditionalLineTotal(row) {
        var quantity = parseFloat(row.find('input.quantity').val()) || 0;
        var price = parseFloat(row.attr('data-price')) || 0;
        var total = quantity * price;

        row.find('.product-total').text(formatPrice(total));
        return total;
    }

    function updateAdditionalItemsTotal() {
        var total = 0;
        $('#awcom-additional-items-body tr:not(.no-items)').each(function() {
            total += updateAdditionalLineTotal($(this));
        });

        $('.additional-total-amount').text(formatPrice(total));
        return total;
    }

    function checkAdditionalItemsButton() {
        const hasItems = $('#awcom-additional-items-body tr:not(.no-items)').length > 0;
        const buttonExists = $('#awcom-create-additional-order').length > 0;
        
        if (hasItems && buttonExists) {
            $('#awcom-create-additional-order').show();
        } else {
            $('#awcom-create-additional-order').hide();
        }
    }

    function createAdditionalOrder() {
        // Validate additional items
        if ($('#awcom-additional-items-body tr:not(.no-items)').length === 0) {
            showNotice('error', 'Please add at least one additional item.');
            return;
        }

        // Collect additional items data
        var additionalItems = [];
        $('#awcom-additional-items-body tr:not(.no-items)').each(function() {
            const productId = $(this).data('product-id');
            const quantity = $(this).find('input.quantity').val();
            if (productId && quantity > 0) {
                additionalItems.push({
                    product_id: productId,
                    quantity: quantity
                });
            }
        });

        var shippingMethod = $('input[name="shipping_method"]:checked').val();
        var orderNotes = $('#order-notes').val();

        // Show loading state
        $('.awcom-frontend-order-editor').addClass('awcom-loading');
        $('#awcom-create-additional-order').prop('disabled', true).text('Creating Order...');

        $.ajax({
            url: awcomFrontendVars.ajaxurl,
            type: 'POST',
            data: {
                action: 'awcom_frontend_create_additional_order',
                nonce: awcomFrontendVars.nonce,
                order_id: orderId,
                order_key: orderKey,
                additional_items: additionalItems,
                shipping_method: shippingMethod,
                order_notes: orderNotes
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    
                    // Redirect to payment page for additional order
                    setTimeout(function() {
                        window.location.href = response.data.payment_url;
                    }, 2000);
                } else {
                    showNotice('error', response.data || 'Error creating additional order');
                }
            },
            error: function() {
                showNotice('error', 'Error creating additional order. Please try again.');
            },
            complete: function() {
                // Remove loading state
                $('.awcom-frontend-order-editor').removeClass('awcom-loading');
                $('#awcom-create-additional-order').prop('disabled', false).text('Add Items & Pay');
            }
        });
    }

    // Load shipping methods on page load
    // For pending orders, load immediately
    // For paid orders, will load when items are added
    if (!isPaidOrder) {
        updateShippingMethods();
    } else {
        // For paid orders, show "no shipping" initially since no additional items yet
        $('#awcom-shipping-methods').html('<p class="no-shipping">' + (awcomFrontendVars.i18n.no_shipping || 'No shipping methods available') + '</p>');
    }
}); // End jQuery wrapper
