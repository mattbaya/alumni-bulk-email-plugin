# Alumni Bulk Email WordPress Plugin

A comprehensive WordPress plugin for sending bulk emails to alumni with advanced list management, dynamic column detection, campaign tracking, and professional email delivery through Mailgun integration.

## 🚀 Key Features Overview

### 📊 **Smart List Management**
- **Dynamic Column Detection**: Import any CSV/Excel structure - no rigid column requirements
- **Flexible Email Column Selection**: Choose which column contains email addresses during campaign creation
- **Automatic Tagging**: Recipients are automatically tagged when used in campaigns
- **List Merging & Deduplication**: Combine multiple lists with intelligent duplicate removal
- **Bulk Editing**: Edit individual recipients or apply bulk changes with tag management
- **Search & Filter**: Find specific recipients with advanced search capabilities
- **Sublist Creation**: Create targeted sublists from search results

### 📧 **Advanced Email Campaigns**
- **Personalized Content**: Use merge tags like `{name}`, `{first_name}`, `{email}` in subject and body
- **HTML & Plain Text**: Support for rich HTML emails with fallback to plain text
- **File Attachments**: Attach documents, images, or any file type to campaigns
- **Custom Headers & Footers**: Create reusable email templates with your branding
- **Automatic Unsubscribe**: Built-in unsubscribe functionality with token-based security
- **Test Campaigns**: Send test emails before launching full campaigns

### 📈 **Comprehensive Tracking & Analytics**
- **Real-time Campaign Monitoring**: Track opens, clicks, bounces, and delivery status
- **Bounce Management**: Automatic bounce tracking with detailed failure reasons
- **Unsubscribe Tracking**: Monitor opt-outs and automatically exclude from future campaigns
- **Campaign History**: Complete audit trail of all email activities
- **Performance Metrics**: Detailed statistics for each campaign and recipient list

### 🔄 **Import/Export Flexibility**
- **Multiple Formats**: Support for CSV (.csv), Excel (.xlsx, .xls) import/export
- **Dynamic Structure**: No predefined column requirements - use any field names
- **Export Options**: Filter exports to exclude bounced or unsubscribed recipients
- **Backup & Migration**: Easy data portability between systems

### 🔧 **Professional Email Delivery**
- **Mailgun Integration**: Reliable delivery through industry-leading email service
- **Dual Sending Methods**: Choose between API and SMTP delivery
- **Custom Domain Support**: Send from your organization's domain
- **Webhook Processing**: Real-time delivery status updates
- **Rate Limiting**: Prevent overwhelming recipient servers

## 📋 Installation & Setup

### Prerequisites
- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher  
- **Mailgun Account**: For email delivery (free tier available)

### Installation Methods

