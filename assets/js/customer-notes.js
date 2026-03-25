jQuery(document).ready(function($) {
    var i18n = awcomCustomerNotes.i18n || {};

    function formatString(template) {
        var replacements = Array.prototype.slice.call(arguments, 1);

        return replacements.reduce(function(result, replacement, index) {
            return String(result)
                .replace('%' + (index + 1) + '$s', replacement)
                .replace('%s', replacement);
        }, String(template || ''));
    }

    function getResponseMessage(response) {
        return response && response.data && response.data.message ? response.data.message : '';
    }

    function getXhrResponseMessage(xhr) {
        return xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
            ? xhr.responseJSON.data.message
            : '';
    }

    function getNotesFeedback() {
        return $('#awcom-notes-feedback');
    }

    function clearNotesFeedback() {
        getNotesFeedback()
            .attr('hidden', true)
            .removeClass('notice notice-error notice-success')
            .removeAttr('role tabindex')
            .empty();
    }

    function showNotesFeedback(type, message) {
        var $feedback = getNotesFeedback();

        if ($feedback.length === 0) {
            return;
        }

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

    function showOperationError(messageTemplate, genericMessage, detailMessage) {
        showNotesFeedback('error', detailMessage ? formatString(messageTemplate, detailMessage) : genericMessage);
    }

    function getNoteId(noteDiv) {
        return String(noteDiv.data('note-id') || '');
    }

    function setDisabledState(elements, isDisabled) {
        var disabled = Boolean(isDisabled);

        elements.prop('disabled', disabled);

        if (disabled) {
            elements.attr('aria-disabled', 'true');
            return;
        }

        elements.removeAttr('aria-disabled');
    }

    function toggleSpinner(spinner, isActive) {
        if (!spinner.length) {
            return;
        }

        spinner.toggleClass('is-active', Boolean(isActive));
    }

    function setNoteBusy(noteDiv, isBusy) {
        noteDiv.toggleClass('is-busy', Boolean(isBusy)).attr('aria-busy', isBusy ? 'true' : 'false');
        toggleSpinner(noteDiv.find('.awcom-note-spinner'), isBusy);
    }

    function renderEmptyState() {
        return $('<div></div>', {
            'class': 'awcom-empty-state awcom-notes-empty-state'
        })
            .append($('<h3></h3>', { text: i18n.no_notes_title }))
            .append($('<p></p>', { text: i18n.no_notes_description }))
            .append($('<a></a>', {
                href: '#customer_note',
                'class': 'button',
                text: i18n.add_first_note
            }));
    }

    function getNotePreview(noteDiv) {
        var preview = $.trim(noteDiv.find('.note-content').text()).replace(/\s+/g, ' ');

        if (preview.length > 80) {
            preview = preview.substring(0, 77) + '...';
        }

        return preview;
    }

    function getDeleteDialog() {
        var $dialog = $('#awcom-note-delete-dialog');

        if ($dialog.length) {
            return $dialog;
        }

        $dialog = $('<div></div>', {
            id: 'awcom-note-delete-dialog',
            class: 'awcom-delete-dialog'
        }).append($('<p></p>'));

        $('body').append($dialog);

        return $dialog;
    }

    function openDeleteDialog(message, onConfirm) {
        var $dialog = getDeleteDialog();

        if (typeof $.fn.dialog !== 'function') {
            if (window.confirm(message)) {
                onConfirm();
            }

            return;
        }

        $dialog.find('p').text(message);
        $dialog.dialog({
            modal: true,
            width: 420,
            minHeight: 0,
            dialogClass: 'wp-dialog awcom-confirm-dialog',
            title: i18n.delete_note_title,
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
                    text: i18n.delete_note_action,
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

    function showAddNoteError(message) {
        var $addNote = $('.add-note');
        var $textarea = $addNote.find('#customer_note');
        var $error = $addNote.find('.awcom-note-inline-error');

        if ($error.length === 0) {
            $error = $('<p></p>', {
                id: 'awcom-customer-note-error',
                'class': 'awcom-note-inline-error description',
                role: 'alert'
            });
            $textarea.after($error);
        }

        $error.text(message);
        $textarea.attr({
            'aria-invalid': 'true',
            'aria-describedby': 'customer-note-description awcom-customer-note-error'
        });
    }

    function clearAddNoteError() {
        $('.add-note .awcom-note-inline-error').remove();
        $('#customer_note')
            .removeAttr('aria-invalid')
            .attr('aria-describedby', 'customer-note-description');
    }

    function showEditNoteError(editForm, noteId, message) {
        var errorId = 'awcom-edit-note-error-' + noteId;
        var $textarea = editForm.find('.edit-note-textarea');
        var $error = editForm.find('.awcom-note-inline-error');

        if ($error.length === 0) {
            $error = $('<p></p>', {
                id: errorId,
                'class': 'awcom-note-inline-error description',
                role: 'alert'
            });
            editForm.append($error);
        }

        $error.text(message);
        $textarea.attr({
            'aria-invalid': 'true',
            'aria-describedby': errorId
        });
    }

    function clearEditNoteError(editForm) {
        editForm.find('.awcom-note-inline-error').remove();
        editForm.find('.edit-note-textarea')
            .removeAttr('aria-invalid')
            .removeAttr('aria-describedby');
    }

    function buildNoteElement(noteData) {
        var actions = $('<div></div>', { 'class': 'note-actions' });
        var editButton = $('<button></button>', {
            type: 'button',
            'class': 'button button-small edit-note'
        });
        var deleteButton = $('<button></button>', {
            type: 'button',
            'class': 'button-link-delete delete-note'
        });
        var spinner = $('<span></span>', {
            'class': 'spinner awcom-note-spinner',
            'aria-hidden': 'true'
        });

        editButton
            .append($('<span></span>', {
                'class': 'dashicons dashicons-edit',
                'aria-hidden': 'true'
            }))
            .append(document.createTextNode(' ' + i18n.edit_label));

        deleteButton
            .append($('<span></span>', {
                'class': 'dashicons dashicons-trash',
                'aria-hidden': 'true'
            }))
            .append(document.createTextNode(' ' + i18n.delete_label));

        actions
            .append(editButton)
            .append(document.createTextNode(' '))
            .append(deleteButton)
            .append(spinner);

        return $('<div></div>', {
            'class': 'customer-note',
            'data-note-id': noteData.id
        })
            .append($('<div></div>', {
                'class': 'note-content',
                text: noteData.content
            }))
            .append(actions)
            .append($('<div></div>', {
                'class': 'note-meta',
                text: formatString(i18n.note_meta, noteData.author, noteData.date)
            }));
    }

    function buildEditForm(noteContent) {
        return $('<div></div>', { 'class': 'edit-note-form' })
            .append($('<textarea></textarea>', {
                'class': 'edit-note-textarea'
            }).val(noteContent))
            .append(
                $('<div></div>', { 'class': 'edit-note-actions' })
                    .append($('<button></button>', {
                        type: 'button',
                        'class': 'button button-primary save-note',
                        text: i18n.save_label
                    }))
                    .append(document.createTextNode(' '))
                    .append($('<button></button>', {
                        type: 'button',
                        'class': 'button cancel-edit',
                        text: i18n.cancel_label
                    }))
            );
    }

    function syncShippingFieldsFromBilling() {
        $('#shipping_address_1').val($('#billing_address_1').val());
        $('#shipping_address_2').val($('#billing_address_2').val());
        $('#shipping_phone').val($('#phone').val());
        $('#shipping_city').val($('#billing_city').val());
        $('#shipping_state').val($('#billing_state').val());
        $('#shipping_postcode').val($('#billing_postcode').val());
        $('#shipping_country').val($('#billing_country').val());
    }

    function toggleShippingFields() {
        if (!$('#same_as_billing').length || !$('#shipping-address-fields').length) {
            return;
        }

        if ($('#same_as_billing').is(':checked')) {
            $('#shipping-address-fields').hide();
            syncShippingFieldsFromBilling();
            return;
        }

        $('#shipping-address-fields').show();
    }

    // Add note functionality
    $('.add-note-button').on('click', function() {
        var button = $(this);
        var $addNote = button.closest('.add-note');
        var textarea = button.closest('.add-note').find('textarea');
        var spinner = $addNote.find('.awcom-add-note-spinner');

        var customerId = button.data('customer-id');
        var noteContent = textarea.val().trim();

        if (!noteContent) {
            textarea.attr('aria-invalid', 'true').trigger('focus');
            showAddNoteError(i18n.empty_note);
            return;
        }

        clearAddNoteError();
        clearNotesFeedback();

        $.ajax({
            url: awcomCustomerNotes.ajaxurl,
            type: 'POST',
            data: {
                action: 'awcom_add_customer_note',
                customer_id: customerId,
                note: noteContent,
                nonce: awcomCustomerNotes.nonce
            },
            beforeSend: function() {
                setDisabledState(button.add(textarea), true);
                button.attr('aria-busy', 'true');
                toggleSpinner(spinner, true);
            },
            success: function(response) {
                if (response.success) {
                    $('.customer-notes-list').prepend(buildNoteElement(response.data));
                    textarea.val('');
                    $('.customer-notes-list .awcom-empty-state').remove();
                    showNotesFeedback('success', i18n.note_added);
                } else {
                    var addErrorMessage = getResponseMessage(response);
                    showOperationError(i18n.add_error, i18n.add_error_generic, addErrorMessage);
                }
            },
            error: function(xhr) {
                var addErrorMessage = getXhrResponseMessage(xhr);
                showOperationError(i18n.add_error, i18n.add_error_generic, addErrorMessage);
            },
            complete: function() {
                setDisabledState(button.add(textarea), false);
                button.removeAttr('aria-busy');
                toggleSpinner(spinner, false);
            }
        });
    });

    // Edit note functionality
    $(document).on('click', '.edit-note', function() {
        var noteDiv = $(this).closest('.customer-note');
        var noteContent = noteDiv.find('.note-content').text();
        var noteId = getNoteId(noteDiv);

        // Create edit form
        var editForm = buildEditForm(noteContent);

        // Replace note content with edit form
        noteDiv.find('.note-content').hide().after(editForm);
        noteDiv.find('.note-actions').hide();
        editForm.find('textarea').trigger('focus');
        editForm.find('.edit-note-textarea').on('input', function() {
            clearEditNoteError(editForm);
        });

        // Handle save
        editForm.find('.save-note').on('click', function() {
            var newContent = editForm.find('textarea').val().trim();
            var $saveButton = $(this);
            var $cancelButton = editForm.find('.cancel-edit');
            if (!newContent) {
                showEditNoteError(editForm, noteId, i18n.empty_note);
                editForm.find('textarea').trigger('focus');
                return;
            }

            clearEditNoteError(editForm);
            clearNotesFeedback();

            $.ajax({
                url: awcomCustomerNotes.ajaxurl,
                type: 'POST',
                data: {
                    action: 'awcom_edit_customer_note',
                    customer_id: awcomCustomerNotes.customer_id,
                    note_id: noteId,
                    note: newContent,
                    nonce: awcomCustomerNotes.nonce
                },
                beforeSend: function() {
                    setNoteBusy(noteDiv, true);
                    setDisabledState($saveButton.add($cancelButton), true);
                    $saveButton.attr('aria-busy', 'true');
                },
                success: function(response) {
                    if (response.success) {
                        noteDiv.find('.note-content').text(newContent).show();
                        editForm.remove();
                        noteDiv.find('.note-actions').show();
                        showNotesFeedback('success', i18n.note_updated);
                    } else {
                        var updateErrorMessage = getResponseMessage(response);
                        showOperationError(i18n.update_error, i18n.update_error_generic, updateErrorMessage);
                    }
                },
                error: function(xhr) {
                    var updateErrorMessage = getXhrResponseMessage(xhr);
                    showOperationError(i18n.update_error, i18n.update_error_generic, updateErrorMessage);
                },
                complete: function() {
                    setNoteBusy(noteDiv, false);
                    setDisabledState($saveButton.add($cancelButton), false);
                    $saveButton.removeAttr('aria-busy');
                }
            });
        });

        // Handle cancel
        editForm.find('.cancel-edit').on('click', function() {
            noteDiv.find('.note-content').show();
            editForm.remove();
            noteDiv.find('.note-actions').show();
        });
    });

    // Delete note functionality
    $(document).on('click', '.delete-note', function() {
        var $deleteButton = $(this);
        var noteDiv = $(this).closest('.customer-note');
        var noteId = getNoteId(noteDiv);

        openDeleteDialog(formatString(i18n.delete_note_message, getNotePreview(noteDiv)), function() {
            clearNotesFeedback();
            $.ajax({
                url: awcomCustomerNotes.ajaxurl,
                type: 'POST',
                data: {
                    action: 'awcom_delete_customer_note',
                    customer_id: awcomCustomerNotes.customer_id,
                    note_id: noteId,
                    nonce: awcomCustomerNotes.nonce
                },
                beforeSend: function() {
                    setNoteBusy(noteDiv, true);
                    setDisabledState(noteDiv.find('button'), true);
                    $deleteButton.attr('aria-busy', 'true');
                },
                success: function(response) {
                    if (response.success) {
                        noteDiv.fadeOut(300, function() {
                            $(this).remove();
                            if ($('.customer-note').length === 0) {
                                $('.customer-notes-list').empty().append(renderEmptyState());
                            }
                        });
                    } else {
                        var deleteErrorMessage = getResponseMessage(response);
                        showOperationError(i18n.delete_error, i18n.delete_error_generic, deleteErrorMessage);
                    }
                },
                error: function(xhr) {
                    var deleteErrorMessage = getXhrResponseMessage(xhr);
                    showOperationError(i18n.delete_error, i18n.delete_error_generic, deleteErrorMessage);
                },
                complete: function() {
                    if ($.contains(document, noteDiv[0])) {
                        setNoteBusy(noteDiv, false);
                        setDisabledState(noteDiv.find('button'), false);
                    }
                    $deleteButton.removeAttr('aria-busy');
                }
            });
        });
    });

    $('#customer_note').on('input', clearAddNoteError);

    $('#same_as_billing').on('change', toggleShippingFields);
    toggleShippingFields();
});