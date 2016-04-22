<?php
defined( 'ABSPATH' ) or die( 'Cheatin&#8217; uh?' );

/*------------------------------------------------------------------------------------------------*/
/* ADMIN POST / AJAX CALLBACKS ================================================================== */
/*------------------------------------------------------------------------------------------------*/

// Scan callback.

add_action( 'admin_post_secupress_scanner', '__secupress_scanit_action_callback' );
add_action( 'wp_ajax_secupress_scanner',    '__secupress_scanit_action_callback' );
/**
 * Used to scan a test in scanner page.
 * Prints a JSON or redirects the user.
 *
 * @since 1.0
 */
function __secupress_scanit_action_callback() {
	if ( empty( $_GET['test'] ) ) { // WPCS: CSRF ok.
		secupress_admin_die();
	}

	$test_name        = esc_attr( $_GET['test'] ); // WPCS: CSRF ok.
	$for_current_site = ! empty( $_GET['for-current-site'] ); // WPCS: CSRF ok.
	$site_id          = $for_current_site && ! empty( $_GET['site'] ) ? '-' . absint( $_GET['site'] ) : ''; // WPCS: CSRF ok.

	secupress_check_user_capability( $for_current_site );
	secupress_check_admin_referer( 'secupress_scanner_' . $test_name . $site_id );

	$doing_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;
	$response   = secupress_scanit( $test_name, $doing_ajax, $for_current_site );

	secupress_admin_send_response_or_redirect( $response );
}


/**
 * Get the result of a scan.
 *
 * @since 1.0
 *
 * @param (string) $test_name        The suffix of the class name. Format example: Admin_User (not admin-user).
 * @param (bool)   $format_response  Change the output format.
 * @param (bool)   $for_current_site If multisite, tell to perform the scan for the current site, not network-wide.
 *                                   It has no effect on non multisite installations.
 *
 * @return (array|bool) The scan result or false on failure.
 */
function secupress_scanit( $test_name, $format_response = false, $for_current_site = false ) {
	$response = false;

	if ( ! $test_name || ! file_exists( secupress_class_path( 'scan', $test_name ) ) ) {
		return false;
	}

	secupress_require_class( 'scan' );
	secupress_require_class( 'scan', $test_name );

	$classname = 'SecuPress_Scan_' . $test_name;

	if ( class_exists( $classname ) ) {
		ob_start();
		@set_time_limit( 0 );
		$response = $classname::get_instance()->for_current_site( $for_current_site )->scan();
		/**
		 * $response is an array that MUST contain "status" and MUST contain "msgs".
		 */
		ob_end_clean();
	}

	if ( $response && $format_response ) {
		$response = array(
			'status'  => secupress_status( $response['status'] ),
			'class'   => sanitize_key( $response['status'] ),
			'message' => isset( $response['msgs'] ) ? secupress_format_message( $response['msgs'], $test_name ) : '',
			'fix_msg' => isset( $response['fix_msg'] ) ? secupress_format_message( $response['fix_msg'], $test_name ) : '',
		);
	}

	return $response;
}


// Fix callback.

add_action( 'admin_post_secupress_fixit', '__secupress_fixit_action_callback' );
add_action( 'wp_ajax_secupress_fixit',    '__secupress_fixit_action_callback' );
/**
 * Used to automatically fix a test in scanner page.
 * Prints a JSON or redirects the user.
 *
 * @since 1.0
 */
function __secupress_fixit_action_callback() {
	if ( empty( $_GET['test'] ) ) { // WPCS: CSRF ok.
		secupress_admin_die();
	}

	$test_name        = esc_attr( $_GET['test'] ); // WPCS: CSRF ok.
	$for_current_site = ! empty( $_GET['for-current-site'] ); // WPCS: CSRF ok.
	$site_id          = $for_current_site && ! empty( $_GET['site'] ) ? '-' . absint( $_GET['site'] ) : ''; // WPCS: CSRF ok.

	secupress_check_user_capability( $for_current_site );
	secupress_check_admin_referer( 'secupress_fixit_' . $test_name . $site_id );

	$doing_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;
	$response   = secupress_fixit( $test_name, $doing_ajax, $for_current_site );

	// If not ajax, perform a scan.
	if ( ! $doing_ajax ) {
		secupress_scanit( $test_name, false, $for_current_site );
	}

	secupress_admin_send_response_or_redirect( $response );
}


