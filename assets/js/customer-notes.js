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
                    // Get the current highest note index and increment it
                    var highestIndex = -1;
                    $('.customer-note').each(function() {
                        var index = parseInt($(this).data('note-index'));
                        if (index > highestIndex) {
                            highestIndex = index;
                        }
                    });

                    // Update all existing note indices since we're adding to the beginning
                    $('.customer-note').each(function() {
                        var currentIndex = parseInt($(this).data('note-index'));
                        $(this).attr('data-note-index', currentIndex + 1);
                    });

                    // Create new note HTML with index 0 (newest first)
                    var noteHtml = '<div class="customer-note" data-note-index="0">' +
                        '<div class="note-content">' + response.data.content + '</div>' +
                        '<div class="note-actions">' +
                        '<button type="button" class="button button-small edit-note"><span class="dashicons dashicons-edit" aria-hidden="true"></span> ' + i18n.edit_label + '</button> ' +
                        '<button type="button" class="button button-small delete-note"><span class="dashicons dashicons-trash" aria-hidden="true"></span> ' + i18n.delete_label + '</button>' +
                        '</div>' +
                        '<div class="note-meta">' + formatString(i18n.note_meta, response.data.author, response.data.date) + '</div>' +
                        '</div>';

                    // Add new note to the top of the list
                    $('.customer-notes-list').prepend(noteHtml);
                    textarea.val('');

                    // Remove "No notes found" message if it exists
                    $('.customer-notes-list > p').remove();
                } else {
                    var addErrorMessage = getResponseMessage(response);
                    alert(addErrorMessage ? formatString(i18n.add_error, addErrorMessage) : i18n.add_error_generic);
                }
            },
            error: function() {
                alert(i18n.add_error_generic);
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
        var noteIndex = noteDiv.data('note-index');

        // Create edit form
        var editForm = $('<div class="edit-note-form">' +
            '<textarea class="edit-note-textarea">' + noteContent + '</textarea>' +
            '<div class="edit-note-actions">' +
            '<button type="button" class="button button-primary save-note">' + i18n.save_label + '</button> ' +
            '<button type="button" class="button cancel-edit">' + i18n.cancel_label + '</button>' +
            '</div>' +
            '</div>');

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
                beforeSend: function() {
                    noteDiv.addClass('is-busy').attr('aria-busy', 'true');
                    $saveButton.prop('disabled', true).attr('aria-busy', 'true');
                    $cancelButton.prop('disabled', true);
                },
                data: {
                    action: 'awcom_edit_customer_note',
                    customer_id: awcomCustomerNotes.customer_id,
                    note_index: noteIndex,
                    note: newContent,
                    nonce: awcomCustomerNotes.nonce
                },
                success: function(response) {
                    if (response.success) {
                        noteDiv.find('.note-content').text(newContent).show();
                        editForm.remove();
                        noteDiv.find('.note-actions').show();
                    } else {
                        var updateErrorMessage = getResponseMessage(response);
                        alert(updateErrorMessage ? formatString(i18n.update_error, updateErrorMessage) : i18n.update_error_generic);
                    }
                },
                error: function() {
                    alert(i18n.update_error_generic);
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
        var noteIndex = noteDiv.data('note-index');

        $.ajax({
            url: awcomCustomerNotes.ajaxurl,
            type: 'POST',
            beforeSend: function() {
                noteDiv.addClass('is-busy').attr('aria-busy', 'true');
                noteDiv.find('button').prop('disabled', true);
                $deleteButton.attr('aria-busy', 'true');
            },
            data: {
                action: 'awcom_delete_customer_note',
                customer_id: awcomCustomerNotes.customer_id,
                note_index: noteIndex,
                nonce: awcomCustomerNotes.nonce
            },
            success: function(response) {
                if (response.success) {
                    noteDiv.fadeOut(300, function() {
                        $(this).remove();
                        // Show "No notes found" if this was the last note
                        if ($('.customer-note').length === 0) {
                            $('.customer-notes-list').html('<p>' + i18n.no_notes + '</p>');
                        }
                    });
                } else {
                    var deleteErrorMessage = getResponseMessage(response);
                    alert(deleteErrorMessage ? formatString(i18n.delete_error, deleteErrorMessage) : i18n.delete_error_generic);
                }
            },
            error: function() {
                alert(i18n.delete_error_generic);
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
});