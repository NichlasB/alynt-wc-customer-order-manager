jQuery(function($) {
    var $modal = $('#email-template-modal');
    var $trigger = $('#edit-email-template');
    var $feedback = $('#awcom-email-template-feedback');
    var i18n = awcomEmailVars.i18n || {};
    var initialContent = '';
    var isSaving = false;
    var isDiscardingChanges = false;

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

    function clearFeedback() {
        $feedback.attr('hidden', true).removeClass('notice notice-error notice-success').empty();
        $('#login_email_template').removeAttr('aria-invalid');
    }

    function showFeedback(type, message) {
        $feedback
            .removeAttr('hidden')
            .removeClass('notice-error notice-success')
            .addClass('notice ' + (type === 'success' ? 'notice-success' : 'notice-error'))
            .attr('role', type === 'success' ? 'status' : 'alert')
            .attr('tabindex', '-1')
            .html('<p></p>')
            .find('p')
            .text(message);

        $feedback.trigger('focus');
    }

    function getDiscardDialog() {
        var $dialog = $('#awcom-email-template-discard-dialog');

        if ($dialog.length) {
            return $dialog;
        }

        $dialog = $('<div></div>', {
            id: 'awcom-email-template-discard-dialog',
            class: 'awcom-delete-dialog'
        }).append($('<p></p>'));

        $('body').append($dialog);

        return $dialog;
    }

    function openDiscardDialog(onConfirm) {
        var $dialog = getDiscardDialog();

        $dialog.find('p').text(i18n.unsaved_changes);
        $dialog.dialog({
            modal: true,
            width: 420,
            minHeight: 0,
            dialogClass: 'wp-dialog awcom-confirm-dialog',
            title: i18n.discard_title,
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
                    text: i18n.discard_action,
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

    function requestClose() {
        if (isSaving) {
            return;
        }

        if (!hasUnsavedChanges()) {
            isDiscardingChanges = true;
            $modal.dialog('close');
            return;
        }

        openDiscardDialog(function() {
            isDiscardingChanges = true;
            $modal.dialog('close');
        });
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

                if (isDiscardingChanges || !hasUnsavedChanges()) {
                    return true;
                }

                openDiscardDialog(function() {
                    isDiscardingChanges = true;
                    $modal.dialog('close');
                });

                return false;
            },
            create: function() {
                $(this).css('maxWidth', '800px');
            },
            open: function() {
                clearFeedback();
                $trigger.attr('aria-expanded', 'true');
                window.setTimeout(rememberInitialContent, 0);
                $('.ui-widget-overlay')
                    .off('click.awcomEmailTemplate')
                    .on('click.awcomEmailTemplate', function() {
                        $modal.dialog('close');
                    });
            },
            close: function() {
                clearFeedback();
                $modal.removeAttr('aria-busy');
                $trigger.attr('aria-expanded', 'false');
                isDiscardingChanges = false;
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
        requestClose();
    });

    $('.save-template').on('click', function() {
        var content;
        var $saveButton = $(this);
        var originalButtonText = $saveButton.data('originalText') || $saveButton.text();

        try {
            content = getEditorContent();
            clearFeedback();

            if (!content) {
                $('#login_email_template').attr('aria-invalid', 'true').trigger('focus');
                showFeedback('error', i18n.empty_content);
                return;
            }

            isSaving = true;
            $modal.attr('aria-busy', 'true');
            $saveButton
                .data('originalText', originalButtonText)
                .prop('disabled', true)
                .attr('aria-disabled', 'true')
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
                        showFeedback('success', i18n.save_success);
                    } else {
                        var errorMessage = response && response.data && response.data.message
                            ? response.data.message
                            : (response && typeof response.data === 'string' ? response.data : i18n.unknown_error);

                        showFeedback('error', formatString(i18n.save_error, errorMessage));
                    }
                },
                error: function(xhr) {
                    var errorMessage = i18n.server_error;

                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.data && typeof xhr.responseJSON.data.message === 'string') {
                            errorMessage = xhr.responseJSON.data.message;
                        } else if (typeof xhr.responseJSON.data === 'string') {
                            errorMessage = xhr.responseJSON.data;
                        } else if (xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                    }

                    showFeedback('error', formatString(i18n.save_error, errorMessage));
                },
                complete: function() {
                    isSaving = false;
                    $modal.removeAttr('aria-busy');
                    $saveButton
                        .prop('disabled', false)
                        .removeAttr('aria-disabled')
                        .removeClass('loading')
                        .text($saveButton.data('originalText') || originalButtonText);
                }
            });
        } catch (e) {
            isSaving = false;
            $modal.removeAttr('aria-busy');
            showFeedback('error', formatString(i18n.prepare_error, e.message));
            $saveButton
                .prop('disabled', false)
                .removeAttr('aria-disabled')
                .removeClass('loading')
                .text($saveButton.data('originalText') || originalButtonText);
        }
    });

    if (typeof awcomEmailVars !== 'undefined' && awcomEmailVars.mergeTags) {
        var $mergeTagsSelect = $('<select>', {
            class: 'merge-tags-select'
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