<?php
/**
 * Post Editor Sidebar Metabox for Word402 Pricing
 */

if (!defined('ABSPATH')) {
    exit;
}

class Word402_Metabox {

    public function init() {
        add_action('add_meta_boxes', array($this, 'add_metabox'));
        add_action('save_post', array($this, 'save_metabox'), 10, 2);
    }

    public function add_metabox() {
        add_meta_box(
            'word402_post_settings',
            'Word402 에이전트 유료화 설정',
            array($this, 'render_metabox'),
            'post',
            'side',
            'high'
        );
    }

    public function render_metabox($post) {
        wp_nonce_field('word402_metabox_save', 'word402_metabox_nonce');

        $enabled_meta = get_post_meta($post->ID, '_word402_enabled', true);
        $is_enabled = ($enabled_meta === '') ? 1 : (bool)$enabled_meta;
        $custom_price = get_post_meta($post->ID, '_word402_custom_price', true);
        $default_price = get_option('word402_default_price', '0.005');
        ?>
        <div style="padding:5px 0;">
            <p>
                <label>
                    <input type="checkbox" name="word402_enabled" value="1" <?php checked($is_enabled, true); ?> />
                    <strong>AI 에이전트 402 페이월 적용</strong>
                </label>
            </p>
            <p style="margin-top:12px;">
                <label for="word402_custom_price" style="display:block; font-weight:600; margin-bottom:4px;">
                    이 글 개별 단가 (USDC):
                </label>
                <input type="number" step="0.0001" min="0" name="word402_custom_price" id="word402_custom_price" 
                       value="<?php echo esc_attr($custom_price); ?>" placeholder="<?php echo esc_attr($default_price); ?>" style="width:100%;" />
                <small style="color:#646970; display:block; margin-top:4px;">
                    비워두면 사이트 기본 단가(<?php echo esc_html($default_price); ?> USDC)가 적용됩니다.
                </small>
            </p>
            <hr style="margin:12px 0; border:0; border-top:1px solid #ddd;" />
            <p style="font-size:11px; color:#50575e; margin:0;">
                REST 엔드포인트:<br/>
                <code>/wp-json/word402/v1/posts/<?php echo $post->ID; ?></code>
            </p>
        </div>
        <?php
    }

    public function save_metabox($post_id, $post) {
        if (!isset($_POST['word402_metabox_nonce']) || !wp_verify_nonce($_POST['word402_metabox_nonce'], 'word402_metabox_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save enabled state
        $enabled = isset($_POST['word402_enabled']) ? 1 : 0;
        update_post_meta($post_id, '_word402_enabled', $enabled);

        // Save custom price
        if (isset($_POST['word402_custom_price']) && $_POST['word402_custom_price'] !== '') {
            update_post_meta($post_id, '_word402_custom_price', floatval($_POST['word402_custom_price']));
        } else {
            delete_post_meta($post_id, '_word402_custom_price');
        }
    }
}
