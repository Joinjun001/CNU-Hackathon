<?php
/**
 * Request Interceptor for REST API & Frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

class Word402_Interceptor {

    public function init() {
        // 1. Register Custom REST Routes
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // 2. Intercept Frontend Web Requests for AI agents
        add_action('template_redirect', array($this, 'intercept_frontend_request'), 5);
    }

    /**
     * Register /wp-json/word402/v1/posts/{id}
     */
    public function register_rest_routes() {
        register_rest_route('word402/v1', '/posts/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_rest_post_request'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ),
            ),
        ));
    }

    /**
     * Handle REST API post request
     */
    public function handle_rest_post_request(WP_REST_Request $request) {
        $post_id = (int)$request->get_param('id');
        $this->process_post_access($post_id);
    }

    /**
     * Intercept standard frontend single post URLs if accessed with Agent headers
     */
    public function intercept_frontend_request() {
        if (!is_singular('post')) {
            return;
        }

        $accept = isset($_SERVER['HTTP_ACCEPT']) ? strtolower($_SERVER['HTTP_ACCEPT']) : '';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
        $has_x_payment = !empty($_SERVER['HTTP_X_PAYMENT']) || (isset($_SERVER['HTTP_AUTHORIZATION']) && stripos($_SERVER['HTTP_AUTHORIZATION'], 'x402') === 0);

        // Check if agent format requested or payment header present
        $is_agent_request = (
            strpos($accept, 'text/markdown') !== false ||
            strpos($accept, 'application/json') !== false ||
            strpos($user_agent, 'agent') !== false ||
            strpos($user_agent, 'bot') !== false ||
            $has_x_payment
        );

        if ($is_agent_request) {
            $post_id = get_the_ID();
            $this->process_post_access($post_id);
        }
    }

    /**
     * Core processing logic: Paywall & Verification Workflow
     */
    private function process_post_access($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            Word402_Protocol::send_error_response(404, 'POST_NOT_FOUND', 'The requested post does not exist.');
        }

        // Check pricing policy
        $price_info = Word402_DB::get_post_price_info($post_id);

        // Free post: Serve directly
        if (!$price_info['is_enabled'] || $price_info['price'] <= 0.0) {
            Word402_Protocol::send_settled_content($post, array('tx_hash' => 'FREE_POST'));
        }

        // Check if client submitted payment proof
        $submission = Word402_Protocol::get_payment_submission();

        // Case A: No payment submitted -> Return HTTP 402
        if (!$submission) {
            Word402_Protocol::send_402_response($post, $price_info);
        }

        // Case B: Payment submitted -> Verify
        $tx_hash = $submission['tx_hash'];
        $challenge_id = $submission['challenge_id'];

        // 1. Verify Challenge ID if provided
        if (!empty($challenge_id)) {
            $challenge = Word402_DB::get_challenge($challenge_id);
            if (!$challenge) {
                Word402_Protocol::send_error_response(400, 'INVALID_CHALLENGE', 'The provided challenge_id does not exist.');
            }
            if (strtotime($challenge->expires_at) < time()) {
                Word402_Protocol::send_error_response(402, 'CHALLENGE_EXPIRED', 'The payment challenge has expired. Request a new 402 challenge.');
            }
        }

        // 2. Anti-Replay Check (Has this tx_hash already been redeemed?)
        if (Word402_DB::is_tx_used($tx_hash)) {
            Word402_Protocol::send_error_response(
                409,
                'REPLAY_ATTACK_DETECTED',
                'This transaction hash has already been redeemed for access. Each request requires a distinct on-chain transaction.'
            );
        }

        // 3. On-chain Verification
        $admin_wallet = get_option('word402_admin_wallet', '');
        $verification = Word402_Chain_Verifier::verify_payment($tx_hash, $admin_wallet, $price_info['price']);

        if (!$verification['success']) {
            Word402_Protocol::send_error_response(402, $verification['code'], $verification['message']);
        }

        // 4. Record Receipt & Complete Challenge
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $receipt_data = array(
            'tx_hash'           => $tx_hash,
            'challenge_id'      => $challenge_id,
            'post_id'           => $post_id,
            'payer_address'     => $verification['data']['payer_address'],
            'recipient_address' => $verification['data']['recipient_address'],
            'settled_amount'    => $verification['data']['settled_amount'],
            'block_number'      => $verification['data']['block_number'],
            'agent_user_agent'  => $user_agent
        );
        Word402_DB::record_receipt($receipt_data);

        if (!empty($challenge_id)) {
            Word402_DB::mark_challenge_completed($challenge_id);
        }

        // 5. Serve Clean Settled Markdown
        Word402_Protocol::send_settled_content($post, $receipt_data);
    }
}
