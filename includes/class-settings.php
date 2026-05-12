<?php
/**
 * Settings Page Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIIG_Settings {
    
    private static $instance = null;
    private $option_name = 'aiig_settings';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    public function enqueue_assets($hook) {
        // Only load on our settings page
        if ($hook !== 'media_page_ai-image-generator-congcuseoai') {
            return;
        }
        
        wp_enqueue_style(
            'aiig-admin-style',
            AIIG_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            AIIG_VERSION
        );
        
        wp_enqueue_script(
            'aiig-admin-script',
            AIIG_PLUGIN_URL . 'assets/js/admin-script.js',
            array('jquery'),
            AIIG_VERSION,
            true
        );
        
        wp_localize_script('aiig-admin-script', 'aiigData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aiig_nonce'),
        ));
    }
    
    public function add_menu_page() {
        add_submenu_page(
            'upload.php',
            __('AI Image Generator', 'ai-image-generator-congcuseoai'),
            __('AI Image Generator', 'ai-image-generator-congcuseoai'),
            'manage_options',
            'ai-image-generator-congcuseoai',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('aiig_settings_group', $this->option_name, array($this, 'sanitize_settings'));
    }
    
    public function sanitize_settings( $input ) {
        $sanitized = array();

        // Get existing settings to preserve API keys if not changed.
        $existing = get_option( $this->option_name, array() );

        if ( ! is_array( $input ) ) {
            $input = array();
        }

        $allowed_prompt_providers      = array( 'openai', 'gemini' );
        $input_prompt_provider         = $input['prompt_provider'] ?? 'openai';
        $sanitized['prompt_provider']  = in_array( $input_prompt_provider, $allowed_prompt_providers, true )
            ? sanitize_key( $input_prompt_provider )
            : 'openai';

        // Handle prompt API key - password fields come empty if not changed.
        if ( ! empty( $input['prompt_api_key'] ) && '' !== trim( $input['prompt_api_key'] ) ) {
            $sanitized['prompt_api_key'] = sanitize_text_field( $input['prompt_api_key'] );
        } else {
            $sanitized['prompt_api_key'] = $existing['prompt_api_key'] ?? '';
        }

        $sanitized['prompt_model']          = sanitize_text_field( $input['prompt_model'] ?? 'gpt-5-nano-2025-08-07' );
        $sanitized['prompt_temperature']    = floatval( $input['prompt_temperature'] ?? 1 );
        $sanitized['prompt_max_tokens']     = intval( $input['prompt_max_tokens'] ?? 500 );
        $sanitized['prompt_system_message'] = wp_kses_post( $input['prompt_system_message'] ?? $this->get_default_system_prompt() );

        // Active image provider.
        $allowed_image_providers     = array( 'gemini', '9router' );
        $sanitized['image_provider'] = in_array( $input['image_provider'] ?? 'gemini', $allowed_image_providers, true )
            ? sanitize_key( $input['image_provider'] )
            : 'gemini';

        // Agent Platform service account JSON - textarea comes empty if not changed.
        if ( ! empty( $input['vertex_service_account_json'] ) && '' !== trim( $input['vertex_service_account_json'] ) ) {
            $sanitized['vertex_service_account_json'] = $this->sanitize_vertex_service_account_json( $input['vertex_service_account_json'] );
        } else {
            $sanitized['vertex_service_account_json'] = $existing['vertex_service_account_json'] ?? '';
        }

        $sanitized['vertex_location'] = sanitize_key( $input['vertex_location'] ?? ( $existing['vertex_location'] ?? 'global' ) );

        $allowed_gemini_image_models = array( 'gemini-3.1-flash-image-preview', 'gemini-3-pro-image-preview' );
        $input_gemini_model          = $input['gemini_model'] ?? 'gemini-3.1-flash-image-preview';
        $sanitized['gemini_model']   = in_array( $input_gemini_model, $allowed_gemini_image_models, true )
            ? sanitize_text_field( $input_gemini_model )
            : 'gemini-3.1-flash-image-preview';

        $allowed_image_sizes       = array( '1024x1024', '1792x1024', '1024x1792', '1408x1056', '1056x1408' );
        $input_image_size          = $input['image_size'] ?? '1024x1024';
        $sanitized['image_size']   = in_array( $input_image_size, $allowed_image_sizes, true )
            ? sanitize_text_field( $input_image_size )
            : '1024x1024';

        // 9router fields.
        $sanitized['9router_endpoint'] = esc_url_raw( $input['9router_endpoint'] ?? '' );

        if ( ! empty( $input['9router_api_key'] ) && '' !== trim( $input['9router_api_key'] ) ) {
            $sanitized['9router_api_key'] = sanitize_text_field( $input['9router_api_key'] );
        } else {
            $sanitized['9router_api_key'] = $existing['9router_api_key'] ?? '';
        }

        $sanitized['9router_model']      = sanitize_text_field( $input['9router_model'] ?? 'cx/gpt-5.4-image' );
        $sanitized['9router_image_size'] = sanitize_text_field( $input['9router_image_size'] ?? 'auto' );

        $allowed_qualities              = array( 'auto', 'low', 'medium', 'high', 'standard', 'hd' );
        $sanitized['9router_quality']   = in_array( $input['9router_quality'] ?? 'auto', $allowed_qualities, true )
            ? sanitize_key( $input['9router_quality'] )
            : 'auto';

        $allowed_backgrounds             = array( 'auto', 'transparent', 'opaque' );
        $sanitized['9router_background'] = in_array( $input['9router_background'] ?? 'auto', $allowed_backgrounds, true )
            ? sanitize_key( $input['9router_background'] )
            : 'auto';

        $allowed_image_details             = array( 'auto', 'low', 'high', 'original' );
        $sanitized['9router_image_detail'] = in_array( $input['9router_image_detail'] ?? 'high', $allowed_image_details, true )
            ? sanitize_key( $input['9router_image_detail'] )
            : 'high';

        $allowed_formats                     = array( 'jpeg', 'png', 'webp' );
        $sanitized['9router_output_format']  = in_array( $input['9router_output_format'] ?? 'png', $allowed_formats, true )
            ? sanitize_key( $input['9router_output_format'] )
            : 'png';

        // Image optimization.
        $sanitized['image_width']   = max( 400, min( 4000, intval( $input['image_width'] ?? 1200 ) ) );
        $sanitized['image_quality'] = max( 1, min( 100, intval( $input['image_quality'] ?? 85 ) ) );
        $sanitized['enable_webp']   = isset( $input['enable_webp'] ) ? 1 : 0;

        return $sanitized;
    }

    private function sanitize_vertex_service_account_json( $raw_json ) {
        $raw_json    = trim( (string) $raw_json );
        $credentials = json_decode( $raw_json, true );

        if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $credentials ) ) {
            throw new Exception( __( 'Agent Platform service account JSON không hợp lệ.', 'ai-image-generator-congcuseoai' ) );
        }

        $required_fields = array( 'type', 'project_id', 'private_key', 'client_email' );
        foreach ( $required_fields as $field ) {
            if ( empty( $credentials[ $field ] ) || ! is_string( $credentials[ $field ] ) ) {
                throw new Exception(
                    sprintf(
                        /* translators: %s: service account JSON field name */
                        __( 'Agent Platform service account JSON thiếu trường bắt buộc: %s', 'ai-image-generator-congcuseoai' ),
                        $field
                    )
                );
            }
        }

        if ( 'service_account' !== $credentials['type'] ) {
            throw new Exception( __( 'Agent Platform JSON phải là service account key.', 'ai-image-generator-congcuseoai' ) );
        }

        $encoded = wp_json_encode( $credentials );
        if ( false === $encoded ) {
            throw new Exception( __( 'Không thể lưu Agent Platform service account JSON.', 'ai-image-generator-congcuseoai' ) );
        }

        return $encoded;
    }
    
    private function get_default_system_prompt() {
        return __("Bạn là một chuyên gia tạo prompt cho AI tạo ảnh. Hãy phân tích nội dung bài viết và tạo các prompt chi tiết bằng tiếng Việt để tạo ảnh minh họa phù hợp. Mỗi prompt nên mô tả rõ ràng về chủ đề, phong cách, màu sắc, và cảm xúc của ảnh.", 'ai-image-generator-congcuseoai');
    }
    
    public function render_settings_page() {
        $settings = get_option($this->option_name, array());
        ?>
        <div class="wrap aiig-settings-wrap">
            <h1><?php esc_html_e('Cấu hình AI Image Generator', 'ai-image-generator-congcuseoai'); ?></h1>
            
            <form id="aiig-settings-form" method="post" action="options.php">
                <?php settings_fields('aiig_settings_group'); ?>
                
                <div class="aiig-nav-tabs">
                    <a href="#" class="aiig-nav-tab active" data-tab="tab-prompt"><?php esc_html_e('Cấu hình Prompt AI', 'ai-image-generator-congcuseoai'); ?></a>
                    <a href="#" class="aiig-nav-tab" data-tab="tab-image"><?php esc_html_e( 'Cấu hình Tạo Ảnh', 'ai-image-generator-congcuseoai' ); ?></a>
                    <a href="#" class="aiig-nav-tab" data-tab="tab-optimization"><?php esc_html_e( 'Tối ưu hóa ảnh', 'ai-image-generator-congcuseoai' ); ?></a>
                </div>
                
                <div id="tab-prompt" class="aiig-tab-content active">
                    <h2><?php esc_html_e('Cấu hình AI để tạo Prompt', 'ai-image-generator-congcuseoai'); ?></h2>
                    
                    <div class="aiig-tab-layout">
                        <!-- Left Sidebar: Instructions -->
                        <div class="aiig-tab-sidebar">
                            <div class="aiig-notice" style="background: #e7f5ff; border-left: 4px solid #2271b1; padding: 12px 15px; border-radius: 4px;">
                                <h3 style="margin-top: 0; color: #2271b1; font-size: 14px;"><?php esc_html_e('📝 Hướng dẫn cấu hình', 'ai-image-generator-congcuseoai'); ?></h3>
                                <p style="margin: 8px 0; font-size: 13px;"><?php esc_html_e('Cấu hình AI này để quét nội dung bài viết để tạo prompt mô tả ảnh, sau đó gửi prompt cho Gemini tạo ảnh. Nhớ nhập thông tin cấu hình ở form và ấn lưu thay đổi để sử dụng', 'ai-image-generator-congcuseoai'); ?></p>
                                <p style="margin: 8px 0; padding: 8px; background: #e3f2fd; border-left: 3px solid #2196f3; color: #0d47a1; font-size: 13px;">
                                    <strong>💡 <?php esc_html_e('Lưu ý:', 'ai-image-generator-congcuseoai'); ?></strong><br>
                                    <?php esc_html_e('Đây là AI đọc nội dung bài viết, KHÔNG phải AI tạo ảnh. Bạn có thể sử dụng API khác với API Gemini tạo ảnh ở tab 2. Nên dùng model nhanh và thông minh như gpt-5-nano để tiết kiệm chi phí.', 'ai-image-generator-congcuseoai'); ?>
                                </p>
                                <p style="margin: 8px 0 4px 0; font-size: 13px; font-weight: 600;"><?php esc_html_e('Chọn provider và nhập API key:', 'ai-image-generator-congcuseoai'); ?></p>
                                <ul style="margin: 0 0 8px 0; padding-left: 20px; font-size: 13px;">
                                    <li><strong>OpenAI:</strong> <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">Tạo key</a></li>
                                    <li><strong>Gemini:</strong> <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer">Tạo key</a></li>
                                </ul>
                            </div>
                            
                            <div class="aiig-notice" style="background: #fff3e0; border-left: 4px solid #ff9800; padding: 12px 15px; border-radius: 4px;">
                                <h3 style="margin-top: 0; color: #e65100; font-size: 14px;"><?php esc_html_e('💡 Gợi ý Model', 'ai-image-generator-congcuseoai'); ?></h3>
                                <div style="font-size: 13px;">
                                    <p style="margin: 0 0 8px 0;"><strong>OpenAI:</strong></p>
                                    <ul style="margin: 0 0 12px 0; padding-left: 20px;">
                                        <li>gpt-5-nano-2025-08-07</li>
                                        <li>gpt-5-mini-2025-08-07</li>
                                        <li>gpt-4o-mini</li>
                                    </ul>
                                    
                                    <p style="margin: 0 0 8px 0;"><strong>Gemini:</strong></p>
                                    <ul style="margin: 0 0 12px 0; padding-left: 20px;">
                                        <li>gemini-2.5-flash-lite</li>
                                        <li>gemini-2.5-flash</li>
                                        <li>gemini-2.5-pro</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="aiig-notice" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px 15px; border-radius: 4px;">
                                <p style="margin: 0; font-size: 13px; color: #856404;">
                                    <strong>⚠️ <?php esc_html_e('Lưu ý:', 'ai-image-generator-congcuseoai'); ?></strong><br>
                                    • <?php esc_html_e('API key được ẩn sau khi lưu', 'ai-image-generator-congcuseoai'); ?><br>
                                    • <?php esc_html_e('Để trống để giữ key cũ', 'ai-image-generator-congcuseoai'); ?><br>
                                    • <?php esc_html_e('Test API trước khi sử dụng', 'ai-image-generator-congcuseoai'); ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Right Main: Configuration Fields -->
                        <div class="aiig-tab-main">
                            <div class="aiig-form-group">
                                <label><?php esc_html_e('AI Provider', 'ai-image-generator-congcuseoai'); ?></label>
                                <select name="<?php echo esc_attr( $this->option_name ); ?>[prompt_provider]" id="aiig-prompt-provider">
                                    <option value="openai" <?php selected($settings['prompt_provider'] ?? 'openai', 'openai'); ?>>OpenAI</option>
                                    <option value="gemini" <?php selected($settings['prompt_provider'] ?? 'openai', 'gemini'); ?>>Gemini</option>
                                </select>
                                <span class="description"><?php esc_html_e('Chọn AI provider để tạo prompts từ nội dung bài viết', 'ai-image-generator-congcuseoai'); ?></span>
                            </div>
                            
                            <div class="aiig-form-group">
                                <label><?php esc_html_e('API Key', 'ai-image-generator-congcuseoai'); ?></label>
                                <?php 
                                $prompt_key = $settings['prompt_api_key'] ?? '';
                                $has_prompt_key = !empty($prompt_key);
                                $placeholder = $has_prompt_key ? '••••••••••••••••' : __('Nhập API key', 'ai-image-generator-congcuseoai');
                                ?>
                                <input type="password" 
                                       name="<?php echo esc_attr( $this->option_name ); ?>[prompt_api_key]" 
                                       class="aiig-api-key-input" 
                                       placeholder="<?php echo esc_attr($placeholder); ?>" 
                                       value="" 
                                       autocomplete="new-password"
                                       style="width: 100%;" />
                                <div style="margin-top: 10px;">
                                    <button type="button" class="button button-secondary aiig-test-prompt-connection" style="white-space: nowrap;">
                                        Test API
                                    </button>
                                </div>
                                <span class="description"><?php esc_html_e('Thay API Key nếu thay đổi AI Provider', 'ai-image-generator-congcuseoai'); ?></span>
                                <span class="description" style="color: #2271b1; font-style: italic;"><?php esc_html_e('💡 Test API sẽ tự động lưu cài đặt trước khi kiểm tra', 'ai-image-generator-congcuseoai'); ?></span>
                                <div id="aiig-test-prompt-result" style="margin-top: 8px;"></div>
                            </div>
                            

                            
                            <div class="aiig-form-group">
                                <label><?php esc_html_e('Model', 'ai-image-generator-congcuseoai'); ?></label>
                                <input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[prompt_model]" value="<?php echo esc_attr($settings['prompt_model'] ?? 'gpt-5-nano-2025-08-07'); ?>" />
                                <span class="description"><?php esc_html_e('Nhập model ID (xem gợi ý bên trái)', 'ai-image-generator-congcuseoai'); ?></span>
                            </div>
                            
                            <div class="aiig-form-group">
                                <label><?php esc_html_e('Temperature', 'ai-image-generator-congcuseoai'); ?></label>
                                <input type="number" name="<?php echo esc_attr( $this->option_name ); ?>[prompt_temperature]" value="<?php echo esc_attr($settings['prompt_temperature'] ?? 1); ?>" min="0" max="2" step="0.1" />
                                <span class="description"><?php esc_html_e('Độ sáng tạo: 0 (chính xác) - 2 (sáng tạo)', 'ai-image-generator-congcuseoai'); ?></span>
                            </div>
                            
                            <div class="aiig-form-group">
                                <label><?php esc_html_e('Max Tokens', 'ai-image-generator-congcuseoai'); ?></label>
                                <input type="number" name="<?php echo esc_attr( $this->option_name ); ?>[prompt_max_tokens]" value="<?php echo esc_attr($settings['prompt_max_tokens'] ?? 500); ?>" min="100" max="2000" />
                                <span class="description"><?php esc_html_e('Số lượng tokens tối đa cho response', 'ai-image-generator-congcuseoai'); ?></span>
                            </div>
                            
                            <div class="aiig-form-group">
                                <label><?php esc_html_e('System Prompt', 'ai-image-generator-congcuseoai'); ?></label>
                                <textarea name="<?php echo esc_attr( $this->option_name ); ?>[prompt_system_message]" rows="5"><?php echo esc_textarea($settings['prompt_system_message'] ?? $this->get_default_system_prompt()); ?></textarea>
                                <span class="description"><?php esc_html_e('Hướng dẫn cho AI về cách tạo prompts', 'ai-image-generator-congcuseoai'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="tab-image" class="aiig-tab-content">
                    <h2><?php esc_html_e( 'Cấu hình Tạo Ảnh', 'ai-image-generator-congcuseoai' ); ?></h2>

                    <?php $image_provider = $settings['image_provider'] ?? 'gemini'; ?>

                    <div class="aiig-form-group" style="background:#f0f6fc;padding:12px 15px;border-radius:4px;border-left:4px solid #2271b1;margin-bottom:20px;">
                        <label style="font-weight:700;font-size:14px;"><?php esc_html_e( '🖼️ Dịch vụ tạo ảnh', 'ai-image-generator-congcuseoai' ); ?></label>
                        <div style="margin-top:10px;display:flex;gap:20px;">
                            <label style="font-weight:normal;display:flex;align-items:center;gap:6px;cursor:pointer;">
                                <input type="radio" name="<?php echo esc_attr( $this->option_name ); ?>[image_provider]" value="gemini" id="aiig-provider-gemini"
                                    <?php checked( $image_provider, 'gemini' ); ?> />
                                <strong>Gemini Enterprise Agent Platform</strong> &nbsp;<?php esc_html_e( '(Agent Platform AI)', 'ai-image-generator-congcuseoai' ); ?>
                            </label>
                            <label style="font-weight:normal;display:flex;align-items:center;gap:6px;cursor:pointer;">
                                <input type="radio" name="<?php echo esc_attr( $this->option_name ); ?>[image_provider]" value="9router" id="aiig-provider-9router"
                                    <?php checked( $image_provider, '9router' ); ?> />
                                <strong>9router (Codex)</strong> &nbsp;<?php esc_html_e( '(Local/Custom endpoint)', 'ai-image-generator-congcuseoai' ); ?>
                            </label>
                        </div>
                    </div>

                    <!-- ===== GEMINI SECTION ===== -->
                    <div id="aiig-section-gemini" class="aiig-provider-section"<?php if ( 'gemini' !== $image_provider ) : ?> style="display:none"<?php endif; ?>>
                        <div class="aiig-tab-layout">
                            <div class="aiig-tab-sidebar">
                                <div class="aiig-notice" style="background:#e8f5e9;border-left:4px solid #4caf50;padding:12px 15px;border-radius:4px;">
                                    <h3 style="margin-top:0;color:#2e7d32;font-size:14px;"><?php esc_html_e( '🎨 Hướng dẫn Agent Platform AI', 'ai-image-generator-congcuseoai' ); ?></h3>
                                    <ol style="margin:8px 0;padding-left:20px;font-size:13px;">
                                        <li><?php esc_html_e( 'Tạo Google Cloud project, bật Agent Platform AI / Vertex AI API và billing cho project.', 'ai-image-generator-congcuseoai' ); ?></li>
                                        <li><?php esc_html_e( 'Tạo service account có quyền Vertex AI User, sau đó tạo JSON key. Agent Platform hiện vẫn dùng IAM và endpoint kỹ thuật của Vertex AI API.', 'ai-image-generator-congcuseoai' ); ?></li>
                                        <li><?php esc_html_e( 'Dán toàn bộ service account JSON vào ô cấu hình bên phải.', 'ai-image-generator-congcuseoai' ); ?></li>
                                        <li><?php esc_html_e( 'Giữ location là global nếu dùng Gemini 3.1 Flash Image theo tài liệu Agent Platform / Vertex AI API.', 'ai-image-generator-congcuseoai' ); ?></li>
                                    </ol>
                                </div>
                                <div class="aiig-notice" style="background:#e3f2fd;border-left:4px solid #2196f3;padding:12px 15px;border-radius:4px;">
                                    <h3 style="margin-top:0;color:#0d47a1;font-size:14px;"><?php esc_html_e( '💡 Gợi ý Model', 'ai-image-generator-congcuseoai' ); ?></h3>
                                    <div style="font-size:13px;">
                                        <p style="margin:0 0 4px 0;"><strong>gemini-3.1-flash-image-preview</strong> — <?php esc_html_e( 'Mới nhất, khuyến nghị', 'ai-image-generator-congcuseoai' ); ?></p>
                                        <p style="margin:0;"><strong>gemini-3-pro-image-preview</strong> — <?php esc_html_e( 'Chất lượng cao hơn', 'ai-image-generator-congcuseoai' ); ?></p>
                                    </div>
                                </div>
                                <div class="aiig-notice" style="background:#ffebee;border-left:4px solid #f44336;padding:12px 15px;border-radius:4px;">
                                    <h3 style="margin-top:0;color:#c62828;font-size:14px;"><?php esc_html_e( '🔴 Lưu ý bảo mật', 'ai-image-generator-congcuseoai' ); ?></h3>
                                    <div style="font-size:13px;color:#c62828;">
                                        <p style="margin:0 0 6px 0;"><?php esc_html_e( 'Service account JSON chứa private key và được lưu trong wp_options. Chỉ cấp quyền tối thiểu cho service account và không chia sẻ JSON này.', 'ai-image-generator-congcuseoai' ); ?></p>
                                        <p style="margin:0;"><?php esc_html_e( 'Nút Test Kết Nối chỉ kiểm tra OAuth và countTokens, không tạo ảnh test để tránh phát sinh chi phí ảnh.', 'ai-image-generator-congcuseoai' ); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="aiig-tab-main">
                                <div class="aiig-form-group">
                                    <label><?php esc_html_e( 'Agent Platform Service Account JSON', 'ai-image-generator-congcuseoai' ); ?></label>
                                    <?php
                                    $has_vertex_credentials = ! empty( $settings['vertex_service_account_json'] ?? '' );
                                    $placeholder_vertex     = $has_vertex_credentials
                                        ? __( 'Service account JSON đã được lưu. Để trống để giữ JSON cũ.', 'ai-image-generator-congcuseoai' )
                                        : __( 'Dán toàn bộ nội dung file service account JSON tại đây', 'ai-image-generator-congcuseoai' );
                                    ?>
                                    <textarea name="<?php echo esc_attr( $this->option_name ); ?>[vertex_service_account_json]"
                                              class="aiig-api-key-input"
                                              placeholder="<?php echo esc_attr( $placeholder_vertex ); ?>"
                                              rows="8"
                                              autocomplete="off"
                                              style="width:100%;font-family:monospace;"></textarea>
                                    <div style="margin-top:10px;">
                                        <button type="button" class="button button-secondary aiig-test-gemini-connection"><?php esc_html_e( 'Test Kết Nối Agent Platform', 'ai-image-generator-congcuseoai' ); ?></button>
                                    </div>
                                    <span class="description" style="color:#2271b1;font-style:italic;"><?php esc_html_e( '💡 Test sẽ tự động lưu cài đặt trước khi kiểm tra. JSON không được in lại sau khi lưu.', 'ai-image-generator-congcuseoai' ); ?></span>
                                    <div id="aiig-test-gemini-result" style="margin-top:8px;"></div>
                                </div>
                                <div class="aiig-form-group">
                                    <label><?php esc_html_e( 'Agent Platform Location', 'ai-image-generator-congcuseoai' ); ?></label>
                                    <input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[vertex_location]"
                                           value="<?php echo esc_attr( $settings['vertex_location'] ?? 'global' ); ?>"
                                           placeholder="global" style="width:100%;" />
                                    <span class="description"><?php esc_html_e( 'Khuyến nghị: global cho Gemini 3.1 Flash Image.', 'ai-image-generator-congcuseoai' ); ?></span>
                                </div>
                                <div class="aiig-form-group">
                                    <label><?php esc_html_e( 'Gemini Model', 'ai-image-generator-congcuseoai' ); ?></label>
                                    <select name="<?php echo esc_attr( $this->option_name ); ?>[gemini_model]" id="aiig-gemini-model">
                                        <option value="gemini-3.1-flash-image-preview" <?php selected( $settings['gemini_model'] ?? 'gemini-3.1-flash-image-preview', 'gemini-3.1-flash-image-preview' ); ?>>Gemini 3.1 Flash Image Preview</option>
                                        <option value="gemini-3-pro-image-preview" <?php selected( $settings['gemini_model'] ?? 'gemini-3.1-flash-image-preview', 'gemini-3-pro-image-preview' ); ?>>Gemini 3 Pro Image Preview</option>
                                    </select>
                                </div>
                                <div class="aiig-form-group">
                                    <label><?php esc_html_e( 'Tỷ lệ khung hình (Gemini)', 'ai-image-generator-congcuseoai' ); ?></label>
                                    <select name="<?php echo esc_attr( $this->option_name ); ?>[image_size]">
                                        <option value="1024x1024" <?php selected( $settings['image_size'] ?? '1024x1024', '1024x1024' ); ?>>1:1 — <?php esc_html_e( 'Vuông', 'ai-image-generator-congcuseoai' ); ?></option>
                                        <option value="1792x1024" <?php selected( $settings['image_size'] ?? '1024x1024', '1792x1024' ); ?>>16:9 — <?php esc_html_e( 'Ngang', 'ai-image-generator-congcuseoai' ); ?></option>
                                        <option value="1024x1792" <?php selected( $settings['image_size'] ?? '1024x1024', '1024x1792' ); ?>>9:16 — <?php esc_html_e( 'Dọc', 'ai-image-generator-congcuseoai' ); ?></option>
                                        <option value="1408x1056" <?php selected( $settings['image_size'] ?? '1024x1024', '1408x1056' ); ?>>4:3 — <?php esc_html_e( 'Ngang (Chuẩn)', 'ai-image-generator-congcuseoai' ); ?></option>
                                        <option value="1056x1408" <?php selected( $settings['image_size'] ?? '1024x1024', '1056x1408' ); ?>>3:4 — <?php esc_html_e( 'Dọc (Chuẩn)', 'ai-image-generator-congcuseoai' ); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div><!-- /#aiig-section-gemini -->

                    <!-- ===== 9ROUTER SECTION ===== -->
                    <div id="aiig-section-9router" class="aiig-provider-section"<?php if ( '9router' !== $image_provider ) : ?> style="display:none"<?php endif; ?>>
                        <div class="aiig-tab-layout">
                            <div class="aiig-tab-sidebar">
                                <div class="aiig-notice" style="background:#f3e5f5;border-left:4px solid #9c27b0;padding:12px 15px;border-radius:4px;">
                                    <h3 style="margin-top:0;color:#6a1b9a;font-size:14px;"><?php esc_html_e( '🔌 Hướng dẫn 9router', 'ai-image-generator-congcuseoai' ); ?></h3>
                                    <p style="font-size:13px;margin:8px 0;"><?php esc_html_e( 'Đây là cấu hình CLIProxy của 9router, sử dụng tài khoản Codex để tạo ảnh qua model cx/gpt-5.4-image. Endpoint tương thích OpenAI có thể chạy local hoặc remote.', 'ai-image-generator-congcuseoai' ); ?></p>
                                    <p style="font-size:13px;margin:4px 0;"><strong><?php esc_html_e( 'Endpoint mẫu:', 'ai-image-generator-congcuseoai' ); ?></strong></p>
                                    <code style="font-size:12px;display:block;background:#fff;padding:4px 8px;border-radius:3px;">http://localhost:20128</code>
                                    <ol style="font-size:13px;margin:10px 0 0 18px;padding:0;">
                                        <li><?php esc_html_e( 'Chạy CLIProxy của 9router local hoặc chuẩn bị endpoint remote có hỗ trợ /v1/models và /v1/images/generations.', 'ai-image-generator-congcuseoai' ); ?></li>
                                        <li><?php esc_html_e( 'Nhập Endpoint URL là base URL, ví dụ http://localhost:20128. Nếu dán full URL từ curl, plugin sẽ tự chuẩn hóa.', 'ai-image-generator-congcuseoai' ); ?></li>
                                        <li><?php esc_html_e( 'Dán API key 9router. Có thể dán token hoặc chuỗi Bearer token từ curl.', 'ai-image-generator-congcuseoai' ); ?></li>
                                        <li><?php esc_html_e( 'Chọn model tạo ảnh, sau đó giữ size, quality, background, image_detail và output format theo cấu hình mong muốn.', 'ai-image-generator-congcuseoai' ); ?></li>
                                        <li><?php esc_html_e( 'Bấm Test Kết Nối trước khi tạo ảnh thật.', 'ai-image-generator-congcuseoai' ); ?></li>
                                    </ol>
                                </div>
                                <div class="aiig-notice" style="background:#e8eaf6;border-left:4px solid #3f51b5;padding:12px 15px;border-radius:4px;">
                                    <h3 style="margin-top:0;color:#1a237e;font-size:14px;"><?php esc_html_e( '💡 Thông số curl mẫu', 'ai-image-generator-congcuseoai' ); ?></h3>
                                    <pre style="font-size:11px;background:#fff;padding:8px;border-radius:3px;overflow:auto;margin:0;">model: cx/gpt-5.4-image
size: auto
quality: auto
background: auto
image_detail: high
output: png</pre>
                                </div>
                                <div class="aiig-notice" style="background:#fff3cd;border-left:4px solid #ffc107;padding:12px 15px;border-radius:4px;">
                                    <p style="margin:0;font-size:13px;color:#856404;">
                                        <strong>⚠️ <?php esc_html_e( 'Lưu ý:', 'ai-image-generator-congcuseoai' ); ?></strong><br>
                                        • <?php esc_html_e( 'Endpoint phải truy cập được từ server WordPress', 'ai-image-generator-congcuseoai' ); ?><br>
                                        • <?php esc_html_e( 'API key được ẩn sau khi lưu', 'ai-image-generator-congcuseoai' ); ?><br>
                                        • <?php esc_html_e( 'Nếu gặp 404, thường là endpoint bị sai path hoặc service không có route /v1/images/generations', 'ai-image-generator-congcuseoai' ); ?><br>
                                        • <?php esc_html_e( 'Nếu gặp 401, kiểm tra lại API key hoặc Bearer token', 'ai-image-generator-congcuseoai' ); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="aiig-tab-main">
                                <div class="aiig-form-group">
                                    <label><?php esc_html_e( 'Endpoint URL', 'ai-image-generator-congcuseoai' ); ?></label>
                                    <input type="url" name="<?php echo esc_attr( $this->option_name ); ?>[9router_endpoint]"
                                           value="<?php echo esc_attr( $settings['9router_endpoint'] ?? '' ); ?>"
                                           placeholder="http://localhost:20128" style="width:100%;" />
                                    <span class="description"><?php esc_html_e( 'URL gốc của 9router. Có thể dán base URL hoặc full URL từ curl, plugin sẽ tự chuẩn hóa.', 'ai-image-generator-congcuseoai' ); ?></span>
                                </div>
                                <div class="aiig-form-group">
                                    <label><?php esc_html_e( '9router API Key', 'ai-image-generator-congcuseoai' ); ?></label>
                                    <?php $has_9r_key = ! empty( $settings['9router_api_key'] ?? '' ); ?>
                                    <input type="password" name="<?php echo esc_attr( $this->option_name ); ?>[9router_api_key]"
                                           class="aiig-api-key-input"
                                           placeholder="<?php echo esc_attr( $has_9r_key ? '••••••••••••••••' : __( 'Nhập Bearer token', 'ai-image-generator-congcuseoai' ) ); ?>"
                                           value="" autocomplete="new-password" style="width:100%;" />
                                    <div style="margin-top:10px;">
                                        <button type="button" class="button button-secondary aiig-test-9router-connection"><?php esc_html_e( 'Test Kết Nối', 'ai-image-generator-congcuseoai' ); ?></button>
                                    </div>
                                    <span class="description" style="color:#2271b1;font-style:italic;"><?php esc_html_e( '💡 Test sẽ tự động lưu cài đặt trước khi kiểm tra', 'ai-image-generator-congcuseoai' ); ?></span>
                                    <div id="aiig-test-9router-result" style="margin-top:8px;"></div>
                                </div>
                                <div class="aiig-form-group">
                                    <label><?php esc_html_e( 'Model', 'ai-image-generator-congcuseoai' ); ?></label>
                                    <input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[9router_model]"
                                           value="<?php echo esc_attr( $settings['9router_model'] ?? 'cx/gpt-5.4-image' ); ?>"
                                           style="width:100%;" />
                                    <span class="description"><?php esc_html_e( 'Ví dụ: cx/gpt-5.4-image', 'ai-image-generator-congcuseoai' ); ?></span>
                                </div>
                                <div class="aiig-form-group">
                                    <label><?php esc_html_e( 'Kích thước ảnh', 'ai-image-generator-congcuseoai' ); ?></label>
                                    <input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[9router_image_size]"
                                           value="<?php echo esc_attr( $settings['9router_image_size'] ?? 'auto' ); ?>"
                                           placeholder="auto" style="width:100%;" />
                                    <span class="description"><?php esc_html_e( 'Ví dụ: 1792x1024 | 1024x1024 | 1024x1792', 'ai-image-generator-congcuseoai' ); ?></span>
                                </div>
                                <div class="aiig-form-group">
                                    <label><?php esc_html_e( 'Quality', 'ai-image-generator-congcuseoai' ); ?></label>
                                    <select name="<?php echo esc_attr( $this->option_name ); ?>[9router_quality]">
                                        <option value="auto" <?php selected( $settings['9router_quality'] ?? 'auto', 'auto' ); ?>>auto</option>
                                        <option value="low" <?php selected( $settings['9router_quality'] ?? 'auto', 'low' ); ?>>low</option>
                                        <option value="medium" <?php selected( $settings['9router_quality'] ?? 'auto', 'medium' ); ?>>medium</option>
                                        <option value="high" <?php selected( $settings['9router_quality'] ?? 'auto', 'high' ); ?>>high</option>
                                        <option value="hd" <?php selected( $settings['9router_quality'] ?? 'auto', 'hd' ); ?>>hd</option>
                                        <option value="standard" <?php selected( $settings['9router_quality'] ?? 'auto', 'standard' ); ?>>standard</option>
                                    </select>
                                </div>
                                <div class="aiig-form-group">
                                    <label><?php esc_html_e( 'Background', 'ai-image-generator-congcuseoai' ); ?></label>
                                    <select name="<?php echo esc_attr( $this->option_name ); ?>[9router_background]">
                                        <option value="auto" <?php selected( $settings['9router_background'] ?? 'auto', 'auto' ); ?>>auto</option>
                                        <option value="transparent" <?php selected( $settings['9router_background'] ?? 'auto', 'transparent' ); ?>>transparent</option>
                                        <option value="opaque" <?php selected( $settings['9router_background'] ?? 'auto', 'opaque' ); ?>>opaque</option>
                                    </select>
                                </div>
                                <div class="aiig-form-group">
                                    <label><?php esc_html_e( 'Image Detail', 'ai-image-generator-congcuseoai' ); ?></label>
                                    <select name="<?php echo esc_attr( $this->option_name ); ?>[9router_image_detail]">
                                        <option value="high" <?php selected( $settings['9router_image_detail'] ?? 'high', 'high' ); ?>>high</option>
                                        <option value="auto" <?php selected( $settings['9router_image_detail'] ?? 'high', 'auto' ); ?>>auto</option>
                                        <option value="low" <?php selected( $settings['9router_image_detail'] ?? 'high', 'low' ); ?>>low</option>
                                        <option value="original" <?php selected( $settings['9router_image_detail'] ?? 'high', 'original' ); ?>>original</option>
                                    </select>
                                </div>
                                <div class="aiig-form-group">
                                    <label><?php esc_html_e( 'Output Format', 'ai-image-generator-congcuseoai' ); ?></label>
                                    <select name="<?php echo esc_attr( $this->option_name ); ?>[9router_output_format]">
                                        <option value="png" <?php selected( $settings['9router_output_format'] ?? 'png', 'png' ); ?>>png</option>
                                        <option value="jpeg" <?php selected( $settings['9router_output_format'] ?? 'png', 'jpeg' ); ?>>jpeg</option>
                                        <option value="webp" <?php selected( $settings['9router_output_format'] ?? 'png', 'webp' ); ?>>webp</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div><!-- /#aiig-section-9router -->
                </div><!-- /#tab-image -->


                <div id="tab-optimization" class="aiig-tab-content">
                    <h2><?php esc_html_e('Tối ưu hóa ảnh', 'ai-image-generator-congcuseoai'); ?></h2>
                    
                    <div class="aiig-tab-layout">
                        <!-- Left Sidebar: Instructions -->
                        <div class="aiig-tab-sidebar">
                            <div class="aiig-notice" style="background: #fff3e0; border-left: 4px solid #ff9800; padding: 12px 15px; border-radius: 4px;">
                                <h3 style="margin-top: 0; color: #e65100; font-size: 14px;"><?php esc_html_e('⚙️ Hướng dẫn tối ưu hóa', 'ai-image-generator-congcuseoai'); ?></h3>
                                <p style="margin: 8px 0; font-size: 13px;"><?php esc_html_e('Các cài đặt này giúp tối ưu kích thước và chất lượng ảnh trước khi tải lên thư viện WordPress. Nhớ nhập thông tin cấu hình ở Form và ấn lưu thay đổi để sử dụng:', 'ai-image-generator-congcuseoai'); ?></p>
                                <ul style="margin: 8px 0; padding-left: 20px; font-size: 13px;">
                                    <li><strong>Max Width:</strong> <?php esc_html_e('Giới hạn chiều rộng tối đa (tỷ lệ khung hình được giữ nguyên)', 'ai-image-generator-congcuseoai'); ?></li>
                                    <li><strong>JPEG Quality:</strong> <?php esc_html_e('Chất lượng nén (85 = cân bằng tốt giữa chất lượng & dung lượng)', 'ai-image-generator-congcuseoai'); ?></li>
                                    <li><strong>WebP:</strong> <?php esc_html_e('Định dạng ảnh hiện đại, nhẹ hơn 25-35% so với JPEG', 'ai-image-generator-congcuseoai'); ?></li>
                                </ul>
                            </div>
                            
                            <div class="aiig-notice" style="background: #e8f5e9; border-left: 4px solid #4caf50; padding: 12px 15px; border-radius: 4px;">
                                <h3 style="margin-top: 0; color: #2e7d32; font-size: 14px;"><?php esc_html_e('💡 Khuyến nghị', 'ai-image-generator-congcuseoai'); ?></h3>
                                <div style="font-size: 13px;">
                                    <p style="margin: 0 0 6px 0;">• <?php esc_html_e('Max Width: 1200-1600px cho blog thông thường', 'ai-image-generator-congcuseoai'); ?></p>
                                    <p style="margin: 0 0 6px 0;">• <?php esc_html_e('JPEG Quality: 80-85 cho web, 90-95 cho ảnh chất lượng cao', 'ai-image-generator-congcuseoai'); ?></p>
                                    <p style="margin: 0;">• <?php esc_html_e('Bật WebP nếu hosting hỗ trợ để tăng tốc tải trang', 'ai-image-generator-congcuseoai'); ?></p>
                                </div>
                            </div>
                            
                            <div class="aiig-notice" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px 15px; border-radius: 4px;">
                                <p style="margin: 0; font-size: 13px; color: #856404;">
                                    <strong>⚠️ <?php esc_html_e('Lưu ý:', 'ai-image-generator-congcuseoai'); ?></strong><br>
                                    • <?php esc_html_e('Ảnh từ Agent Platform AI đã có kích thước lớn, sử dụng cấu hình tối ưu hóa để giảm kích thước', 'ai-image-generator-congcuseoai'); ?><br>
                                    • <?php esc_html_e('Nếu Max Width nhỏ hơn kích thước gốc, ảnh sẽ bị resize', 'ai-image-generator-congcuseoai'); ?><br>
                                    • <?php esc_html_e('Quality càng cao thì dung lượng file càng lớn', 'ai-image-generator-congcuseoai'); ?><br>
                                    • <?php esc_html_e('WebP cần server hỗ trợ GD Library hoặc Imagick', 'ai-image-generator-congcuseoai'); ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Right Main: Configuration Fields -->
                        <div class="aiig-tab-main">
                            <div class="aiig-form-group">
                                <label><?php esc_html_e('Max Width (px)', 'ai-image-generator-congcuseoai'); ?></label>
                                <input type="number" name="<?php echo esc_attr( $this->option_name ); ?>[image_width]" value="<?php echo esc_attr($settings['image_width'] ?? 1200); ?>" min="400" max="4000" />
                                <span class="description"><?php esc_html_e('Chiều rộng tối đa của ảnh (giữ nguyên tỷ lệ)', 'ai-image-generator-congcuseoai'); ?></span>
                            </div>
                            
                            <div class="aiig-form-group">
                                <label><?php esc_html_e('JPEG Quality (%)', 'ai-image-generator-congcuseoai'); ?></label>
                                <input type="number" name="<?php echo esc_attr( $this->option_name ); ?>[image_quality]" value="<?php echo esc_attr($settings['image_quality'] ?? 85); ?>" min="1" max="100" />
                                <span class="description"><?php esc_html_e('Chất lượng nén JPEG (1-100, khuyến nghị: 85)', 'ai-image-generator-congcuseoai'); ?></span>
                            </div>
                            
                            <div class="aiig-form-group">
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[enable_webp]" value="1" <?php checked($settings['enable_webp'] ?? 0, 1); ?> />
                                    <?php esc_html_e('Bật chuyển đổi WebP', 'ai-image-generator-congcuseoai'); ?>
                                </label>
                                <span class="description"><?php esc_html_e('Tạo phiên bản WebP của ảnh (nếu server hỗ trợ)', 'ai-image-generator-congcuseoai'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php submit_button(__('Lưu thay đổi', 'ai-image-generator-congcuseoai')); ?>
            </form>
        </div>
        

        <?php
    }
    
    public static function get_settings() {
        return get_option('aiig_settings', array());
    }
    
    public static function get_setting($key, $default = '') {
        $settings = self::get_settings();
        return $settings[$key] ?? $default;
    }
}
