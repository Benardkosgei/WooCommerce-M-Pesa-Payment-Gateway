# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Planned features for future releases

### Changed
- Improvements to existing features

### Deprecated
- Features that will be removed in future versions

### Removed
- Features removed in this version

### Fixed
- Bug fixes

### Security
- Security improvements

## [1.0.0] - 2024-01-01

### Added
- Initial release of WooCommerce M-PESA Payment Gateway
- STK Push integration with Safaricom Daraja API
- Real-time payment processing with callback handling
- Test and production environment support
- Phone number validation and formatting for Kenyan mobile numbers
- Network operator detection (Safaricom, Airtel, Telkom)
- Comprehensive transaction logging and debugging
- Refund support through M-PESA B2C API
- Security features including data encryption and callback validation
- Native WooCommerce integration with orders and email notifications
- Responsive admin interface with configuration validation
- Frontend JavaScript for enhanced user experience
- Multi-language support with translation template
- Comprehensive documentation and setup guide

### Features
- **Payment Processing**
  - STK Push (Lipa na M-PESA Online) integration
  - Real-time payment confirmations via webhooks
  - Automatic order status updates
  - Payment timeout handling
  - Transaction retry mechanism

- **Security**
  - SSL/TLS encrypted communications
  - Callback data validation and sanitization
  - IP whitelisting for M-PESA callbacks
  - Secure storage of API credentials
  - Data encryption for sensitive information

- **User Experience**
  - Smart phone number formatting and validation
  - Network operator detection and display
  - Real-time form validation
  - Payment status updates on thank you page
  - Mobile-responsive design

- **Admin Features**
  - Easy configuration interface
  - Test connection functionality
  - Comprehensive transaction logging
  - Debug mode for troubleshooting
  - Order management integration
  - Refund processing support

- **Developer Features**
  - Clean, documented codebase
  - WordPress coding standards compliance
  - Extensible architecture
  - Comprehensive error handling
  - Unit test compatibility
  - Translation support

### Technical Specifications
- **Requirements**
  - WordPress 5.0 or higher
  - WooCommerce 3.0 or higher
  - PHP 7.4 or higher
  - SSL certificate (production)
  - Safaricom Daraja API credentials

- **Supported APIs**
  - M-Pesa Express (STK Push)
  - M-Pesa Express Query
  - B2C (Business to Customer) for refunds
  - OAuth for authentication

- **Supported Networks**
  - Safaricom (Primary)
  - Airtel Kenya
  - Telkom Kenya

### Installation & Setup
- WordPress plugin architecture
- Easy installation via admin dashboard
- Step-by-step configuration guide
- Sandbox testing environment
- Production deployment checklist

### Documentation
- Comprehensive README with setup instructions
- API integration documentation
- Troubleshooting guide
- Security best practices
- Developer API reference

### Tested Compatibility
- WordPress 6.4
- WooCommerce 8.4
- PHP 8.0, 8.1, 8.2
- MySQL 5.7+
- Major hosting providers in Kenya

## Security Notice

This version includes several security enhancements:
- Encrypted storage of API credentials
- Callback IP validation
- Data sanitization and validation
- Secure random string generation
- Protection against common vulnerabilities

## Upgrade Notice

### From Development to 1.0.0
This is the initial stable release. Please follow the installation instructions in the README file.

## Known Issues

### Version 1.0.0
- None reported

## Support

For support and bug reports, please use the GitHub Issues page:
https://github.com/yourusername/woocommerce-mpesa-gateway/issues

## Contributing

We welcome contributions! Please read our contributing guidelines and submit pull requests to the develop branch.

## License

This plugin is licensed under GPL v2 or later. See LICENSE file for details.

---

**Note**: M-PESA is a trademark of Safaricom PLC. This plugin is not officially endorsed by Safaricom.
