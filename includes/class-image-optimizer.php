<?php
/**
 * Image Optimizer Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIIG_Image_Optimizer {
    
    private $max_width;
    private $quality;
    private $enable_webp;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->max_width = intval(AIIG_Settings::get_setting('image_width', 1200));
        $this->quality = intval(AIIG_Settings::get_setting('image_quality', 85));
        $this->enable_webp = AIIG_Settings::get_setting('enable_webp', 0);
    }
    
    /**
     * Optimize image (resize, compress, and optionally convert to WebP)
     */
    public function optimize($file_path) {
        if (!file_exists($file_path)) {
            throw new Exception(__('File not found', 'ai-image-generator-congcuseoai'));
        }
        
        // Use WordPress image editor
        $editor = wp_get_image_editor($file_path);
        
        if (is_wp_error($editor)) {
            throw new Exception('Image Editor Error: ' . $editor->get_error_message());
        }
        
        // Get current size
        $size = $editor->get_size();
        $width = $size['width'];
        $height = $size['height'];
        
        // Resize if needed (maintain aspect ratio)
        if ($width > $this->max_width) {
            $new_height = round(($this->max_width / $width) * $height);
            $editor->resize($this->max_width, $new_height, false);
        }
        
        // Set quality (applies to JPEG/WebP and, depending on editor, PNG compression)
        $editor->set_quality($this->quality);
        
        // Save optimized image over original path
        $saved = $editor->save($file_path);
        
        if (is_wp_error($saved)) {
            throw new Exception('Save Error: ' . $saved->get_error_message());
        }
        
        $final_path = $file_path;
        
        // If WebP is enabled and server supports it, convert and use WebP as final file
        if ($this->enable_webp && function_exists('imagewebp')) {
            $webp_path = $this->create_webp_version($file_path);
            if ($webp_path) {
                // Remove original file to avoid duplicates and save space
                wp_delete_file( $file_path );
                $final_path = $webp_path;
            }
        }
        
        return $final_path;
    }
    
    /**
     * Create WebP version of image and return its path on success
     */
    private function create_webp_version($file_path) {
        $editor = wp_get_image_editor($file_path);
        
        if (is_wp_error($editor)) {
            return false; // Silently fail
        }
        
        $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file_path);
        if ($webp_path === $file_path) {
            // Fallback in case regex didn't match for some reason
            $webp_path = $file_path . '.webp';
        }
        
        // Save as WebP
        $saved = $editor->save($webp_path, 'image/webp');
        if (is_wp_error($saved)) {
            return false;
        }
        
        return $webp_path;
    }
    

}
