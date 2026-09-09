<?php
if (!defined('ABSPATH')) {
    exit;
}

$total_revenue = Word402_DB::get_total_revenue();
$settled_count = Word402_DB::get_total_settled_count();
$recent_receipts = Word402_DB::get_recent_receipts(8);
$admin_wallet = get_option('word402_admin_wallet', '미설정');
$network = get_option('word402_network', 'base-sepolia');
?>

<div class="wrap">
    <h1 style="display:flex; align-items:center; gap:10px;">
        <span class="dashicons dashicons-money-alt" style="font-size:32px; width:32px; height:32px;"></span>
        Word402 에이전틱 페이먼트 대시보드
        <span style="font-size:12px; background:#2271b1; color:#fff; padding:3px 8px; border-radius:12px;">v1.0.0</span>
    </h1>
    <p>AI 에이전트로부터 발생한 x402 마이크로페이먼트 정산 현황 및 트랜잭션 통계입니다.</p>

    <!-- KPI Cards -->
    <div style="display:flex; gap:20px; margin:25px 0;">
        <div style="flex:1; background:#fff; border:1px solid #ccd0d4; padding:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size:13px; color:#646970; font-weight:600; text-transform:uppercase;">총 누적 정산 수익</div>
            <div style="font-size:32px; font-weight:700; color:#1d2327; margin-top:8px;">
                $<?php echo number_format($total_revenue, 4); ?> <span style="font-size:16px; color:#2271b1;">USDC</span>
            </div>
            <div style="font-size:12px; color:#50575e; margin-top:5px;">네트워크: <code><?php echo esc_html($network); ?></code></div>
        </div>

        <div style="flex:1; background:#fff; border:1px solid #ccd0d4; padding:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size:13px; color:#646970; font-weight:600; text-transform:uppercase;">에이전트 결제 건수</div>
            <div style="font-size:32px; font-weight:700; color:#00a32a; margin-top:8px;">
                <?php echo number_format($settled_count); ?> <span style="font-size:16px; color:#50575e;">회</span>
            </div>
            <div style="font-size:12px; color:#50575e; margin-top:5px;">온체인 결제 완결(Finalized) 건수</div>
        </div>

        <div style="flex:1; background:#fff; border:1px solid #ccd0d4; padding:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size:13px; color:#646970; font-weight:600; text-transform:uppercase;">수취 지갑 주소</div>
            <div style="font-size:15px; font-weight:600; color:#1d2327; margin-top:12px; word-break:break-all;">
                <code><?php echo esc_html($admin_wallet); ?></code>
            </div>
            <div style="font-size:12px; color:#2271b1; margin-top:8px;">
                <a href="<?php echo admin_url('admin.php?page=word402-settings'); ?>">지갑 및 RPC 설정 변경 &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Recent Transactions Table -->
    <div style="background:#fff; border:1px solid #ccd0d4; border-radius:8px; padding:20px; margin-top:20px;">
        <h2 style="margin-top:0;">최근 정산된 결제 내역 (Recent Settled Receipts)</h2>
        <table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
            <thead>
                <tr>
                    <th style="width:160px;">정산 일시</th>
                    <th>열람 포스트</th>
                    <th style="width:180px;">에이전트 지갑</th>
                    <th style="width:120px;">지불 금액</th>
                    <th style="width:200px;">트랜잭션 해시</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_receipts)) : ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:30px; color:#8c8f94;">
                            아직 발생한 결제 내역이 없습니다. AI 에이전트가 402 결제를 완료하면 이곳에 실시간 표시됩니다.
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($recent_receipts as $receipt) : ?>
                        <tr>
                            <td><?php echo esc_html($receipt->settled_at); ?></td>
                            <td>
                                <strong>
                                    <a href="<?php echo get_permalink($receipt->post_id); ?>" target="_blank">
                                        <?php echo esc_html($receipt->post_title ? $receipt->post_title : '포스트 #' . $receipt->post_id); ?>
                                    </a>
                                </strong>
                            </td>
                            <td><code><?php echo esc_html(substr($receipt->payer_address, 0, 8) . '...' . substr($receipt->payer_address, -6)); ?></code></td>
                            <td><strong style="color:#00a32a;">+<?php echo esc_html($receipt->settled_amount); ?> USDC</strong></td>
                            <td>
                                <a href="https://sepolia.basescan.org/tx/<?php echo esc_attr($receipt->tx_hash); ?>" target="_blank" style="text-decoration:none;">
                                    <code><?php echo esc_html(substr($receipt->tx_hash, 0, 10) . '...'); ?></code> &#x2197;
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
