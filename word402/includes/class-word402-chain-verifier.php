<?php
/**
 * On-Chain RPC Verification Engine for Base Sepolia (EVM L2)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Word402_Chain_Verifier {

    const TRANSFER_EVENT_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    /**
     * Verify an EVM ERC-20 transaction via JSON-RPC
     *
     * @param string $tx_hash
     * @param string $expected_recipient
     * @param float  $expected_amount
     * @return array [ 'success' => bool, 'code' => string, 'message' => string, 'data' => array ]
     */
    public static function verify_payment($tx_hash, $expected_recipient, $expected_amount) {
        // Sanitize TX Hash
        $tx_hash = trim($tx_hash);
        if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $tx_hash)) {
            return array(
                'success' => false,
                'code'    => 'INVALID_TX_HASH_FORMAT',
                'message' => 'Transaction hash must be a 66-character hex string starting with 0x.'
            );
        }

        // Mock simulation hook for test environments
        if (defined('WORD402_SIMULATION_MODE') && WORD402_SIMULATION_MODE === true) {
            return self::simulate_verification($tx_hash, $expected_recipient, $expected_amount);
        }

        $rpc_url = get_option('word402_rpc_url', 'https://sepolia.base.org');
        $expected_usdc = strtolower(get_option('word402_usdc_contract', '0x036CbD53842c5426634e7929541eC2318f3dCF7e'));

        // Query eth_getTransactionReceipt
        $receipt = self::rpc_call($rpc_url, 'eth_getTransactionReceipt', array($tx_hash));

        if (!$receipt) {
            return array(
                'success' => false,
                'code'    => 'TX_NOT_FOUND',
                'message' => 'Transaction receipt not found. It may be pending or not yet propagated.'
            );
        }

        // 1. Check Status
        if (!isset($receipt['status']) || hexdec($receipt['status']) !== 1) {
            return array(
                'success' => false,
                'code'    => 'TX_EXECUTION_REVERTED',
                'message' => 'The transaction failed or was reverted on-chain.'
            );
        }

        $block_number = isset($receipt['blockNumber']) ? hexdec($receipt['blockNumber']) : 0;
        $matched_transfer = null;

        // 2. Parse Event Logs for ERC-20 Transfer
        if (!empty($receipt['logs']) && is_array($receipt['logs'])) {
            foreach ($receipt['logs'] as $log) {
                // Must be from the USDC contract
                if (strtolower($log['address']) !== $expected_usdc) {
                    continue;
                }

                // Check Topic 0 == Transfer(...)
                if (empty($log['topics'][0]) || strtolower($log['topics'][0]) !== strtolower(self::TRANSFER_EVENT_TOPIC)) {
                    continue;
                }

                // Topic 1 = from (32 bytes hex, address is last 20 bytes)
                $from_addr = '0x' . substr($log['topics'][1], 26);

                // Topic 2 = to (32 bytes hex, address is last 20 bytes)
                $to_addr = '0x' . substr($log['topics'][2], 26);

                // Data = value (uint256 hex)
                $value_raw = hexdec($log['data']);
                $value_usdc = $value_raw / 1000000.0; // 6 decimals for USDC

                // Check Recipient
                if (strtolower($to_addr) === strtolower($expected_recipient)) {
                    $matched_transfer = array(
                        'from'       => $from_addr,
                        'to'         => $to_addr,
                        'amount'     => $value_usdc,
                        'raw_amount' => $value_raw
                    );
                    break;
                }
            }
        }

        if (!$matched_transfer) {
            return array(
                'success' => false,
                'code'    => 'INVALID_RECIPIENT_OR_TOKEN',
                'message' => 'No valid USDC Transfer event found matching the recipient wallet address.'
            );
        }

        // 3. Verify Amount
        if ($matched_transfer['amount'] < ($expected_amount - 0.000001)) {
            return array(
                'success' => false,
                'code'    => 'INSUFFICIENT_AMOUNT',
                'message' => sprintf(
                    'Transferred amount (%f USDC) is less than required price (%f USDC).',
                    $matched_transfer['amount'],
                    $expected_amount
                )
            );
        }

        return array(
            'success' => true,
            'code'    => 'VERIFIED',
            'message' => 'On-chain transaction successfully verified.',
            'data'    => array(
                'tx_hash'           => $tx_hash,
                'payer_address'     => $matched_transfer['from'],
                'recipient_address' => $matched_transfer['to'],
                'settled_amount'    => $matched_transfer['amount'],
                'block_number'      => $block_number
            )
        );
    }

    /**
     * Perform JSON-RPC HTTP POST request
     */
    private static function rpc_call($url, $method, $params) {
        $body = wp_json_encode(array(
            'jsonrpc' => '2.0',
            'id'      => time(),
            'method'  => $method,
            'params'  => $params
        ));

        $response = wp_remote_post($url, array(
            'headers'     => array('Content-Type' => 'application/json'),
            'body'        => $body,
            'timeout'     => 10,
            'sslverify'   => true
        ));

        if (is_wp_error($response)) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($data['result'])) {
            return $data['result'];
        }

        return null;
    }

    /**
     * Simulation helper for testing environments
     */
    private static function simulate_verification($tx_hash, $expected_recipient, $expected_amount) {
        return array(
            'success' => true,
            'code'    => 'VERIFIED_SIMULATION',
            'message' => 'Simulated test verification successful.',
            'data'    => array(
                'tx_hash'           => $tx_hash,
                'payer_address'     => '0xAgentSimulatedWalletAddress12345678901234',
                'recipient_address' => $expected_recipient,
                'settled_amount'    => (float)$expected_amount,
                'block_number'      => 12345678
            )
        );
    }
}
