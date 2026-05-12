<?php
/**
 * Media Uploader Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIIG_Media_Uploader {
    
    /**
     * Upload image to WordPress Media Library
     */
    public function upload($file_path, $post_id, $title = '') {
        if (!file_exists($file_path)) {
            throw new Exception(__('File not found', 'ai-image-generator-congcuseoai'));
        }
        
        // Read file content
        $file_content = $this->read_file_contents($file_path);
        if ($file_content === false) {
            throw new Exception(__('Failed to read file content', 'ai-image-generator-congcuseoai'));
        }
        
        // Set proper filename (metadata only)
        if (empty($title)) {
            $title = 'AI Generated Image ' . gmdate('Y-m-d H:i:s');
        }

        // File name pattern: post-slug-xxxx.{ext}
        $post_title_slug = 'ai-generated-image';
        if ($post_id) {
            $post = get_post($post_id);
            if ($post) {
                $post_title_slug = sanitize_title($post->post_title);
            }
        }
        
        // Fallback if slug is empty
        if (empty($post_title_slug)) {
            $post_title_slug = 'ai-image';
        }

        $random_number = mt_rand(1000, 9999); // 4-digit random number
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if (empty($ext)) {
            $ext = 'png';
        }
        $filename = $post_title_slug . '-' . $random_number . '.' . $ext;
        $file_type = wp_check_filetype($filename, null);

        if ( empty( $file_type['type'] ) || 0 !== strpos( $file_type['type'], 'image/' ) ) {
            wp_delete_file( $file_path );
            throw new Exception( __( 'Invalid image file type', 'ai-image-generator-congcuseoai' ) );
        }
        
        // Upload file using wp_upload_bits (default WordPress upload dirs: /uploads/YYYY/MM)
        $upload = wp_upload_bits($filename, null, $file_content);
        
        if (!empty($upload['error'])) {
            // Clean up temp file
            wp_delete_file( $file_path );
            throw new Exception('Lỗi Upload: ' . $upload['error']);
        }
        
        $file_path_new = $upload['file'];
        $file_url = $upload['url'];
        
        // Prepare attachment data
        $attachment = array(
            'post_mime_type' => $file_type['type'],
            'post_title'     => $title,
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        
        // Insert attachment
        $attachment_id = wp_insert_attachment($attachment, $file_path_new, $post_id);
        
        if (is_wp_error($attachment_id)) {
            // Clean up files
            wp_delete_file( $file_path );
            wp_delete_file( $file_path_new );
            throw new Exception('Lỗi đính kèm (Attachment): ' . $attachment_id->get_error_message());
        }
        
        // Generate metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path_new);
        wp_update_attachment_metadata($attachment_id, $attach_data);
        
        // Set additional metadata
        $this->set_image_metadata($attachment_id, $title);
        
        // Clean up temp file
        wp_delete_file( $file_path );
        
        return $attachment_id;
    }

    /**
     * Read a local generated image using WordPress filesystem API.
     */
    private function read_file_contents( $file_path ) {
        global $wp_filesystem;

        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if ( ! WP_Filesystem() || ! $wp_filesystem ) {
            return false;
        }

        return $wp_filesystem->get_contents( $file_path );
    }
    

    /**
     * Set image metadata
     */
    private function set_image_metadata($attachment_id, $title) {
        // Update title and excerpt (caption) in one go
        wp_update_post(array(
            'ID' => $attachment_id,
            'post_title' => $title,
            'post_excerpt' => $title,
        ));
        
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $title);
    }
}
