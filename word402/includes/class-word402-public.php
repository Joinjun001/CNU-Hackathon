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
        // Append on-chain proof badge to post content
        add_filter('the_content', array($this, 'render_post_purchase_badge'), 99);

        // Register shortcode [word402_ledger]
        add_shortcode('word402_ledger', array($this, 'render_public_ledger_shortcode'));

        // Virtual page / standalone query handler (?word402_ledger=1)
        add_action('template_redirect', array($this, 'handle_standalone_ledger_page'), 5);
    }

    /**
     * Render On-Chain Proof of Purchase Badge at the bottom of post content
     */
    public function render_post_purchase_badge($content) {
        if (!is_singular('post') || is_admin() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return $content;
        }

        $price_info = Word402_DB::get_post_price_info($post_id);
        $settled_count = Word402_DB::get_post_settled_count($post_id);
        $receipts = Word402_DB::get_post_receipts($post_id, 3);

        // If post is not protected and has no purchases, don't show badge
        if (!$price_info['is_enabled'] && $settled_count === 0) {
            return $content;
        }

        $ledger_url = home_url('/ledger/');
        $html = '';

        if ($settled_count > 0 && !empty($receipts)) {
            $latest = $receipts[0];
            $short_tx = substr($latest->tx_hash, 0, 10) . '...' . substr($latest->tx_hash, -8);
            $short_payer = substr($latest->payer_address, 0, 8) . '...' . substr($latest->payer_address, -6);
            $basescan_url = 'https://sepolia.basescan.org/tx/' . esc_attr($latest->tx_hash);

            $html .= '<div class="word402-proof-card">';
            $html .= '  <div class="word402-card-header">';
            $html .= '    <div class="word402-badge-left">';
            $html .= '      <span class="word402-shield-icon">🛡️</span>';
            $html .= '      <strong>Word402 온체인 결제 인증</strong>';
            $html .= '      <span class="word402-tag-verified">Verified AI Purchase</span>';
            $html .= '    </div>';
            $html .= '    <div class="word402-badge-right">';
            $html .= '      <span class="word402-pulse-dot"></span>';
            $html .= '      <span>Base Sepolia L2 (84532)</span>';
            $html .= '    </div>';
            $html .= '  </div>';

            $html .= '  <div class="word402-card-body">';
            $html .= '    <p class="word402-desc">본 콘텐츠는 자율 AI 에이전트(머신)에 의해 <strong>HTTP 402 프로토콜</strong>을 거쳐 블록체인에 영구 기록된 유료 정산 데이터입니다. (누적 결제: <strong>' . $settled_count . '건</strong>)</p>';
            
            $html .= '    <div class="word402-meta-grid">';
            $html .= '      <div class="word402-meta-item">';
            $html .= '        <span class="word402-label">최근 트랜잭션 해시</span>';
            $html .= '        <a href="' . $basescan_url . '" target="_blank" rel="noopener noreferrer" class="word402-tx-link"><code>' . esc_html($short_tx) . '</code> &#x2197;</a>';
            $html .= '      </div>';
            $html .= '      <div class="word402-meta-item">';
            $html .= '        <span class="word402-label">확정 블록 번호</span>';
            $html .= '        <span class="word402-value"><code>#' . esc_html($latest->block_number) . '</code></span>';
            $html .= '      </div>';
            $html .= '      <div class="word402-meta-item">';
            $html .= '        <span class="word402-label">구매 에이전트 지갑</span>';
            $html .= '        <span class="word402-value"><code>' . esc_html($short_payer) . '</code></span>';
            $html .= '      </div>';
            $html .= '      <div class="word402-meta-item">';
            $html .= '        <span class="word402-label">정산 금액</span>';
            $html .= '        <span class="word402-amount">+' . esc_html($latest->settled_amount) . ' USDC</span>';
            $html .= '      </div>';
            $html .= '    </div>';
            $html .= '  </div>';

            $html .= '  <div class="word402-card-footer">';
            $html .= '    <span class="word402-footer-note">⚡ 정산 완료: ' . esc_html($latest->settled_at) . '</span>';
            $html .= '    <a href="' . esc_url($ledger_url) . '" class="word402-footer-link">전체 AI 결제 원장 보기 &rarr;</a>';
            $html .= '  </div>';
            $html .= '</div>';
        } else {
            // Post has paywall enabled but 0 purchases yet
            $html .= '<div class="word402-proof-card word402-ready-card">';
            $html .= '  <div class="word402-card-header">';
            $html .= '    <div class="word402-badge-left">';
            $html .= '      <span class="word402-shield-icon">🔒</span>';
            $html .= '      <strong>Word402 AI 자율 결제 게이트웨이 활성화</strong>';
            $html .= '    </div>';
            $html .= '    <div class="word402-badge-right">';
            $html .= '      <span>단가: ' . esc_html($price_info['price']) . ' USDC</span>';
            $html .= '    </div>';
            $html .= '  </div>';
            $html .= '  <div class="word402-card-body">';
            $html .= '    <p class="word402-desc">이 글은 AI 에이전트(Perplexity, ChatGPT 등)가 크롤링할 때 <strong>HTTP 402 결제 요구</strong>를 반환하며, Base Sepolia USDC 결제 시 정제 Markdown 본문을 공급합니다.</p>';
            $html .= '  </div>';
            $html .= '  <div class="word402-card-footer">';
            $html .= '    <span class="word402-footer-note">엔드포인트: <code>/wp-json/word402/v1/posts/' . $post_id . '</code></span>';
            $html .= '    <a href="' . esc_url($ledger_url) . '" class="word402-footer-link">전체 AI 결제 원장 보기 &rarr;</a>';
            $html .= '  </div>';
            $html .= '</div>';
        }

        return $content . $this->get_badge_styles() . $html;
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
                            <th style="width:160px;">정산 완료 일시</th>
                            <th>트랜잭션 해시 (Tx Hash)</th>
                            <th>대상 포스트</th>
                            <th>구매자 에이전트 지갑</th>
                            <th style="width:110px;">결제 금액</th>
                            <th style="width:110px;">블록 번호</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($receipts)) : ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:35px; color:#94a3b8;">
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
                                    <td><small><?php echo esc_html($r->settled_at); ?></small></td>
                                    <td>
                                        <a href="<?php echo $basescan_url; ?>" target="_blank" rel="noopener noreferrer" class="word402-tx-link">
                                            <code><?php echo esc_html($short_tx); ?></code> &#x2197;
                                        </a>
                                    </td>
                                    <td>
                                        <strong>
                                            <a href="<?php echo get_permalink($r->post_id); ?>">
                                                <?php echo esc_html($r->post_title ? $r->post_title : '포스트 #' . $r->post_id); ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td><code><?php echo esc_html($short_payer); ?></code></td>
                                    <td><strong class="word402-amount">+<?php echo esc_html($r->settled_amount); ?> USDC</strong></td>
                                    <td><span class="word402-block-tag">#<?php echo esc_html($r->block_number); ?></span></td>
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
            echo '<div style="max-width:1140px; margin:40px auto; padding:0 20px;">';
            echo $this->render_public_ledger_shortcode(array('limit' => 50));
            echo '</div>';
            get_footer();
            exit;
        }
    }

    /**
     * Styles for Post Purchase Badge
     */
    private function get_badge_styles() {
        return '
        <style>
        .word402-proof-card {
            margin: 35px 0 20px 0;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 14px;
            padding: 22px;
            color: #f8fafc;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .word402-ready-card {
            border-color: rgba(148, 163, 184, 0.3);
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        }
        .word402-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding-bottom: 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .word402-badge-left {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
        }
        .word402-tag-verified {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.4);
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .word402-badge-right {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #94a3b8;
        }
        .word402-pulse-dot {
            width: 8px;
            height: 8px;
            background-color: #10b981;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 8px #10b981;
        }
        .word402-card-body {
            padding: 15px 0;
        }
        .word402-desc {
            font-size: 13.5px;
            line-height: 1.6;
            color: #cbd5e1;
            margin: 0 0 14px 0 !important;
        }
        .word402-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 10px;
            padding: 12px 16px;
        }
        .word402-meta-item {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .word402-label {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .word402-tx-link {
            color: #60a5fa !important;
            text-decoration: none !important;
            font-size: 12.5px;
            font-weight: 500;
        }
        .word402-tx-link:hover {
            text-decoration: underline !important;
        }
        .word402-value {
            font-size: 12.5px;
            color: #e2e8f0;
        }
        .word402-amount {
            color: #34d399;
            font-weight: 700;
            font-size: 13px;
        }
        .word402-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            color: #94a3b8;
        }
        .word402-footer-link {
            color: #38bdf8 !important;
            text-decoration: none !important;
            font-weight: 500;
        }
        .word402-footer-link:hover {
            text-decoration: underline !important;
        }
        </style>';
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
            margin: 20px 0;
        }
        .word402-ledger-header {
            margin-bottom: 25px;
        }
        .word402-ledger-header h2 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #0f172a;
        }
        .word402-ledger-header p {
            color: #64748b;
            font-size: 14.5px;
            line-height: 1.6;
        }
        .word402-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
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
            font-size: 20px;
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
        }
        .word402-ledger-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13.5px;
        }
        .word402-ledger-table th {
            background: #f8fafc;
            padding: 14px 16px;
            color: #475569;
            font-weight: 600;
            font-size: 12.5px;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .word402-ledger-table td {
            padding: 14px 16px;
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