/**
 * Get the result of a fix.
 *
 * @since 1.0
 *
 * @param (string) $test_name        The suffix of the class name. Format example: Admin_User (not admin-user).
 * @param (bool)   $format_response  Change the output format.
 * @param (bool)   $for_current_site If multisite, tell to perform the fix for the current site, not network-wide.
 *                                   It has no effect on non multisite installations.
 *
 * @return (array|bool) The scan result or false on failure.
 */
function secupress_fixit( $test_name, $format_response = false, $for_current_site = false ) {
	$response = false;

	if ( ! $test_name || ! file_exists( secupress_class_path( 'scan', $test_name ) ) ) {
		return false;
	}

	secupress_require_class( 'scan' );
	secupress_require_class( 'scan', $test_name );

	$classname = 'SecuPress_Scan_' . $test_name;

	if ( class_exists( $classname ) ) {
		ob_start();
		@set_time_limit( 0 );
		$response = $classname::get_instance()->for_current_site( $for_current_site )->fix();
		/**
		 * $response is an array that MUST contain "status" and MUST contain "msgs".
		 */
		ob_end_clean();
	}

	if ( $response && $format_response ) {
		$response = array_merge( $response, array(
			'class'   => sanitize_key( $response['status'] ),
			'status'  => secupress_status( $response['status'] ),
			'message' => isset( $response['msgs'] ) ? secupress_format_message( $response['msgs'], $test_name ) : '',
		) );
		unset( $response['msgs'], $response['attempted_fixes'] );
	}

	return $response;
}


// Manual fix callback.

add_action( 'admin_post_secupress_manual_fixit', '__secupress_manual_fixit_action_callback' );
add_action( 'wp_ajax_secupress_manual_fixit',    '__secupress_manual_fixit_action_callback' );
/**
 * Used to manually fix a test in scanner page.
 * Prints a JSON or redirects the user.
 *
 * @since 1.0
 */
function __secupress_manual_fixit_action_callback() {
	if ( empty( $_POST['test'] ) ) { // WPCS: CSRF ok.
		secupress_admin_die();
	}

	$test_name        = esc_attr( $_POST['test'] ); // WPCS: CSRF ok.
	$for_current_site = ! empty( $_POST['for-current-site'] ); // WPCS: CSRF ok.
	$site_id          = $for_current_site && ! empty( $_POST['site'] ) ? '-' . absint( $_POST['site'] ) : ''; // WPCS: CSRF ok.

	secupress_check_user_capability( $for_current_site );
	secupress_check_admin_referer( 'secupress_manual_fixit_' . $test_name . $site_id );

	$doing_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;
	$response   = secupress_manual_fixit( $test_name, $doing_ajax, $for_current_site );

	// If not ajax, perform a scan.
	if ( ! $doing_ajax ) {
		secupress_scanit( $test_name, false, $for_current_site );
	}

	secupress_admin_send_response_or_redirect( $response );
}


/**
 * Get the result of a manual fix.
 *
 * @since 1.0
 *
 * @param (string) $test_name        The suffix of the class name.
 * @param (bool)   $format_response  Change the output format.
 * @param (bool)   $for_current_site If multisite, tell to perform the manual fix for the current site, not network-wide.
 *                                   It has no effect on non multisite installations.
 *
 * @return (array|bool) The scan result or false on failure.
 */
