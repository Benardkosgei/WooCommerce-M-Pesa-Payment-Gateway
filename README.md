# WooCommerce M-PESA Payment Gateway

A secure and reliable M-PESA payment gateway for WooCommerce that integrates with Safaricom's Daraja API to enable mobile money payments for Kenyan customers.

## Features

- **STK Push Integration**: Seamless customer-initiated payments
- **Real-time Payment Processing**: Instant payment confirmations via callbacks
- **Test & Production Modes**: Sandbox testing with easy production deployment
- **Phone Number Validation**: Smart formatting and validation for Kenyan mobile numbers
- **Network Detection**: Automatic detection of Safaricom, Airtel, and Telkom networks
- **Transaction Logging**: Comprehensive logging for debugging and monitoring
- **Refund Support**: Process refunds through M-PESA B2C API
- **Security Features**: Data encryption, callback validation, and IP whitelisting
- **WooCommerce Integration**: Native integration with WooCommerce orders and emails

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.4 or higher
- SSL Certificate (required for production)
- Safaricom Daraja API credentials

## Installation

### Method 1: Upload Plugin Files

1. Download or clone this repository
2. Upload the `woocommerce-mpesa-gateway` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to WooCommerce > Settings > Payments to configure the gateway

### Method 2: Install from Admin Dashboard

1. Go to Plugins > Add New in your WordPress admin
2. Click "Upload Plugin" and select the plugin zip file
3. Install and activate the plugin
4. Navigate to WooCommerce > Settings > Payments > M-PESA

## Configuration

### 1. Safaricom Daraja API Setup

Before configuring the plugin, you need to obtain API credentials from Safaricom:

