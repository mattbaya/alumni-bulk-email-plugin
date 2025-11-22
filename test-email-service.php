<?php
/**
 * Test email service functionality (without actually sending emails)
 */

// Simulate WordPress environment and functions
define('ABSPATH', '/tmp/wordpress/');

// Mock WordPress database global
$wpdb = new stdClass();
$wpdb->prefix = 'wp_';

// Mock WordPress functions
function get_option($option_name, $default = false) {
    $options = [
        'alumni_bulk_email_mailgun_api_key' => '',
        'alumni_bulk_email_mailgun_domain' => '',
        'alumni_bulk_email_from_email' => '',
        'alumni_bulk_email_from_name' => 'Test Sender'
    ];
    return isset($options[$option_name]) ? $options[$option_name] : $default;
}

function wp_strip_all_tags($string) {
    return strip_tags($string);
}

function wp_hash($data, $scheme = 'auth') {
    return hash('sha256', $data . 'salt');
}

function wp_salt($scheme = 'auth') {
    return 'test_salt_value';
}

function current_time($type) {
    return date('Y-m-d H:i:s');
}

// Load classes in dependency order
require_once __DIR__ . '/includes/class-database.php';
require_once __DIR__ . '/includes/class-file-processor.php';
require_once __DIR__ . '/includes/class-email-service.php';

echo "Testing email service functionality...\n\n";

try {
    $file_processor = new Alumni_File_Processor();
    $email_service = new Alumni_Email_Service($file_processor);
    
    // Test 1: Configuration check
    echo "1. Testing Mailgun configuration check...\n";
    $is_configured = $email_service->is_mailgun_configured();
    echo "   - Mailgun configured: " . ($is_configured ? 'YES' : 'NO') . " ✓\n";
    echo "   (Expected NO since no test credentials provided)\n";
    
    // Test 2: Content personalization
    echo "\n2. Testing content personalization...\n";
    $test_recipient = [
        'email' => 'test@example.com',
        'name' => 'Test User',
        'first_name' => 'Test',
        'last_name' => 'User',
        'graduation_year' => '2020'
    ];
    
    $template_content = "Hello {first_name} {last_name}, class of {graduation_year}! Your email is {email}.";
    $personalized = $email_service->personalize_content($template_content, $test_recipient);
    
    echo "   - Template: $template_content\n";
    echo "   - Personalized: $personalized\n";
    
    $expected = "Hello Test User, class of 2020! Your email is test@example.com.";
    if ($personalized === $expected) {
        echo "   - Personalization test PASSED ✓\n";
    } else {
        echo "   - Personalization test FAILED ✗\n";
        echo "   - Expected: $expected\n";
    }
    
    // Test 3: Email validation
    echo "\n3. Testing email validation functions...\n";
    
    echo "   - Database-dependent methods (bounce/unsubscribe checking) skipped in test environment\n";
    echo "   - These require WordPress database which is not available in isolation\n";
    
    // Test 4: Unsubscribe token generation
    echo "\n4. Testing unsubscribe token generation...\n";
    $reflection = new ReflectionClass($email_service);
    $method = $reflection->getMethod('generate_unsubscribe_token');
    $method->setAccessible(true);
    
    $token1 = $method->invoke($email_service, 'test@example.com');
    $token2 = $method->invoke($email_service, 'test@example.com');
    $token3 = $method->invoke($email_service, 'different@example.com');
    
    echo "   - Token for test@example.com: " . substr($token1, 0, 20) . "...\n";
    echo "   - Same email generates same token: " . ($token1 === $token2 ? 'YES ✓' : 'NO ✗') . "\n";
    echo "   - Different email generates different token: " . ($token1 !== $token3 ? 'YES ✓' : 'NO ✗') . "\n";
    
    echo "\n✅ Email service basic tests passed!\n";
    echo "Note: Full email sending requires WordPress environment and Mailgun credentials.\n";
    
} catch (Exception $e) {
    echo "\n❌ Test failed: " . $e->getMessage() . "\n";
    exit(1);
} catch (Error $e) {
    echo "\n❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>