function secupress_manual_fixit( $test_name, $format_response = false, $for_current_site = false ) {
	$response = false;

	if ( ! $test_name || ! file_exists( secupress_class_path( 'scan', $test_name ) ) ) {
		return false;
	}

	secupress_require_class( 'scan' );
	secupress_require_class( 'scan', $test_name );

	$classname = 'SecuPress_Scan_' . $test_name;

	if ( class_exists( $classname ) ) {
		ob_start();
		@set_time_limit( 0 );
		$response = $classname::get_instance()->for_current_site( $for_current_site )->manual_fix();
		/**
		 * $response is an array that MUST contain "status" and MUST contain "msgs".
		 */
		ob_end_clean();
	}

	if ( $response && $format_response ) {
		$response = array_merge( $response, array(
			'class'   => sanitize_key( $response['status'] ),
			'status'  => secupress_status( $response['status'] ),
			'message' => isset( $response['msgs'] ) ? secupress_format_message( $response['msgs'], $test_name ) : '',
		) );
		unset( $response['msgs'], $response['attempted_fixes'] );
	}

	return $response;
}


// Date of the last One-click scan.

add_action( 'wp_ajax_secupress-update-oneclick-scan-date', '__secupress_update_oneclick_scan_date' );
/**
 * Used to update the date of the last One-click scan.
 * Prints a JSON containing the HTML of the new line to insert in the page.
 *
 * @since 1.0
 */
function __secupress_update_oneclick_scan_date() {
	secupress_check_user_capability();
	secupress_check_admin_referer( 'secupress-update-oneclick-scan-date' );

	$last_pc = -1;
	$counts  = secupress_get_scanner_counts();
	$time    = array(
		'percent' => $counts['good'] * 100 / $counts['total'],
		'grade'   => $counts['grade'],
		'time'    => time(),
	);

	$times = array_filter( (array) get_site_option( SECUPRESS_SCAN_TIMES ) );

	if ( $times ) {
		$last_pc = end( $times );
		$last_pc = $last_pc['percent'];
	}

	array_push( $times, $time );
	// Limit to 5 results.
	$times = array_slice( $times, -5, 5 );

	update_site_option( SECUPRESS_SCAN_TIMES, $times );

	$icon = 'right';

	if ( $last_pc > -1 ) {
		if ( $last_pc < $time['percent'] ) {
			$icon = 'up';
		} elseif ( $last_pc > $time['percent'] ) {
			$icon = 'down';
		}
	}

	$out = sprintf(
		'<li class="hidden" data-percent="%1$d"><span class="dashicons mini dashicons-arrow-%2$s-alt2" aria-hidden="true"></span><strong>%3$s (%1$d %%)</strong> <span class="timeago">%4$s</span></li>',
		round( $time['percent'] ),
		$icon,
		$time['grade'],
		sprintf( __( '%s ago' ), human_time_diff( $time['time'] ) )
	);

	wp_send_json_success( $out );
}


add_action( 'admin_post_secupress-ban-ip', '__secupress_ban_ip_ajax_post_cb' );
add_action( 'wp_ajax_secupress-ban-ip',    '__secupress_ban_ip_ajax_post_cb' );
/**
 * Ban an IP address.
 *
 * @since 1.0
 */
