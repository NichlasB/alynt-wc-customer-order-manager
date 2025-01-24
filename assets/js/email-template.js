jQuery(function($) {
    var $modal = $('#email-template-modal');

    $('#edit-email-template').on('click', function() {
        $modal.dialog({
            modal: true,
            width: '80%',
            maxWidth: 800,
            closeOnEscape: true,
            draggable: false,
            resizable: false,
            title: 'Edit Email Template',
            // Ensure proper z-index for WordPress admin
            create: function() {
                $(this).css("maxWidth", "800px");
            },
            open: function() {
                $('.ui-widget-overlay').on('click', function() {
                    $modal.dialog('close');
                });
            }
        });

        // Initialize or refresh TinyMCE if needed
        if (typeof tinyMCE !== 'undefined') {
            if (tinyMCE.get('login_email_template')) {
                tinyMCE.execCommand('mceRemoveEditor', false, 'login_email_template');
            }
            tinyMCE.execCommand('mceAddEditor', false, 'login_email_template');
        }
    });

    $('.cancel-edit').on('click', function() {
        $modal.dialog('close');
    });

    $('.save-template').on('click', function() {
        var content;
        var $saveButton = $(this);

        try {
            // Check if we're in Visual or Text mode
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('login_email_template') && !tinyMCE.get('login_email_template').isHidden()) {
                content = tinyMCE.get('login_email_template').getContent();
            } else {
                content = $('#login_email_template').val();
            }

            if (!content) {
                alert('Please enter some content for the email template.');
                return;
            }

            // Disable the save button and show loading state
            $saveButton.prop('disabled', true).addClass('loading');

            $.ajax({
                url: cmEmailVars.ajaxurl,
                type: 'POST',
                data: {
                    action: 'save_login_email_template',
                    nonce: cmEmailVars.nonce,
                    template: content
                },
                success: function(response) {
                    if (response.success) {
                        alert('Template saved successfully');
                        $modal.dialog('close');
                    } else {
                        var errorMessage = response.data && typeof response.data === 'string' 
                            ? response.data 
                            : 'Unknown error occurred while saving the template';
                        alert('Error saving template: ' + errorMessage);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', {xhr: xhr, status: status, error: error});
                    var errorMessage = 'Server error occurred while saving the template';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    alert('Error saving template: ' + errorMessage);
                },
                complete: function() {
                    // Remove loading state and re-enable the save button
                    $saveButton.prop('disabled', false).removeClass('loading');
                }
            });

        } catch (e) {
            console.error('Template save error:', e);
            alert('An error occurred while preparing to save the template: ' + e.message);
            $saveButton.prop('disabled', false).removeClass('loading');
        }
    });

    // Handle merge tag insertion if needed
    if (typeof cmEmailVars !== 'undefined' && cmEmailVars.mergeTags) {
        var $mergeTagsSelect = $('<select>', {
            class: 'merge-tags-select',
            style: 'margin-bottom: 10px;'
        }).append($('<option>', {
            value: '',
            text: 'Insert Merge Tag...'
        }));

        // Add merge tags to select
        $.each(cmEmailVars.mergeTags, function(tag, description) {
            $mergeTagsSelect.append($('<option>', {
                value: tag,
                text: description + ' (' + tag + ')'
            }));
        });

        // Insert select before editor
        $('#wp-login_email_template-wrap').before($mergeTagsSelect);

        // Handle merge tag insertion
        $mergeTagsSelect.on('change', function() {
            var tag = $(this).val();
            if (!tag) return;

            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('login_email_template') && !tinyMCE.get('login_email_template').isHidden()) {
                tinyMCE.get('login_email_template').execCommand('mceInsertContent', false, tag);
            } else {
                var $textarea = $('#login_email_template');
                var pos = $textarea[0].selectionStart;
                var content = $textarea.val();
                $textarea.val(content.substring(0, pos) + tag + content.substring(pos));
            }

            $(this).val('');
        });
    }
});