<?php
/**
 * WooCommerce M-PESA Utility Functions
 *
 * @package WooCommerce_Mpesa_Gateway
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * M-PESA Utility Functions Class
 */
class WC_Mpesa_Utils {
    
    /**
     * Format phone number to M-PESA format
     *
     * @param string $phone
     * @return string|false
     * @throws Exception
     */
    public static function format_phone($phone) {
        if (empty($phone)) {
            throw new Exception(__('Phone number is required.', 'woocommerce-mpesa-gateway'));
        }
        
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove leading zeros
        $phone = ltrim($phone, '0');
        
        // Handle different formats
        if (strlen($phone) == 9) {
            // Format: 712345678 -> 254712345678
            $phone = '254' . $phone;
        } elseif (strlen($phone) == 10 && substr($phone, 0, 1) == '7') {
            // Format: 0712345678 -> 254712345678
            $phone = '254' . $phone;
        } elseif (strlen($phone) == 12 && substr($phone, 0, 3) == '254') {
            // Already in correct format: 254712345678
            // Do nothing
        } else {
            throw new Exception(__('Invalid Kenyan phone number format. Please use format like 254712345678 or 0712345678.', 'woocommerce-mpesa-gateway'));
        }
        
        // Validate Kenyan mobile number
        if (!self::is_valid_kenyan_mobile($phone)) {
            throw new Exception(__('Invalid Kenyan mobile number. Please provide a valid Safaricom, Airtel, or Telkom number.', 'woocommerce-mpesa-gateway'));
        }
        
        return $phone;
    }
    
    /**
     * Validate Kenyan mobile number
     *
     * @param string $phone
     * @return bool
     */
    public static function is_valid_kenyan_mobile($phone) {
        // Must start with 254 and be 12 digits
        if (strlen($phone) !== 12 || substr($phone, 0, 3) !== '254') {
            return false;
        }
        
        // Get the prefix after country code
        $prefix = substr($phone, 3, 3);
        
        // Valid Kenyan mobile prefixes
        $valid_prefixes = array(
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
        );
        
        return in_array($prefix, $valid_prefixes);
    }
    
    /**
     * Mask phone number for display
     *
     * @param string $phone
     * @return string
     */
    public static function mask_phone($phone) {
        if (strlen($phone) !== 12) {
            return $phone;
        }
        
        return substr($phone, 0, 6) . '***' . substr($phone, -3);
    }
    
    /**
     * Format amount for M-PESA
     *
     * @param float $amount
     * @return int
     */
    public static function format_amount($amount) {
        return intval(round($amount));
    }
    
    /**
     * Validate amount
     *
     * @param float $amount
     * @return bool
     */
    public static function is_valid_amount($amount) {
        $amount = floatval($amount);
        return $amount >= 1 && $amount <= 150000; // M-PESA limits
    }
    
    /**
     * Generate unique transaction reference
     *
     * @param int $order_id
     * @return string
     */
    public static function generate_transaction_reference($order_id) {
        return 'WC' . $order_id . '_' . time();
    }
    
    /**
     * Sanitize callback data
     *
     * @param array $data
     * @return array
     */
    public static function sanitize_callback_data($data) {
        if (!is_array($data)) {
            return array();
        }
        
        $sanitized = array();
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[sanitize_key($key)] = self::sanitize_callback_data($value);
            } else {
                $sanitized[sanitize_key($key)] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate timestamp
     *
     * @param string $timestamp
     * @return bool
     */
    public static function is_valid_timestamp($timestamp) {
        return preg_match('/^\d{14}$/', $timestamp) && strlen($timestamp) === 14;
    }
    
    /**
     * Convert M-PESA timestamp to WordPress format
     *
     * @param string $mpesa_timestamp Format: 20231201143045
     * @return string
     */
    public static function convert_mpesa_timestamp($mpesa_timestamp) {
        if (!self::is_valid_timestamp($mpesa_timestamp)) {
            return current_time('mysql');
        }
        
        $year = substr($mpesa_timestamp, 0, 4);
        $month = substr($mpesa_timestamp, 4, 2);
        $day = substr($mpesa_timestamp, 6, 2);
        $hour = substr($mpesa_timestamp, 8, 2);
        $minute = substr($mpesa_timestamp, 10, 2);
        $second = substr($mpesa_timestamp, 12, 2);
        
        return sprintf('%s-%s-%s %s:%s:%s', $year, $month, $day, $hour, $minute, $second);
    }
    
    /**
     * Check if IP is from M-PESA servers
     *
     * @param string $ip
     * @return bool
     */
    public static function is_mpesa_ip($ip) {
        // M-PESA callback IP addresses
        $valid_ips = array(
            '196.201.214.200',
            '196.201.214.206',
            '196.201.213.114',
            '196.201.214.207',
            '196.201.214.208',
            '196.201.213.44',
            '196.201.212.127',
            '196.201.212.138',
            '196.201.212.129',
            '196.201.212.136',
            '196.201.212.74',
            '196.201.212.69'
        );
        
        return in_array($ip, $valid_ips);
    }
    
    /**
     * Get client IP address
     *
     * @return string
     */
    public static function get_client_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Handle comma-separated list of IPs
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }
    
