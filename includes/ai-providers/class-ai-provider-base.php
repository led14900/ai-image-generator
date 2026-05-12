<?php
/**
 * Base AI Provider Class
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class AIIG_AI_Provider_Base {
    
    protected $api_key;
    protected $model;
    protected $temperature;
    protected $max_tokens;
    
    /**
     * Constructor
     */
    public function __construct($api_key, $model, $temperature = 1, $max_tokens = 500) {
        $this->api_key = $api_key;
        $this->model = $model;
        $this->temperature = $temperature;
        $this->max_tokens = $max_tokens;
    }
    
    /**
     * Send request to AI API
     * Must be implemented by child classes
     */
    abstract public function send_request($messages);
    
    /**
     * Test connection
     */
    public function test() {
        try {
            $messages = array(
                array('role' => 'user', 'content' => 'Hello'),
            );
            
            $response = $this->send_request($messages);
            return !empty($response);
            
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    
    /**
     * Make HTTP request
     */
    protected function make_request($url, $body, $headers = array()) {
        $json_body = wp_json_encode($body);
        if ($json_body === false) {
            throw new Exception('Lỗi JSON encode: ' . json_last_error_msg());
        }

        $args = array(
            'headers' => $headers,
            'body' => $json_body,
            'timeout' => 60,
            'method' => 'POST',
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('Lỗi kết nối HTTP: ' . $response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = $error_data['error']['message'] ?? 'Lỗi API không xác định';
            throw new Exception("Lỗi API ($code): $error_message");
        }
        
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Phản hồi JSON không hợp lệ: ' . json_last_error_msg());
        }
        
        return $data;
    }
}