function __secupress_ban_ip_ajax_post_cb() {
	// Make all security tests.
	secupress_check_admin_referer( 'secupress-ban-ip' );
	secupress_check_user_capability();

	if ( empty( $_REQUEST['ip'] ) ) {
		secupress_admin_send_message_die( array(
			'message' => __( 'IP address not provided.', 'secupress' ),
			'code'    => 'no_ip',
			'type'    => 'error',
		) );
	}

	// Test the IP.
	$ip = urldecode( $_REQUEST['ip'] );

	if ( ! secupress_ip_is_valid( $ip ) ) {
		secupress_admin_send_message_die( array(
			'message' => sprintf( __( '%s is not a valid IP address.', 'secupress' ), '<code>' . esc_html( $ip ) . '</code>' ),
			'code'    => 'invalid_ip',
			'type'    => 'error',
		) );
	}

	if ( secupress_ip_is_whitelisted( $ip ) || secupress_get_ip() === $ip ) {
		secupress_admin_send_message_die( array(
			'message' => sprintf( __( 'The IP address %s is whitelisted.', 'secupress' ), '<code>' . esc_html( $ip ) . '</code>' ),
			'code'    => 'own_ip',
			'type'    => 'error',
		) );
	}

	// Add the IP to the option.
	$ban_ips = get_site_option( SECUPRESS_BAN_IP );
	$ban_ips = is_array( $ban_ips ) ? $ban_ips : array();

	$ban_ips[ $ip ] = time() + YEAR_IN_SECONDS * 100; // Now you got 100 years to think about your future, kiddo. In the meantime, go clean your room.

	update_site_option( SECUPRESS_BAN_IP, $ban_ips );

	// Add the IP to the `.htaccess` file.
	if ( secupress_write_in_htaccess_on_ban() ) {
		secupress_write_htaccess( 'ban_ip', secupress_get_htaccess_ban_ip() );
	}

	/* This hook is documented in /inc/functions/admin.php */
	do_action( 'secupress.ban.ip_banned', $ip, $ban_ips );

	$referer_arg = '&_wp_http_referer=' . urlencode( esc_url_raw( secupress_admin_url( 'modules', 'logs' ) ) );

	// Send a response.
	secupress_admin_send_message_die( array(
		'message'    => sprintf( __( 'The IP address %s has been banned.', 'secupress' ), '<code>' . esc_html( $ip ) . '</code>' ),
		'code'       => 'ip_banned',
		'tmplValues' => array(
			array(
				'ip'        => $ip,
				'time'      => __( 'Forever', 'secupress' ),
				'unban_url' => esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=secupress-unban-ip&ip=' . esc_attr( $ip ) . $referer_arg ), 'secupress-unban-ip_' . $ip ) ),
			),
		),
	) );
}


add_action( 'admin_post_secupress-unban-ip', '__secupress_unban_ip_ajax_post_cb' );
add_action( 'wp_ajax_secupress-unban-ip',    '__secupress_unban_ip_ajax_post_cb' );
/**
 * Unban an IP address.
 *
 * @since 1.0
 */
function __secupress_unban_ip_ajax_post_cb() {
	// Make all security tests.
	if ( empty( $_REQUEST['ip'] ) ) {
		secupress_admin_send_message_die( array(
			'message' => __( 'IP address not provided.', 'secupress' ),
			'code'    => 'no_ip',
			'type'    => 'error',
		) );
	}

	secupress_check_admin_referer( 'secupress-unban-ip_' . $_REQUEST['ip'] );
	secupress_check_user_capability();

	// Test the IP.
	$ip = urldecode( $_REQUEST['ip'] );

	if ( ! secupress_ip_is_valid( $ip ) ) {
		secupress_admin_send_message_die( array(
			'message' => sprintf( __( '%s is not a valid IP address.', 'secupress' ), '<code>' . esc_html( $ip ) . '</code>' ),
			'code'    => 'invalid_ip',
			'type'    => 'error',
		) );
	}

	// Remove the IP from the option.
	$ban_ips = get_site_option( SECUPRESS_BAN_IP );
	$ban_ips = is_array( $ban_ips ) ? $ban_ips : array();

	if ( empty( $ban_ips[ $ip ] ) ) {
		secupress_admin_send_message_die( array(
			'message' => sprintf( __( 'The IP address %s is not banned.', 'secupress' ), '<code>' . esc_html( $ip ) . '</code>' ),
			'code'    => 'ip_not_banned',
		) );
	}

	unset( $ban_ips[ $ip ] );

	if ( $ban_ips ) {
		update_site_option( SECUPRESS_BAN_IP, $ban_ips );
	} else {
		delete_site_option( SECUPRESS_BAN_IP );
	}

	// Remove the IP from the `.htaccess` file.
	if ( secupress_write_in_htaccess_on_ban() ) {
		secupress_write_htaccess( 'ban_ip', secupress_get_htaccess_ban_ip() );
	}

	/**
	 * Fires once a IP is unbanned.
	 *
	 * @since 1.0
	 *
	 * @param (string) $ip      The IP unbanned.
	 * @param (array)  $ban_ips The list of IPs banned (keys) and the time they were banned (values).
	 */
	do_action( 'secupress.ban.ip_unbanned', $ip, $ban_ips );

	// Send a response.
	secupress_admin_send_message_die( array(
		'message' => sprintf( __( 'The IP address %s has been unbanned.', 'secupress' ), '<code>' . esc_html( $ip ) . '</code>' ),
		'code'    => 'ip_unbanned',
	) );
}


