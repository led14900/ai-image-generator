<?php
/**
 * Plugin Name: AI Image Generator by CongCuSEOAI
 * Description: Tạo prompt và ảnh minh họa bằng AI cho bài viết WordPress, tối ưu ảnh và upload vào Media Library.
 * Version: 1.0.0
 * Author: Phạm Ngọc Tú
 * Author URI: https://www.facebook.com/ngoctu.gttn
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-image-generator-congcuseoai
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AIIG_VERSION', '1.0.0');
define('AIIG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIIG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIIG_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main AI Image Generator Class
 */
class AI_Image_Generator {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once AIIG_PLUGIN_DIR . 'includes/class-settings.php';
        require_once AIIG_PLUGIN_DIR . 'includes/class-meta-box.php';
        require_once AIIG_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
        
        // AI Provider classes
        require_once AIIG_PLUGIN_DIR . 'includes/ai-providers/class-ai-provider-base.php';
        require_once AIIG_PLUGIN_DIR . 'includes/ai-providers/class-openai-provider.php';
        require_once AIIG_PLUGIN_DIR . 'includes/ai-providers/class-gemini-provider.php';
        require_once AIIG_PLUGIN_DIR . 'includes/ai-providers/class-9router-image-provider.php';
        
        // Helper classes
        require_once AIIG_PLUGIN_DIR . 'includes/class-prompt-generator.php';
        require_once AIIG_PLUGIN_DIR . 'includes/class-image-generator.php';
        require_once AIIG_PLUGIN_DIR . 'includes/class-image-optimizer.php';
        require_once AIIG_PLUGIN_DIR . 'includes/class-media-uploader.php';
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize components
        add_action('plugins_loaded', array($this, 'init_components'));
        
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $default_options = array(
            // Prompt AI provider
            'prompt_provider'    => 'openai',
            'prompt_api_key'     => '',
            'prompt_model'       => 'gpt-5-nano-2025-08-07',
            'prompt_temperature' => 1,
            'prompt_max_tokens'  => 500,
            // Gemini Enterprise Agent Platform image provider (Vertex AI API).
            'vertex_service_account_json' => '',
            'vertex_location'             => 'global',
            'gemini_model'                => 'gemini-3.1-flash-image-preview',
            'image_size'                  => '1024x1024',
            // Active image provider: 'gemini' | '9router'
            'image_provider'     => 'gemini',
            // 9router image provider
            '9router_endpoint'      => '',
            '9router_api_key'       => '',
            '9router_model'         => 'cx/gpt-5.4-image',
            '9router_image_size'    => 'auto',
            '9router_quality'       => 'auto',
            '9router_background'    => 'auto',
            '9router_image_detail'  => 'high',
            '9router_output_format' => 'png',
            // Image optimization
            'image_width'   => 1200,
            'image_quality' => 85,
            'enable_webp'   => false,
        );

        if ( ! get_option( 'aiig_settings' ) ) {
            add_option( 'aiig_settings', $default_options );
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cleanup if needed
    }
    
    /**
     * Initialize plugin components
     */
    public function init_components() {
        if (is_admin()) {
            AIIG_Settings::get_instance();
            AIIG_Meta_Box::get_instance();
            AIIG_Ajax_Handlers::get_instance();
        }
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'ai-image-generator-congcuseoai',
            false,
            dirname(AIIG_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on post edit pages
        $allowed_hooks = array(
            'post.php',
            'post-new.php',
        );
        
        if (!in_array($hook, $allowed_hooks)) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'aiig-admin-style',
            AIIG_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            AIIG_VERSION
        );
        
        // Enqueue JS
        wp_enqueue_script(
            'aiig-admin-script',
            AIIG_PLUGIN_URL . 'assets/js/admin-script.js',
            array('jquery'),
            AIIG_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('aiig-admin-script', 'aiigData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aiig_nonce'),
            'strings' => array(
                'generating_prompts' => __('Đang tạo prompts...', 'ai-image-generator-congcuseoai'),
                'generating_images' => __('Đang tạo ảnh...', 'ai-image-generator-congcuseoai'),
                'error' => __('Có lỗi xảy ra. Vui lòng thử lại.', 'ai-image-generator-congcuseoai'),
            ),
        ));
    }
    
}

/**
 * Initialize the plugin
 */
function aiig_init() {
    return AI_Image_Generator::get_instance();
}

// Start the plugin
aiig_init();
