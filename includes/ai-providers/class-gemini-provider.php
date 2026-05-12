<?php
/**
 * Gemini Provider Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIIG_Gemini_Provider extends AIIG_AI_Provider_Base {
    
    /**
     * Send request to Gemini API
     */
    public function send_request($messages) {
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent?key=' . $this->api_key;
        
        // Convert messages to Gemini format
        $contents = array();
        $system_instruction = '';
        
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system_instruction = $msg['content'];
            } else {
                $role = $msg['role'] === 'assistant' ? 'model' : 'user';
                $contents[] = array(
                    'role' => $role,
                    'parts' => array(
                        array('text' => $msg['content']),
                    ),
                );
            }
        }
        
        $body = array(
            'contents' => $contents,
            'generationConfig' => array(
                'temperature' => $this->temperature,
                'maxOutputTokens' => $this->max_tokens,
            ),
        );
        
        if (!empty($system_instruction)) {
            $body['systemInstruction'] = array(
                'parts' => array(
                    array('text' => $system_instruction),
                ),
            );
        }
        
        $headers = array(
            'Content-Type' => 'application/json',
        );
        
        $data = $this->make_request($api_url, $body, $headers);
        
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }
    

}