add_action( 'admin_post_secupress-clear-ips', '__secupress_clear_ips_ajax_post_cb' );
add_action( 'wp_ajax_secupress-clear-ips',    '__secupress_clear_ips_ajax_post_cb' );
/**
 * Unban all IP addresses.
 *
 * @since 1.0
 */
function __secupress_clear_ips_ajax_post_cb() {
	// Make all security tests.
	secupress_check_admin_referer( 'secupress-clear-ips' );
	secupress_check_user_capability();

	// Remove all IPs from the option.
	delete_site_option( SECUPRESS_BAN_IP );

	// Remove all IPs from the `.htaccess` file.
	if ( secupress_write_in_htaccess_on_ban() ) {
		secupress_write_htaccess( 'ban_ip' );
	}

	/**
	 * Fires once all IPs are unbanned.
	 *
	 * @since 1.0
	 */
	do_action( 'secupress.ban.ips_cleared' );

	// Send a response.
	secupress_admin_send_message_die( array(
		'message' => __( 'All IP addresses have been unbanned.', 'secupress' ),
		'code'    => 'banned_ips_cleared',
	) );
}


add_action( 'admin_post_secupress_reset_settings', '__secupress_admin_post_reset_settings' );
/**
 * Reset SecuPress settings or module settings.
 *
 * @since 1.0
 */
function __secupress_admin_post_reset_settings() {
	if ( empty( $_GET['module'] ) ) {
		secupress_admin_die();
	}
	// Make all security tests.
	secupress_check_admin_referer( 'secupress_reset_' . $_GET['module'] );
	secupress_check_user_capability();

	do_action( 'wp_secupress_first_install', $_GET['module'] );

	wp_safe_redirect( esc_url_raw( secupress_admin_url( 'modules', $_GET['module'] ) ) );
	die();
}


add_filter( 'http_request_args', '__secupress_add_own_ua', 10, 3 );
/**
 * Force our user agent header when we hit our urls //// X-Secupress header.
 *
 * @since 1.0
 *
 * @param (array)  $r   The request parameters.
 * @param (string) $url The request URL.
 *
 * @return (array)
 */
function __secupress_add_own_ua( $r, $url ) {
	if ( false !== strpos( $url, 'secupress.fr' ) ) {
		$r['user-agent'] = secupress_user_agent( $r['user-agent'] );
	}

	return $r;
}


add_filter( 'registration_errors', '__secupress_registration_test_errors', PHP_INT_MAX, 2 );
/**
 * This is used in the Subscription scan to test user registrations from the login page.
 *
 * @since 1.0
 * @see `register_new_user()`
 *
 * @param (object) $errors               A WP_Error object containing any errors encountered during registration.
 * @param (string) $sanitized_user_login User's username after it has been sanitized.
 *
 * @return (object) The WP_Error object with a new error if the user name is blacklisted.
 */
function __secupress_registration_test_errors( $errors, $sanitized_user_login ) {
	if ( ! $errors->get_error_code() && false !== strpos( $sanitized_user_login, 'secupress' ) ) {
		set_transient( 'secupress_registration_test', 'failed', HOUR_IN_SECONDS );
		$errors->add( 'secupress_registration_test', 'secupress_registration_test_failed' );
	}

	return $errors;
}


