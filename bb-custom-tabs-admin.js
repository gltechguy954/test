/**
 * BuddyBoss Custom Tabs - Admin JavaScript
 */
jQuery(document).ready(function($) {
    let isEditing = false;
    let currentTabId = 0;
    let editor;

    // Initialize TinyMCE editor if available
    if (typeof wp !== 'undefined' && wp.editor) {
        setTimeout(function() {
            if ($('#tab-content').length) {
                wp.editor.initialize('tab-content', {
                    tinymce: {
                        wpautop: true,
                        plugins: 'lists,paste,tabfocus,wplink,wordpress',
                        toolbar1: 'formatselect bold italic bullist numlist blockquote alignleft aligncenter alignright link unlink fullscreen wp_adv',
                        toolbar2: 'strikethrough hr removeformat charmap outdent indent undo redo wp_help',
                        height: 300
                    },
                    quicktags: true,
                    mediaButtons: true
                });
                editor = tinyMCE.get('tab-content');
            }
        }, 500);
    }

    // Show Add New Tab form
    $('#bb-add-new-tab').on('click', function(e) {
        e.preventDefault();
        isEditing = false;
        currentTabId = 0;
        $('#tab-id, #tab-title, #tab-slug').val('');
        if (editor) editor.setContent(''); else $('#tab-content').val('');
        $('#bb-tab-form').slideDown();
    });

    // Edit existing tab
    $(document).on('click', '.edit-tab', function(e) {
        e.preventDefault();
        isEditing = true;
        currentTabId = $(this).data('id');

        $.post(bbCustomTabs.ajaxUrl, {
            action: 'bb_custom_tabs_get',
            nonce: bbCustomTabs.nonce,
            tab_id: currentTabId
        }, function(response) {
            if (response.success) {
                $('#tab-id').val(response.data.tab.id);
                $('#tab-title').val(response.data.tab.title);
                $('#tab-slug').val(response.data.tab.slug);
                if (editor) editor.setContent(response.data.tab.content);
                else $('#tab-content').val(response.data.tab.content);
                $('#bb-tab-form').slideDown();
            } else {
                alert(response.data.message);
            }
        });
    });

    // Cancel add/edit
    $('#bb-cancel-tab').on('click', function(e) {
        e.preventDefault();
        $('#bb-tab-form').slideUp();
    });

    // Save tab (add or edit)
    $('#bb-save-tab').on('click', function(e) {
        e.preventDefault();
        const title = $('#tab-title').val().trim();
        const slug = $('#tab-slug').val().trim();
        const content = editor ? editor.getContent() : $('#tab-content').val();

        if (!title || !slug) {
            alert('Title and slug are required.');
            return;
        }

        const action = isEditing ? 'bb_custom_tabs_edit' : 'bb_custom_tabs_add';
        $.post(bbCustomTabs.ajaxUrl, {
            action: action,
            nonce: bbCustomTabs.nonce,
            group_id: $('#group-id').val(),
            tab_id: currentTabId,
            tab_title: title,
            tab_slug: slug,
            tab_content: content
        }, function(response) {
            $('#bb-save-tab').prop('disabled', false);
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message);
            }
        }).fail(function() {
            $('#bb-save-tab').prop('disabled', false);
            alert('An error occurred. Please try again.');
        });

        $('#bb-save-tab').prop('disabled', true);
    });

    // Delete tab
    $(document).on('click', '.delete-tab', function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to delete this tab?')) return;
        const tabId = $(this).data('id');
        $.post(bbCustomTabs.ajaxUrl, {
            action: 'bb_custom_tabs_delete',
            nonce: bbCustomTabs.nonce,
            tab_id: tabId
        }, function(response) {
            if (response.success) location.reload();
            else alert(response.data.message);
        }).fail(function() {
            alert('An error occurred. Please try again.');
        });
    });

    // Auto-generate slug from title
    $('#tab-title').on('blur', function() {
        const title = $(this).val();
        const $slug = $('#tab-slug');
        if (title && !$slug.val()) {
            const slug = title.toLowerCase()
                .trim()
                .replace(/\s+/g, '-')
                .replace(/[^\w-]/g, '')
                .replace(/--+/g, '-')
                .replace(/^-+|-+$/g, '');
            $slug.val(slug);
        }
    });
});
