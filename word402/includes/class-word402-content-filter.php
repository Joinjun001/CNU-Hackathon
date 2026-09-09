<?php
/**
 * Content Transformer & Markdown Parser for AI Agents
 */

if (!defined('ABSPATH')) {
    exit;
}

class Word402_Content_Filter {

    /**
     * Convert HTML post content to clean Markdown
     */
    public static function to_markdown($html, $title = '') {
        if (empty($html)) {
            return '';
        }

        // 1. Remove dangerous / UI-only tags
        $html = preg_replace('/<(script|style|nav|footer|header|aside|iframe|noscript)\b[^>]*>(.*?)<\/\1>/is', '', $html);

        // 2. Convert Headers
        $html = preg_replace('/<h1\b[^>]*>(.*?)<\/h1>/is', "\n# $1\n", $html);
        $html = preg_replace('/<h2\b[^>]*>(.*?)<\/h2>/is', "\n## $1\n", $html);
        $html = preg_replace('/<h3\b[^>]*>(.*?)<\/h3>/is', "\n### $1\n", $html);
        $html = preg_replace('/<h4\b[^>]*>(.*?)<\/h4>/is', "\n#### $1\n", $html);
        $html = preg_replace('/<h5\b[^>]*>(.*?)<\/h5>/is', "\n##### $1\n", $html);
        $html = preg_replace('/<h6\b[^>]*>(.*?)<\/h6>/is', "\n###### $1\n", $html);

        // 3. Convert Bold & Italic
        $html = preg_replace('/<(strong|b)\b[^>]*>(.*?)<\/\1>/is', '**$2**', $html);
        $html = preg_replace('/<(em|i)\b[^>]*>(.*?)<\/\1>/is', '*$2*', $html);

        // 4. Convert Code Blocks
        $html = preg_replace('/<pre\b[^>]*><code\b[^>]*>(.*?)<\/code><\/pre>/is', "\n```\n$1\n```\n", $html);
        $html = preg_replace('/<code\b[^>]*>(.*?)<\/code>/is', '`$1`', $html);

        // 5. Convert Links & Images
        $html = preg_replace('/<a\b[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', '[$2]($1)', $html);
        $html = preg_replace('/<img\b[^>]*src=["\']([^"\']*)["\'][^>]*alt=["\']([^"\']*)["\'][^>]*>/is', '![$2]($1)', $html);
        $html = preg_replace('/<img\b[^>]*src=["\']([^"\']*)["\'][^>]*>/is', '![]($1)', $html);

        // 6. Convert Lists
        $html = preg_replace('/<li\b[^>]*>(.*?)<\/li>/is', "- $1\n", $html);
        $html = preg_replace('/<\/(ul|ol)>/is', "\n", $html);
        $html = preg_replace('/<(ul|ol)\b[^>]*>/is', '', $html);

        // 7. Convert Paragraphs & Line Breaks
        $html = preg_replace('/<p\b[^>]*>(.*?)<\/p>/is', "\n$1\n", $html);
        $html = preg_replace('/<br\s*\/?>/is', "\n", $html);
        $html = preg_replace('/<hr\s*\/?>/is', "\n---\n", $html);

        // 8. Strip remaining HTML tags
        $markdown = strip_tags($html);

        // 9. Decode HTML entities and normalize whitespace
        $markdown = html_entity_decode($markdown, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $markdown = preg_replace("/\n{3,}/", "\n\n", trim($markdown));

        // Prepend title if provided
        if (!empty($title)) {
            $markdown = "# " . trim($title) . "\n\n" . $markdown;
        }

        return $markdown;
    }

    /**
     * Generate teaser preview for unpaid access
     */
    public static function get_teaser($post, $max_paragraphs = 2) {
        $raw = $post->post_excerpt;
        if (empty($raw)) {
            $raw = $post->post_content;
        }

        $md = self::to_markdown($raw);
        $paragraphs = explode("\n\n", $md);
        $teaser_paragraphs = array_slice($paragraphs, 0, $max_paragraphs);

        return implode("\n\n", $teaser_paragraphs) . "\n\n*(본문이 유료화되어 있습니다. x402 결제 후 전체 데이터를 열람할 수 있습니다.)*";
    }
}
