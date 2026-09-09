<?php
if (!defined('ABSPATH')) {
    exit;
}

$receipts = Word402_DB::get_recent_receipts(50);
?>

<div class="wrap">
    <h1>온체인 트랜잭션 원장 (Transaction Ledger)</h1>
    <p>Word402 플러그인을 통해 온체인 검증이 완료된 모든 AI 에이전트 결제 영수증 내역입니다.</p>

    <table class="wp-list-table widefat fixed striped" style="margin-top:15px;">
        <thead>
            <tr>
                <th style="width:160px;">정산 완료 일시</th>
                <th style="width:220px;">트랜잭션 해시</th>
                <th>대상 포스트</th>
                <th style="width:180px;">에이전트 지갑</th>
                <th style="width:110px;">결제 금액</th>
                <th style="width:100px;">블록 번호</th>
                <th>User-Agent 식별자</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($receipts)) : ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:30px; color:#8c8f94;">
                        기록된 트랜잭션 영수증이 없습니다.
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($receipts as $r) : ?>
                    <tr>
                        <td><?php echo esc_html($r->settled_at); ?></td>
                        <td>
                            <a href="https://sepolia.basescan.org/tx/<?php echo esc_attr($r->tx_hash); ?>" target="_blank">
                                <code><?php echo esc_html(substr($r->tx_hash, 0, 12) . '...' . substr($r->tx_hash, -8)); ?></code> &#x2197;
                            </a>
                        </td>
                        <td>
                            <strong>
                                <a href="<?php echo get_permalink($r->post_id); ?>" target="_blank">
                                    <?php echo esc_html($r->post_title ? $r->post_title : '포스트 #' . $r->post_id); ?>
                                </a>
                            </strong>
                        </td>
                        <td><code><?php echo esc_html(substr($r->payer_address, 0, 8) . '...' . substr($r->payer_address, -6)); ?></code></td>
                        <td><strong style="color:#00a32a;">+<?php echo esc_html($r->settled_amount); ?> USDC</strong></td>
                        <td><code>#<?php echo esc_html($r->block_number); ?></code></td>
                        <td><small style="color:#646970;"><?php echo esc_html($r->agent_user_agent); ?></small></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
