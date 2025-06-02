/**
 * M-PESA Gateway Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // M-PESA Admin Object
    var MpesaAdmin = {
        
        init: function() {
            this.bindEvents();
            this.checkConfiguration();
            this.initAccordions();
            this.initTooltips();
        },
        
        bindEvents: function() {
            // Test connection button
            $(document).on('click', '.wc-mpesa-test-connection', this.testConnection);
            
            // Generate timestamp button
            $(document).on('click', '.wc-mpesa-generate-timestamp', this.generateTimestamp);
            
            // Clear logs button
            $(document).on('click', '.wc-mpesa-clear-logs', this.clearLogs);
            
            // Environment change
            $(document).on('change', '#woocommerce_mpesa_testmode', this.onEnvironmentChange);
            
            // Form validation
            $(document).on('change', '.wc-mpesa-required-field', this.validateField);
            
            // Phone number formatting
            $(document).on('input', '.wc-mpesa-phone-input', this.formatPhoneNumber);
        },
        
        testConnection: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $spinner = $('<span class="wc-mpesa-loading"></span>');
            
            $button.prop('disabled', true).prepend($spinner);
            
            var data = {
                action: 'wc_mpesa_test_connection',
                nonce: wc_mpesa_admin.nonce,
                consumer_key: $('#woocommerce_mpesa_consumer_key').val(),
                consumer_secret: $('#woocommerce_mpesa_consumer_secret').val(),
                testmode: $('#woocommerce_mpesa_testmode').is(':checked')
            };
            
            $.ajax({
                url: wc_mpesa_admin.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        MpesaAdmin.showNotice('Connection successful!', 'success');
                    } else {
                        MpesaAdmin.showNotice('Connection failed: ' + response.data, 'error');
                    }
                },
                error: function() {
                    MpesaAdmin.showNotice('Connection test failed. Please try again.', 'error');
                },
                complete: function() {
                    $spinner.remove();
                    $button.prop('disabled', false);
                }
            });
        },
        
        generateTimestamp: function(e) {
            e.preventDefault();
            
            var timestamp = new Date().toISOString().replace(/[-T:]/g, '').substr(0, 14);
            var $target = $($(this).data('target'));
            
            if ($target.length) {
                $target.val(timestamp);
                MpesaAdmin.showNotice('Timestamp generated: ' + timestamp, 'info');
            }
        },
        
        clearLogs: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear all M-PESA logs?')) {
                return;
            }
            
            var $button = $(this);
            var $spinner = $('<span class="wc-mpesa-loading"></span>');
            
            $button.prop('disabled', true).prepend($spinner);
            
            var data = {
                action: 'wc_mpesa_clear_logs',
                nonce: wc_mpesa_admin.nonce
            };
            
            $.ajax({
                url: wc_mpesa_admin.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        $('.wc-mpesa-log-entry').remove();
                        MpesaAdmin.showNotice('Logs cleared successfully.', 'success');
                    } else {
                        MpesaAdmin.showNotice('Failed to clear logs: ' + response.data, 'error');
                    }
                },
                error: function() {
                    MpesaAdmin.showNotice('Failed to clear logs. Please try again.', 'error');
                },
                complete: function() {
                    $spinner.remove();
                    $button.prop('disabled', false);
                }
            });
        },
        
        onEnvironmentChange: function() {
            var isTestMode = $(this).is(':checked');
            var $testNotice = $('.wc-mpesa-test-mode-notice');
            
            if (isTestMode) {
                if ($testNotice.length === 0) {
                    var notice = '<div class="wc-mpesa-test-mode-notice">' +
                        '<p><strong>TEST MODE ENABLED</strong> - Use sandbox credentials and test phone numbers.</p>' +
                        '</div>';
                    $(this).closest('tr').after(notice);
                }
            } else {
                $testNotice.remove();
            }
            
            MpesaAdmin.updateEndpointUrls(isTestMode);
        },
        
        updateEndpointUrls: function(isTestMode) {
            var baseUrl = isTestMode ? 
                'https://sandbox.safaricom.co.ke' : 
                'https://api.safaricom.co.ke';
            
            $('.wc-mpesa-endpoint-url').each(function() {
                var endpoint = $(this).data('endpoint');
                $(this).text(baseUrl + endpoint);
            });
        },
        
        validateField: function() {
            var $field = $(this);
            var value = $field.val().trim();
            var fieldName = $field.attr('name');
            
            $field.removeClass('error success');
            
            if (value === '') {
                $field.addClass('error');
                return;
            }
            
            // Field-specific validation
            switch (fieldName) {
                case 'woocommerce_mpesa_consumer_key':
                case 'woocommerce_mpesa_consumer_secret':
                    if (value.length < 10) {
                        $field.addClass('error');
                    } else {
                        $field.addClass('success');
                    }
                    break;
                    
                case 'woocommerce_mpesa_shortcode':
                    if (!/^\d{5,7}$/.test(value)) {
                        $field.addClass('error');
                    } else {
                        $field.addClass('success');
                    }
                    break;
                    
                case 'woocommerce_mpesa_passkey':
                    if (value.length < 20) {
                        $field.addClass('error');
                    } else {
                        $field.addClass('success');
                    }
                    break;
            }
        },
        
        formatPhoneNumber: function() {
            var $input = $(this);
            var value = $input.val().replace(/\D/g, '');
            
            // Format as +254 XXX XXX XXX
            if (value.length > 0) {
                if (value.startsWith('254')) {
                    value = value.substring(3);
                } else if (value.startsWith('0')) {
                    value = value.substring(1);
                }
                
                if (value.length <= 9) {
                    var formatted = '+254 ';
                    for (var i = 0; i < value.length; i++) {
                        if (i > 0 && i % 3 === 0) {
                            formatted += ' ';
                        }
                        formatted += value[i];
                    }
                    $input.val(formatted);
                }
            }
        },
        
        checkConfiguration: function() {
            var requiredFields = [
                'woocommerce_mpesa_consumer_key',
                'woocommerce_mpesa_consumer_secret',
                'woocommerce_mpesa_shortcode',
                'woocommerce_mpesa_passkey'
            ];
            
            var missingFields = [];
            
            requiredFields.forEach(function(field) {
                var $field = $('#' + field);
                if ($field.length && $field.val().trim() === '') {
                    missingFields.push($field.closest('tr').find('th').text().replace('*', '').trim());
                }
            });
            
            var $statusContainer = $('.wc-mpesa-config-status');
            
            if (missingFields.length > 0) {
                var message = '<div class="wc-mpesa-config-status error">' +
                    '<h4>Configuration Incomplete</h4>' +
                    '<p>The following fields are required:</p>' +
                    '<ul>';
                
                missingFields.forEach(function(field) {
                    message += '<li><span class="wc-mpesa-config-check error"></span>' + field + '</li>';
                });
                
                message += '</ul></div>';
                
                if ($statusContainer.length) {
                    $statusContainer.replaceWith(message);
                } else {
                    $('.form-table').before(message);
                }
            } else {
                if ($statusContainer.length && $statusContainer.hasClass('error')) {
                    var successMessage = '<div class="wc-mpesa-config-status">' +
                        '<h4>Configuration Complete</h4>' +
                        '<p><span class="wc-mpesa-config-check success"></span>All required fields are configured.</p>' +
                        '</div>';
                    $statusContainer.replaceWith(successMessage);
                }
            }
        },
        
        initAccordions: function() {
            $('.wc-mpesa-accordion-header').on('click', function() {
                var $header = $(this);
                var $content = $header.next('.wc-mpesa-accordion-content');
                
                $header.toggleClass('active');
                $content.toggleClass('active').slideToggle();
            });
        },
        
        initTooltips: function() {
            // Simple tooltip implementation
            $('.wc-mpesa-tooltip').on('mouseenter', function() {
                var tooltip = $(this).data('tooltip');
                if (tooltip) {
                    var $tooltip = $('<div class="wc-mpesa-tooltip-popup">' + tooltip + '</div>');
                    $(this).append($tooltip);
                }
            }).on('mouseleave', function() {
                $(this).find('.wc-mpesa-tooltip-popup').remove();
            });
        },
        
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible">' +
                '<p>' + message + '</p>' +
                '<button type="button" class="notice-dismiss">' +
                '<span class="screen-reader-text">Dismiss this notice.</span>' +
                '</button>' +
                '</div>');
            
            $('.wc-mpesa-admin-notices').prepend($notice);
            
            // Auto-remove after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Manual dismiss
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        },
        
        refreshTransactionLog: function() {
            var data = {
                action: 'wc_mpesa_get_recent_logs',
                nonce: wc_mpesa_admin.nonce
            };
            
            $.ajax({
                url: wc_mpesa_admin.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        $('.wc-mpesa-transaction-log .wc-mpesa-log-entry').remove();
                        
                        if (response.data.length > 0) {
                            response.data.forEach(function(log) {
                                var $entry = $('<div class="wc-mpesa-log-entry">' +
                                    '<span class="timestamp">' + log.timestamp + '</span>' +
                                    '<span class="level ' + log.level + '">' + log.level + '</span>' +
                                    '<span class="message">' + log.message + '</span>' +
                                    '</div>');
                                $('.wc-mpesa-transaction-log').append($entry);
                            });
                        } else {
                            $('.wc-mpesa-transaction-log').append('<div class="wc-mpesa-log-entry">No recent log entries found.</div>');
                        }
                    }
                }
            });
        }
    };
    
    // Initialize admin functionality
    MpesaAdmin.init();
    
    // Refresh logs every 30 seconds if on settings page
    if ($('.wc-mpesa-transaction-log').length) {
        setInterval(function() {
            MpesaAdmin.refreshTransactionLog();
        }, 30000);
    }
    
    // Global reference
    window.MpesaAdmin = MpesaAdmin;
});

// WooCommerce settings page integration
jQuery(document).ready(function($) {
    // Show/hide fields based on test mode
    function toggleTestModeFields() {
        var isTestMode = $('#woocommerce_mpesa_testmode').is(':checked');
        var $productionFields = $('.wc-mpesa-production-only');
        
        if (isTestMode) {
            $productionFields.hide();
        } else {
            $productionFields.show();
        }
    }
    
    // Initial check
    toggleTestModeFields();
    
    // On change
    $('#woocommerce_mpesa_testmode').on('change', toggleTestModeFields);
    
    // Add help text for sandbox
    if ($('#woocommerce_mpesa_testmode').is(':checked')) {
        var helpText = '<div class="wc-mpesa-sandbox-help">' +
            '<h4>Sandbox Testing Information</h4>' +
            '<ul>' +
            '<li><strong>Test Phone Number:</strong> 254708374149</li>' +
            '<li><strong>Test Shortcode:</strong> 174379</li>' +
            '<li><strong>Base URL:</strong> https://sandbox.safaricom.co.ke</li>' +
            '</ul>' +
            '</div>';
        
        $('#woocommerce_mpesa_testmode').closest('tr').after('<tr><td colspan="2">' + helpText + '</td></tr>');
    }
});
