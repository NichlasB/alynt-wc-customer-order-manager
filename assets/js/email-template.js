jQuery(function($) {
    var $modal = $('#email-template-modal');
    var $trigger = $('#edit-email-template');
    var i18n = awcomEmailVars.i18n || {};
    var initialContent = '';
    var isSaving = false;

    function formatString(template, value) {
        return String(template || '').replace('%s', value);
    }

    function getEditorContent() {
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('login_email_template') && !tinyMCE.get('login_email_template').isHidden()) {
            return tinyMCE.get('login_email_template').getContent();
        }

        return $('#login_email_template').val();
    }

    function rememberInitialContent() {
        initialContent = String(getEditorContent() || '');
    }

    function hasUnsavedChanges() {
        return String(getEditorContent() || '') !== initialContent;
    }

    $trigger.on('click', function() {
        $modal.dialog({
            modal: true,
            width: '80%',
            maxWidth: 800,
            closeOnEscape: true,
            draggable: false,
            resizable: false,
            title: i18n.dialog_title,
            beforeClose: function() {
                if (isSaving) {
                    return false;
                }

                if (!hasUnsavedChanges()) {
                    return true;
                }

                return window.confirm(i18n.unsaved_changes);
            },
            create: function() {
                $(this).css('maxWidth', '800px');
            },
            open: function() {
                $trigger.attr('aria-expanded', 'true');
                window.setTimeout(rememberInitialContent, 0);
                $('.ui-widget-overlay')
                    .off('click.awcomEmailTemplate')
                    .on('click.awcomEmailTemplate', function() {
                        $modal.dialog('close');
                    });
            },
            close: function() {
                $modal.removeAttr('aria-busy');
                $trigger.attr('aria-expanded', 'false');
                $trigger.trigger('focus');
            }
        });

        if (typeof tinyMCE !== 'undefined') {
            if (tinyMCE.get('login_email_template')) {
                tinyMCE.execCommand('mceRemoveEditor', false, 'login_email_template');
            }
            tinyMCE.execCommand('mceAddEditor', false, 'login_email_template');
        }

        window.setTimeout(rememberInitialContent, 100);
    });

    $('.cancel-edit').on('click', function() {
        $modal.dialog('close');
    });

    $('.save-template').on('click', function() {
        var content;
        var $saveButton = $(this);
        var originalButtonText = $saveButton.data('originalText') || $saveButton.text();

        try {
            content = getEditorContent();

            if (!content) {
                alert(i18n.empty_content);
                return;
            }

            isSaving = true;
            $modal.attr('aria-busy', 'true');
            $saveButton
                .data('originalText', originalButtonText)
                .prop('disabled', true)
                .addClass('loading')
                .text(i18n.saving);

            $.ajax({
                url: awcomEmailVars.ajaxurl,
                type: 'POST',
                data: {
                    action: 'awcom_save_login_email_template',
                    nonce: awcomEmailVars.nonce,
                    template: content
                },
                success: function(response) {
                    if (response.success) {
                        initialContent = String(content || '');
                        isSaving = false;
                        alert(i18n.save_success);
                        $modal.dialog('close');
                    } else {
                        var errorMessage = response.data && typeof response.data === 'string'
                            ? response.data
                            : i18n.unknown_error;
                        alert(formatString(i18n.save_error, errorMessage));
                    }
                },
                error: function(xhr) {
                    var errorMessage = i18n.server_error;
                    if (xhr.responseJSON) {
                        if (typeof xhr.responseJSON.data === 'string') {
                            errorMessage = xhr.responseJSON.data;
                        } else if (xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                    }
                    alert(formatString(i18n.save_error, errorMessage));
                },
                complete: function() {
                    isSaving = false;
                    $modal.removeAttr('aria-busy');
                    $saveButton
                        .prop('disabled', false)
                        .removeClass('loading')
                        .text($saveButton.data('originalText') || originalButtonText);
                }
            });
        } catch (e) {
            isSaving = false;
            $modal.removeAttr('aria-busy');
            alert(formatString(i18n.prepare_error, e.message));
            $saveButton
                .prop('disabled', false)
                .removeClass('loading')
                .text($saveButton.data('originalText') || originalButtonText);
        }
    });

    if (typeof awcomEmailVars !== 'undefined' && awcomEmailVars.mergeTags) {
        var $mergeTagsSelect = $('<select>', {
            class: 'merge-tags-select',
            style: 'margin-bottom: 10px;'
        }).append($('<option>', {
            value: '',
            text: i18n.insert_merge_tag
        }));

        $.each(awcomEmailVars.mergeTags, function(tag, description) {
            $mergeTagsSelect.append($('<option>', {
                value: tag,
                text: description + ' (' + tag + ')'
            }));
        });

        $('#wp-login_email_template-wrap').before($mergeTagsSelect);

        $mergeTagsSelect.on('change', function() {
            var tag = $(this).val();
            if (!tag) {
                return;
            }

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