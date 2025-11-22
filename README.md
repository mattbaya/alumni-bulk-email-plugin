# Antioch Alumni Bulk Email WordPress Plugin

A comprehensive WordPress plugin for sending bulk emails to alumni with advanced tracking, bounce management, and automatic updates from GitHub.

## 🚀 Features

### 📊 **Email List Management**
- **Save uploaded CSV files** as reusable email lists
- **View and manage subscribers** with detailed information
- **Export lists to CSV** with bounce data and status
- **Real-time statistics**: Active vs bounced subscribers
- **Automatic bounce tracking** updates subscriber status

### 🔄 **Mailgun Integration**
- **Dual sending methods**: API and SMTP
- **Real-time webhook handling** for bounces, opens, clicks
- **Automatic bounce updates** to subscriber records
- **Custom domain support** for professional sending
- **Rate limiting** to prevent overwhelming recipients

### 📈 **Advanced Tracking & Analytics**
- **Campaign performance** metrics
- **Individual email logs** with timestamps
- **Bounce tracking** with detailed reasons
- **Open and click tracking**
- **CSV export** of all campaign data
- **Subscriber status management** (active/bounced)

### 🔄 **Auto-Updates from GitHub**
- **Automatic update checking** from this GitHub repository
- **One-click updates** through WordPress admin
- **Version management** with semantic versioning
- **Secure updates** with integrity verification

## 📋 Installation

### Method 1: Download from GitHub
1. **Download** the latest release from [Releases](https://github.com/mattbaya/antioch-bulk-email-plugin/releases)
2. **Upload** to your WordPress site via Plugins → Add New → Upload Plugin
3. **Activate** the plugin
4. **Configure** settings in Bulk Email → Settings

### Method 2: Git Clone (for developers)
```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/mattbaya/antioch-bulk-email-plugin.git
cd antioch-bulk-email-plugin
composer install
```

### Method 3: Download ZIP
1. **Download** this repository as a ZIP file
2. **Extract** to your `wp-content/plugins/` directory
3. **Activate** in WordPress admin

## ⚙️ Configuration

### 1. Mailgun Settings
Navigate to **Bulk Email → Settings** and configure:

- **Mailgun API Key**: Your production API key from Mailgun
- **Mailgun Domain**: `alumni.antiochians.org`
- **From Email**: `antiochalumni@alumni.antiochians.org`
- **From Name**: `Antioch Alumni Association`
- **SMTP Settings**: Optional, for SMTP sending method

### 2. Webhook Setup
Add this webhook URL in your Mailgun dashboard:
```
https://yoursite.com/wp-admin/admin-ajax.php?action=handle_mailgun_webhook
```

Configure these events in Mailgun:
- `delivered`
- `failed` 
- `bounced`
- `opened`
- `clicked`

## 🔄 Automatic Updates

This plugin automatically checks for updates from this GitHub repository. When a new version is available:

1. **Update notification** appears in WordPress admin
2. **Click "Update Now"** to install automatically
3. **Changelog** displays what's new in each version

### Manual Update Check
Go to **Dashboard → Updates** to manually check for plugin updates.

## 🔧 Development

### Requirements
- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **Composer**: For dependency management

### Setup Development Environment
```bash
git clone https://github.com/mattbaya/antioch-bulk-email-plugin.git
cd antioch-bulk-email-plugin
composer install
```

### Release Process
1. Update version in `antioch-bulk-email.php`
2. Update `CHANGELOG.md` with new features
3. Create a new GitHub release with version tag
4. Plugin will automatically notify users of updates

## 📊 Database Structure

### Tables Created
- `wp_antioch_email_lists` - Saved email lists
- `wp_antioch_email_subscribers` - Individual contacts with bounce tracking
- `wp_antioch_email_campaigns` - Campaign records
- `wp_antioch_email_logs` - Detailed email sending logs

## 🛡️ Security

- **WordPress options** store all settings securely
- **Nonce verification** for all admin actions
- **Permission checks** for administrative functions
- **SQL injection protection** with prepared statements
- **Input sanitization** for all user inputs

## 🔄 Bounce Handling

### Automatic Process
1. **Email bounces** in Mailgun
2. **Webhook notification** sent to WordPress
3. **Plugin processes** bounce data automatically
4. **Subscriber record** updated with bounce count and reason
5. **Status changed** to 'bounced' after 3 bounces
6. **List statistics** updated in real-time

### Bounce Management
- View bounce reasons in Email Lists → View Subscribers
- Export updated lists with bounce data
- Automatically exclude bounced subscribers from future campaigns

## 📝 Usage

### Creating Email Campaigns
1. Navigate to **Bulk Email**
2. Choose recipient source (existing list or upload CSV)
3. Write your email content with personalization tags
4. Add optional attachments
5. Configure sending method and timing
6. Send campaign and monitor progress

### Managing Email Lists
1. Go to **Bulk Email → Email Lists**
2. View subscriber details and bounce statistics
3. Export updated lists with current status
4. Monitor list health over time

### Personalization
Use these merge tags in subject lines and content:
- `{name}` - Full name
- `{first_name}` - First name only  
- `{last_name}` - Last name only
- `{email}` - Email address

## 📊 Analytics & Reporting

### Campaign Analytics
- Total emails sent
- Bounce count and rate
- Open and click tracking
- Detailed recipient logs

### List Analytics  
- Active vs bounced subscribers
- Bounce rate trends
- Subscriber engagement history

### CSV Exports
- Complete campaign logs with timestamps
- Updated subscriber lists with bounce data
- Bounce reason details and statistics

## 🆘 Troubleshooting

### Common Issues

**Plugin not updating automatically:**
- Check WordPress permissions for plugin updates
- Verify GitHub repository is accessible
- Check WordPress update settings

**Bounces not updating subscriber status:**
- Verify webhook URL is configured in Mailgun
- Check webhook events are enabled (bounced, failed)
- Test webhook URL accessibility

**Emails not sending:**
- Verify Mailgun API credentials
- Check from email domain matches Mailgun domain
- Test with small recipient list first

### Debug Mode
Enable WordPress debug mode to see detailed error messages:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 📞 Support

For issues or questions:
1. Check plugin logs in **Bulk Email → Email Logs**
2. Verify webhook configuration in Mailgun
3. Test with small recipient lists
4. Open an issue on [GitHub](https://github.com/mattbaya/antioch-bulk-email-plugin/issues)

## 🔄 Changelog

### Version 1.0.0
- Initial release
- Email list management
- Mailgun integration
- Automatic bounce tracking
- GitHub auto-updates
- WordPress admin interface

---

**License**: GPL v2 or later  
**Repository**: https://github.com/mattbaya/antioch-bulk-email-plugin  
**Author**: Antioch Alumni Association