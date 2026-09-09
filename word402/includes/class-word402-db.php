<?php
/**
 * Database operations handler for Word402
 */

if (!defined('ABSPATH')) {
    exit;
}

class Word402_DB {

    /**
     * Get price and settings for a given post
     */
    public static function get_post_price_info($post_id) {
        global $wpdb;

        // 1. Check if post has individual post_meta override
        $is_enabled_meta = get_post_meta($post_id, '_word402_enabled', true);
        if ($is_enabled_meta !== '' && !(bool)$is_enabled_meta) {
            return array('is_enabled' => false, 'price' => 0.0);
        }

        $custom_price = get_post_meta($post_id, '_word402_custom_price', true);
        if ($custom_price !== '' && is_numeric($custom_price)) {
            return array(
                'is_enabled' => true,
                'price'      => (float)$custom_price,
                'network'    => get_option('word402_network', 'base-sepolia'),
            );
        }

        // 2. Check wp_x402_pricing_rules table for post-specific rule
        $table_pricing = $wpdb->prefix . 'x402_pricing_rules';
        $rule = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_pricing WHERE post_id = %d LIMIT 1",
            $post_id
        ));

        if ($rule) {
            return array(
                'is_enabled' => (bool)$rule->is_enabled,
                'price'      => (float)$rule->price_usdc,
                'network'    => $rule->network,
            );
        }

        // 3. Fallback to global options
        $default_price = (float)get_option('word402_default_price', '0.005');
        return array(
            'is_enabled' => true,
            'price'      => $default_price,
            'network'    => get_option('word402_network', 'base-sepolia'),
        );
    }

    /**
     * Issue and store a new payment challenge
     */
    public static function create_challenge($post_id, $required_amount, $recipient_wallet, $ttl_seconds = 300) {
        global $wpdb;

        $challenge_id = 'chn_' . wp_generate_uuid4();
        $expires_at = gmdate('Y-m-d H:i:s', time() + $ttl_seconds);

        $table_challenges = $wpdb->prefix . 'x402_challenges';
        $wpdb->insert(
            $table_challenges,
            array(
                'challenge_id'     => $challenge_id,
                'post_id'          => $post_id,
                'required_amount'  => $required_amount,
                'recipient_wallet' => $recipient_wallet,
                'status'           => 'PENDING',
                'expires_at'       => $expires_at,
                'created_at'       => current_time('mysql', 1)
            ),
            array('%s', '%d', '%f', '%s', '%s', '%s', '%s')
        );

        return array(
            'challenge_id' => $challenge_id,
            'expires_at'   => strtotime($expires_at),
            'ttl_seconds'  => $ttl_seconds
        );
    }

    /**
     * Retrieve a challenge
     */
    public static function get_challenge($challenge_id) {
        global $wpdb;
        $table_challenges = $wpdb->prefix . 'x402_challenges';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_challenges WHERE challenge_id = %s LIMIT 1",
            $challenge_id
        ));
    }

    /**
     * Mark a challenge as COMPLETED
     */
    public static function mark_challenge_completed($challenge_id) {
        global $wpdb;
        $table_challenges = $wpdb->prefix . 'x402_challenges';
        $wpdb->update(
            $table_challenges,
            array('status' => 'COMPLETED'),
            array('challenge_id' => $challenge_id),
            array('%s'),
            array('%s')
        );
    }

    /**
     * Check if a transaction hash has already been used (Anti-Replay Attack)
     */
    public static function is_tx_used($tx_hash) {
        global $wpdb;
        $table_receipts = $wpdb->prefix . 'x402_receipts';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM $table_receipts WHERE tx_hash = %s",
            $tx_hash
        ));
        return ((int)$existing > 0);
    }

    /**
     * Record a verified payment receipt
     */
    public static function record_receipt($data) {
        global $wpdb;
        $table_receipts = $wpdb->prefix . 'x402_receipts';
        return $wpdb->insert(
            $table_receipts,
            array(
                'tx_hash'           => $data['tx_hash'],
                'challenge_id'      => $data['challenge_id'],
                'post_id'           => $data['post_id'],
                'payer_address'     => $data['payer_address'],
                'recipient_address' => $data['recipient_address'],
                'settled_amount'    => $data['settled_amount'],
                'block_number'      => $data['block_number'],
                'agent_user_agent'  => isset($data['agent_user_agent']) ? substr($data['agent_user_agent'], 0, 255) : '',
                'settled_at'        => current_time('mysql', 1)
            ),
            array('%s', '%s', '%d', '%s', '%s', '%f', '%d', '%s', '%s')
        );
    }

    /**
     * Analytics: Total Revenue in USDC
     */
    public static function get_total_revenue() {
        global $wpdb;
        $table_receipts = $wpdb->prefix . 'x402_receipts';
        $total = $wpdb->get_var("SELECT SUM(settled_amount) FROM $table_receipts");
        return $total ? (float)$total : 0.0;
    }

    /**
     * Analytics: Total Settled Requests Count
     */
    public static function get_total_settled_count() {
        global $wpdb;
        $table_receipts = $wpdb->prefix . 'x402_receipts';
        return (int)$wpdb->get_var("SELECT COUNT(1) FROM $table_receipts");
    }

    /**
     * Analytics: Get Recent Receipts
     */
    public static function get_recent_receipts($limit = 10) {
        global $wpdb;
        $table_receipts = $wpdb->prefix . 'x402_receipts';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, p.post_title 
             FROM $table_receipts r 
             LEFT JOIN {$wpdb->posts} p ON r.post_id = p.ID 
             ORDER BY r.settled_at DESC LIMIT %d",
            $limit
        ));
    }

    /**
     * Daily Cron cleanup for expired pending challenges
     */
    public static function cleanup_expired_challenges() {
        global $wpdb;
        $table_challenges = $wpdb->prefix . 'x402_challenges';
        $wpdb->query("DELETE FROM $table_challenges WHERE status = 'PENDING' AND expires_at < NOW()");
    }
}

// Hook Cron Action
add_action('word402_daily_cleanup_cron', array('Word402_DB', 'cleanup_expired_challenges'));
