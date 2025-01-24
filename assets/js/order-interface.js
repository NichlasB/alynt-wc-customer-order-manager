jQuery(function($) {
    // ---------------
    // Initialization
    // ---------------

    $('<style>')
    .text(`
        .cm-order-items .original-price del {
            color: #999;
            margin-right: 5px;
        }
        .cm-order-items .price-discount {
            color: #4CAF50;
            font-size: 0.9em;
            margin-left: 5px;
        }
        `)
    .appendTo('head');

    // Initialize Select2 for product search
    $('#cm-add-product').select2({
        ajax: {
            url: cmOrderVars.ajaxurl,
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    term: params.term,
                    action: 'cm_search_products',
                    nonce: cmOrderVars.nonce,
                customer_id: jQuery('input[name="customer_id"]').val() // Add this line
            };
        },
        processResults: function(data) {
            return {
                results: data
            };
        },
        cache: true
    },
    minimumInputLength: 2,
    placeholder: cmOrderVars.i18n.search_products,
    allowClear: true,
    width: '100%',
    dropdownParent: $('.cm-product-search')
});

    // ---------------
    // Event Handlers
    // ---------------

    // Handle product selection
    $('#cm-add-product').on('select2:select', function(e) {
        var product = e.params.data;
        addProductToOrder(product);
        $(this).val(null).trigger('change');
        updateOrderTotals();
        updateShippingMethods();
    });

    // Handle quantity changes
    $(document).on('change keyup', '.cm-order-items input.quantity', function() {
        updateLineTotal($(this).closest('tr'));
        updateOrderTotals();
        updateShippingMethods();
    });

    // Handle remove item
    $(document).on('click', '.cm-order-items .remove-item', function(e) {
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

        row.append('<td class="product-name">' + product.text + '</td>');
        row.append('<td class="quantity"><input type="number" name="items[' + product.id + '][quantity]" class="quantity" value="1" min="1"></td>');
        row.append('<td class="price">' + priceDisplay + '</td>');
        row.append('<td class="total">' + product.formatted_price + '</td>');
        row.append('<td class="actions"><a href="#" class="remove-item" title="' + cmOrderVars.i18n.remove_item + '">×</a></td>');

        $('.cm-order-items tbody').append(row);
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
        $('.cm-order-items tbody tr').each(function() {
            subtotal += updateLineTotal($(this));
        });

        // Get selected shipping cost
        var shippingCost = 0;
        var selectedShipping = $('input[name="shipping_method"]:checked');
        if (selectedShipping.length) {
            shippingCost = parseFloat(selectedShipping.data('cost')) || 0;
        }

        // Update displayed totals
        $('.cm-order-items .subtotal').text(formatPrice(subtotal));
        $('.shipping-total').text(formatPrice(shippingCost));
        $('.order-total').text(formatPrice(subtotal + shippingCost));
    }

    function formatPrice(price) {
        return accounting.formatMoney(price, {
            symbol: cmOrderVars.currency_symbol,
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
        $('.cm-order-items tbody tr').each(function() {
            items.push({
                product_id: $(this).data('product-id'),
                quantity: $(this).find('input.quantity').val()
            });
        });

        $('#shipping-methods').html('<p class="loading">' + cmOrderVars.i18n.calculating + '</p>');

        $.ajax({
            url: cmOrderVars.ajaxurl,
            type: 'POST',
            data: {
                action: 'cm_get_shipping_methods',
                nonce: cmOrderVars.nonce,
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
                    html = '<p class="no-shipping">' + cmOrderVars.i18n.no_shipping + '</p>';
                }

                $('#shipping-methods').html(html);

                // Select first shipping method by default if available
                if (response && response.methods && response.methods.length > 0) {
                    $('#shipping-methods input[type="radio"]:first').prop('checked', true);
                    updateOrderTotals();
                }
            },
            error: function() {
                $('#shipping-methods').html('<p class="error">' + cmOrderVars.i18n.shipping_error + '</p>');
            }
        });
    }

    // ---------------
    // Initial Setup
    // ---------------

    // Initialize shipping methods if there are items in the order
    if ($('.cm-order-items tbody tr').length > 0) {
        updateShippingMethods();
    }

    // Form submission handling
    $('#cm-create-order-form').on('submit', function(e) {
        if ($('.cm-order-items tbody tr').length === 0) {
            e.preventDefault();
            alert(cmOrderVars.i18n.no_items || 'Please add at least one item to the order.');
            return false;
        }

        if ($('input[name="shipping_method"]:checked').length === 0) {
            e.preventDefault();
            alert(cmOrderVars.i18n.no_shipping_selected || 'Please select a shipping method.');
            return false;
        }
    });

}); // End jQuery wrapper