<?php
/**
 * Uninstall cleanup for AI Image Generator by CongCuSEOAI.
 *
 * @package AI_Image_Generator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'aiig_settings' );
delete_site_option( 'aiig_settings' );

delete_post_meta_by_key( '_aiig_generated_images' );
delete_post_meta_by_key( '_aiig_caption' );

global $wpdb;

if ( isset( $wpdb ) ) {
	$transient_like         = $wpdb->esc_like( '_transient_aiig_' ) . '%';
	$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_aiig_' ) . '%';

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$transient_like,
			$transient_timeout_like
		)
	);
}
