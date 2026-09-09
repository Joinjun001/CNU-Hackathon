<?php
/**
 * Fired during plugin activation & deactivation
 */

if (!defined('ABSPATH')) {
    exit;
}

class Word402_Activator {

    public static function activate() {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // 1. Table: wp_x402_pricing_rules
        $table_pricing = $wpdb->prefix . 'x402_pricing_rules';
        $sql_pricing = "CREATE TABLE $table_pricing (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            price_usdc DECIMAL(18, 6) NOT NULL DEFAULT 0.005000,
            network VARCHAR(32) NOT NULL DEFAULT 'base-sepolia',
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_post_id (post_id)
        ) $charset_collate;";
        dbDelta($sql_pricing);

        // 2. Table: wp_x402_challenges
        $table_challenges = $wpdb->prefix . 'x402_challenges';
        $sql_challenges = "CREATE TABLE $table_challenges (
            challenge_id VARCHAR(64) NOT NULL,
            post_id BIGINT UNSIGNED NOT NULL,
            required_amount DECIMAL(18, 6) NOT NULL,
            recipient_wallet VARCHAR(66) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (challenge_id),
            KEY idx_post_status (post_id, status),
            KEY idx_expires_at (expires_at)
        ) $charset_collate;";
        dbDelta($sql_challenges);

        // 3. Table: wp_x402_receipts
        $table_receipts = $wpdb->prefix . 'x402_receipts';
        $sql_receipts = "CREATE TABLE $table_receipts (
            tx_hash VARCHAR(66) NOT NULL,
            challenge_id VARCHAR(64) NOT NULL,
            post_id BIGINT UNSIGNED NOT NULL,
            payer_address VARCHAR(66) NOT NULL,
            recipient_address VARCHAR(66) NOT NULL,
            settled_amount DECIMAL(18, 6) NOT NULL,
            block_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
            agent_user_agent VARCHAR(255) NULL,
            settled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (tx_hash),
            KEY idx_post_id (post_id),
            KEY idx_payer (payer_address),
            KEY idx_settled_at (settled_at)
        ) $charset_collate;";
        dbDelta($sql_receipts);

        // Set Default Options if not already present
        add_option('word402_admin_wallet', '0x71C...B29'); // Sample wallet placeholder
        add_option('word402_network', 'base-sepolia');
        add_option('word402_chain_id', 84532);
        add_option('word402_rpc_url', 'https://sepolia.base.org');
        add_option('word402_usdc_contract', '0x036CbD53842c5426634e7929541eC2318f3dCF7e');
        add_option('word402_default_price', '0.005');
        add_option('word402_challenge_ttl', 300); // 5 minutes
        add_option('word402_enable_markdown', 1);

        // Schedule Cron for pruning expired challenges
        if (!wp_next_scheduled('word402_daily_cleanup_cron')) {
            wp_schedule_event(time(), 'daily', 'word402_daily_cleanup_cron');
        }
    }

    public static function deactivate() {
        // Clear scheduled cron
        $timestamp = wp_next_scheduled('word402_daily_cleanup_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'word402_daily_cleanup_cron');
        }
    }
}
