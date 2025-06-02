<?php
/**
 * WooCommerce M-PESA Payment Gateway Class
 *
 * @package WooCommerce_Mpesa_Gateway
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * M-PESA Payment Gateway Class
 */
class WC_Mpesa_Gateway extends WC_Payment_Gateway {
    
    /**
     * M-PESA API instance
     *
     * @var WC_Mpesa_API
     */
    private $api;
    
    /**
     * Logger instance
     *
     * @var WC_Logger
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->id = 'mpesa';
        $this->icon = apply_filters('woocommerce_mpesa_icon', WC_MPESA_PLUGIN_URL . 'assets/images/mpesa-logo.png');
        $this->has_fields = true;
        $this->method_title = __('M-PESA', 'woocommerce-mpesa-gateway');
        $this->method_description = __('Accept payments via M-PESA mobile money using Safaricom Daraja API.', 'woocommerce-mpesa-gateway');
        
        // Supported features
        $this->supports = array(
            'products',
            'refunds'
        );
        
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();
        
        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions', $this->description);
        $this->enabled = $this->get_option('enabled');
        $this->testmode = 'yes' === $this->get_option('testmode');
        $this->debug = 'yes' === $this->get_option('debug');
        
        // M-PESA API settings
        $this->consumer_key = $this->get_option('consumer_key');
        $this->consumer_secret = $this->get_option('consumer_secret');
        $this->shortcode = $this->get_option('shortcode');
        $this->passkey = $this->get_option('passkey');
        $this->callback_url = home_url('/wc-api/mpesa_callback/');
        
        // Initialize API
        $this->api = new WC_Mpesa_API($this);
        
        // Initialize logger
        if ('yes' === $this->debug) {
            $this->logger = wc_get_logger();
        }
        
        // Hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_mpesa_callback', array($this, 'handle_callback'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        
        // Customer emails
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
        
        // Admin scripts
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
    }
    
    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce-mpesa-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable M-PESA Payment', 'woocommerce-mpesa-gateway'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce-mpesa-gateway'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-mpesa-gateway'),
                'default' => __('M-PESA', 'woocommerce-mpesa-gateway'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce-mpesa-gateway'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce-mpesa-gateway'),
                'default' => __('Pay securely using M-PESA mobile money.', 'woocommerce-mpesa-gateway'),
                'desc_tip' => true,
            ),
            'instructions' => array(
                'title' => __('Instructions', 'woocommerce-mpesa-gateway'),
                'type' => 'textarea',
                'description' => __('Instructions that will be added to the thank you page and emails.', 'woocommerce-mpesa-gateway'),
                'default' => __('You will receive an M-PESA prompt on your phone to complete the payment.', 'woocommerce-mpesa-gateway'),
                'desc_tip' => true,
            ),
            'testmode' => array(
                'title' => __('Test mode', 'woocommerce-mpesa-gateway'),
                'label' => __('Enable Test Mode', 'woocommerce-mpesa-gateway'),
                'type' => 'checkbox',
                'description' => __('Place the payment gateway in test mode using sandbox API credentials.', 'woocommerce-mpesa-gateway'),
                'default' => 'yes',
                'desc_tip' => true,
            ),
            'debug' => array(
                'title' => __('Debug Log', 'woocommerce-mpesa-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable logging', 'woocommerce-mpesa-gateway'),
                'default' => 'no',
                'description' => sprintf(__('Log M-PESA events, such as API requests, inside %s Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished.', 'woocommerce-mpesa-gateway'), '<code>' . WC_Log_Handler_File::get_log_file_path('mpesa') . '</code>'),
            ),
            'api_credentials' => array(
                'title' => __('API Credentials', 'woocommerce-mpesa-gateway'),
                'type' => 'title',
                'description' => sprintf(__('Get your API credentials from the <a href="%s" target="_blank">Safaricom Daraja Portal</a>.', 'woocommerce-mpesa-gateway'), 'https://developer.safaricom.co.ke/'),
            ),
            'consumer_key' => array(
                'title' => __('Consumer Key', 'woocommerce-mpesa-gateway'),
                'type' => 'text',
                'description' => __('Get this from Safaricom Daraja Portal', 'woocommerce-mpesa-gateway'),
                'desc_tip' => true,
            ),
            'consumer_secret' => array(
                'title' => __('Consumer Secret', 'woocommerce-mpesa-gateway'),
                'type' => 'password',
                'description' => __('Get this from Safaricom Daraja Portal', 'woocommerce-mpesa-gateway'),
                'desc_tip' => true,
            ),
            'shortcode' => array(
                'title' => __('Business Shortcode', 'woocommerce-mpesa-gateway'),
                'type' => 'text',
                'description' => __('Your M-PESA business shortcode (PayBill or Till Number)', 'woocommerce-mpesa-gateway'),
                'desc_tip' => true,
            ),
            'passkey' => array(
                'title' => __('Passkey', 'woocommerce-mpesa-gateway'),
                'type' => 'password',
                'description' => __('Lipa na M-PESA Online Passkey from Daraja Portal', 'woocommerce-mpesa-gateway'),
                'desc_tip' => true,
            ),
            'advanced' => array(
                'title' => __('Advanced Settings', 'woocommerce-mpesa-gateway'),
                'type' => 'title',
                'description' => __('Advanced configuration options for the M-PESA gateway.', 'woocommerce-mpesa-gateway'),
            ),
            'timeout' => array(
                'title' => __('Payment Timeout', 'woocommerce-mpesa-gateway'),
                'type' => 'number',
                'description' => __('Time in seconds to wait for customer payment confirmation', 'woocommerce-mpesa-gateway'),
                'default' => '300',
                'desc_tip' => true,
                'custom_attributes' => array(
                    'min' => 60,
                    'max' => 600,
                )
            ),
            'auto_complete' => array(
                'title' => __('Auto Complete Orders', 'woocommerce-mpesa-gateway'),
                'label' => __('Automatically complete orders after successful payment', 'woocommerce-mpesa-gateway'),
                'type' => 'checkbox',
                'description' => __('Orders will be marked as completed automatically when payment is received.', 'woocommerce-mpesa-gateway'),
                'default' => 'no',
                'desc_tip' => true,
            ),
        );
    }
    
    /**
     * Payment form on checkout page
     */
    public function payment_fields() {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }
        
