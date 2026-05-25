<?php
/**
 * Supersonique – Agent Ready
 * Full cleanup when the plugin is uninstalled.
 *
 * This file runs automatically when the user clicks "Delete"
 * in the WordPress plugins list.
 */

// Security: only execute when called by WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// 1. Delete the options stored in the database
delete_option( 'sar_settings' );

// 2. Remove the static files generated in .well-known/
$well_known = ABSPATH . '.well-known/';
$files = [
    'oauth-protected-resource',
    'openid-configuration',
    'oauth-authorization-server',
    'http-message-signatures-directory',
    'api-catalog',
    'mcp/server-card.json',
    'agent-skills/index.json',
    '.htaccess',
];
foreach ( $files as $rel ) {
    $f = $well_known . $rel;
    if ( file_exists( $f ) ) {
        @unlink( $f );
    }
}

// 3. Remove empty subdirectories
@rmdir( $well_known . 'mcp' );
@rmdir( $well_known . 'agent-skills' );

// Note: we do NOT remove .well-known/ itself — other plugins
// (Let's Encrypt, certbot, etc.) may use it for their own files.

// 4. Flush rewrite rules
flush_rewrite_rules();
