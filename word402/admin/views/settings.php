<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Word402 환경설정 (Settings)</h1>
    <p>블록체인 네트워크 연동 및 결제 정산 계좌, 기본 단가를 설정합니다.</p>

    <form method="post" action="options.php">
        <?php
        settings_fields('word402_settings_group');
        do_settings_sections('word402_settings_group');
        ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="word402_admin_wallet">수취 지갑 주소 (Admin Wallet) <span style="color:red;">*</span></label></th>
                    <td>
                        <input name="word402_admin_wallet" type="text" id="word402_admin_wallet" value="<?php echo esc_attr(get_option('word402_admin_wallet')); ?>" class="regular-text" placeholder="0x..." required />
                        <p class="description">AI 에이전트가 송금한 USDC가 입금될 운영자의 Base Sepolia 지갑 주소입니다.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="word402_network">결제 네트워크 (Network)</label></th>
                    <td>
                        <select name="word402_network" id="word402_network">
                            <option value="base-sepolia" <?php selected(get_option('word402_network'), 'base-sepolia'); ?>>Base Sepolia (EVM L2 Testnet)</option>
                            <option value="base-mainnet" <?php selected(get_option('word402_network'), 'base-mainnet'); ?> disabled>Base Mainnet (준비 중)</option>
                            <option value="solana-devnet" <?php selected(get_option('word402_network'), 'solana-devnet'); ?> disabled>Solana Devnet (준비 중)</option>
                        </select>
                        <p class="description">현재 x402 표준 레퍼런스인 Base Sepolia 네트워크가 활성화되어 있습니다.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="word402_rpc_url">JSON-RPC 노드 URL</label></th>
                    <td>
                        <input name="word402_rpc_url" type="url" id="word402_rpc_url" value="<?php echo esc_attr(get_option('word402_rpc_url', 'https://sepolia.base.org')); ?>" class="regular-text" />
                        <p class="description">트랜잭션 영수증을 직접 검증할 Base Sepolia RPC 엔드포인트입니다 (Alchemy, QuickNode 또는 Public RPC).</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="word402_usdc_contract">USDC 토큰 컨트랙트 주소</label></th>
                    <td>
                        <input name="word402_usdc_contract" type="text" id="word402_usdc_contract" value="<?php echo esc_attr(get_option('word402_usdc_contract', '0x036CbD53842c5426634e7929541eC2318f3dCF7e')); ?>" class="regular-text" />
                        <p class="description">Base Sepolia 공식 Circle USDC 컨트랙트 주소입니다.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="word402_default_price">포스트 기본 단가 (USDC)</label></th>
                    <td>
                        <input name="word402_default_price" type="number" step="0.0001" min="0" id="word402_default_price" value="<?php echo esc_attr(get_option('word402_default_price', '0.005')); ?>" class="small-text" /> USDC
                        <p class="description">개별 가격이 지정되지 않은 모든 글에 기본 적용되는 1회 열람 가격입니다 (기본: 0.005 USDC).</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="word402_challenge_ttl">결제 챌린지 유효시간 (TTL)</label></th>
                    <td>
                        <input name="word402_challenge_ttl" type="number" min="60" max="3600" id="word402_challenge_ttl" value="<?php echo esc_attr(get_option('word402_challenge_ttl', '300')); ?>" class="small-text" /> 초
                        <p class="description">402 결제 요구 발행 후 유효한 시간입니다 (기본: 300초 / 5분).</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">콘텐츠 정제 서빙</th>
                    <td>
                        <label for="word402_enable_markdown">
                            <input name="word402_enable_markdown" type="checkbox" id="word402_enable_markdown" value="1" <?php checked(get_option('word402_enable_markdown', 1), 1); ?> />
                            결제 완료 시 불필요한 HTML 태그를 제거하고 LLM 친화적 클린 Markdown으로 변환하여 서빙
                        </label>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button('설정 저장'); ?>
    </form>
</div>
