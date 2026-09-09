<?php
/**
 * Plugin Name:       Word402
 * Plugin URI:        https://github.com/your-org/word402
 * Description:       Autonomous agent monetization gateway for WordPress via HTTP 402 & Base L2 blockchain micropayments.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Word402 Capstone Team
 * License:           MIT
 * Text Domain:       word402
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('WORD402_VERSION', '1.0.0');
define('WORD402_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WORD402_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include Core Classes
require_once WORD402_PLUGIN_DIR . 'includes/class-word402-activator.php';
require_once WORD402_PLUGIN_DIR . 'includes/class-word402-db.php';
require_once WORD402_PLUGIN_DIR . 'includes/class-word402-chain-verifier.php';
require_once WORD402_PLUGIN_DIR . 'includes/class-word402-content-filter.php';
require_once WORD402_PLUGIN_DIR . 'includes/class-word402-protocol.php';
require_once WORD402_PLUGIN_DIR . 'includes/class-word402-interceptor.php';

// Include Admin Classes if in admin context
if (is_admin()) {
    require_once WORD402_PLUGIN_DIR . 'admin/class-word402-admin.php';
    require_once WORD402_PLUGIN_DIR . 'admin/metabox/class-word402-metabox.php';
}

/**
 * Activation & Deactivation Hooks
 */
register_activation_hook(__FILE__, array('Word402_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('Word402_Activator', 'deactivate'));

/**
 * Main Plugin Bootstrap
 */
function word402_init() {
    // Initialize Interceptor (REST API & Frontend Hooks)
    $interceptor = new Word402_Interceptor();
    $interceptor->init();

    // Initialize Admin
    if (is_admin()) {
        $admin = new Word402_Admin();
        $admin->init();

        $metabox = new Word402_Metabox();
        $metabox->init();
    }
}
add_action('plugins_loaded', 'word402_init');
