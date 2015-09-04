<?php
defined( 'ABSPATH' ) or	die( 'Cheatin&#8217; uh?' );

/*
 * Tell WP what to do when admin is loaded aka upgrader
 *
 * @since 1.0
 */
add_action( 'admin_init', 'secupress_upgrader' );
function secupress_upgrader()
{
	// Grab some infos
	$actual_version = get_secupress_option( 'version' );
	// You can hook the upgrader to trigger any action when WP secupress is upgraded
	// first install
	if ( ! $actual_version ){
		do_action( 'wp_secupress_first_install' );
	}
	// already installed but got updated
	elseif ( SECUPRESS_VERSION != $actual_version ) {
		do_action( 'wp_secupress_upgrade', SECUPRESS_VERSION, $actual_version );
	}
	// If any upgrade has been done, we flush and update version #
	if ( did_action( 'wp_secupress_first_install' ) || did_action( 'wp_secupress_upgrade' ) ) {
		// flush_secupress_htaccess(); ////

		secupress_renew_all_boxes( 0, array( 'secupress_warning_plugin_modification' ) );

		$options = get_option( SECUPRESS_SETTINGS_SLUG ); // do not use get_secupress_option() here
		$options['version'] = SECUPRESS_VERSION;

		$keys = secupress_check_key( 'live' );
		if ( is_array( $keys ) ) {
			$options = array_merge( $keys, $options );
		}

		update_option( SECUPRESS_SETTINGS_SLUG, $options );
	} else {
		if ( empty( $_POST ) && secupress_valid_key() ) {
			secupress_check_key( 'transient_30' );
		}
	}
	/** This filter is documented in inc/admin-bar.php */
	if ( ! secupress_valid_key() && current_user_can( apply_filters( 'secupress_capacity', 'manage_options' ) ) &&
		( ! isset( $_GET['page'] ) || 'secupress' != $_GET['page'] ) ) {
		add_action( 'admin_notices', 'secupress_need_api_key' );
	}
}

/* BEGIN UPGRADER'S HOOKS */

/**
 * Keeps this function up to date at each version
 *
 * @since 1.0
 */
add_action( 'wp_secupress_first_install', 'secupress_first_install' );
function secupress_first_install()
{
	// Generate an random key
	$secret_cache_key = create_secupress_uniqid();

	// Create Options
	add_option( SECUPRESS_SETTINGS_SLUG,
		array(
			
		)
	);
	add_option( 'secupress_users_login_settings',
		array(
			'module_active' => 1,
			'plugin_double_auth' => '-1',
		)
	);
	secupress_dismiss_box( 'secupress_warning_plugin_modification' );
	// secupress_reset_white_label_values( false );
}

/**
 * What to do when secupress is updated, depending on versions
 *
 * @since 1.0
 */
add_action( 'wp_secupress_upgrade', 'secupress_new_upgrade', 10, 2 );
function secupress_new_upgrade( $wp_secupress_version, $actual_version )
{
	
}
/* END UPGRADER'S HOOKS */