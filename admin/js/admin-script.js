(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        /* ===== Select All Checkbox ===== */
        $('#spb-select-all').on('change', function() {
            $('input[name="key_ids[]"]').prop('checked', $(this).prop('checked'));
        });
        
        /* ===== Copy Key Button ===== */
        $(document).on('click', '.spb-copy-key-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $button = $(this);
            var target = $button.data('clipboard-target');
            var $target = $(target);
            
            // Create a temporary textarea to copy from
            var tempTextarea = $('<textarea>');
            $('body').append(tempTextarea);
            tempTextarea.val($target.text()).select();
            
            // Execute copy
            document.execCommand('copy');
            tempTextarea.remove();
            
            // Show feedback
            var originalHTML = $button.html();
            $button.html('<span class="dashicons dashicons-yes"></span>');
            $button.addClass('spb-copied');
            
            setTimeout(function() {
                $button.html(originalHTML);
                $button.removeClass('spb-copied');
            }, 2000);
        });

        /* ===== Enhanced API Keys Table Functionality ===== */

            // Toggle full key display
            $(document).on('click', '.spb-show-key-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var keyId = $(this).data('key-id');
                $('#spb-full-key-' + keyId).slideDown();
                $(this).hide();
            });

            $(document).on('click', '.spb-hide-key-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var keyId = $(this).data('key-id');
                $('#spb-full-key-' + keyId).slideUp();
                $('#spb-full-key-' + keyId).closest('.spb-key-preview').find('.spb-show-key-btn').show();
            });

            // More actions dropdown
            $(document).on('click', '.spb-more-actions-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var keyId = $(this).data('key-id');
                var $dropdown = $('#spb-dropdown-' + keyId);
                
                // Close other dropdowns
                $('.spb-dropdown-menu').not($dropdown).removeClass('show');
                
                // Toggle current dropdown
                $dropdown.toggleClass('show');
                
                // Close dropdown when clicking outside
                $(document).on('click.spb-dropdown', function(e) {
                    if (!$(e.target).closest('.spb-secondary-actions').length) {
                        $dropdown.removeClass('show');
                        $(document).off('click.spb-dropdown');
                    }
                });
            });

            // Copy cURL example
            $(document).on('click', '.spb-copy-curl-btn', function(e) {
                e.preventDefault();
                
                var keyId = $(this).data('key-id');
                var $row = $(this).closest('tr');
                var keyName = $row.find('.spb-key-name-text').text();
                var apiKey = $row.find('.spb-full-key-text').text();
                
                if (!apiKey) {
                    alert('API key not available for this key.');
                    return;
                }
                
                var curlExample = 'curl -X POST \\\n' +
                    '  -H "Content-Type: application/json" \\\n' +
                    '  -H "X-API-Key: ' + apiKey + '" \\\n' +
                    '  -d \'{"pages":[{"title":"' + keyName + ' Page","content":"Page content"}]}\' \\\n' +
                    '  "' + window.location.origin + '/wp-json/pagebuilder/v1/create-pages"';
                
                // Copy to clipboard
                var tempTextarea = $('<textarea>');
                $('body').append(tempTextarea);
                tempTextarea.val(curlExample).select();
                document.execCommand('copy');
                tempTextarea.remove();
                
                // Show feedback
                alert('cURL example copied to clipboard!');
                
                // Close dropdown
                $(this).closest('.spb-dropdown-menu').removeClass('show');
            });

            // Show QR Code
            $(document).on('click', '.spb-qrcode-btn', function(e) {
                e.preventDefault();
                
                var keyId = $(this).data('key-id');
                var keyName = $(this).data('key-name');
                var $row = $(this).closest('tr');
                var apiKey = $row.find('.spb-full-key-text').text();
                
                if (!apiKey) {
                    alert('API key not available for QR code.');
                    return;
                }
                
                // You'll need to implement QR code generation here
                // For now, show a message
                alert('QR Code feature would show for: ' + keyName);
                
                // Close dropdown
                $(this).closest('.spb-dropdown-menu').removeClass('show');
            });

            // Export All
            $(document).on('click', '.spb-export-btn', function() {
                // Implement export functionality
                alert('Export all keys functionality would be implemented here.');
            });

            // Refresh table
            $(document).on('click', '.spb-refresh-btn', function() {
                location.reload();
            });

            // Bulk actions selection
            $(document).on('change', '.spb-key-checkbox', function() {
                updateBulkActions();
            });

            $(document).on('click', '#spb-select-all-toggle', function() {
                var isChecked = $(this).hasClass('selected');
                
                if (isChecked) {
                    $('.spb-key-checkbox').prop('checked', false);
                    $(this).removeClass('selected').text('Select All');
                } else {
                    $('.spb-key-checkbox').prop('checked', true);
                    $(this).addClass('selected').text('Deselect All');
                }
                
                updateBulkActions();
            });

            $(document).on('change', '#spb-select-all', function() {
                $('.spb-key-checkbox').prop('checked', $(this).prop('checked'));
                updateBulkActions();
            });

            // Clear selection
            $(document).on('click', '#spb-bulk-clear', function() {
                $('.spb-key-checkbox').prop('checked', false);
                $('#spb-select-all').prop('checked', false);
                $('#spb-select-all-toggle').removeClass('selected').text('Select All');
                updateBulkActions();
            });

            function updateBulkActions() {
                var selectedCount = $('.spb-key-checkbox:checked').length;
                var $container = $('.spb-bulk-actions-container');
                var $count = $('.spb-selected-count');
                
                if (selectedCount > 0) {
                    $container.slideDown();
                    $count.text(selectedCount);
                } else {
                    $container.slideUp();
                }
            }

            // Initialize bulk actions
            updateBulkActions();
        
        /* ===== Copy Buttons in Recent Key Notice ===== */
        $(document).on('click', '.spb-copy-btn', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var target = $button.data('clipboard-target');
            var $target = $(target);
            
            $target.select();
            document.execCommand('copy');
            
            // Show feedback
            var originalText = $button.text();
            $button.text(spb_ajax.copied_text);
            
            setTimeout(function() {
                $button.text(originalText);
            }, 2000);
        });
        
        /* ===== API Keys Management ===== */
        
        // Revoke API Key
        $(document).on('click', '.spb-revoke-btn', function(e) {
            e.preventDefault();
            
            var keyId = $(this).data('key-id');
            var keyName = $(this).data('key-name');
            
            showConfirmModal(
                'Revoke API Key',
                'Are you sure you want to revoke the API key "' + keyName + '"? This will immediately prevent any new requests with this key.',
                function() {
                    revokeApiKey(keyId);
                }
            );
        });
        
        // Delete API Key
        $(document).on('click', '.spb-delete-btn', function(e) {
            e.preventDefault();
            
            var keyId = $(this).data('key-id');
            var keyName = $(this).data('key-name');
            
            showConfirmModal(
                'Delete API Key',
                'Are you sure you want to permanently delete the API key "' + keyName + '"? This action cannot be undone.',
                function() {
                    deleteApiKey(keyId);
                }
            );
        });
        
        // Regenerate Secret Key
        $(document).on('click', '.spb-regenerate-secret-btn', function(e) {
            e.preventDefault();
            
            var keyId = $(this).data('key-id');
            
            showConfirmModal(
                'Regenerate Secret Key',
                'Are you sure you want to regenerate the secret key? The old secret will no longer work for request signing.',
                function() {
                    regenerateSecretKey(keyId);
                }
            );
        });
        
        // Bulk Actions
        $('#spb-bulk-apply').on('click', function() {
            var action = $('#spb-bulk-action').val();
            var selectedKeys = [];
            
            $('input[name="key_ids[]"]:checked').each(function() {
                selectedKeys.push($(this).val());
            });
            
            if (!action) {
                alert('Please select a bulk action.');
                return;
            }
            
            if (selectedKeys.length === 0) {
                alert('Please select at least one API key.');
                return;
            }
            
            var actionText = action === 'revoke' ? 'revoke' : 'delete';
            var message = 'Are you sure you want to ' + actionText + ' ' + selectedKeys.length + ' selected API key(s)?';
            
            if (action === 'delete') {
                message += ' This action cannot be undone.';
            }
            
            showConfirmModal(
                'Bulk ' + (action === 'revoke' ? 'Revoke' : 'Delete'),
                message,
                function() {
                    performBulkAction(action, selectedKeys);
                }
            );
        });
        
        /* ===== Activity Log ===== */
        
        // View Log Details
        $(document).on('click', '.spb-view-log-btn', function() {
            var logId = $(this).data('log-id');
            viewLogDetails(logId);
        });
        
        /* ===== Settings ===== */
        
        // Generate Random Secret
        $('.spb-generate-secret').on('click', function() {
            var target = $(this).data('target');
            var secret = generateRandomString(64);
            $(target).val(secret);
            $(target).attr('type', 'text');
            
            // Change back to password after 5 seconds
            setTimeout(function() {
                $(target).attr('type', 'password');
            }, 5000);
        });
        
        // Test Webhook
        $('#spb-test-webhook').on('click', function() {
            testWebhook();
        });
        
        // Toggle webhook test button based on URL
        $('#webhook_url').on('input', function() {
            $('#spb-test-webhook').prop('disabled', $(this).val().trim() === '');
        });
        
        /* ===== Modals ===== */
        
        // Close modal
        $(document).on('click', '.spb-modal-close', function() {
            $(this).closest('.spb-modal').hide();
        });
        
        // Close modal when clicking outside
        $(document).on('click', '.spb-modal', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });
        
        // Escape key to close modal
        $(document).on('keyup', function(e) {
            if (e.key === 'Escape') {
                $('.spb-modal').hide();
            }
        });
        
        /* ===== Helper Functions ===== */
        
        // Show confirmation modal
        function showConfirmModal(title, message, confirmCallback) {
            $('#spb-confirm-title').text(title);
            $('#spb-confirm-message').text(message);
            
            $('#spb-confirm-action').off('click').on('click', function() {
                confirmCallback();
                $('#spb-confirm-modal').hide();
            });
            
            $('#spb-confirm-modal').show();
        }
        
        // Generate random string
        function generateRandomString(length) {
            var charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
            var result = '';
            for (var i = 0; i < length; i++) {
                result += charset.charAt(Math.floor(Math.random() * charset.length));
            }
            return result;
        }
        
        /* ===== AJAX Functions ===== */
        
        // Revoke API Key
        function revokeApiKey(keyId) {
            $.ajax({
                url: spb_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'spb_revoke_api_key',
                    key_id: keyId,
                    nonce: spb_ajax.nonce
                },
                beforeSend: function() {
                    // Show loading
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        }
        
        // Delete API Key
        function deleteApiKey(keyId) {
            $.ajax({
                url: spb_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'spb_delete_api_key',
                    key_id: keyId,
                    nonce: spb_ajax.nonce
                },
                beforeSend: function() {
                    // Show loading
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        }
        
        // Regenerate Secret Key
        function regenerateSecretKey(keyId) {
    $.ajax({
        url: spb_ajax.ajax_url,
        method: 'POST',
        data: {
            action: 'spb_regenerate_secret',
            key_id: keyId,
            nonce: spb_ajax.nonce
        },
        beforeSend: function() {
            // Show loading indicator
            $('#spb-confirm-modal .spb-modal-footer').html('<p>Regenerating keys...</p>');
        },
        success: function(response) {
            if (response.success) {
                // Show both keys in modal
                showSecretKeyModal(response.data);
                
                // Update confirm button to reload page
                $('#spb-confirm-action').off('click').on('click', function() {
                    location.reload();
                }).text('Close & Refresh');
                
            } else {
                alert('Error: ' + response.data);
                $('#spb-confirm-modal').hide();
            }
        },
        error: function(xhr, status, error) {
            alert('An error occurred: ' + error);
            $('#spb-confirm-modal').hide();
        }
    });
}
        
        // Perform Bulk Action
        function performBulkAction(action, keyIds) {
            $.ajax({
                url: spb_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: action === 'revoke' ? 'spb_revoke_api_key' : 'spb_delete_api_key',
                    key_ids: keyIds,
                    nonce: spb_ajax.nonce
                },
                beforeSend: function() {
                    // Show loading
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        }
        
        // View Log Details
        function viewLogDetails(logId) {
            $.ajax({
                url: spb_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'spb_get_log_details',
                    log_id: logId,
                    nonce: spb_ajax.nonce
                },
                beforeSend: function() {
                    $('#spb-log-details').html('<p>Loading...</p>');
                },
                success: function(response) {
                    if (response.success) {
                        $('#spb-log-details').html(response.data.html);
                    } else {
                        $('#spb-log-details').html('<p class="error">Error loading log details.</p>');
                    }
                    $('#spb-log-modal').show();
                },
                error: function() {
                    $('#spb-log-details').html('<p class="error">An error occurred.</p>');
                    $('#spb-log-modal').show();
                }
            });
        }
        
        // Test Webhook
        function testWebhook() {
            var $button = $('#spb-test-webhook');
            var $result = $('#spb-test-result');
            
            $button.prop('disabled', true).text('Testing...');
            $result.removeClass().text('');
            
            $.ajax({
                url: spb_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'spb_test_webhook',
                    nonce: spb_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('spb-test-result success').text('✓ ' + response.data.message);
                    } else {
                        $result.addClass('spb-test-result error').text('✗ ' + response.data.message);
                    }
                },
                error: function() {
                    $result.addClass('spb-test-result error').text('✗ Network error. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Webhook');
                }
            });
        }
        
        function showSecretKeyModal(data) {
    var modal = $('#spb-key-modal');
    
    // Update modal title
    $('#spb-confirm-title').text('New API Keys Generated');
    
    // Create content for modal
    var content = '<div class="spb-alert spb-alert-warning">' +
        '<p><span class="dashicons dashicons-warning"></span> ' +
        'Save these keys securely. You will not be able to see them again.</p>' +
        '</div>' +
        
        '<div class="spb-form-group">' +
        '<label>New API Key:</label>' +
        '<div class="spb-key-display">' +
        '<input type="text" id="spb-new-api-key" class="regular-text spb-key-input" value="' + data.api_key + '" readonly>' +
        '<button type="button" class="button spb-copy-key-btn" data-clipboard-target="#spb-new-api-key">' +
        '<span class="dashicons dashicons-clipboard"></span> Copy' +
        '</button>' +
        '</div>' +
        '</div>' +
        
        '<div class="spb-form-group">' +
        '<label>New Secret Key:</label>' +
        '<div class="spb-key-display">' +
        '<input type="text" id="spb-new-secret-key" class="regular-text spb-key-input" value="' + data.secret_key + '" readonly>' +
        '<button type="button" class="button spb-copy-key-btn" data-clipboard-target="#spb-new-secret-key">' +
        '<span class="dashicons dashicons-clipboard"></span> Copy' +
        '</button>' +
        '</div>' +
        '</div>' +
        
        '<div class="spb-curl-example">' +
        '<h4>New cURL Example:</h4>' +
        '<pre class="spb-code-block">' +
        'curl -X POST \\' + '\n' +
        '  -H "Content-Type: application/json" \\' + '\n' +
        '  -H "X-API-Key: ' + data.api_key + '" \\' + '\n' +
        '  -H "X-API-Secret: ' + data.secret_key + '" \\' + '\n' +
        '  -d \'{"pages":[{"title":"Example Page","content":"Page content"}]}\' \\' + '\n' +
        '  "' + window.location.origin + '/wp-json/pagebuilder/v1/create-pages"' +
        '</pre>' +
        '</div>';
    
    $('#spb-confirm-message').html(content);
    
    // Show the modal
    $('#spb-confirm-modal').show();
    
    // Re-attach copy button handlers
    $(document).on('click', '.spb-copy-key-btn', function(e) {
        e.preventDefault();
        var target = $(this).data('clipboard-target');
        var $input = $(target);
        
        $input.select();
        document.execCommand('copy');
        
        // Show feedback
        var $button = $(this);
        var originalHTML = $button.html();
        $button.html('<span class="dashicons dashicons-yes"></span> Copied!');
        
        setTimeout(function() {
            $button.html(originalHTML);
        }, 2000);
    });
}
        
    });
    
})(jQuery);