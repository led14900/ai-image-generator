<?php
/**
 * OpenAI Provider Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIIG_OpenAI_Provider extends AIIG_AI_Provider_Base {
    
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    
    /**
     * Send request to OpenAI API
     */
    public function send_request($messages) {
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key,
        );
        
        $body = array(
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $this->temperature,
        );

        if (strpos($this->model, 'gpt-3') === 0 || strpos($this->model, 'gpt-4') === 0) {
            $body['max_tokens'] = $this->max_tokens;
        } else {
            $body['max_completion_tokens'] = $this->max_tokens;
        }
        
        $data = $this->make_request($this->api_url, $body, $headers);
        
        return $data['choices'][0]['message']['content'] ?? '';
    }
    

}
