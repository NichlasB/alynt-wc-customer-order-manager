jQuery(document).ready(function($) {
    $('.awcom-copy-payment-link').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var paymentLink = $button.data('payment-link');
        
        // Create a temporary textarea
        var $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(paymentLink).select();
        
        try {
            // Copy the text
            document.execCommand('copy');
            // Show success message
            alert('Payment link copied to clipboard!');
        } catch (err) {
            alert('Failed to copy payment link: ' + err);
        } finally {
            // Clean up
            $temp.remove();
        }
    });
});