<?php
defined( 'ABSPATH' ) or die( 'Cheatin&#8217; uh?' );

/**
 * Callback to filter, sanitize and de/activate submodules
 *
 * @since 1.0
 * @return array $settings
 */
function __secupress_logs_settings_callback( $settings ) {
	$modulenow = 'logs';
	$settings  = $settings ? $settings : array();

	if ( isset( $settings['sanitized'] ) ) {
		return $settings;
	}
	$settings['sanitized'] = 1;

	// Sanitization.
	if ( ! empty( $settings['banned-ips_whitelist'] ) ) {
		$settings['banned-ips_whitelist'] = explode( "\n", $settings['banned-ips_whitelist'] );
		$settings['banned-ips_whitelist'] = array_map( 'secupress_ip_is_valid', $settings['banned-ips_whitelist'] );
		$settings['banned-ips_whitelist'] = array_filter( $settings['banned-ips_whitelist'] );
		$settings['banned-ips_whitelist'] = array_map( 'sanitize_text_field', $settings['banned-ips_whitelist'] );

		if ( $settings['banned-ips_whitelist'] ) {
			// Remove whitelisted IPs from the banned IPs list.
			$ban_ips = get_site_option( SECUPRESS_BAN_IP );

			if ( $ban_ips && is_array( $ban_ips ) ) {
				$whitelist = array_flip( $settings['banned-ips_whitelist'] );
				$ban_ips   = array_diff_key( $ban_ips, $whitelist );
				update_site_option( SECUPRESS_BAN_IP, $ban_ips );
			}
		}

		$settings['banned-ips_whitelist'] = implode( "\n", $settings['banned-ips_whitelist'] );
	}

	return $settings;
}


/**
 * Activate or deactivate "action" Logs.
 *
 * @since 1.0
 **/
add_action( 'admin_post_secupress_activate_action_logs', '__secupress_activate_action_logs' );

function __secupress_activate_action_logs() {
	// Make all security tests.
	check_admin_referer( 'secupress_activate_action_logs' );
	secupress_check_user_capability();

	// (De)Activate.
	$activate = ! empty( $_POST['secupress-plugin-activation']['action-logs_activated'] );
	secupress_manage_submodule( 'logs', 'action-logs', $activate );

	// Redirect.
	wp_redirect( wp_get_referer() );
	die();
}


/**
 * Activate or deactivate "404" Logs.
 *
 * @since 1.0
 **/
add_action( 'admin_post_secupress_activate_404_logs', '__secupress_activate_404_logs' );

function __secupress_activate_404_logs() {
	// Make all security tests.
	check_admin_referer( 'secupress_activate_404_logs' );
	secupress_check_user_capability();

	// (De)Activate.
	$activate = ! empty( $_POST['secupress-plugin-activation']['404-logs_activated'] );
	secupress_manage_submodule( 'logs', '404-logs', $activate );

	// Redirect.
	wp_redirect( wp_get_referer() );
	die();
}
