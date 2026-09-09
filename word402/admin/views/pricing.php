<?php
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission for saving custom post pricing
if (isset($_POST['word402_pricing_nonce']) && wp_verify_nonce($_POST['word402_pricing_nonce'], 'word402_save_pricing')) {
    if (isset($_POST['post_prices']) && is_array($_POST['post_prices'])) {
        foreach ($_POST['post_prices'] as $post_id => $price) {
            $post_id = (int)$post_id;
            $price = floatval($price);
            $enabled = isset($_POST['post_enabled'][$post_id]) ? 1 : 0;

            update_post_meta($post_id, '_word402_enabled', $enabled);
            update_post_meta($post_id, '_word402_custom_price', $price);
        }
        echo '<div class="notice notice-success is-dismissible"><p>포스트별 가격 정책이 성공적으로 저장되었습니다.</p></div>';
    }
}

$default_price = get_option('word402_default_price', '0.005');

// Query published posts
$posts = get_posts(array(
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => 50,
    'orderby'        => 'date',
    'order'          => 'DESC'
));
?>

<div class="wrap">
    <h1>포스트별 x402 유료화 및 가격 관리</h1>
    <p>글별로 AI 에이전트 대상 유료화 여부와 개별 단가를 설정할 수 있습니다. 값을 비워두면 사이트 기본 단가(<code><?php echo esc_html($default_price); ?> USDC</code>)가 적용됩니다.</p>

    <form method="post" action="">
        <?php wp_nonce_field('word402_save_pricing', 'word402_pricing_nonce'); ?>
        <table class="wp-list-table widefat fixed striped" style="margin-top:15px;">
            <thead>
                <tr>
                    <th style="width:70px;">유료화</th>
                    <th>글 제목</th>
                    <th style="width:140px;">작성일</th>
                    <th style="width:180px;">열람 단가 (USDC)</th>
                    <th style="width:120px;">엔드포인트</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($posts)) : ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:20px;">발행된 글이 없습니다.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($posts as $post) : 
                        $enabled_meta = get_post_meta($post->ID, '_word402_enabled', true);
                        $is_enabled = ($enabled_meta === '') ? 1 : (bool)$enabled_meta;
                        $custom_price = get_post_meta($post->ID, '_word402_custom_price', true);
                        $display_price = ($custom_price !== '') ? $custom_price : $default_price;
                    ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="post_enabled[<?php echo $post->ID; ?>]" value="1" <?php checked($is_enabled, true); ?> />
                            </td>
                            <td>
                                <strong><a href="<?php echo get_edit_post_link($post->ID); ?>"><?php echo esc_html($post->post_title); ?></a></strong>
                            </td>
                            <td><?php echo get_the_date('Y-m-d', $post); ?></td>
                            <td>
                                <input type="number" step="0.0001" min="0" name="post_prices[<?php echo $post->ID; ?>]" value="<?php echo esc_attr($display_price); ?>" style="width:110px;" /> USDC
                            </td>
                            <td>
                                <a href="<?php echo rest_url('word402/v1/posts/' . $post->ID); ?>" target="_blank" class="button button-small">
                                    API 확인
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <p class="submit">
            <input type="submit" class="button button-primary" value="가격 설정 일괄 저장" />
        </p>
    </form>
</div>
