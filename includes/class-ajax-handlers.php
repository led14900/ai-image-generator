<?php
/**
 * AJAX Handlers Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIIG_Ajax_Handlers {
    
    const RATE_LIMIT_REQUESTS = 5;
    const RATE_LIMIT_PERIOD = 60;
    const IMAGE_GENERATION_TIMEOUT = 150;
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_aiig_generate_prompts', array($this, 'generate_prompts'));
        add_action('wp_ajax_aiig_generate_images', array($this, 'generate_images'));
        add_action('wp_ajax_aiig_test_connection', array($this, 'test_connection'));
        add_action('wp_ajax_aiig_test_image_generation', array($this, 'test_image_generation'));
        add_action('wp_ajax_aiig_save_settings', array($this, 'save_settings'));
    }
    
    private function verify_nonce() {
        if (!check_ajax_referer('aiig_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'ai-image-generator-congcuseoai')));
            exit;
        }
    }
    
    private function check_permissions() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('You do not have permission', 'ai-image-generator-congcuseoai')));
            exit;
        }
    }

    private function check_post_permissions( $post_id ) {
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this post', 'ai-image-generator-congcuseoai' ) ) );
            exit;
        }
    }

    private function get_posted_settings() {
        if ( ! isset( $_POST['aiig_settings'] ) || ! is_array( $_POST['aiig_settings'] ) ) {
            return array();
        }

        return wp_unslash( $_POST['aiig_settings'] );
    }

    private function maybe_log_error( $message ) {
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }
    
    public function generate_prompts() {
        $this->verify_nonce();
        $this->check_permissions();
        
        $post_id = intval( wp_unslash( $_POST['post_id'] ?? 0 ) );
        $count   = intval( wp_unslash( $_POST['count'] ?? 3 ) );

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid post ID', 'ai-image-generator-congcuseoai' ) ) );
            return;
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( array( 'message' => __( 'Post not found', 'ai-image-generator-congcuseoai' ) ) );
            return;
        }

        $this->check_post_permissions( $post_id );
        
        try {
            $generator = new AIIG_Prompt_Generator();
            $prompts = $generator->generate($post, $count);
            
            wp_send_json_success(array(
                'prompts' => $prompts,
                'message' => __('Prompts generated successfully', 'ai-image-generator-congcuseoai'),
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function generate_images() {
        set_time_limit(self::IMAGE_GENERATION_TIMEOUT);
        
        $this->verify_nonce();
        $this->check_permissions();
        
        $post_id    = intval( wp_unslash( $_POST['post_id'] ?? 0 ) );
        $prompt     = sanitize_textarea_field( wp_unslash( $_POST['prompt'] ?? '' ) );
        $request_id = sanitize_text_field( wp_unslash( $_POST['request_id'] ?? '' ) );

        if ( ! $post_id || empty( $prompt ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid data', 'ai-image-generator-congcuseoai' ) ) );
            return;
        }

        $this->check_post_permissions( $post_id );

        // Initialize transient keys (prevent undefined variable notice in catch block)
        $transient_key_processing = '';
        $transient_key_result     = '';

        // Idempotency check
        if ( ! empty( $request_id ) ) {
            $transient_key_processing = 'aiig_proc_' . $request_id;
            $transient_key_result     = 'aiig_res_' . $request_id;

            // Check if result already exists
            $cached_result = get_transient($transient_key_result);
            if ($cached_result) {
                wp_send_json_success($cached_result);
                return;
            }

            // Check if processing
            if (get_transient($transient_key_processing)) {
                // Still processing, return error to trigger retry later
                wp_send_json_error(array('message' => __('Đang xử lý, vui lòng chờ...', 'ai-image-generator-congcuseoai')));
                return;
            }

            // Set processing lock (5 minutes)
            set_transient($transient_key_processing, true, 300);
        }
        
        try {
            $image_generator = new AIIG_Image_Generator();
            $image_data = $image_generator->generate($prompt);
            
            $optimizer = new AIIG_Image_Optimizer();
            $optimized_path = $optimizer->optimize($image_data['path']);
            
            $caption = $this->generate_caption($prompt);
            
            $uploader = new AIIG_Media_Uploader();
            // Use caption as title/alt if available, otherwise prompt
            $image_title = !empty($caption) ? $caption : $prompt;
            $attachment_id = $uploader->upload($optimized_path, $post_id, $image_title);
            
            if (is_wp_error($attachment_id) || empty($attachment_id)) {
                throw new Exception(__('Không thể lưu ảnh vào thư viện Media.', 'ai-image-generator-congcuseoai'));
            }

            if (!empty($caption)) {
                update_post_meta($attachment_id, '_aiig_caption', $caption);
                wp_update_post(array(
                    'ID' => $attachment_id,
                    'post_excerpt' => $caption,
                ));
            }
            
            $attachment_url = wp_get_attachment_url($attachment_id);
            if (!$attachment_url) {
                throw new Exception(__('Không thể lấy URL của ảnh đã upload.', 'ai-image-generator-congcuseoai'));
            }

            $attachment = array(
                'id' => $attachment_id,
                'url' => $attachment_url,
                'title' => get_the_title($attachment_id),
                'caption' => $caption,
            );
            
            $generated_images = get_post_meta($post_id, '_aiig_generated_images', true);
            if (!is_array($generated_images)) {
                $generated_images = array();
            }
            $generated_images[] = $attachment;
            update_post_meta($post_id, '_aiig_generated_images', $generated_images);
            
            $response_data = array(
                'image' => $attachment,
                'message' => __('Ảnh đã được tạo và upload thành công', 'ai-image-generator-congcuseoai'),
            );

            // Save result to transient and remove processing lock
            if (!empty($request_id)) {
                set_transient($transient_key_result, $response_data, 300); // Cache result for 5 mins
                delete_transient($transient_key_processing);
            }

            wp_send_json_success($response_data);
            
        } catch (Throwable $e) {
            // Remove processing lock on error
            if (!empty($request_id)) {
                delete_transient($transient_key_processing);
            }

            $this->maybe_log_error( 'AIIG Error: ' . $e->getMessage() );
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    private function generate_caption($prompt) {
        try {
            $generator = new AIIG_Prompt_Generator();
            return $generator->generate_caption($prompt);
        } catch (Exception $e) {
            $this->maybe_log_error( 'Caption generation failed: ' . $e->getMessage() );
            return '';
        }
    }
    
    public function test_connection() {
        $this->verify_nonce();
        $this->check_permissions();
        
        if (!$this->check_rate_limit('test_connection')) {
            wp_send_json_error(array('message' => __('Vui lòng chờ trước khi thử lại', 'ai-image-generator-congcuseoai')));
            return;
        }
        
        try {
            $settings = $this->get_posted_settings();
            $api_key = sanitize_text_field($settings['prompt_api_key'] ?? '');
            
            if (empty($api_key) || trim($api_key) === '') {
                $saved_settings = AIIG_Settings::get_settings();
                $api_key = $saved_settings['prompt_api_key'] ?? '';
            }
            
            $generator = new AIIG_Prompt_Generator();
            $result = $generator->test_connection();
            
            if ($result) {
                wp_send_json_success(array('message' => __('Kết nối thành công!', 'ai-image-generator-congcuseoai')));
            } else {
                wp_send_json_error(array('message' => __('Kết nối thất bại', 'ai-image-generator-congcuseoai')));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function test_image_generation() {
        $this->verify_nonce();
        $this->check_permissions();
        
        if (!$this->check_rate_limit('test_image')) {
            wp_send_json_error(array('message' => __('Vui lòng chờ trước khi thử lại', 'ai-image-generator-congcuseoai')));
            return;
        }
        
        try {
            $settings       = $this->get_posted_settings();
            $saved_settings = AIIG_Settings::get_settings();
            $image_provider              = sanitize_key( $settings['image_provider'] ?? ( $saved_settings['image_provider'] ?? 'gemini' ) );
            $model                       = sanitize_text_field( $settings['gemini_model'] ?? ( $saved_settings['gemini_model'] ?? 'gemini-3.1-flash-image-preview' ) );
            $vertex_location             = sanitize_key( $settings['vertex_location'] ?? ( $saved_settings['vertex_location'] ?? 'global' ) );
            $vertex_service_account_json = isset( $settings['vertex_service_account_json'] ) ? trim( (string) $settings['vertex_service_account_json'] ) : '';

            if ( '' === $vertex_service_account_json ) {
                $vertex_service_account_json = $saved_settings['vertex_service_account_json'] ?? '';
            }

            if ( 'gemini' === $image_provider && empty( $vertex_service_account_json ) ) {
                throw new Exception( __( 'Agent Platform service account JSON is required', 'ai-image-generator-congcuseoai' ) );
            }

            $generator = new AIIG_Image_Generator( null, $model, $vertex_service_account_json, $vertex_location );
            
            if (method_exists($generator, 'test_connection')) {
                $result = $generator->test_connection();
            } else {
                $result = true;
            }
            
            if ($result) {
                $message = '9router' === $image_provider
                    ? __( '9router kết nối thành công!', 'ai-image-generator-congcuseoai' )
                    : __( 'Gemini Enterprise Agent Platform kết nối thành công!', 'ai-image-generator-congcuseoai' );

                wp_send_json_success(array('message' => $message));
            } else {
                wp_send_json_error(array('message' => __('Kết nối thất bại', 'ai-image-generator-congcuseoai')));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function save_settings() {
        $this->verify_nonce();
        
        // Settings require admin capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Bạn không có quyền thay đổi cài đặt', 'ai-image-generator-congcuseoai')));
            exit;
        }
        
        try {
            if (!isset($_POST['aiig_settings']) || !is_array($_POST['aiig_settings'])) {
                throw new Exception(__('Không có dữ liệu để lưu', 'ai-image-generator-congcuseoai'));
            }
            
            $settings_instance = AIIG_Settings::get_instance();
            $input = wp_unslash( $_POST['aiig_settings'] );
            $sanitized = $settings_instance->sanitize_settings($input);
            
            update_option('aiig_settings', $sanitized);
            
            wp_send_json_success(array(
                'message' => __('Đã lưu cài đặt thành công!', 'ai-image-generator-congcuseoai'),
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    private function check_rate_limit($action, $limit = null, $period = null) {
        if ($limit === null) {
            $limit = self::RATE_LIMIT_REQUESTS;
        }
        if ($period === null) {
            $period = self::RATE_LIMIT_PERIOD;
        }
        
        $user_id = get_current_user_id();
        $transient_key = 'aiig_rate_limit_' . $action . '_' . $user_id;
        
        $requests = get_transient($transient_key);
        
        if ($requests === false) {
            set_transient($transient_key, 1, $period);
            return true;
        }
        
        if ($requests >= $limit) {
            return false;
        }
        
        set_transient($transient_key, $requests + 1, $period);
        return true;
    }
}
