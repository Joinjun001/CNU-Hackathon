<?php
/**
 * Public-facing Frontend Handler for Word402
 * Renders On-Chain Proof of Purchase Badges & Public Transaction Ledger
 */

if (!defined('ABSPATH')) {
    exit;
}

class Word402_Public {

    public function init() {
        // Register shortcode [word402_ledger]
        add_shortcode('word402_ledger', array($this, 'render_public_ledger_shortcode'));

        // Virtual page / standalone query handler (?word402_ledger=1)
        add_action('template_redirect', array($this, 'handle_standalone_ledger_page'), 5);
    }

    /**
     * Render Public Ledger Shortcode [word402_ledger]
     */
    public function render_public_ledger_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'limit' => 50,
        ), $atts, 'word402_ledger');

        $limit = intval($atts['limit']);
        $receipts = Word402_DB::get_recent_receipts($limit);
        $total_revenue = Word402_DB::get_total_revenue();
        $total_count = Word402_DB::get_total_settled_count();
        $admin_wallet = get_option('word402_admin_wallet', '0xf49FA400df523A65827Cb2CB30C4A3dCBf784FdD');
        $usdc_contract = get_option('word402_usdc_contract', '0x036CbD53842c5426634e7929541eC2318f3dCF7e');

        ob_start();
        echo $this->get_ledger_styles();
        ?>
        <div class="word402-public-ledger">
            <!-- Header Banner -->
            <div class="word402-ledger-header">
                <h2>🤖 Word402 실시간 온체인 결제 원장 (Live Ledger)</h2>
                <p>전 세계 자율 AI 에이전트가 본 블로그의 유료 데이터를 구매하고 Base Sepolia 블록체인 상에 기록된 실시간 트랜잭션 증명 원장입니다.</p>
            </div>

            <!-- Stats Metric Cards -->
            <div class="word402-stats-grid">
                <div class="word402-stat-card">
                    <span class="word402-stat-label">총 누적 AI 결제 정산액</span>
                    <strong class="word402-stat-value word402-highlight">+<?php echo number_format($total_revenue, 4); ?> USDC</strong>
                    <span class="word402-stat-sub">실제 판매자 지갑 수취액</span>
                </div>
                <div class="word402-stat-card">
                    <span class="word402-stat-label">총 온체인 정산 건수</span>
                    <strong class="word402-stat-value"><?php echo number_format($total_count); ?> 건</strong>
                    <span class="word402-stat-sub">HTTP 402 완료 트랜잭션</span>
                </div>
                <div class="word402-stat-card">
                    <span class="word402-stat-label">블록체인 네트워크</span>
                    <strong class="word402-stat-value">Base Sepolia L2</strong>
                    <span class="word402-stat-sub">Chain ID: 84532</span>
                </div>
                <div class="word402-stat-card">
                    <span class="word402-stat-label">결제 토큰 (USDC)</span>
                    <strong class="word402-stat-value"><code>0x036C...dCF7e</code></strong>
                    <span class="word402-stat-sub">Circle Official ERC-20</span>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="word402-table-wrapper">
                <table class="word402-ledger-table">
                    <thead>
                        <tr>
                            <th class="word402-col-date">정산 완료 일시</th>
                            <th class="word402-col-tx">트랜잭션 해시 (Tx Hash)</th>
                            <th class="word402-col-post">대상 포스트</th>
                            <th class="word402-col-wallet">구매자 에이전트 지갑</th>
                            <th class="word402-col-amount">결제 금액</th>
                            <th class="word402-col-block">블록 번호</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($receipts)) : ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:40px; color:#94a3b8;">
                                    기록된 온체인 결제 내역이 없습니다.
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($receipts as $r) : ?>
                                <?php 
                                    $short_tx = substr($r->tx_hash, 0, 10) . '...' . substr($r->tx_hash, -8);
                                    $short_payer = substr($r->payer_address, 0, 8) . '...' . substr($r->payer_address, -6);
                                    $basescan_url = 'https://sepolia.basescan.org/tx/' . esc_attr($r->tx_hash);
                                ?>
                                <tr>
                                    <td class="word402-col-date"><?php echo esc_html($r->settled_at); ?></td>
                                    <td class="word402-col-tx">
                                        <a href="<?php echo $basescan_url; ?>" target="_blank" rel="noopener noreferrer" class="word402-tx-link">
                                            <code><?php echo esc_html($short_tx); ?></code> &#x2197;
                                        </a>
                                    </td>
                                    <td class="word402-col-post">
                                        <a href="<?php echo get_permalink($r->post_id); ?>">
                                            <?php echo esc_html($r->post_title ? $r->post_title : '포스트 #' . $r->post_id); ?>
                                        </a>
                                    </td>
                                    <td class="word402-col-wallet"><code><?php echo esc_html($short_payer); ?></code></td>
                                    <td class="word402-col-amount">+<?php echo esc_html($r->settled_amount); ?> USDC</td>
                                    <td class="word402-col-block"><span class="word402-block-tag">#<?php echo esc_html($r->block_number); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="word402-ledger-footer-info">
                <p>💡 <em>Word402는 EOA 지갑 및 스마트 컨트랙트를 통해 무인으로 온체인 결제를 검증하며, 영수증 재사용 공격(Replay Attack)을 원천 차단합니다.</em></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle standalone virtual page for /?word402_ledger=1
     */
    public function handle_standalone_ledger_page() {
        if (isset($_GET['word402_ledger']) && $_GET['word402_ledger'] === '1') {
            get_header();
            echo '<div style="max-width:1440px; width:94vw; margin:40px auto; padding:0 20px; box-sizing:border-box;">';
            echo $this->render_public_ledger_shortcode(array('limit' => 50));
            echo '</div>';
            get_footer();
            exit;
        }
    }

    /**
     * Styles for Public Ledger Page & Table
     */
    private function get_ledger_styles() {
        return '
        <style>
        .word402-public-ledger {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #1e293b;
            margin-top: 30px;
            margin-bottom: 50px;
            width: 94vw;
            max-width: 1440px;
            margin-left: calc(50% - 47vw);
            margin-right: calc(50% - 47vw);
            box-sizing: border-box;
            padding: 0 16px;
        }
        /* Break out from WordPress block theme constrained widths */
        .entry-content .word402-public-ledger,
        .is-layout-constrained > .word402-public-ledger,
        .wp-block-post-content .word402-public-ledger,
        .alignwide .word402-public-ledger {
            max-width: 1440px !important;
            width: 94vw !important;
        }
        .word402-ledger-header {
            margin-bottom: 25px;
        }
        .word402-ledger-header h2 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 8px;
            color: #0f172a;
            letter-spacing: -0.5px;
        }
        .word402-ledger-header p {
            color: #64748b;
            font-size: 15px;
            line-height: 1.6;
        }
        .word402-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        @media (max-width: 992px) {
            .word402-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 576px) {
            .word402-stats-grid {
                grid-template-columns: 1fr;
            }
        }
        .word402-stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .word402-stat-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .word402-stat-value {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
        }
        .word402-stat-value.word402-highlight {
            color: #059669;
        }
        .word402-stat-sub {
            font-size: 11.5px;
            color: #94a3b8;
        }
        .word402-table-wrapper {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            -webkit-overflow-scrolling: touch;
        }
        .word402-ledger-table {
            width: 100%;
            min-width: 960px;
            border-collapse: collapse;
            text-align: left;
            font-size: 13.5px;
        }
        .word402-ledger-table th {
            background: #f8fafc;
            padding: 14px 18px;
            color: #475569;
            font-weight: 600;
            font-size: 12.5px;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .word402-ledger-table td {
            padding: 14px 18px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }
        .word402-ledger-table tr:hover td {
            background: #f8fafc;
        }
        .word402-block-tag {
            background: #f1f5f9;
            color: #475569;
            padding: 3px 8px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 12px;
            font-weight: 600;
        }
        .word402-ledger-footer-info {
            margin-top: 14px;
            font-size: 12.5px;
            color: #64748b;
        }
        </style>';
    }
}
