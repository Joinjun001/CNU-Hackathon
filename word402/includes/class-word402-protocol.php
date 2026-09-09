<?php
/**
 * x402 Protocol Handler & Response Builder
 */

if (!defined('ABSPATH')) {
    exit;
}

class Word402_Protocol {

    const PROTOCOL_VERSION = '2.0';

    /**
     * Parse payment proof from request headers
     */
    public static function get_payment_submission() {
        $headers = self::get_all_headers();

        // 1. Check X-Payment header
        if (!empty($headers['x-payment'])) {
            $raw = trim($headers['x-payment']);
            $data = json_decode($raw, true);
            if (is_array($data) && !empty($data['tx_hash'])) {
                return array(
                    'tx_hash'      => sanitize_text_field($data['tx_hash']),
                    'challenge_id' => isset($data['challenge_id']) ? sanitize_text_field($data['challenge_id']) : '',
                    'sender'       => isset($data['sender']) ? sanitize_text_field($data['sender']) : ''
                );
            }
        }

        // 2. Check Authorization header: Authorization: x402 tx_hash="...", challenge_id="..."
        if (!empty($headers['authorization']) && stripos($headers['authorization'], 'x402') === 0) {
            $auth_str = substr($headers['authorization'], 4);
            $parsed = array();
            if (preg_match('/tx_hash=["\']([^"\']+)["\']/', $auth_str, $m)) {
                $parsed['tx_hash'] = sanitize_text_field($m[1]);
            }
            if (preg_match('/challenge_id=["\']([^"\']+)["\']/', $auth_str, $m)) {
                $parsed['challenge_id'] = sanitize_text_field($m[1]);
            }
            if (!empty($parsed['tx_hash'])) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * Send HTTP 402 Payment Required response
     */
    public static function send_402_response($post, $price_info) {
        $admin_wallet = get_option('word402_admin_wallet', '0x0000000000000000000000000000000000000000');
        $ttl = (int)get_option('word402_challenge_ttl', 300);
        $network = $price_info['network'];
        $price = $price_info['price'];

        // Create Challenge record
        $challenge = Word402_DB::create_challenge($post->ID, $price, $admin_wallet, $ttl);
        $challenge_id = $challenge['challenge_id'];
        $expires_at = $challenge['expires_at'];

        $usdc_contract = get_option('word402_usdc_contract', '0x036CbD53842c5426634e7929541eC2318f3dCF7e');
        $chain_id = (int)get_option('word402_chain_id', 84532);
        $amount_raw = (string)((int)round($price * 1000000));

        // Format WWW-Authenticate header
        $www_auth = sprintf(
            'x402 network="%s", currency="USDC", amount="%f", recipient="%s", challenge="%s"',
            $network,
            $price,
            $admin_wallet,
            $challenge_id
        );

        status_header(402);
        header('Content-Type: application/json; charset=utf-8');
        header('WWW-Authenticate: ' . $www_auth);
        header('X-402-Version: ' . self::PROTOCOL_VERSION);
        header('X-402-Challenge-ID: ' . $challenge_id);
        header('X-402-Expires-At: ' . $expires_at);

        $response_body = array(
            'protocol' => 'x402',
            'version'  => self::PROTOCOL_VERSION,
            'status'   => 'payment_required',
            'resource' => array(
                'post_id'      => $post->ID,
                'title'        => $post->post_title,
                'teaser'       => Word402_Content_Filter::get_teaser($post, 2),
                'content_type' => 'text/markdown'
            ),
            'payment_requirements' => array(
                'network'        => $network,
                'chain_id'       => $chain_id,
                'currency'       => 'USDC',
                'token_address'  => $usdc_contract,
                'decimals'       => 6,
                'amount'         => (string)$price,
                'amount_raw'     => $amount_raw,
                'recipient'      => $admin_wallet,
                'challenge_id'   => $challenge_id,
                'expires_at'     => $expires_at,
                'ttl_seconds'    => $ttl
            )
        );

        echo wp_json_encode($response_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send Error response
     */
    public static function send_error_response($http_status, $code, $message, $details = array()) {
        status_header($http_status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-402-Version: ' . self::PROTOCOL_VERSION);

        $body = array(
            'error'   => true,
            'code'    => $code,
            'message' => $message,
            'details' => $details
        );

        echo wp_json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send HTTP 200 Settled Markdown response
     */
    public static function send_settled_content($post, $receipt_data) {
        $markdown = Word402_Content_Filter::to_markdown($post->post_content, $post->post_title);

        status_header(200);
        header('Content-Type: text/markdown; charset=utf-8');
        header('X-402-Status: settled');
        header('X-402-Tx-Hash: ' . $receipt_data['tx_hash']);
        header('X-402-Settled-At: ' . time());

        echo $markdown;
        exit;
    }

    /**
     * Cross-server header retrieval helper
     */
    private static function get_all_headers() {
        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $key = strtolower(str_replace('_', '-', substr($name, 5)));
                $headers[$key] = $value;
            } elseif ($name === 'CONTENT_TYPE') {
                $headers['content-type'] = $value;
            } elseif ($name === 'CONTENT_LENGTH') {
                $headers['content-length'] = $value;
            }
        }
        return $headers;
    }
}
