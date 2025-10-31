jQuery(function($) {
    // ---------------
    // Initialization
    // ---------------

    $('<style>')
    .text(`
        .awcom-order-items .original-price del {
            color: #999;
            margin-right: 5px;
        }
        .awcom-order-items .price-discount {
            color: #4CAF50;
            font-size: 0.9em;
            margin-left: 5px;
        }
        .awcom-stock-info {
            color: #666;
            font-weight: normal;
        }
        .awcom-stock-low {
            color: #ff9800;
            font-weight: bold;
        }
        .awcom-stock-out {
            color: #f44336;
            font-weight: bold;
        }
        .select2-results__option .awcom-out-of-stock {
            color: #f44336;
        }
        .select2-results__option .awcom-low-stock {
            color: #ff9800;
        }
        `)
    .appendTo('head');

    // Initialize Select2 for product search
    $('#awcom-add-product').select2({
        ajax: {
            url: awcomOrderVars.ajaxurl,
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    term: params.term,
                    action: 'awcom_search_products',
                    nonce: awcomOrderVars.nonce,
                    customer_id: jQuery('input[name="customer_id"]').val()
                };
            },
            processResults: function(data) {
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
        placeholder: awcomOrderVars.i18n.search_products,
        allowClear: true,
        width: '100%'
    });

    // ---------------
    // Event Handlers
    // ---------------

    // Handle product selection
    $('#awcom-add-product').on('select2:select', function(e) {
        var product = e.params.data;
        addProductToOrder(product);
        $(this).val(null).trigger('change');
        updateOrderTotals();
        updateShippingMethods();
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

    // ---------------
    // Product Functions
    // ---------------

    function addProductToOrder(product) {
        var row = $('<tr></tr>');
        row.attr('data-product-id', product.id);
        row.attr('data-price', product.price);

        var priceDisplay = product.has_discount 
        ? '<span class="original-price"><del>' + product.formatted_original_price + '</del></span> ' + product.formatted_price
        : product.formatted_price;

        // Strip stock info from product title for the order items table
        // The product.text from the dropdown includes stock info, but we want clean product name here
        var cleanProductName = product.text;
        if (product.stock_display) {
            // Remove the stock info that was added to the dropdown text
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
            }
            productNameWithStock += '<br><small class="awcom-stock-info ' + stockClass + '">' + product.stock_display + '</small>';
        }

        row.append('<td class="product-name">' + productNameWithStock + '</td>');
        row.append('<td class="quantity"><input type="number" name="items[' + product.id + '][quantity]" class="quantity" value="1" min="1"></td>');
        row.append('<td class="price">' + priceDisplay + '</td>');
        row.append('<td class="total">' + product.formatted_price + '</td>');
        row.append('<td class="actions"><a href="#" class="remove-item" title="' + awcomOrderVars.i18n.remove_item + '">×</a></td>');

        $('.awcom-order-items tbody').append(row);
        updateOrderTotals();
        updateShippingMethods();
    }

    // ---------------
    // Calculation Functions
    // ---------------

    function updateLineTotal(row) {
        var quantity = parseFloat(row.find('input.quantity').val()) || 0;
        var price = parseFloat(row.attr('data-price')) || 0;
        var total = quantity * price;

    // Format the total with the current price (already adjusted if applicable)
        row.find('.total').text(formatPrice(total));
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
        $('.order-total').text(formatPrice(subtotal + shippingCost));
    }

    function formatPrice(price) {
        return accounting.formatMoney(price, {
            symbol: awcomOrderVars.currency_symbol,
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
        $('.awcom-order-items tbody tr').each(function() {
            items.push({
                product_id: $(this).data('product-id'),
                quantity: $(this).find('input.quantity').val()
            });
        });

        // Remember the currently selected shipping method before recalculating
        var previouslySelected = $('input[name="shipping_method"]:checked').val();

        $('#shipping-methods').html('<p class="loading">' + awcomOrderVars.i18n.calculating + '</p>');

        $.ajax({
            url: awcomOrderVars.ajaxurl,
            type: 'POST',
            data: {
                action: 'awcom_get_shipping_methods',
                nonce: awcomOrderVars.nonce,
                customer_id: $('input[name="customer_id"]').val(),
                items: items
            },
            success: function(response) {
                var html = '';

                if (response && response.methods && response.methods.length > 0) {
                    html += '<ul class="shipping-method-list">';
                    response.methods.forEach(function(method) {
                        html += '<li>';
                        html += '<label>';
                        html += '<input type="radio" name="shipping_method" value="' + method.id + '" data-cost="' + method.cost + '">';
                        html += method.label + ' (' + method.formatted_cost + ')';
                        html += '</label>';
                        html += '</li>';
                    });
                    html += '</ul>';
                } else {
                    html = '<p class="no-shipping">' + awcomOrderVars.i18n.no_shipping + '</p>';
                }

                $('#shipping-methods').html(html);

                // Restore previously selected shipping method if it's still available
                if (response && response.methods && response.methods.length > 0) {
                    var methodRestored = false;
                    
                    if (previouslySelected) {
                        // Try to restore the previously selected method
                        var matchingMethod = $('#shipping-methods input[name="shipping_method"][value="' + previouslySelected + '"]');
                        if (matchingMethod.length > 0) {
                            matchingMethod.prop('checked', true);
                            methodRestored = true;
                        }
                    }
                    
                    // If no previous selection or it's no longer available, select the first method
                    if (!methodRestored) {
                        $('#shipping-methods input[type="radio"]:first').prop('checked', true);
                    }
                    
                    updateOrderTotals();
                }
            },
            error: function() {
                $('#shipping-methods').html('<p class="error">' + awcomOrderVars.i18n.shipping_error + '</p>');
            }
        });
    }

    // ---------------
    // Initial Setup
    // ---------------

    // Initialize shipping methods if there are items in the order
    if ($('.awcom-order-items tbody tr').length > 0) {
        updateShippingMethods();
    }

    // Form submission handling
    $('#awcom-create-order-form').on('submit', function(e) {
        if ($('.awcom-order-items tbody tr').length === 0) {
            e.preventDefault();
            alert(awcomOrderVars.i18n.no_items || 'Please add at least one item to the order.');
            return false;
        }

        if ($('input[name="shipping_method"]:checked').length === 0) {
            e.preventDefault();
            alert(awcomOrderVars.i18n.no_shipping_selected || 'Please select a shipping method.');
            return false;
        }
    });

}); // End jQuery wrapper