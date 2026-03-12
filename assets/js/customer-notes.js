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

    function showOperationError(messageTemplate, genericMessage, detailMessage) {
        alert(detailMessage ? formatString(messageTemplate, detailMessage) : genericMessage);
    }

    function getNoteId(noteDiv) {
        return String(noteDiv.data('note-id') || '');
    }

    function buildNoteElement(noteData) {
        var actions = $('<div></div>', { 'class': 'note-actions' });
        var editButton = $('<button></button>', {
            type: 'button',
            'class': 'button button-small edit-note'
        });
        var deleteButton = $('<button></button>', {
            type: 'button',
            'class': 'button button-small delete-note'
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
            .append(deleteButton);

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
        var textarea = button.closest('.add-note').find('textarea');
        var customerId = button.data('customer-id');
        var noteContent = textarea.val().trim();

        if (!noteContent) {
            alert(i18n.empty_note);
            return;
        }

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
                button.prop('disabled', true).attr('aria-busy', 'true');
            },
            success: function(response) {
                if (response.success) {
                    $('.customer-notes-list').prepend(buildNoteElement(response.data));
                    textarea.val('');

                    $('.customer-notes-list > p').remove();
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
                button.prop('disabled', false).removeAttr('aria-busy');
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

        // Handle save
        editForm.find('.save-note').on('click', function() {
            var newContent = editForm.find('textarea').val().trim();
            var $saveButton = $(this);
            var $cancelButton = editForm.find('.cancel-edit');
            if (!newContent) {
                alert(i18n.empty_note);
                return;
            }

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
                    noteDiv.addClass('is-busy').attr('aria-busy', 'true');
                    $saveButton.prop('disabled', true).attr('aria-busy', 'true');
                    $cancelButton.prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        noteDiv.find('.note-content').text(newContent).show();
                        editForm.remove();
                        noteDiv.find('.note-actions').show();
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
                    noteDiv.removeClass('is-busy').removeAttr('aria-busy');
                    $saveButton.prop('disabled', false).removeAttr('aria-busy');
                    $cancelButton.prop('disabled', false);
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

        if (!confirm(i18n.confirm_delete)) {
            return;
        }

        var noteDiv = $(this).closest('.customer-note');
        var noteId = getNoteId(noteDiv);

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
                noteDiv.addClass('is-busy').attr('aria-busy', 'true');
                noteDiv.find('button').prop('disabled', true);
                $deleteButton.attr('aria-busy', 'true');
            },
            success: function(response) {
                if (response.success) {
                    noteDiv.fadeOut(300, function() {
                        $(this).remove();
                        if ($('.customer-note').length === 0) {
                            $('.customer-notes-list').html('<p>' + i18n.no_notes + '</p>');
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
                    noteDiv.removeClass('is-busy').removeAttr('aria-busy');
                    noteDiv.find('button').prop('disabled', false);
                }
                $deleteButton.removeAttr('aria-busy');
            }
        });
    });

    $('#same_as_billing').on('change', toggleShippingFields);
    toggleShippingFields();
});