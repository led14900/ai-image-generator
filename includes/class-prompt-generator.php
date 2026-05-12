<?php
/**
 * Prompt Generator Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIIG_Prompt_Generator {
    
    // Maximum content length to avoid token limits
    const MAX_CONTENT_LENGTH = 2000;
    
    private $provider;
    
    /**
     * Constructor
     */
    public function __construct() {
        $settings = AIIG_Settings::get_settings();
        $this->provider = $this->get_provider($settings);
    }
    
    /**
     * Get AI provider instance
     */
    private function get_provider($settings) {
        $provider_type = $settings['prompt_provider'] ?? 'openai';
        $api_key = $settings['prompt_api_key'] ?? '';
        $model = $settings['prompt_model'] ?? 'gpt-5-nano-2025-08-07';
        $temperature = floatval($settings['prompt_temperature'] ?? 1);
        $max_tokens = intval($settings['prompt_max_tokens'] ?? 500);
        
        if (empty($api_key)) {
            throw new Exception(__('API key không được để trống. Vui lòng cấu hình trong Settings.', 'ai-image-generator-congcuseoai'));
        }
        
        switch ($provider_type) {
            case 'openai':
                return new AIIG_OpenAI_Provider($api_key, $model, $temperature, $max_tokens);

            case 'gemini':
                return new AIIG_Gemini_Provider($api_key, $model, $temperature, $max_tokens);

            default:
                return new AIIG_OpenAI_Provider($api_key, $model, $temperature, $max_tokens);
        }
    }
    
    /**
     * Generate prompts from post content
     */
    public function generate($post, $count = 3) {
        if (!$post) {
            throw new Exception(__('Bài viết không hợp lệ', 'ai-image-generator-congcuseoai'));
        }
        
        // Extract post content
        $title = $post->post_title;
        $content = wp_strip_all_tags($post->post_content);
        $excerpt = $post->post_excerpt;
        
        if (empty($content)) {
            throw new Exception(__('Nội dung bài viết trống. Vui lòng thêm nội dung trước khi tạo ảnh.', 'ai-image-generator-congcuseoai'));
        }
        
        // Prepare messages
        $settings = AIIG_Settings::get_settings();
        $system_prompt = $settings['prompt_system_message'] ?? $this->get_default_system_prompt();
        
        $user_prompt = $this->build_user_prompt($title, $content, $excerpt, $count);
        
        $messages = array(
            array(
                'role' => 'system',
                'content' => $system_prompt,
            ),
            array(
                'role' => 'user',
                'content' => $user_prompt,
            ),
        );
        
        // Send request
        $response = $this->provider->send_request($messages);
        
        // Parse prompts from response
        $prompts = $this->parse_prompts($response, $count);
        
        return $prompts;
    }
    
    /**
     * Build user prompt
     */
    private function build_user_prompt($title, $content, $excerpt, $count) {
        $prompt = "Dựa vào thông tin bài viết sau, hãy tạo {$count} prompts bằng tiếng Việt để tạo ảnh minh họa:\n\n";
        $prompt .= "Tiêu đề: {$title}\n\n";
        
        if (!empty($excerpt)) {
            $prompt .= "Tóm tắt: {$excerpt}\n\n";
        }
        
        // Limit content length to avoid token limits
        $content = mb_substr($content, 0, self::MAX_CONTENT_LENGTH);
        $prompt .= "Nội dung: {$content}\n\n";
        
        $prompt .= "Yêu cầu:\n";
        $prompt .= "- Mỗi prompt nên chi tiết, mô tả rõ ràng về chủ đề, phong cách, màu sắc\n";
        $prompt .= "- Prompts phải phù hợp với nội dung bài viết\n";
        $prompt .= "- Mỗi prompt trên một dòng, đánh số từ 1 đến {$count}\n";
        $prompt .= "- Chỉ trả về các prompts, không cần giải thích thêm";
        
        return $prompt;
    }
    
    /**
     * Parse prompts from AI response
     */
    private function parse_prompts($response, $count) {
        $prompts = array();
        
        // Split by lines
        $lines = explode("\n", $response);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines
            if (empty($line)) {
                continue;
            }
            
            // Remove numbering (1., 2., etc.)
            $line = preg_replace('/^\d+[\.\\)]\s*/', '', $line);
            
            // Remove bullet points
            $line = preg_replace('/^[-*•]\s*/', '', $line);
            
            $line = trim($line);
            
            if (!empty($line)) {
                $prompts[] = $line;
            }
            
            // Stop if we have enough prompts
            if (count($prompts) >= $count) {
                break;
            }
        }
        
        // If we don't have enough prompts, pad with duplicates
        while (count($prompts) < $count && !empty($prompts)) {
            $prompts[] = $prompts[0];
        }
        
        return array_slice($prompts, 0, $count);
    }
    
    /**
     * Get default system prompt
     */
    private function get_default_system_prompt() {
        return __("Bạn là một chuyên gia tạo prompt cho AI tạo ảnh. Hãy phân tích nội dung bài viết và tạo các prompt chi tiết bằng tiếng Việt để tạo ảnh minh họa phù hợp. Mỗi prompt nên mô tả rõ ràng về chủ đề, phong cách, màu sắc, và cảm xúc của ảnh.", 'ai-image-generator-congcuseoai');
    }
    
    /**
     * Test connection
     */
    public function test_connection() {
        return $this->provider->test();
    }
    
    /**
     * Generate caption from image prompt
     */
    public function generate_caption($prompt) {
        if (empty($prompt)) {
            return '';
        }
        
        // Prepare messages for caption generation
        $system_prompt = "Bạn là chuyên gia viết chú thích cho ảnh. Hãy tạo chú thích ngắn gọn, súc tích bằng tiếng Việt từ mô tả ảnh, phù hợp để đưa vào bài viết blog.";
        
        $user_prompt = "Dựa vào mô tả ảnh sau, hãy tạo một câu chú thích ngắn gọn (1-2 câu) bằng tiếng Việt:\n\n";
        $user_prompt .= $prompt . "\n\n";
        $user_prompt .= "Yêu cầu:\n";
        $user_prompt .= "- Chú thích phải ngắn gọn, súc tích (tối đa 2 câu)\n";
        $user_prompt .= "- Phù hợp để copy vào bài viết blog\n";
        $user_prompt .= "- Chỉ trả về câu chú thích, không cần giải thích thêm";
        
        $messages = array(
            array(
                'role' => 'system',
                'content' => $system_prompt,
            ),
            array(
                'role' => 'user',
                'content' => $user_prompt,
            ),
        );
        
        try {
            // Send request
            $response = $this->provider->send_request($messages);
            
            // Clean up and return caption
            $caption = trim($response);
            $caption = preg_replace('/^["\']+ |["\' ]+$/u', '', $caption); // Remove quotes
            
            return $caption;
        } catch (Exception $e) {
            if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                error_log( 'Caption generation error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
            return '';
        }
    }
}