        if ($this->testmode) {
            echo '<div class="wc-mpesa-test-mode-notice">';
            echo '<p><strong>' . __('TEST MODE ENABLED', 'woocommerce-mpesa-gateway') . '</strong> - ' . __('Use test phone number: 254708374149', 'woocommerce-mpesa-gateway') . '</p>';
            echo '</div>';
        }
        ?>
        <fieldset id="wc-<?php echo esc_attr($this->id); ?>-form" class="wc-mpesa-form wc-payment-form">
            <div class="form-row form-row-wide">
                <label for="mpesa-phone-number"><?php echo __('M-PESA Phone Number', 'woocommerce-mpesa-gateway'); ?> <span class="required">*</span></label>
                <input id="mpesa-phone-number" name="mpesa_phone_number" type="tel" 
                       placeholder="<?php echo esc_attr__('254712345678', 'woocommerce-mpesa-gateway'); ?>"
                       value="<?php echo esc_attr($this->get_customer_phone()); ?>"
                       class="input-text wc-mpesa-phone-number" 
                       required />
                <small class="wc-mpesa-phone-help"><?php echo __('Enter your M-PESA registered phone number (e.g., 254712345678)', 'woocommerce-mpesa-gateway'); ?></small>
            </div>
            <div class="clear"></div>
        </fieldset>
        <?php
    }
    
    /**
     * Validate payment fields on the frontend
     */
    public function validate_fields() {
        if (empty($_POST['mpesa_phone_number'])) {
            wc_add_notice(__('M-PESA phone number is required.', 'woocommerce-mpesa-gateway'), 'error');
            return false;
        }
        
        $phone = sanitize_text_field($_POST['mpesa_phone_number']);
        
        try {
            $formatted_phone = WC_Mpesa_Utils::format_phone($phone);
            if (!$formatted_phone) {
                throw new Exception(__('Invalid phone number format.', 'woocommerce-mpesa-gateway'));
            }
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            return false;
        }
        
        return true;
    }
    
    /**
     * Process the payment and return the result
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wc_add_notice(__('Order not found.', 'woocommerce-mpesa-gateway'), 'error');
            return array('result' => 'fail');
        }
        
        // Get and validate phone number
        $phone = sanitize_text_field($_POST['mpesa_phone_number']);
        try {
            $formatted_phone = WC_Mpesa_Utils::format_phone($phone);
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            return array('result' => 'fail');
        }
        
        // Update order with phone number
        $order->update_meta_data('_mpesa_phone_number', $formatted_phone);
        $order->save();
        
        // Initiate STK Push
        $stk_response = $this->api->initiate_stk_push($order, $formatted_phone);
        
        if ($stk_response['success']) {
            // Mark as pending (we're awaiting the payment)
            $order->update_status('pending', sprintf(__('M-PESA payment initiated. Checkout request ID: %s', 'woocommerce-mpesa-gateway'), $stk_response['data']['CheckoutRequestID']));
            
            // Store checkout request ID
            $order->update_meta_data('_mpesa_checkout_request_id', $stk_response['data']['CheckoutRequestID']);
            $order->update_meta_data('_mpesa_merchant_request_id', $stk_response['data']['MerchantRequestID']);
            $order->save();
            
            // Save transaction record
            $this->save_transaction_record($order, $stk_response['data'], $formatted_phone);
            
            // Reduce stock levels
            wc_reduce_stock_levels($order_id);
            
            // Remove cart
            WC()->cart->empty_cart();
            
            $this->log(sprintf('STK Push initiated successfully for order %d', $order_id));
            
            // Return thankyou redirect
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        } else {
            $error_message = sprintf(__('M-PESA payment initiation failed: %s', 'woocommerce-mpesa-gateway'), $stk_response['message']);
            wc_add_notice($error_message, 'error');
            $this->log($error_message, 'error');
            return array('result' => 'fail');
        }
    }
    
    /**
     * Handle M-PESA callback
     */
    public function handle_callback() {
        $callback_json = file_get_contents('php://input');
        $callback_data = json_decode($callback_json, true);
        
        $this->log('Callback received: ' . $callback_json);
        
        if (!$this->validate_callback($callback_data)) {
            $this->log('Invalid callback received', 'error');
            status_header(400);
            exit;
        }
        
        $stk_callback = $callback_data['Body']['stkCallback'];
        $checkout_request_id = $stk_callback['CheckoutRequestID'];
        $result_code = $stk_callback['ResultCode'];
        
        // Find order by checkout request ID
        $orders = wc_get_orders(array(
            'meta_key' => '_mpesa_checkout_request_id',
            'meta_value' => $checkout_request_id,
            'limit' => 1
        ));
        
        if (empty($orders)) {
            $this->log('Order not found for checkout request ID: ' . $checkout_request_id, 'error');
            status_header(404);
            exit;
        }
        
        $order = $orders[0];
        
        // Update transaction record
        $this->update_transaction_record($checkout_request_id, $result_code, $stk_callback);
        
        if ($result_code == 0) {
            // Payment successful
            $this->handle_successful_payment($order, $stk_callback);
        } else {
            // Payment failed
            $this->handle_failed_payment($order, $stk_callback);
        }
        
        status_header(200);
        exit;
    }
    
    /**
     * Handle successful payment
     */
    private function handle_successful_payment($order, $callback_data) {
        $callback_metadata = isset($callback_data['CallbackMetadata']['Item']) ? $callback_data['CallbackMetadata']['Item'] : array();
        
        $mpesa_receipt_number = '';
        $amount = '';
        $phone_number = '';
        $transaction_date = '';
        
        // Extract metadata
        foreach ($callback_metadata as $item) {
            switch ($item['Name']) {
                case 'MpesaReceiptNumber':
                    $mpesa_receipt_number = $item['Value'];
                    break;
                case 'Amount':
                    $amount = $item['Value'];
                    break;
                case 'PhoneNumber':
                    $phone_number = $item['Value'];
                    break;
                case 'TransactionDate':
                    $transaction_date = $item['Value'];
                    break;
            }
        }
        
        // Store payment details
        $order->update_meta_data('_mpesa_receipt_number', $mpesa_receipt_number);
        $order->update_meta_data('_mpesa_amount', $amount);
        $order->update_meta_data('_mpesa_transaction_date', $transaction_date);
        
        // Add order note
        $order->add_order_note(sprintf(
            __('M-PESA payment completed. Receipt: %s, Amount: %s, Phone: %s', 'woocommerce-mpesa-gateway'),
            $mpesa_receipt_number,
            $amount,
            $phone_number
        ));
        
        // Mark order as paid
        $order->payment_complete($mpesa_receipt_number);
        
        // Auto complete if enabled
        if ('yes' === $this->get_option('auto_complete')) {
            $order->update_status('completed');
        }
        
        $order->save();
        
        $this->log(sprintf('Payment completed for order %d. Receipt: %s', $order->get_id(), $mpesa_receipt_number));
    }
    
    /**
     * Handle failed payment
     */
    private function handle_failed_payment($order, $callback_data) {
        $result_desc = isset($callback_data['ResultDesc']) ? $callback_data['ResultDesc'] : __('Payment failed', 'woocommerce-mpesa-gateway');
        
        $order->update_status('failed', sprintf(__('M-PESA payment failed: %s', 'woocommerce-mpesa-gateway'), $result_desc));
        $order->save();
        
        $this->log(sprintf('Payment failed for order %d: %s', $order->get_id(), $result_desc), 'error');
    }
    
    /**
     * Output for the order received page
     */
    public function thankyou_page($order_id) {
        if ($this->instructions) {
            echo wpautop(wptexturize($this->instructions));
        }
        
        $order = wc_get_order($order_id);
        if ($order && $order->has_status('pending')) {
            echo '<div class="wc-mpesa-pending-notice">';
            echo '<p><strong>' . __('Payment Pending', 'woocommerce-mpesa-gateway') . '</strong></p>';
            echo '<p>' . __('Please complete the payment on your phone when prompted. Your order will be processed once payment is confirmed.', 'woocommerce-mpesa-gateway') . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Add content to the WC emails
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false) {
        if (!$sent_to_admin && 'mpesa' === $order->get_payment_method() && $order->has_status('pending')) {
            if ($this->instructions) {
                echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
            }
        }
    }
    
    /**
     * Process refund
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }
        
        $mpesa_receipt = $order->get_meta('_mpesa_receipt_number');
        if (!$mpesa_receipt) {
            return new WP_Error('mpesa_refund_error', __('M-PESA receipt number not found for this order.', 'woocommerce-mpesa-gateway'));
        }
        
        // Process refund via API
        $refund_response = $this->api->process_refund($order, $amount, $reason);
        
        if ($refund_response['success']) {
            $order->add_order_note(sprintf(__('M-PESA refund completed. Amount: %s, Reason: %s', 'woocommerce-mpesa-gateway'), $amount, $reason));
            return true;
        } else {
            return new WP_Error('mpesa_refund_error', $refund_response['message']);
        }
    }
    
    /**
     * Check if gateway is configured
     */
    public function is_configured() {
        return !empty($this->consumer_key) && !empty($this->consumer_secret) && !empty($this->shortcode) && !empty($this->passkey);
    }
    
    /**
     * Admin scripts
     */
    public function admin_scripts() {
        if ('woocommerce_page_wc-settings' !== get_current_screen()->id) {
            return;
        }
        
        wp_enqueue_script('wc-mpesa-admin', WC_MPESA_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), WC_MPESA_VERSION, true);
        wp_enqueue_style('wc-mpesa-admin', WC_MPESA_PLUGIN_URL . 'assets/css/admin.css', array(), WC_MPESA_VERSION);
    }
    
    /**
     * Get customer phone number
     */
    private function get_customer_phone() {
        $customer = WC()->customer;
        if ($customer) {
            $phone = $customer->get_billing_phone();
            if ($phone) {
                try {
                    return WC_Mpesa_Utils::format_phone($phone);
                } catch (Exception $e) {
                    return '';
                }
            }
        }
        return '';
    }
    
    /**
     * Validate callback data
     */
    private function validate_callback($callback_data) {
        // Check if callback has required structure
        if (!isset($callback_data['Body']['stkCallback']['CheckoutRequestID'])) {
            return false;
        }
        
        // Additional validation can be added here (IP validation, signature verification, etc.)
        
        return true;
    }
    
    /**
     * Save transaction record
     */
    private function save_transaction_record($order, $stk_data, $phone) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_mpesa_transactions';
        
        $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order->get_id(),
                'merchant_request_id' => $stk_data['MerchantRequestID'],
                'checkout_request_id' => $stk_data['CheckoutRequestID'],
                'phone_number' => $phone,
                'amount' => $order->get_total(),
            ),
            array('%d', '%s', '%s', '%s', '%f')
        );
    }
    
    /**
     * Update transaction record
     */
    private function update_transaction_record($checkout_request_id, $result_code, $callback_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_mpesa_transactions';
        
        $update_data = array(
            'result_code' => $result_code,
            'result_desc' => isset($callback_data['ResultDesc']) ? $callback_data['ResultDesc'] : ''
        );
        
        if ($result_code == 0 && isset($callback_data['CallbackMetadata']['Item'])) {
            foreach ($callback_data['CallbackMetadata']['Item'] as $item) {
                if ($item['Name'] === 'MpesaReceiptNumber') {
                    $update_data['mpesa_receipt_number'] = $item['Value'];
                    break;
                }
            }
        }
        
        $wpdb->update(
            $table_name,
            $update_data,
            array('checkout_request_id' => $checkout_request_id),
            array('%d', '%s', '%s'),
            array('%s')
        );
    }
    
    /**
     * Log messages
     */
    public function log($message, $level = 'info') {
        if (!$this->logger || 'yes' !== $this->debug) {
            return;
        }
        
        $this->logger->log($level, $message, array('source' => 'mpesa'));
    }
}