#### Method 1: WordPress Admin Upload
1. Download the latest release from [GitHub Releases](https://github.com/mattbaya/alumni-bulk-email-plugin/releases)
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP file and activate

#### Method 2: Direct Installation
```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/mattbaya/alumni-bulk-email-plugin.git
cd alumni-bulk-email-plugin
composer install
```

### Configuration

#### 1. Mailgun Setup
Navigate to **Bulk Email → Settings**:

- **Mailgun API Key**: Your production API key from mailgun.com
- **Mailgun Domain**: Your verified sending domain (e.g., `mail.yourorganization.org`)
- **From Email**: Your sender address (e.g., `news@yourorganization.org`)
- **From Name**: Display name (e.g., `Alumni Association`)

#### 2. Webhook Configuration
Add this webhook URL in your Mailgun dashboard:
```
https://yoursite.com/wp-admin/admin-ajax.php?action=handle_mailgun_webhook
```

Enable these events: `delivered`, `failed`, `bounced`, `opened`, `clicked`

## 🎯 Detailed Feature Guide with Examples

### 1. Dynamic List Creation

#### Import from CSV/Excel Files
The plugin automatically detects column structure from your files:

**Example CSV Structure:**
```csv
email_address,full_name,graduation_year,major,location
john@alumni.edu,John Smith,1995,Computer Science,New York
mary@alumni.edu,Mary Johnson,1998,Business Administration,California
```

**Example Excel Structure:**
```
| Email | Name | Class Year | Field of Study | Current City |
|-------|------|------------|----------------|--------------|
| jane@alumni.edu | Jane Doe | 2001 | Engineering | Boston |
```

**Import Process:**
1. Go to **Bulk Email → Create Campaign**
2. Select **"Upload new CSV/Excel file"**
3. Choose your file and enter a descriptive list name
4. Click **"Preview & Save List"**
5. Review detected columns and recipient data
6. Save the list for future use

#### Manual Recipient Entry
For smaller lists, enter recipients directly:
```
Alumni Director <director@alumni.edu>
John Smith <john.smith@alumni.edu>  
Mary Johnson <mary.johnson@alumni.edu>
```

### 2. Flexible Email Column Selection

Unlike rigid systems requiring specific column names, this plugin adapts to your data:

**During Campaign Creation:**
1. Select your saved recipient list
2. **Choose Email Column**: Select which column contains email addresses
   - Options might include: `email`, `email_address`, `work_email`, `personal_email`
3. All other columns remain available for personalization

**Example Scenario:**
Your list has both `work_email` and `personal_email` columns. For professional announcements, select `work_email`. For reunion invites, select `personal_email`.

### 3. Advanced Personalization

#### Merge Tags
Use these placeholders in subject lines and email content:

- `{name}` - Full name field
- `{first_name}` - First name only
- `{last_name}` - Last name only  
- `{email}` - Email address
- `{graduation_year}` - Custom field (if exists)
- `{major}` - Custom field (if exists)

**Example Campaign:**
```
Subject: {first_name}, you're invited to the {graduation_year} Class Reunion!

Dear {name},

We're excited to invite you to our upcoming class reunion for the {graduation_year} graduating class. As a {major} alumnus, we know you'll enjoy reconnecting with classmates.

Event Details:
- Date: June 15, 2024
- Location: Campus Alumni Center

Please RSVP by clicking here: [RSVP Link]

Best regards,
Alumni Relations Team

Contact us: {email}
```

### 4. Automatic Campaign Tagging

When a list is used for a campaign, recipients automatically receive tags for tracking:

**Example Tag Creation:**
- Campaign: "Spring 2024 Newsletter" sent on March 15, 2024
- Automatic tag added: `Spring 2024 Newsletter (2024-03-15)`

**Benefits:**
- Track which campaigns each recipient has received
- Create targeted follow-up campaigns
- Analyze engagement patterns
- Manage communication frequency

### 5. List Management Operations

#### Bulk Editing
Select multiple recipients and apply changes:

**Example Operations:**
- **Add Tags**: Tag all engineering alumni with `Engineering Alumni`
- **Update Fields**: Change location for recipients who moved
- **Bulk Delete**: Remove bounced or unsubscribed recipients

**Process:**
1. Go to **Email Lists → View** (for any list)
2. Check boxes next to recipients you want to modify
3. Click **"Bulk Edit Selected"**
4. Apply your changes

#### List Merging
Combine multiple lists intelligently:

**Example Scenario:**
- List A: 2023 Event Attendees (500 recipients)
- List B: Newsletter Subscribers (1,200 recipients)
- Merge Result: Combined list with 1,400 unique recipients (300 duplicates removed)

**Process:**
1. Open your target list
2. Click **"Add to List"**
3. Upload new CSV/Excel file
4. Review merge preview showing new vs. existing recipients
5. Confirm merge with automatic deduplication

#### Sublist Creation
Create targeted segments from search results:

**Example Use Case:**
From your main alumni list, create sublists for:
- **Class of 2000**: Filter by graduation year
- **Local Alumni**: Filter by location = "New York"
- **Engineering Grads**: Filter by major contains "Engineering"

**Process:**
1. Use search/filter functionality in list view
2. Apply your criteria to find target recipients
3. Click **"Create Sublist from Results"**
4. Name your new targeted list
5. Use for specific campaigns

### 6. Export & Backup

#### Export Options
Create backups or migrate data with flexible export settings:

**Export Formats:**
- **CSV**: Universal compatibility
- **Excel**: Professional formatting with styled headers

**Filtering Options:**
- ✅ **Exclude Bounced**: Remove recipients with delivery failures
- ✅ **Exclude Unsubscribed**: Remove opted-out recipients
- ✅ **Include Tags**: Export with all campaign tags for analysis

**Example Export Names:**
- `Alumni_Newsletter_List_export_2024-03-15.xlsx`
- `Class_2000_Reunion_export_2024-03-15.csv`

### 7. Headers & Footers Management

#### Creating Templates
Build consistent branding across all emails:

**Example Header:**
```html
<table width="100%" style="background-color: #003366; color: white; padding: 20px;">
  <tr>
    <td>
      <img src="https://yoursite.com/logo.png" alt="Alumni Association" style="height: 50px;" />
      <h2 style="margin: 10px 0; color: white;">Alumni Association Newsletter</h2>
    </td>
  </tr>
</table>
```

**Example Footer:**
```html
<div style="background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #dee2e6;">
  <p><strong>Alumni Association</strong><br>
  123 University Avenue, Campus City, State 12345<br>
  Phone: (555) 123-4567 | Email: alumni@university.edu</p>
  
  <p style="font-size: 12px; color: #6c757d;">
    You received this email because you are part of our alumni network.<br>
    <a href="{unsubscribe_url}">Unsubscribe</a> | <a href="https://yoursite.com/update-info">Update Information</a>
  </p>
</div>
```

**Management:**
1. Go to **Bulk Email → Headers & Footers**
2. Create new templates or upload HTML files
3. Set default templates for automatic inclusion
4. Select specific templates per campaign

### 8. Campaign Analytics

#### Performance Tracking
Monitor campaign effectiveness with detailed metrics:

**Campaign Dashboard Shows:**
- **Total Sent**: 1,250 emails
- **Delivered**: 1,198 (95.8%)
- **Bounced**: 52 (4.2%)
- **Opened**: 456 (38.1% open rate)
- **Clicked**: 123 (10.3% click rate)
- **Unsubscribed**: 3 (0.25%)

#### Bounce Management
Automatic bounce tracking protects your sender reputation:

**Bounce Types Tracked:**
- **Temporary**: Server temporarily unavailable
- **Permanent**: Invalid email address
- **Content**: Email rejected due to content filters

**Automatic Actions:**
- After 3 bounces: Recipient marked as "bounced"
- Bounced recipients excluded from future campaigns
- Detailed bounce reasons logged for analysis

### 9. Advanced Use Cases

#### Segmented Campaigns
Create targeted messaging for different alumni groups:

**Example Campaign Strategy:**
1. **Recent Graduates (Last 5 Years)**: Career development content
2. **Established Alumni (10+ Years)**: Donation requests and major events  
3. **Local Alumni**: Regional meetup invitations
4. **International Alumni**: Virtual event announcements

#### Event Management
Use the plugin for comprehensive event communication:

**Pre-Event Sequence:**
1. **Save the Date** (3 months prior)
2. **Formal Invitation** (6 weeks prior) 
3. **Registration Reminder** (2 weeks prior)
4. **Final Details** (1 week prior)

**Post-Event Follow-up:**
1. **Thank You** (1 day after)
2. **Photo Sharing** (1 week after)
3. **Survey Request** (2 weeks after)

Each campaign automatically tags recipients, creating a complete communication history.

#### Donor Stewardship
Manage donor relationships with personalized communication:

**Example Workflow:**
1. Import donor list with giving history columns
2. Create segments by donation level
3. Personalize appeals using `{last_gift_amount}` and `{last_gift_date}` fields
4. Track engagement to identify major gift prospects

## 🛡️ Security & Privacy

### Data Protection
- **WordPress Security Standards**: All inputs sanitized and validated
- **Nonce Verification**: CSRF protection on all actions
- **Permission Checks**: Role-based access control
- **Secure Unsubscribe**: Token-based unsubscribe links prevent abuse

### Email Security
- **SPF/DKIM**: Mailgun provides authentication records
- **Bounce Handling**: Automatic list hygiene protects reputation
- **Rate Limiting**: Prevents spam accusations
- **Opt-out Compliance**: One-click unsubscribe in all emails

## 🔄 Automatic Updates

The plugin includes built-in update checking from GitHub:

1. **Update Notifications**: Appear in WordPress admin when new versions are available
2. **One-Click Updates**: Install directly from the plugins page
3. **Changelog Display**: See what's new in each version
4. **Version Management**: Semantic versioning for stable releases

## 📊 Database Structure

### Core Tables
- `wp_alumni_recipient_lists`: Saved email lists with JSON data storage
- `wp_alumni_email_campaigns`: Campaign records and settings
- `wp_alumni_email_logs`: Detailed sending and engagement logs
- `wp_alumni_email_unsubscribes`: Opt-out tracking with tokens
- `wp_alumni_headers_footers`: Reusable email templates

### Data Storage
- **Recipients**: JSON format enables unlimited custom fields
- **Campaign Logs**: Track every email sent with full details
- **Engagement Data**: Opens, clicks, bounces linked to specific recipients

## 🆘 Troubleshooting

### Common Issues

**Emails not sending:**
1. Verify Mailgun API credentials in settings
2. Confirm sending domain matches Mailgun domain
3. Check WordPress error logs for detailed messages
4. Test with small recipient list first

**Import errors:**
1. Ensure file is valid CSV/Excel format
2. Check that file contains email addresses
3. Verify file permissions allow PHP to read the upload
4. Review error logs for specific parsing issues

**Bounces not updating:**
1. Confirm webhook URL is configured in Mailgun
2. Test webhook URL accessibility from external source
3. Check that required events are enabled in Mailgun
4. Review webhook logs in Mailgun dashboard

### Debug Mode
Enable WordPress debugging for detailed error information:

```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Error logs will appear in `/wp-content/debug.log`

## 📞 Support & Contributing

### Getting Help
1. **Plugin Logs**: Check **Bulk Email → Email Logs** for campaign details
2. **WordPress Logs**: Review `/wp-content/debug.log` for errors
3. **Mailgun Dashboard**: Verify webhook and domain configuration
4. **GitHub Issues**: Report bugs or request features

### Contributing
We welcome contributions! Please:
1. Fork the repository
2. Create a feature branch
3. Make your changes with proper documentation
4. Submit a pull request with detailed description

### License
GPL v2 or later - Free to use and modify

---

## 🔗 Quick Links

- **Repository**: https://github.com/mattbaya/alumni-bulk-email-plugin
- **Releases**: https://github.com/mattbaya/alumni-bulk-email-plugin/releases
- **Issues**: https://github.com/mattbaya/alumni-bulk-email-plugin/issues
- **Mailgun**: https://mailgun.com (for email delivery service)

---

**Built for Alumni Organizations** | **Professional Email Marketing** | **WordPress Integration**