    /**
     * Encrypt sensitive data
     *
     * @param string $data
     * @return string
     */
    public static function encrypt_data($data) {
        if (!function_exists('openssl_encrypt')) {
            return base64_encode($data);
        }
        
        $key = wp_salt('secure_auth');
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     *
     * @param string $encrypted_data
     * @return string
     */
    public static function decrypt_data($encrypted_data) {
        if (!function_exists('openssl_decrypt')) {
            return base64_decode($encrypted_data);
        }
        
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $key = wp_salt('secure_auth');
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Generate secure random string
     *
     * @param int $length
     * @return string
     */
    public static function generate_random_string($length = 32) {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length / 2));
        } else {
            return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
        }
    }
    
    /**
     * Log transaction data
     *
     * @param array $data
     * @param string $type
     */
    public static function log_transaction($data, $type = 'info') {
        $logger = wc_get_logger();
        $context = array('source' => 'mpesa-transaction');
        
        $message = sprintf('[%s] %s', strtoupper($type), json_encode($data));
        $logger->log($type, $message, $context);
    }
    
    /**
     * Get network operator from phone number
     *
     * @param string $phone
     * @return string
     */
    public static function get_network_operator($phone) {
        if (strlen($phone) !== 12 || substr($phone, 0, 3) !== '254') {
            return 'unknown';
        }
        
        $prefix = substr($phone, 3, 3);
        
        // Safaricom prefixes
        $safaricom_prefixes = array(
            '701', '702', '703', '704', '705', '706', '707', '708', '709',
            '710', '711', '712', '713', '714', '715', '716', '717', '718', '719',
            '720', '721', '722', '723', '724', '725', '726', '727', '728', '729',
            '740', '741', '742', '743', '744', '745', '746', '747', '748', '749'
        );
        
        // Airtel prefixes
        $airtel_prefixes = array(
            '730', '731', '732', '733', '734', '735', '736', '737', '738', '739',
            '750', '751', '752', '753', '754', '755', '756', '757', '758', '759',
            '780', '781', '782', '783', '784', '785', '786', '787', '788', '789'
        );
        
        // Telkom prefixes
        $telkom_prefixes = array(
            '770', '771', '772', '773', '774', '775', '776', '777', '778', '779'
        );
        
        if (in_array($prefix, $safaricom_prefixes)) {
            return 'safaricom';
        } elseif (in_array($prefix, $airtel_prefixes)) {
            return 'airtel';
        } elseif (in_array($prefix, $telkom_prefixes)) {
            return 'telkom';
        }
        
        return 'unknown';
    }
    
    /**
     * Check if order can be refunded
     *
     * @param WC_Order $order
     * @return bool
     */
    public static function can_refund_order($order) {
        if (!$order || 'mpesa' !== $order->get_payment_method()) {
            return false;
        }
        
        // Check if order has M-PESA receipt
        $receipt = $order->get_meta('_mpesa_receipt_number');
        if (!$receipt) {
            return false;
        }
        
        // Check order status
        $valid_statuses = array('completed', 'processing', 'refunded');
        if (!in_array($order->get_status(), $valid_statuses)) {
            return false;
        }
        
        // Check if order is not too old (M-PESA has time limits)
        $order_date = $order->get_date_created();
        $days_old = (time() - $order_date->getTimestamp()) / DAY_IN_SECONDS;
        
        if ($days_old > 365) { // 1 year limit
            return false;
        }
        
        return true;
    }
    
    /**
     * Format currency for display
     *
     * @param float $amount
     * @param string $currency
     * @return string
     */
    public static function format_currency($amount, $currency = 'KES') {
        return sprintf('%s %s', $currency, number_format($amount, 2));
    }
    
    /**
     * Check if WooCommerce is compatible
     *
     * @return bool
     */
    public static function is_woocommerce_compatible() {
        if (!class_exists('WooCommerce')) {
            return false;
        }
        
        $wc_version = WC()->version;
        $min_version = '3.0.0';
        
        return version_compare($wc_version, $min_version, '>=');
    }
    
    /**
     * Get supported currencies
     *
     * @return array
     */
    public static function get_supported_currencies() {
        return array('KES'); // Only Kenyan Shilling supported
    }
    
    /**
     * Check if current currency is supported
     *
     * @return bool
     */
    public static function is_currency_supported() {
        $current_currency = get_woocommerce_currency();
        return in_array($current_currency, self::get_supported_currencies());
    }
}
