/**
 * M-PESA Gateway Frontend JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // M-PESA Frontend Object
    var MpesaFrontend = {
        
        init: function() {
            this.bindEvents();
            this.initPhoneValidation();
            this.setupCheckoutIntegration();
        },
        
        bindEvents: function() {
            // Phone number formatting
            $(document).on('input', '.wc-mpesa-phone-number', this.formatPhoneNumber);
            
            // Phone number validation
            $(document).on('blur', '.wc-mpesa-phone-number', this.validatePhoneNumber);
            
            // Payment method selection
            $(document).on('change', 'input[name="payment_method"]', this.onPaymentMethodChange);
            
            // Form submission
            $(document).on('submit', 'form.woocommerce-checkout', this.onCheckoutSubmit);
            
            // Real-time validation
            $(document).on('keyup', '.wc-mpesa-phone-number', this.debounce(this.validatePhoneNumber, 500));
        },
        
        formatPhoneNumber: function() {
            var $input = $(this);
            var value = $input.val();
            var cleanValue = value.replace(/\D/g, '');
            
            // Remove leading zeros
            cleanValue = cleanValue.replace(/^0+/, '');
            
            // Format the number
            var formatted = '';
            
            if (cleanValue.length > 0) {
                // Add country code if not present
                if (!cleanValue.startsWith('254')) {
                    if (cleanValue.length <= 9) {
                        cleanValue = '254' + cleanValue;
                    }
                }
                
                // Format as 254 XXX XXX XXX
                if (cleanValue.startsWith('254')) {
                    formatted = '254';
                    var remaining = cleanValue.substring(3);
                    
                    if (remaining.length > 0) {
                        formatted += ' ' + remaining.substring(0, 3);
                    }
                    if (remaining.length > 3) {
                        formatted += ' ' + remaining.substring(3, 6);
                    }
                    if (remaining.length > 6) {
                        formatted += ' ' + remaining.substring(6, 9);
                    }
                }
            }
            
            // Update the input value
            if (formatted !== value) {
                var cursorPos = $input[0].selectionStart;
                $input.val(formatted);
                
                // Restore cursor position
                var newPos = cursorPos + (formatted.length - value.length);
                $input[0].setSelectionRange(newPos, newPos);
            }
            
            // Update network operator indicator
            this.updateNetworkOperator($input, cleanValue);
        },
        
        validatePhoneNumber: function() {
            var $input = $(this);
            var value = $input.val().replace(/\D/g, '');
            var $errorContainer = $input.siblings('.wc-mpesa-phone-error');
            
            // Remove existing error
            $input.removeClass('error');
            $errorContainer.remove();
            
            if (value === '') {
                return;
            }
            
            // Validation rules
            var isValid = true;
            var errorMessage = '';
            
            // Check length
            if (value.length !== 12) {
                isValid = false;
                errorMessage = 'Phone number must be 12 digits (including country code 254)';
            }
            // Check country code
            else if (!value.startsWith('254')) {
                isValid = false;
                errorMessage = 'Phone number must start with 254 (Kenya country code)';
            }
            // Check valid operator prefixes
            else if (!MpesaFrontend.isValidKenyanMobile(value)) {
                isValid = false;
                errorMessage = 'Please enter a valid Kenyan mobile number (Safaricom, Airtel, or Telkom)';
            }
            
            if (!isValid) {
                $input.addClass('error');
                $input.after('<div class="wc-mpesa-phone-error">' + errorMessage + '</div>');
            }
            
            return isValid;
        },
        
        isValidKenyanMobile: function(phone) {
            if (phone.length !== 12 || !phone.startsWith('254')) {
                return false;
            }
            
            var prefix = phone.substring(3, 6);
            var validPrefixes = [
                // Safaricom
                '701', '702', '703', '704', '705', '706', '707', '708', '709',
                '710', '711', '712', '713', '714', '715', '716', '717', '718', '719',
                '720', '721', '722', '723', '724', '725', '726', '727', '728', '729',
                '740', '741', '742', '743', '744', '745', '746', '747', '748', '749',
                // Airtel
                '730', '731', '732', '733', '734', '735', '736', '737', '738', '739',
                '750', '751', '752', '753', '754', '755', '756', '757', '758', '759',
                '780', '781', '782', '783', '784', '785', '786', '787', '788', '789',
                // Telkom
                '770', '771', '772', '773', '774', '775', '776', '777', '778', '779'
            ];
            
            return validPrefixes.indexOf(prefix) !== -1;
        },
        
        updateNetworkOperator: function($input, phone) {
            var $indicator = $input.siblings('.wc-mpesa-network-indicator');
            
            if ($indicator.length === 0) {
                $indicator = $('<div class="wc-mpesa-network-indicator"></div>');
                $input.after($indicator);
            }
            
            if (phone.length >= 6) {
                var prefix = phone.substring(3, 6);
                var operator = this.getNetworkOperator(prefix);
                
                var operatorNames = {
                    'safaricom': 'Safaricom',
                    'airtel': 'Airtel',
                    'telkom': 'Telkom',
                    'unknown': 'Unknown Network'
                };
                
                $indicator.html('<span class="wc-mpesa-network-icon wc-mpesa-network-' + operator + '"></span>' + 
                               operatorNames[operator]);
                $indicator.show();
            } else {
                $indicator.hide();
            }
        },
        
        getNetworkOperator: function(prefix) {
            var safaricomPrefixes = [
                '701', '702', '703', '704', '705', '706', '707', '708', '709',
                '710', '711', '712', '713', '714', '715', '716', '717', '718', '719',
                '720', '721', '722', '723', '724', '725', '726', '727', '728', '729',
                '740', '741', '742', '743', '744', '745', '746', '747', '748', '749'
            ];
            
            var airtelPrefixes = [
                '730', '731', '732', '733', '734', '735', '736', '737', '738', '739',
                '750', '751', '752', '753', '754', '755', '756', '757', '758', '759',
                '780', '781', '782', '783', '784', '785', '786', '787', '788', '789'
            ];
            
            var telkomPrefixes = [
                '770', '771', '772', '773', '774', '775', '776', '777', '778', '779'
            ];
            
            if (safaricomPrefixes.indexOf(prefix) !== -1) {
                return 'safaricom';
            } else if (airtelPrefixes.indexOf(prefix) !== -1) {
                return 'airtel';
            } else if (telkomPrefixes.indexOf(prefix) !== -1) {
                return 'telkom';
            }
            
            return 'unknown';
        },
        
        onPaymentMethodChange: function() {
            var selectedMethod = $('input[name="payment_method"]:checked').val();
            
            if (selectedMethod === 'mpesa') {
                // Focus on phone number field
                setTimeout(function() {
                    $('.wc-mpesa-phone-number').focus();
                }, 100);
                
                // Show M-PESA specific instructions
                MpesaFrontend.showInstructions();
            } else {
                // Hide M-PESA instructions
                $('.wc-mpesa-instructions').hide();
            }
        },
        
        onCheckoutSubmit: function(e) {
            var selectedMethod = $('input[name="payment_method"]:checked').val();
            
            if (selectedMethod === 'mpesa') {
                var $phoneInput = $('.wc-mpesa-phone-number');
                
                if ($phoneInput.length) {
                    // Validate phone number before submission
                    var isValid = MpesaFrontend.validatePhoneNumber.call($phoneInput[0]);
                    
                    if (!isValid) {
                        e.preventDefault();
                        $phoneInput.focus();
                        
                        // Scroll to error
                        $('html, body').animate({
                            scrollTop: $phoneInput.offset().top - 100
                        }, 500);
                        
                        return false;
                    }
                    
                    // Show processing message
                    MpesaFrontend.showProcessingMessage();
                }
            }
        },
        
        showInstructions: function() {
            var instructions = '<div class="wc-mpesa-instructions">' +
                '<h4>M-PESA Payment Instructions</h4>' +
                '<ol>' +
                '<li>Enter your M-PESA registered phone number</li>' +
                '<li>Click "Place Order" to proceed</li>' +
                '<li>You will receive an M-PESA prompt on your phone</li>' +
                '<li>Enter your M-PESA PIN to complete payment</li>' +
                '<li>You will receive an SMS confirmation</li>' +
                '</ol>' +
                '</div>';
            
            var $existing = $('.wc-mpesa-instructions');
            if ($existing.length) {
                $existing.show();
            } else {
                $('.payment_method_mpesa .payment_box').append(instructions);
            }
        },
        
        showProcessingMessage: function() {
            var message = '<div class="wc-mpesa-processing">' +
                '<div class="wc-mpesa-loading"></div>' +
                '<p>Processing your payment request...</p>' +
                '<p><small>Please wait while we initiate your M-PESA payment.</small></p>' +
                '</div>';
            
            // Disable form submission
            $('form.woocommerce-checkout').find('button[type="submit"]').prop('disabled', true);
            
            // Show overlay
            var $overlay = $('<div class="wc-mpesa-overlay">' + message + '</div>');
            $('body').append($overlay);
        },
        
        initPhoneValidation: function() {
            // Pre-populate phone number from billing info
            var billingPhone = $('#billing_phone').val();
            if (billingPhone && $('.wc-mpesa-phone-number').val() === '') {
                var cleanPhone = billingPhone.replace(/\D/g, '');
                if (cleanPhone.length >= 9) {
                    $('.wc-mpesa-phone-number').val(cleanPhone).trigger('input');
                }
            }
        },
        
        setupCheckoutIntegration: function() {
            // WooCommerce checkout integration
            $(document.body).on('updated_checkout', function() {
                MpesaFrontend.initPhoneValidation();
                
                // Re-bind events after checkout update
                $('.wc-mpesa-phone-number').off('input blur keyup').on({
                    'input': MpesaFrontend.formatPhoneNumber,
                    'blur': MpesaFrontend.validatePhoneNumber,
                    'keyup': MpesaFrontend.debounce(MpesaFrontend.validatePhoneNumber, 500)
                });
            });
            
            // Initial check if M-PESA is selected
            if ($('input[name="payment_method"]:checked').val() === 'mpesa') {
                MpesaFrontend.showInstructions();
            }
        },
        
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },
        
        // Utility function to show notifications
        showNotification: function(message, type) {
            type = type || 'info';
            
            var $notification = $('<div class="wc-mpesa-notification wc-mpesa-notification-' + type + '">' +
                '<span class="message">' + message + '</span>' +
                '<button class="close">&times;</button>' +
                '</div>');
            
            $('body').append($notification);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Manual close
            $notification.find('.close').on('click', function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            });
        }
    };
    
    // Initialize frontend functionality
    MpesaFrontend.init();
    
    // Global reference
    window.MpesaFrontend = MpesaFrontend;
});

// Payment status checking (for thank you page)
jQuery(document).ready(function($) {
    if ($('.wc-mpesa-pending-notice').length) {
        var checkInterval = setInterval(function() {
            var orderId = $('body').attr('class').match(/page-id-(\d+)/);
            if (orderId) {
                $.ajax({
                    url: wc_mpesa_frontend.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wc_mpesa_check_payment_status',
                        order_id: orderId[1],
                        nonce: wc_mpesa_frontend.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data.status !== 'pending') {
                            clearInterval(checkInterval);
                            location.reload();
                        }
                    }
                });
            }
        }, 10000); // Check every 10 seconds
        
        // Stop checking after 5 minutes
        setTimeout(function() {
            clearInterval(checkInterval);
        }, 300000);
    }
});