1. Visit [Safaricom Daraja Portal](https://developer.safaricom.co.ke/)
2. Create an account and log in
3. Create a new app to get your Consumer Key and Consumer Secret
4. Subscribe to the following APIs:
   - M-Pesa Express (STK Push)
   - M-Pesa Express Query
   - B2C (for refunds)
5. Note down your:
   - Consumer Key
   - Consumer Secret
   - Business Shortcode
   - Lipa na M-Pesa Online Passkey

### 2. Plugin Configuration

1. **Enable the Gateway**
   - Go to WooCommerce > Settings > Payments
   - Find "M-PESA" in the list and click "Set up"
   - Check "Enable M-PESA Payment"

2. **Basic Settings**
   - **Title**: Name displayed to customers (default: "M-PESA")
   - **Description**: Description shown during checkout
   - **Instructions**: Text shown on thank you page and emails

3. **API Credentials**
   - **Consumer Key**: Your Daraja API Consumer Key
   - **Consumer Secret**: Your Daraja API Consumer Secret
   - **Business Shortcode**: Your M-PESA business number (PayBill or Till Number)
   - **Passkey**: Lipa na M-PESA Online Passkey

4. **Environment Settings**
   - **Test Mode**: Enable for testing with sandbox credentials
   - **Debug Log**: Enable to log API requests for debugging

5. **Advanced Settings**
   - **Payment Timeout**: Time to wait for payment confirmation (300 seconds default)
   - **Auto Complete Orders**: Automatically mark orders as completed after payment

### 3. Testing Configuration

For testing, use these sandbox credentials:

- **Base URL**: `https://sandbox.safaricom.co.ke`
- **Test Shortcode**: `174379`
- **Test Phone Number**: `254708374149`
- **Test Consumer Key**: Get from your Daraja sandbox app
- **Test Consumer Secret**: Get from your Daraja sandbox app

### 4. Production Setup

Before going live:

1. **Obtain Production Credentials**
   - Get production API credentials from Daraja Portal
   - Ensure your business is registered for M-PESA services

2. **SSL Certificate**
   - Install and activate SSL certificate on your website
   - Ensure all pages load over HTTPS

3. **IP Whitelisting**
   - Whitelist M-PESA callback IPs in your server firewall
   - Configure your hosting provider if necessary

4. **Go Live Process**
   - Follow Safaricom's go-live process at [https://developer.safaricom.co.ke/GoLive](https://developer.safaricom.co.ke/GoLive)
   - Test with small amounts before full deployment

## Usage

### For Customers

1. Select M-PESA as payment method during checkout
2. Enter M-PESA registered phone number
3. Click "Place Order"
4. Receive STK push notification on phone
5. Enter M-PESA PIN to complete payment
6. Receive SMS confirmation

### For Store Owners

1. **Monitor Transactions**
   - View transaction logs in WooCommerce > Settings > Payments > M-PESA
   - Check order notes for payment details

2. **Process Refunds**
   - Go to WooCommerce > Orders
   - Open the order and click "Refund"
   - Refunds are processed through M-PESA B2C API

3. **Troubleshooting**
   - Enable debug logging to troubleshoot issues
   - Check logs in WooCommerce > Status > Logs

## File Structure

```
woocommerce-mpesa-gateway/
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   ├── js/
│   │   ├── admin.js
│   │   └── frontend.js
│   └── images/
│       └── mpesa-logo.png
├── includes/
│   ├── class-wc-mpesa-gateway.php
│   ├── class-wc-mpesa-api.php
│   └── class-wc-mpesa-utils.php
├── languages/
├── templates/
├── README.md
└── woocommerce-mpesa-gateway.php
```

## Security Considerations

### Data Protection
- All sensitive data is encrypted before storage
- API credentials are stored securely in WordPress options
- Customer phone numbers are validated and sanitized

### Callback Security
- IP validation against Safaricom's official IP ranges
- Callback data validation and sanitization
- Transaction verification before order completion

### SSL Requirements
- SSL certificate required for production use
- All API communications use HTTPS
- Callback URLs must be HTTPS in production

## API Integration Details

### STK Push Flow
1. Customer initiates payment
2. Plugin sends STK push request to Daraja API
3. Customer receives prompt on phone
4. Customer enters PIN to confirm
5. Safaricom sends callback to plugin
6. Order status updated based on payment result

### Supported APIs
- **M-Pesa Express (STK Push)**: Customer-initiated payments
- **M-Pesa Express Query**: Check payment status
- **B2C**: Process refunds
- **C2B**: Alternative payment confirmation

## Troubleshooting

### Common Issues

1. **STK Push Not Received**
   - Verify phone number format (254XXXXXXXXX)
   - Check if customer's phone is M-PESA registered
   - Ensure test mode is enabled for sandbox testing

2. **Callback Not Received**
   - Verify callback URL is accessible
   - Check SSL certificate validity
   - Ensure server allows incoming connections from M-PESA IPs

3. **Invalid Credentials Error**
   - Double-check Consumer Key and Consumer Secret
   - Ensure credentials match the environment (sandbox/production)
   - Verify API subscriptions in Daraja Portal

4. **Payment Timeout**
   - Customer took too long to enter PIN
   - Network connectivity issues
   - Phone not available or switched off

### Debug Logging

Enable debug logging to troubleshoot issues:

1. Go to WooCommerce > Settings > Payments > M-PESA
2. Check "Enable logging"
3. View logs at WooCommerce > Status > Logs
4. Look for entries with source "mpesa"

### Support

For technical support:

1. Check the [GitHub Issues](https://github.com/benardkosgei/woocommerce-mpesa-gateway/issues)
2. Review [Safaricom Daraja Documentation](https://developer.safaricom.co.ke/docs)
3. Contact your hosting provider for server-related issues

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### Version 1.0.0
- Initial release
- STK Push integration
- Phone number validation
- Transaction logging
- Refund support
- Test and production modes

## Credits

- Built for WooCommerce
- Integrates with Safaricom Daraja API
- Follows WordPress coding standards
- Responsive design for mobile and desktop

---

**Note**: This plugin is not officially endorsed by Safaricom. M-PESA is a trademark of Safaricom PLC.
