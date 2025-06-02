<?php
/**
 * Plugin Name: WooCommerce M-PESA Payment Gateway
 * Plugin URI: https://github.com/yourusername/woocommerce-mpesa-gateway
 * Description: M-PESA payment gateway for WooCommerce using Safaricom Daraja API. Enables secure mobile money payments for Kenyan customers.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woocommerce-mpesa-gateway
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 3.0
 * WC tested up to: 8.4
 * Network: false
 *
 * @package WooCommerce_Mpesa_Gateway
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_MPESA_VERSION', '1.0.0');
define('WC_MPESA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_MPESA_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WC_MPESA_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Check if WooCommerce is active
 */
if (!function_exists('is_woocommerce_active')) {
    function is_woocommerce_active() {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
    }
}

/**
 * WooCommerce fallback notice
 */
function wc_mpesa_missing_wc_notice() {
    echo '<div class="error"><p><strong>' . sprintf(esc_html__('M-PESA Gateway requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-mpesa-gateway'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>') . '</strong></p></div>';
}

/**
 * Initialize the gateway
 */
add_action('plugins_loaded', 'wc_mpesa_gateway_init', 11);
function wc_mpesa_gateway_init() {
    // Check if WooCommerce is active
    if (!is_woocommerce_active()) {
        add_action('admin_notices', 'wc_mpesa_missing_wc_notice');
        return;
    }

    // Check if payment gateway class exists
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // Load text domain
    load_plugin_textdomain('woocommerce-mpesa-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    // Include the main gateway class
    require_once WC_MPESA_PLUGIN_PATH . 'includes/class-wc-mpesa-gateway.php';
    
    // Include API handler class
    require_once WC_MPESA_PLUGIN_PATH . 'includes/class-wc-mpesa-api.php';
    
    // Include utility functions
    require_once WC_MPESA_PLUGIN_PATH . 'includes/class-wc-mpesa-utils.php';

    /**
     * Add the gateway to WooCommerce
     */
    function wc_add_mpesa_gateway($methods) {
        $methods[] = 'WC_Mpesa_Gateway';
        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'wc_add_mpesa_gateway');
}

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, 'wc_mpesa_activate_plugin');
function wc_mpesa_activate_plugin() {
    // Check if WooCommerce is active
    if (!is_woocommerce_active()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('Please install and activate WooCommerce before activating the M-PESA Gateway plugin.', 'woocommerce-mpesa-gateway'));
    }
    
    // Create transaction log table
    wc_mpesa_create_transaction_table();
    
    // Set default options
    wc_mpesa_set_default_options();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, 'wc_mpesa_deactivate_plugin');
function wc_mpesa_deactivate_plugin() {
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Create transaction log table
 */
function wc_mpesa_create_transaction_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'wc_mpesa_transactions';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        merchant_request_id varchar(255) NOT NULL,
        checkout_request_id varchar(255) NOT NULL,
        mpesa_receipt_number varchar(255) DEFAULT NULL,
        phone_number varchar(20) NOT NULL,
        amount decimal(10,2) NOT NULL,
        result_code int(11) DEFAULT NULL,
        result_desc text DEFAULT NULL,
        transaction_date datetime DEFAULT CURRENT_TIMESTAMP,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY checkout_request_id (checkout_request_id),
        KEY order_id (order_id),
        KEY merchant_request_id (merchant_request_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Set default plugin options
 */
function wc_mpesa_set_default_options() {
    $default_options = array(
        'woocommerce_mpesa_settings' => array(
            'enabled' => 'no',
            'title' => 'M-PESA',
            'description' => 'Pay securely using M-PESA mobile money.',
            'instructions' => 'You will receive an M-PESA prompt on your phone to complete the payment.',
            'testmode' => 'yes',
            'debug' => 'no'
        )
    );
    
    foreach ($default_options as $option_name => $option_value) {
        if (!get_option($option_name)) {
            add_option($option_name, $option_value);
        }
    }
}

/**
 * Add custom action links
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_mpesa_action_links');
function wc_mpesa_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=mpesa') . '">' . __('Settings', 'woocommerce-mpesa-gateway') . '</a>',
        '<a href="https://developer.safaricom.co.ke/" target="_blank">' . __('API Docs', 'woocommerce-mpesa-gateway') . '</a>',
    );
    return array_merge($plugin_links, $links);
}

/**
 * Add custom links to plugin meta
 */
add_filter('plugin_row_meta', 'wc_mpesa_row_meta', 10, 2);
function wc_mpesa_row_meta($links, $file) {
    if (plugin_basename(__FILE__) == $file) {
        $row_meta = array(
            'docs' => '<a href="https://developer.safaricom.co.ke/docs" target="_blank">' . __('Documentation', 'woocommerce-mpesa-gateway') . '</a>',
            'support' => '<a href="https://github.com/yourusername/woocommerce-mpesa-gateway/issues" target="_blank">' . __('Support', 'woocommerce-mpesa-gateway') . '</a>',
        );
        return array_merge($links, $row_meta);
    }
    return (array) $links;
}

/**
 * Admin notice for configuration
 */
add_action('admin_notices', 'wc_mpesa_admin_notices');
function wc_mpesa_admin_notices() {
    if (!is_woocommerce_active()) {
        return;
    }
    
    $gateway = new WC_Mpesa_Gateway();
    if ('yes' === $gateway->enabled && !$gateway->is_configured()) {
        echo '<div class="notice notice-warning is-dismissible">
            <p><strong>' . __('M-PESA Gateway', 'woocommerce-mpesa-gateway') . '</strong>: ' . 
            sprintf(__('Please <a href="%s">configure your M-PESA API credentials</a> to start accepting payments.', 'woocommerce-mpesa-gateway'), 
            admin_url('admin.php?page=wc-settings&tab=checkout&section=mpesa')) . '</p>
        </div>';
    }
}
