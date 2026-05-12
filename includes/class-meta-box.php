<?php
/**
 * Meta Box Class - Add AI Image Generator to Post Editor
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIIG_Meta_Box {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta_box'), 10, 2);
    }
    
    /**
     * Add meta box to post editor
     */
    public function add_meta_box() {
        add_meta_box(
            'aiig-meta-box',
            __('AI Image Generator', 'ai-image-generator-congcuseoai'),
            array($this, 'render_meta_box'),
            'post',
            'side',
            'default'
        );
    }
    
    /**
     * Render meta box content
     */
    public function render_meta_box($post) {
        wp_nonce_field('aiig_meta_box_nonce', 'aiig_meta_box_nonce');
        ?>
        <div class="aiig-meta-box">
            <!-- Step 1: Generate Prompts -->
            <div class="aiig-section">
                <h3 style="margin-top: 0;">📝 Bước 1: Tạo Prompts</h3>
                <p class="description" style="margin-bottom: 10px;">Chọn số lượng prompts</p>
                
                <select id="aiig-prompt-count" class="widefat" style="margin-bottom: 10px;">
                    <option value="1">1 prompt</option>
                    <option value="2">2 prompts</option>
                    <option value="3" selected>3 prompts</option>
                    <option value="4">4 prompts</option>
                    <option value="5">5 prompts</option>
                </select>
                
                <button type="button" id="aiig-generate-prompts" class="button button-primary button-large" style="width: 100%;">
                    🎨 Tạo Prompts
                </button>
            </div>

            <!-- Prompt List Container -->
            <div id="aiig-prompt-list" style="margin-top: 15px;"></div>

            <!-- Step 2: Generate Images -->
            <div class="aiig-section" style="margin-top: 15px;">
                <h3>🖼️ Bước 2: Tạo Ảnh</h3>
                <button type="button" id="aiig-generate-images" class="button button-primary button-large" style="width: 100%;" disabled>
                    🚀 Tạo Ảnh
                </button>
                <p class="description" style="margin-top: 5px; font-size: 11px; color: #666;">
                    💡 Tạo prompts trước để kích hoạt
                </p>
            </div>

            <!-- Image Gallery Container -->
            <div id="aiig-image-gallery" style="margin-top: 15px;"></div>
        </div>
        <?php
    }
    
    /**
     * Save meta box data
     */
    public function save_meta_box($post_id, $post) {
        if (
            ! isset( $_POST['aiig_meta_box_nonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aiig_meta_box_nonce'] ) ), 'aiig_meta_box_nonce' )
        ) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }
}
