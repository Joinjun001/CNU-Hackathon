<?php
/**
 * WP Admin Menu & Settings Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class Word402_Admin {

    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Word402 AI Monetization',
            'Word402',
            'manage_options',
            'word402',
            array($this, 'render_dashboard_page'),
            'dashicons-money-alt',
            30
        );

        add_submenu_page(
            'word402',
            'Dashboard & Analytics',
            '대시보드',
            'manage_options',
            'word402',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'word402',
            'Post Pricing Rules',
            '가격 정책 관리',
            'manage_options',
            'word402-pricing',
            array($this, 'render_pricing_page')
        );

        add_submenu_page(
            'word402',
            'Transaction Ledger',
            '트랜잭션 원장',
            'manage_options',
            'word402-ledger',
            array($this, 'render_ledger_page')
        );

        add_submenu_page(
            'word402',
            'Word402 Settings',
            '환경설정',
            'manage_options',
            'word402-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('word402_settings_group', 'word402_admin_wallet');
        register_setting('word402_settings_group', 'word402_network');
        register_setting('word402_settings_group', 'word402_chain_id');
        register_setting('word402_settings_group', 'word402_rpc_url');
        register_setting('word402_settings_group', 'word402_usdc_contract');
        register_setting('word402_settings_group', 'word402_default_price');
        register_setting('word402_settings_group', 'word402_challenge_ttl');
        register_setting('word402_settings_group', 'word402_enable_markdown');
    }

    public function render_dashboard_page() {
        require_once WORD402_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    public function render_pricing_page() {
        require_once WORD402_PLUGIN_DIR . 'admin/views/pricing.php';
    }

    public function render_ledger_page() {
        require_once WORD402_PLUGIN_DIR . 'admin/views/ledger.php';
    }

    public function render_settings_page() {
        require_once WORD402_PLUGIN_DIR . 'admin/views/settings.php';
    }
}