add_action( 'admin_init', 'secupress_register_all_settings' );
/**
 * Register all modules settings.
 *
 * @since 1.0
 */
function secupress_register_all_settings() {
	$modules = secupress_get_modules();

	if ( $modules ) {
		foreach ( $modules as $key => $module_data ) {
			secupress_register_setting( $key );
		}
	}
}


add_action( 'admin_post_secupress_toggle_file_scan', '__secupress_toggle_file_scan_ajax_post_cb' );
/**
 * Set a transient to be read later to launch an async job.
 *
 * @since 1.0
 */
function __secupress_toggle_file_scan_ajax_post_cb() {
	if ( empty( $_GET['turn'] ) ) {
		secupress_admin_die();
	}

	secupress_check_user_capability();
	secupress_check_admin_referer( 'secupress_toggle_file_scan' );

	if ( 'on' === $_GET['turn'] ) {
		secupress_set_site_transient( 'secupress_toggle_file_scan', time() );
		set_site_transient( 'secupress_toggle_queue', true, 30 );
	} else {
		secupress_delete_site_transient( 'secupress_toggle_file_scan' );
		delete_site_transient( 'secupress_toggle_queue' );
	}

	wp_redirect( esc_url_raw( wp_get_referer() ) );
	die();
}


/*------------------------------------------------------------------------------------------------*/
/* TOOLS ======================================================================================== */
/*------------------------------------------------------------------------------------------------*/

/**
 * A simple shorthand to `die()`, depending on the admin context.
 *
 * @since 1.0
 */
function secupress_admin_die() {
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		wp_send_json_error();
	}
	wp_nonce_ays( '' );
}


/**
 * A simple shorthand to send a json response, die, or redirect to one of our settings pages, depending on the admin context.
 *
 * @since 1.0
 *
 * @param (array)  $response A scan/fix result or false.
 * @param (string) $redirect One of our pages slug. Can include an URL identifier (#azerty). If omitted, the referrer is used.
 */
function secupress_admin_send_response_or_redirect( $response = false, $redirect = false ) {
	if ( ! $response ) {
		secupress_admin_die();
	}

	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		wp_send_json_success( $response );
	}

	if ( $redirect ) {
		$redirect = explode( '#', $redirect );
		$redirect = secupress_admin_url( $redirect[0] ) . ( ! empty( $redirect[1] ) ? '#' . $redirect[1] : '' );
	} else {
		$redirect = wp_get_referer();
	}

	wp_redirect( esc_url_raw( $redirect ) );
	die();
}


/**
 * A simple shorthand to send a json response with message, die, or redirect with a message, depending on the admin context.
 *
 * @since 1.0
 *
 * @param (array) $args An array of arguments like:
 *                      (string)      $message     The message to return.
 *                      (string|bool) $redirect_to The URL to redirect to: false for the referer, or a complete URL, or the slug of one of our settings pages.
 *                      (string)      $code        An error code used by `add_settings_error()`.
 *                      (string)      $type        `success` (default) or `error`. Will decide to send a success or an error message.
 **/
function secupress_admin_send_message_die( $args ) {
	$args = array_merge( array(
		'message'     => '',
		'redirect_to' => false,
		'code'        => '',
		'type'        => 'success',
	), $args );

	$is_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;

	if ( ! $args['message'] && ! $is_ajax ) {
		secupress_admin_die();
	}

	if ( $is_ajax ) {
		if ( 'success' === $args['type'] ) {
			unset( $args['redirect_to'], $args['type'] );
			wp_send_json_success( $args );
		}

		unset( $args['redirect_to'], $args['type'] );
		wp_send_json_error( $args );
	}

	if ( ! $args['redirect_to'] ) {
		$args['redirect_to'] = wp_get_referer();
	} elseif ( 0 !== strpos( $args['redirect_to'], 'http' ) ) {
		$args['redirect_to'] = secupress_admin_url( $args['redirect_to'] );
	}

	$args['type'] = 'success' === $args['type'] ? 'updated' : 'error';

	add_settings_error( 'general', $args['code'], $args['message'], $args['type'] );
	set_transient( 'settings_errors', get_settings_errors(), 30 );

	$goback = add_query_arg( 'settings-updated', 'true', $args['redirect_to'] );
	wp_redirect( esc_url_raw( $goback ) );
	die();
}


