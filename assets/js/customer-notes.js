jQuery(document).ready(function($) {
    // Add note functionality
    $('.add-note-button').on('click', function() {
        var button = $(this);
        var textarea = button.closest('.add-note').find('textarea');
        var customerId = button.data('customer-id');
        var noteContent = textarea.val().trim();

        if (!noteContent) {
            alert('Please enter a note');
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'awcom_add_customer_note',
                customer_id: customerId,
                note: noteContent,
                nonce: awcomCustomerNotes.nonce
            },
            beforeSend: function() {
                button.prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Create new note HTML
                    var noteHtml = '<div class="customer-note" data-note-index="0">' +
                        '<div class="note-content">' + response.data.content + '</div>' +
                        '<div class="note-actions">' +
                        '<button type="button" class="button button-small edit-note"><span class="dashicons dashicons-edit"></span> Edit</button> ' +
                        '<button type="button" class="button button-small delete-note"><span class="dashicons dashicons-trash"></span> Delete</button>' +
                        '</div>' +
                        '<div class="note-meta">By ' + response.data.author + ' on ' + response.data.date + '</div>' +
                        '</div>';

                    // Add new note to the top of the list
                    $('.customer-notes-list').prepend(noteHtml);
                    textarea.val('');

                    // Remove "No notes found" message if it exists
                    $('.customer-notes-list > p').remove();
                } else {
                    alert('Error adding note: ' + response.data.message);
                }
            },
            error: function() {
                alert('Error adding note. Please try again.');
            },
            complete: function() {
                button.prop('disabled', false);
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
            '<button type="button" class="button button-primary save-note">Save</button> ' +
            '<button type="button" class="button cancel-edit">Cancel</button>' +
            '</div>' +
            '</div>');

        // Replace note content with edit form
        noteDiv.find('.note-content').hide().after(editForm);
        noteDiv.find('.note-actions').hide();

        // Handle save
        editForm.find('.save-note').on('click', function() {
            var newContent = editForm.find('textarea').val().trim();
            if (!newContent) {
                alert('Please enter a note');
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
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
                        alert('Error updating note: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Error updating note. Please try again.');
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
        if (!confirm(awcomCustomerNotes.confirm_delete)) {
            return;
        }

        var noteDiv = $(this).closest('.customer-note');
        var noteIndex = noteDiv.data('note-index');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
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
                            $('.customer-notes-list').html('<p>No notes found.</p>');
                        }
                    });
                } else {
                    alert('Error deleting note: ' + response.data.message);
                }
            },
            error: function() {
                alert('Error deleting note. Please try again.');
            }
        });
    });
});