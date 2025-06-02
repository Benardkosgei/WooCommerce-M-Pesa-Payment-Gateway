<?php
/**
 * WooCommerce M-PESA API Class
 * 
 * Handles all M-PESA API interactions using Safaricom Daraja API
 *
 * @package WooCommerce_Mpesa_Gateway
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * M-PESA API Handler Class
 */
class WC_Mpesa_API {
    
    /**
     * Gateway instance
     *
     * @var WC_Mpesa_Gateway
     */
    private $gateway;
    
    /**
     * API base URLs
     */
    const SANDBOX_BASE_URL = 'https://sandbox.safaricom.co.ke';
    const PRODUCTION_BASE_URL = 'https://api.safaricom.co.ke';
    
    /**
     * Constructor
     *
     * @param WC_Mpesa_Gateway $gateway
     */
    public function __construct($gateway) {
        $this->gateway = $gateway;
    }
    
    /**
     * Get base URL based on environment
     *
     * @return string
     */
    private function get_base_url() {
        return $this->gateway->testmode ? self::SANDBOX_BASE_URL : self::PRODUCTION_BASE_URL;
    }
    
    /**
     * Generate access token
     *
     * @return array
     */
    public function get_access_token() {
        $url = $this->get_base_url() . '/oauth/v1/generate?grant_type=client_credentials';
        
        $credentials = base64_encode($this->gateway->consumer_key . ':' . $this->gateway->consumer_secret);
        
        $headers = array(
            'Authorization' => 'Basic ' . $credentials,
            'Content-Type' => 'application/json',
        );
        
        $response = wp_remote_get($url, array(
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => !$this->gateway->testmode,
        ));
        
        if (is_wp_error($response)) {
            $this->gateway->log('Access token request failed: ' . $response->get_error_message(), 'error');
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['access_token'])) {
            $this->gateway->log('Access token generated successfully');
            return array(
                'success' => true,
                'token' => $data['access_token'],
                'expires_in' => $data['expires_in']
            );
        } else {
            $error = isset($data['errorMessage']) ? $data['errorMessage'] : 'Unknown error';
            $this->gateway->log('Access token generation failed: ' . $error, 'error');
            return array(
                'success' => false,
                'message' => $error
            );
        }
    }
    
    /**
     * Generate timestamp
     *
     * @return string
     */
    private function generate_timestamp() {
        return date('YmdHis');
    }
    
    /**
     * Generate password
     *
     * @param string $timestamp
     * @return string
     */
    private function generate_password($timestamp) {
        return base64_encode($this->gateway->shortcode . $this->gateway->passkey . $timestamp);
    }
    
    /**
     * Initiate STK Push
     *
     * @param WC_Order $order
     * @param string $phone
     * @return array
     */
    public function initiate_stk_push($order, $phone) {
        // Get access token
        $token_response = $this->get_access_token();
        if (!$token_response['success']) {
            return $token_response;
        }
        
        $access_token = $token_response['token'];
        $timestamp = $this->generate_timestamp();
        $password = $this->generate_password($timestamp);
        
        $url = $this->get_base_url() . '/mpesa/stkpush/v1/processrequest';
        
        $amount = intval($order->get_total());
        $callback_url = $this->gateway->callback_url;
        
        // Use test shortcode for sandbox
        $shortcode = $this->gateway->testmode ? '174379' : $this->gateway->shortcode;
        
        $request_data = array(
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => $callback_url,
            'AccountReference' => 'Order #' . $order->get_order_number(),
            'TransactionDesc' => sprintf('Payment for Order #%s', $order->get_order_number())
        );
        
        $headers = array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        );
        
        $this->gateway->log('STK Push request: ' . json_encode($request_data));
        
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => json_encode($request_data),
            'timeout' => 30,
            'sslverify' => !$this->gateway->testmode,
        ));
        
        if (is_wp_error($response)) {
            $this->gateway->log('STK Push request failed: ' . $response->get_error_message(), 'error');
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        $this->gateway->log('STK Push response: ' . $body);
        
        if (isset($data['ResponseCode']) && $data['ResponseCode'] == '0') {
            return array(
                'success' => true,
                'data' => $data
            );
        } else {
            $error = isset($data['errorMessage']) ? $data['errorMessage'] : 
                    (isset($data['ResponseDescription']) ? $data['ResponseDescription'] : 'Unknown error');
            return array(
                'success' => false,
                'message' => $error
            );
        }
    }
    
    /**
     * Query STK Push status
     *
     * @param string $checkout_request_id
     * @return array
     */
    public function query_stk_status($checkout_request_id) {
        // Get access token
        $token_response = $this->get_access_token();
        if (!$token_response['success']) {
            return $token_response;
        }
        
        $access_token = $token_response['token'];
        $timestamp = $this->generate_timestamp();
        $password = $this->generate_password($timestamp);
        
        $url = $this->get_base_url() . '/mpesa/stkpushquery/v1/query';
        
        $shortcode = $this->gateway->testmode ? '174379' : $this->gateway->shortcode;
        
        $request_data = array(
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkout_request_id
        );
        
        $headers = array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        );
        
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => json_encode($request_data),
            'timeout' => 30,
            'sslverify' => !$this->gateway->testmode,
        ));
        
        if (is_wp_error($response)) {
            $this->gateway->log('STK Query request failed: ' . $response->get_error_message(), 'error');
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        $this->gateway->log('STK Query response: ' . $body);
        
        if (isset($data['ResponseCode'])) {
            return array(
                'success' => true,
                'data' => $data
            );
        } else {
            $error = isset($data['errorMessage']) ? $data['errorMessage'] : 'Unknown error';
            return array(
                'success' => false,
                'message' => $error
            );
        }
    }
    
    /**
     * Process refund (B2C Transaction)
     *
     * @param WC_Order $order
     * @param float $amount
     * @param string $reason
     * @return array
     */
    public function process_refund($order, $amount, $reason) {
        // Get access token
        $token_response = $this->get_access_token();
        if (!$token_response['success']) {
            return $token_response;
        }
        
        $access_token = $token_response['token'];
        
        // Generate security credential
        $credential_response = $this->generate_security_credential();
        if (!$credential_response['success']) {
            return $credential_response;
        }
        
        $security_credential = $credential_response['credential'];
        
        $url = $this->get_base_url() . '/mpesa/b2c/v1/paymentrequest';
        
        $phone = $order->get_meta('_mpesa_phone_number');
        $initiator = $this->gateway->testmode ? 'testapi' : $this->gateway->get_option('initiator_name');
        $command_id = 'BusinessPayment'; // For refunds
        
        $request_data = array(
            'InitiatorName' => $initiator,
            'SecurityCredential' => $security_credential,
            'CommandID' => $command_id,
            'Amount' => intval($amount),
            'PartyA' => $this->gateway->shortcode,
            'PartyB' => $phone,
            'Remarks' => $reason,
            'QueueTimeOutURL' => $this->gateway->callback_url,
            'ResultURL' => $this->gateway->callback_url,
            'Occasion' => 'Refund for Order #' . $order->get_order_number()
        );
        
        $headers = array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        );
        
        $this->gateway->log('B2C Refund request: ' . json_encode($request_data));
        
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => json_encode($request_data),
            'timeout' => 30,
            'sslverify' => !$this->gateway->testmode,
        ));
        
        if (is_wp_error($response)) {
            $this->gateway->log('B2C Refund request failed: ' . $response->get_error_message(), 'error');
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        $this->gateway->log('B2C Refund response: ' . $body);
        
        if (isset($data['ResponseCode']) && $data['ResponseCode'] == '0') {
            return array(
                'success' => true,
                'data' => $data
            );
        } else {
            $error = isset($data['errorMessage']) ? $data['errorMessage'] : 
                    (isset($data['ResponseDescription']) ? $data['ResponseDescription'] : 'Unknown error');
            return array(
                'success' => false,
                'message' => $error
            );
        }
    }
    
    /**
     * Generate security credential for B2C transactions
     *
     * @return array
     */
    private function generate_security_credential() {
        $initiator_password = $this->gateway->testmode ? 'Safcom496!' : $this->gateway->get_option('initiator_password');
        
        if (empty($initiator_password)) {
            return array(
                'success' => false,
                'message' => 'Initiator password not configured'
            );
        }
        
        // Get certificate
        $certificate_url = $this->gateway->testmode ? 
            'https://developer.safaricom.co.ke/api/v1/GenerateSecurityCredential/SandboxCertificate.cer' :
            'https://developer.safaricom.co.ke/api/v1/GenerateSecurityCredential/ProductionCertificate.cer';
        
        $cert_response = wp_remote_get($certificate_url);
        
        if (is_wp_error($cert_response)) {
            return array(
                'success' => false,
                'message' => 'Failed to retrieve certificate: ' . $cert_response->get_error_message()
            );
        }
        
        $certificate = wp_remote_retrieve_body($cert_response);
        
        // Encrypt password
        $public_key = openssl_pkey_get_public($certificate);
        
        if (!$public_key) {
            return array(
                'success' => false,
                'message' => 'Invalid certificate'
            );
        }
        
        $encrypted = '';
        $result = openssl_public_encrypt($initiator_password, $encrypted, $public_key, OPENSSL_PKCS1_PADDING);
        
        if (!$result) {
            return array(
                'success' => false,
                'message' => 'Failed to encrypt password'
            );
        }
        
        return array(
            'success' => true,
            'credential' => base64_encode($encrypted)
        );
    }
    
    /**
     * Validate transaction
     *
     * @param array $transaction_data
     * @return array
     */
    public function validate_transaction($transaction_data) {
        // Implementation for C2B validation
        // This would be called by M-PESA before processing payment
        
        return array(
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted'
        );
    }
    
    /**
     * Confirm transaction
     *
     * @param array $transaction_data
     * @return array
     */
    public function confirm_transaction($transaction_data) {
        // Implementation for C2B confirmation
        // This would be called by M-PESA after successful payment
        
        return array(
            'ResultCode' => 0,
            'ResultDesc' => 'Success'
        );
    }
    
    /**
     * Register URLs for C2B
     *
     * @return array
     */
    public function register_urls() {
        // Get access token
        $token_response = $this->get_access_token();
        if (!$token_response['success']) {
            return $token_response;
        }
        
        $access_token = $token_response['token'];
        
        $url = $this->get_base_url() . '/mpesa/c2b/v1/registerurl';
        
        $shortcode = $this->gateway->testmode ? '600984' : $this->gateway->shortcode;
        
        $request_data = array(
            'ShortCode' => $shortcode,
            'ResponseType' => 'Completed',
            'ConfirmationURL' => home_url('/wc-api/mpesa_c2b_confirmation/'),
            'ValidationURL' => home_url('/wc-api/mpesa_c2b_validation/')
        );
        
        $headers = array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        );
        
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => json_encode($request_data),
            'timeout' => 30,
            'sslverify' => !$this->gateway->testmode,
        ));
        
        if (is_wp_error($response)) {
            $this->gateway->log('URL Registration failed: ' . $response->get_error_message(), 'error');
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        $this->gateway->log('URL Registration response: ' . $body);
        
        if (isset($data['ResponseCode']) && $data['ResponseCode'] == '0') {
            return array(
                'success' => true,
                'data' => $data
            );
        } else {
            $error = isset($data['errorMessage']) ? $data['errorMessage'] : 
                    (isset($data['ResponseDescription']) ? $data['ResponseDescription'] : 'Unknown error');
            return array(
                'success' => false,
                'message' => $error
            );
        }
    }
}