/**
 * A shorthand to test if the current user can perform SecuPress operations. Die otherwise.
 *
 * @since 1.0
 */
function secupress_check_user_capability() {
	if ( ! current_user_can( secupress_get_capability() ) ) {
		secupress_admin_die();
	}
}


/**
 * A `check_admin_referer()` that also works for ajax.
 *
 * @since 1.0
 *
 * @param (int|string) $action    Action nonce.
 * @param (string)     $query_arg Optional. Key to check for nonce in `$_REQUEST` (since 2.5).
 *                                Default '_wpnonce'.
 *
 * @return (false|int) No ajax:
 *                     False if the nonce is invalid, 1 if the nonce is valid and generated between 0-12 hours ago, 2 if the nonce is valid and generated between 12-24 hours ago.
 *                     Ajax:
 *                     Send a JSON response back to an Ajax request, indicating failure.
 */
function secupress_check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		if ( false === check_ajax_referer( $action, $query_arg, false ) ) {
			wp_send_json_error();
		}
	} else {
		return check_admin_referer( $action, $query_arg );
	}
}


/**
 * Retrieve messages by their ID and format them by wrapping them in `<ul>` and `<li>` tags.
 *
 * @since 1.0
 *
 * @param (array)  $msgs      An array of messages.
 * @param (string) $test_name The scanner name.
 *
 * @return (string) An HTML list of formatted messages.
 */
function secupress_format_message( $msgs, $test_name ) {
	$classname = 'SecuPress_Scan_' . $test_name;
	$messages  = $classname::get_instance()->get_messages();

	$output = '<ul>';

	foreach ( $msgs as $id => $atts ) {

		if ( ! isset( $messages[ $id ] ) ) {

			$string = __( 'Unknown message', 'secupress' );

		} elseif ( is_array( $messages[ $id ] ) ) {

			$count  = array_shift( $atts );
			$string = translate_nooped_plural( $messages[ $id ], $count );

		} else {

			$string = $messages[ $id ];

		}

		if ( $atts ) {
			foreach ( $atts as $i => $att ) {
				if ( is_array( $att ) ) {
					$atts[ $i ] = wp_sprintf_l( '%l', $att );
				}
			}
		}

		$output .= '<li>' . ( ! empty( $atts ) ? vsprintf( $string, $atts ) : $string ) . '</li>';
	}

	return $output . '</ul>';
}


/*------------------------------------------------------------------------------------------------*/
/* MEH ========================================================================================== */
/*------------------------------------------------------------------------------------------------*/

add_filter( 'plugin_action_links_' . plugin_basename( SECUPRESS_FILE ), '__secupress_settings_action_links' );
/**
 * Link to the configuration page of the plugin.
 *
 * @since 1.0
 *
 * @param (array) $actions An array of links.
 *
 * @return (array) The array of links + our links.
 */
function __secupress_settings_action_links( $actions ) {
	if ( ! secupress_is_white_label() ) {
		array_unshift( $actions, sprintf( '<a href="%s">%s</a>', 'http://secupress.me/support/', __( 'Support', 'secupress' ) ) );

		array_unshift( $actions, sprintf( '<a href="%s">%s</a>', 'http://docs.secupress.me', __( 'Docs', 'secupress' ) ) );
	}

	array_unshift( $actions, sprintf( '<a href="%s">%s</a>', esc_url( secupress_admin_url( 'settings' ) ), __( 'Settings' ) ) );

	return $actions;
}
