/**
 * BuddyBoss Custom Tabs - Frontend JavaScript
 */
jQuery(document).ready(function($) {
    // Tab management variables
    let isEditing = false;
    let currentTabId = 0;
    let editor;
    
    // Initialize TinyMCE editor if it exists
    if (typeof wp !== 'undefined' && wp.editor) {
        // Wait for DOM to be ready
        setTimeout(function() {
            if ($('#tab-content').length) {
                wp.editor.initialize('tab-content', {
                    tinymce: {
                        wpautop: true,
                        plugins: 'lists,paste,tabfocus,wplink,wordpress',
                        toolbar1: 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,fullscreen,wp_adv',
                        toolbar2: 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
                        height: 300
                    },
                    quicktags: true,
                    mediaButtons: true
                });
                
                editor = tinyMCE.get('tab-content');
            }
        }, 500);
    }
    
    // Show add tab form
    $('#bb-add-new-tab').on('click', function(e) {
        e.preventDefault();
        
        isEditing = false;
        currentTabId = 0;
        
        // Update form title
        $('#tab-form-title').text(bbCustomTabs.i18n.addTabTitle);
        
        // Clear form fields
        $('#tab-id').val('');
        $('#tab-title').val('');
        $('#tab-slug').val('');
        
        if (editor) {
            editor.setContent('');
        } else {
            $('#tab-content').val('');
        }
        
        // Show form
        $('#bb-tab-form').slideDown();
    });
    
    // Edit tab
    $(document).on('click', '.edit-tab', function(e) {
        e.preventDefault();
        
        isEditing = true;
        currentTabId = $(this).data('id');
        
        // Update form title
        $('#tab-form-title').text(bbCustomTabs.i18n.editTabTitle);
        
        // Fetch tab data via AJAX
        $.ajax({
            url: bbCustomTabs.ajaxUrl,
            type: 'POST',
            data: {
                action: 'bb_custom_tabs_get',
                tab_id: currentTabId,
                nonce: bbCustomTabs.nonce
            },
            beforeSend: function() {
                // Show loading indicator
            },
            success: function(response) {
                if (response.success) {
                    const tab = response.data.tab;
                    
                    // Populate form
                    $('#tab-id').val(tab.id);
                    $('#tab-title').val(tab.title);
                    $('#tab-slug').val(tab.slug);
                    
                    if (editor) {
                        editor.setContent(tab.content);
                    } else {
                        $('#tab-content').val(tab.content);
                    }
                    
                    // Show form
                    $('#bb-tab-form').slideDown();
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                showMessage(bbCustomTabs.i18n.error, 'error');
            }
        });
    });
    
    // Cancel tab edit/add
    $('#bb-cancel-tab').on('click', function(e) {
        e.preventDefault();
        $('#bb-tab-form').slideUp();
    });
    
    // Save tab
    $('#bb-save-tab').on('click', function(e) {
        e.preventDefault();
        
        const title = $('#tab-title').val();
        const slug = $('#tab-slug').val();
        let content;
        
        if (editor) {
            content = editor.getContent();
        } else {
            content = $('#tab-content').val();
        }
        
        // Validate
        if (!title || !slug) {
            showMessage(bbCustomTabs.i18n.missingFields, 'error');
            return;
        }
        
        // Determine action based on whether we're editing or adding
        const action = isEditing ? 'bb_custom_tabs_edit' : 'bb_custom_tabs_add';
        
        // Save via AJAX
        $.ajax({
            url: bbCustomTabs.ajaxUrl,
            type: 'POST',
            data: {
                action: action,
                nonce: bbCustomTabs.nonce,
                group_id: $('#group-id').val(),
                tab_id: currentTabId,
                title: title,
                slug: slug,
                content: content
            },
            beforeSend: function() {
                // Show loading indicator
                $('#bb-save-tab').prop('disabled', true).text(bbCustomTabs.i18n.saving);
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showMessage(response.data.message, 'success');
                    
                    // Hide form
                    $('#bb-tab-form').slideUp();
                    
                    // Reload page to see changes
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    showMessage(response.data.message, 'error');
                    $('#bb-save-tab').prop('disabled', false).text(bbCustomTabs.i18n.saveTab);
                }
            },
            error: function() {
                showMessage(bbCustomTabs.i18n.error, 'error');
                $('#bb-save-tab').prop('disabled', false).text(bbCustomTabs.i18n.saveTab);
            }
        });
    });
    
    // Delete tab
    $(document).on('click', '.delete-tab', function(e) {
        e.preventDefault();
        
        if (!confirm(bbCustomTabs.i18n.confirmDelete)) {
            return;
        }
        
        const tabId = $(this).data('id');
        const $tabRow = $(this).closest('.bb-custom-tab-item');
        
        // Delete via AJAX
        $.ajax({
            url: bbCustomTabs.ajaxUrl,
            type: 'POST',
            data: {
                action: 'bb_custom_tabs_delete',
                nonce: bbCustomTabs.nonce,
                tab_id: tabId
            },
            beforeSend: function() {
                // Show loading indicator
                $tabRow.addClass('deleting');
            },
            success: function(response) {
                if (response.success) {
                    // Remove tab from list
                    $tabRow.fadeOut(function() {
                        $(this).remove();
                        
                        // Show "no tabs" message if there are no more tabs
                        if ($('.bb-custom-tab-item').length === 0) {
                            $('.bb-custom-tabs-list').html('<p class="no-tabs">' + bbCustomTabs.i18n.noTabs + '</p>');
                        }
                    });
                    
                    // Show success message
                    showMessage(response.data.message, 'success');
                    
                    // Reload page after short delay
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    $tabRow.removeClass('deleting');
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                $tabRow.removeClass('deleting');
                showMessage(bbCustomTabs.i18n.error, 'error');
            }
        });
    });
    
    // Slug generator from title
    $('#tab-title').on('blur', function() {
        const title = $(this).val();
        const $slug = $('#tab-slug');
        
        // Only auto-generate slug if empty
        if (title && !$slug.val()) {
            // Convert to lowercase, replace spaces with hyphens, remove other special chars
            const slug = title.toLowerCase()
                .replace(/\s+/g, '-')
                .replace(/[^\w\-]+/g, '')
                .replace(/\-\-+/g, '-')
                .replace(/^-+/, '')
                .replace(/-+$/, '');
                
            $slug.val(slug);
        }
    });
    
    // Helper function to show messages
    function showMessage(message, type) {
        const $response = $('#bb-tab-response');
        
        // Set message
        $response.html(message);
        
        // Set class based on message type
        if (type === 'error') {
            $response.removeClass('updated').addClass('error');
        } else {
            $response.removeClass('error').addClass('updated');
        }
        
        // Show message
        $response.fadeIn();
        
        // Hide message after delay
        setTimeout(function() {
            $response.fadeOut();
        }, 3000);
    }
});
