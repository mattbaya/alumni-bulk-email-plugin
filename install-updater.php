<?php
/**
 * Plugin Update Checker Installer
 * Run this once to install the GitHub updater dependency
 */

if (!file_exists(__DIR__ . '/vendor')) {
    echo "Installing Plugin Update Checker...\n";
    
    // Create vendor directory
    mkdir(__DIR__ . '/vendor', 0755, true);
    
    // Download Plugin Update Checker
    $url = 'https://github.com/YahnisElsts/plugin-update-checker/archive/v5.4.tar.gz';
    $file = __DIR__ . '/vendor/plugin-update-checker.tar.gz';
    
    if (copy($url, $file)) {
        echo "Downloaded successfully\n";
        
        // Extract
        $archive = new PharData($file);
        $archive->extractTo(__DIR__ . '/vendor/');
        
        // Rename directory
        rename(__DIR__ . '/vendor/plugin-update-checker-5.4', __DIR__ . '/vendor/plugin-update-checker');
        
        // Clean up
        unlink($file);
        
        echo "Plugin Update Checker installed successfully!\n";
        echo "The plugin is now ready for automatic updates from GitHub.\n";
    } else {
        echo "Failed to download Plugin Update Checker\n";
    }
} else {
    echo "Plugin Update Checker already installed\n";
}
?>