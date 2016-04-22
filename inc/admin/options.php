<?php
defined( 'ABSPATH' ) or die( 'Cheatin&#8217; uh?' );

/*------------------------------------------------------------------------------------------------*/
/* MAIN OPTION ================================================================================== */
/*------------------------------------------------------------------------------------------------*/

add_action( 'admin_init', 'secupress_register_global_setting' );
/**
 * Whitelist our global settings.
 *
 * @since 1.0
 */
function secupress_register_global_setting() {
	secupress_register_setting( 'global', SECUPRESS_SETTINGS_SLUG );
}


/**
 * Sanitize our global settings.
 *
 * @since 1.0
 *
 * @param (array) $value Our global settings.
 */
function __secupress_global_settings_callback( $value ) {
	$value = $value ? $value : array();

	if ( isset( $value['sanitized'] ) ) {
		return $value;
	}
	$value['sanitized'] = 1;

	// License validation.
	$value['consumer_email'] = ! empty( $value['consumer_email'] ) ? is_email( $value['consumer_email'] )          : '';
	$value['consumer_key']   = ! empty( $value['consumer_key'] )   ? sanitize_text_field( $value['consumer_key'] ) : '';

	if ( $value['consumer_email'] && $value['consumer_key'] ) {
		$response = wp_remote_post( SECUPRESS_WEB_DEMO . 'valid_key.php',
			array(
				'timeout' => 10,
				'body'    => array(
					'data' => array(
						'user_email' => $value['consumer_email'],
						'user_key'   => $value['consumer_key'],
						'action'     => 'create_free_licence',
					),
				),
			)
		);

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$value['consumer_key'] = sanitize_text_field( wp_remote_retrieve_body( $response ) );
		}
	}

	// Uptime monitor.
	$account_token = secupress_get_option( 'uptime_monitoring_account_key' );
	$site_token    = secupress_get_option( 'uptime_monitoring_site_key' );

	if ( $account_token && $site_token ) {
		$value['uptime_monitoring_account_key'] = $account_token;
		$value['uptime_monitoring_site_key']    = $site_token;
	} else {
		unset( $value['uptime_monitoring_account_key'], $value['uptime_monitoring_site_key'] );
	}

	return $value;
}


/*------------------------------------------------------------------------------------------------*/
/* TRACK CHANGES IN CONSUMER EMAIL ============================================================== */
/*------------------------------------------------------------------------------------------------*/

add_action( 'add_site_option_' . SECUPRESS_SETTINGS_SLUG,    'secupress_updated_consumer_email_option', 20, 2 );
add_action( 'update_site_option_' . SECUPRESS_SETTINGS_SLUG, 'secupress_updated_consumer_email_option', 20, 3 );
/**
 * If the consumer email address is changed, trigger a custom event.
 *
 * @since 1.0
 *
 * @param (string) $option   Name of the option.
 * @param (mixed)  $newvalue The new value of the option.
 * @param (mixed)  $oldvalue The old value of the option.
 */
function secupress_updated_consumer_email_option( $option, $newvalue, $oldvalue = false ) {
	$old_email = isset( $oldvalue['consumer_email'] ) ? is_email( $oldvalue['consumer_email'] ) : false;
	$new_email = isset( $newvalue['consumer_email'] ) ? is_email( $newvalue['consumer_email'] ) : false;

	if ( ! $old_email && ! $new_email ) {
		return;
	}

	if ( ! $new_email ) {
		/**
		 * Fires after the consumer email has been deleted.
		 *
		 * @since 1.0
		 *
		 * @param (string) $old_email The old consumer email address.
		 */
		do_action( 'secupress.deleted-consumer_email', $old_email );
		return;
	}

	if ( ! $old_email ) {
		/**
		 * Fires after the consumer email has been added.
		 *
		 * @since 1.0
		 *
		 * @param (string) $new_email The new consumer email address.
		 */
		do_action( 'secupress.added-consumer_email', $new_email );
		return;
	}

	if ( $old_email !== $new_email ) {
		/**
		 * Fires after the consumer email has been updated.
		 *
		 * @since 1.0
		 *
		 * @param (string) $new_email The new consumer email address.
		 * @param (string) $old_email The old consumer email address.
		 */
		do_action( 'secupress.updated-consumer_email', $new_email, $old_email );
	}
}


add_action( 'pre_delete_site_option_' . SECUPRESS_SETTINGS_SLUG, 'secupress_before_delete_consumer_email_option', 20 );
/**
 * Before deleting the whole option, test if the consumer email exists.
 * Store the current value and use it later in `secupress_deleted_consumer_email_option()`.
 * This way we can trigger a custom event only if there was a value.
 *
 * @since 1.0
 */
function secupress_before_delete_consumer_email_option() {
	$old_email = get_site_option( SECUPRESS_SETTINGS_SLUG );
	$old_email = isset( $old_email['consumer_email'] ) ? is_email( $old_email['consumer_email'] ) : false;
	secupress_cache_data( 'old_consumer_email', $old_email );
}


add_action( 'delete_site_option_' . SECUPRESS_SETTINGS_SLUG, 'secupress_deleted_consumer_email_option', 20 );
/**
 * If the consumer email address is deleted, trigger a custom event.
 *
 * @since 1.0
 */
function secupress_deleted_consumer_email_option() {
	$old_email = secupress_cache_data( 'old_consumer_email' );

	if ( $old_email ) {
		/**
		 * Fires after the consumer email has been deleted.
		 *
		 * @since 1.0
		 *
		 * @param (string) $old_email The old consumer email address.
		 */
		do_action( 'secupress.deleted-consumer_email', $old_email );
	}

	secupress_cache_data( 'old_consumer_email', null );
}


/*------------------------------------------------------------------------------------------------*/
/* CSS, JS, FAVICON ============================================================================= */
/*------------------------------------------------------------------------------------------------*/

add_action( 'admin_enqueue_scripts', '__secupress_add_settings_scripts' );
/**
 * Add some CSS and JS to our settings pages.
 *
 * @since 1.0
 *
 * @param (string) $hook_suffix The current admin page.
 */
function __secupress_add_settings_scripts( $hook_suffix ) {
	$suffix  = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	$version = $suffix ? SECUPRESS_VERSION : time();

	// WordPress Common CSS.
	wp_enqueue_style( 'secupress-wordpress-css', SECUPRESS_ADMIN_CSS_URL . 'secupress-wordpress' . $suffix . '.css', array(), $version );

	// WordPress Common JS.
	wp_enqueue_script( 'secupress-wordpress-js', SECUPRESS_ADMIN_JS_URL . 'secupress-wordpress' . $suffix . '.js', array(), $version, true );

	$pages = array(
		'toplevel_page_secupress_scanners'                 => 1,
		SECUPRESS_PLUGIN_SLUG . '_page_secupress_modules'  => 1,
		SECUPRESS_PLUGIN_SLUG . '_page_secupress_settings' => 1,
		SECUPRESS_PLUGIN_SLUG . '_page_secupress_logs'     => 1,
	);

	if ( ! isset( $pages[ $hook_suffix ] ) ) {
		return;
	}

	// SecuPress Common CSS.
	wp_enqueue_style( 'secupress-common-css', SECUPRESS_ADMIN_CSS_URL . 'secupress-common' . $suffix . '.css', array(), $version );

	// Global settings page.
	if ( SECUPRESS_PLUGIN_SLUG . '_page_secupress_settings' === $hook_suffix ) {
		// CSS.
		wp_enqueue_style( 'secupress-settings-css', SECUPRESS_ADMIN_CSS_URL . 'secupress-settings' . $suffix . '.css', array( 'secupress-common-css' ), $version );
	}
	// Modules page.
	elseif ( SECUPRESS_PLUGIN_SLUG . '_page_secupress_modules' === $hook_suffix ) {
		// CSS.
		wp_enqueue_style( 'secupress-modules-css',  SECUPRESS_ADMIN_CSS_URL . 'secupress-modules' . $suffix . '.css', array( 'secupress-common-css' ), $version );
		wp_enqueue_style( 'wpmedia-css-sweetalert', SECUPRESS_ADMIN_CSS_URL . 'sweetalert' . $suffix . '.css', array(), '1.1.0' );

		// JS.
		wp_enqueue_script( 'wpmedia-js-sweetalert', SECUPRESS_ADMIN_JS_URL . 'sweetalert' . $suffix . '.js', array(), '1.1.0', true );
		wp_enqueue_script( 'secupress-modules-js',  SECUPRESS_ADMIN_JS_URL . 'secupress-modules' . $suffix . '.js', array( 'wpmedia-js-sweetalert' ), $version, true );

		wp_localize_script( 'secupress-modules-js', 'l10nmodules', array(
			// Roles.
			'selectOneRoleMinimum' => __( 'Select 1 role minimum', 'secupress' ),
			// Generic.
			'confirmTitle'         => __( 'Are you sure?', 'secupress' ),
			'confirmCancel'        => _x( 'No, cancel', 'verb', 'secupress' ),
			'error'                => __( 'Error', 'secupress' ),
			'unknownError'         => __( 'Unknown error.', 'secupress' ),
			'delete'               => __( 'Delete', 'secupress' ),
			'done'                 => __( 'Done!', 'secupress' ),
			// Backups.
			'confirmDeleteBackups' => __( 'You are about to delete all your backups.', 'secupress' ),
			'yesDeleteAll'         => __( 'Yes, delete all backups', 'secupress' ),
			'deleteAllImpossible'  => __( 'Impossible to delete all backups.', 'secupress' ),
			'deletingAllText'      => __( 'Deleting all backups&hellip;', 'secupress' ),
			'deletedAllText'       => __( 'All backups deleted', 'secupress' ),
			// Backup.
			'confirmDeleteBackup'  => __( 'You are about to delete a backup.', 'secupress' ),
			'yesDeleteOne'         => __( 'Yes, delete this backup', 'secupress' ),
			'deleteOneImpossible'  => __( 'Impossible to delete this backup.', 'secupress' ),
			'deletingOneText'      => __( 'Deleting Backup&hellip;', 'secupress' ),
			'deletedOneText'       => __( 'Backup deleted', 'secupress' ),
			// Backup actions.
			'backupImpossible'     => __( 'Impossible to backup the database.', 'secupress' ),
			'backupingText'        => __( 'Backuping&hellip;', 'secupress' ),
			'backupedText'         => __( 'Backup done', 'secupress' ),
			// Ban IPs.
			'noBannedIPs'          => __( 'No Banned IPs anymore.', 'secupress' ),
			'IPnotFound'           => __( 'IP not found.', 'secupress' ),
			'IPremoved'            => __( 'IP removed.', 'secupress' ),
			'searchResults'        => __( 'See search result below.', 'adjective', 'secupress' ),
			'searchReset'          => _x( 'Search reset.', 'adjective', 'secupress' ),
		) );

	}
	// Scanners page.
	elseif ( 'toplevel_page_secupress_scanners' === $hook_suffix ) {
		// CSS.
		wp_enqueue_style( 'secupress-scanner-css',  SECUPRESS_ADMIN_CSS_URL . 'secupress-scanner' . $suffix . '.css', array( 'secupress-common-css' ), $version );
		wp_enqueue_style( 'wpmedia-css-sweetalert', SECUPRESS_ADMIN_CSS_URL . 'sweetalert' . $suffix . '.css', array(), '1.1.0' );

		// JS.
		$depts = array();
		if ( is_network_admin() || ! is_multisite() ) {
			$depts  = array( 'secupress-chartjs' );
			$counts = secupress_get_scanner_counts();

			wp_enqueue_script( 'secupress-chartjs', SECUPRESS_ADMIN_JS_URL . 'chart' . $suffix . '.js', array(), '1.0.2.1', true );

			wp_localize_script( 'secupress-chartjs', 'SecuPressi18nChart', array(
				'good'          => array( 'value' => $counts['good'],          'text' => __( 'Good', 'secupress' ) ),
				'warning'       => array( 'value' => $counts['warning'],       'text' => __( 'Warning', 'secupress' ) ),
				'bad'           => array( 'value' => $counts['bad'],           'text' => __( 'Bad', 'secupress' ) ),
				'notscannedyet' => array( 'value' => $counts['notscannedyet'], 'text' => __( 'Not Scanned Yet', 'secupress' ) ),
			) );
		}

		wp_enqueue_script( 'secupress-scanner-js',  SECUPRESS_ADMIN_JS_URL . 'secupress-scanner' . $suffix . '.js', $depts, $version, true );
		wp_enqueue_script( 'wpmedia-js-sweetalert', SECUPRESS_ADMIN_JS_URL . 'sweetalert' . $suffix . '.js', array(), '1.1.0', true );

		wp_localize_script( 'secupress-scanner-js', 'SecuPressi18nScanner', array(
			'fixed'           => __( 'Fixed', 'secupress' ),
			'fixedPartial'    => __( 'Partially fixed', 'secupress' ),
			'notFixed'        => __( 'Not Fixed', 'secupress' ),
			'fixit'           => __( 'Fix it!', 'secupress' ),
			'error'           => __( 'Error', 'secupress' ),
			'oneManualFix'    => __( 'One fix requires your intervention.', 'secupress' ),
			'someManualFixes' => __( 'Some fixes require your intervention.', 'secupress' ),
			'spinnerUrl'      => admin_url( 'images/wpspin_light-2x.gif' ),
			'scanDetails'     => __( 'Scan Details', 'secupress' ),
			'fixDetails'      => __( 'Fix Details', 'secupress' ),
		) );
	}

	// Add the favicon.
	add_action( 'admin_head', 'secupress_favicon' );
}


/**
 * Add a site icon to each of our settings pages.
 *
 * @since 1.0
 */
function secupress_favicon() {
	$version = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '?ver=' . time() : '';
	echo '<link id="favicon" rel="shortcut icon" type="image/png" href="' . SECUPRESS_ADMIN_IMAGES_URL . 'black-shield-16.png' . $version . '" />';
}


/*------------------------------------------------------------------------------------------------*/
/* ADMIN MENU =================================================================================== */
/*------------------------------------------------------------------------------------------------*/

add_action( ( is_multisite() ? 'network_' : '' ) . 'admin_menu', 'secupress_create_menus' );
/**
 * Create the plugin menu and submenus.
 *
 * @since 1.0
 */
function secupress_create_menus() {
	global $menu;

	// Add a counter of scans with bad result.
	$count = 0;
	$scans = secupress_get_scanners();

	if ( $scans ) {
		foreach ( $scans as $scan ) {
			if ( 'bad' === $scan['status'] ) {
				++$count;
			}
		}
	}

	$count = sprintf( ' <span class="update-plugins count-%1$d"><span class="update-count">%1$d</span></span>', $count );
	$cap   = secupress_get_capability();

	// Main menu item.
	add_menu_page( SECUPRESS_PLUGIN_NAME, SECUPRESS_PLUGIN_NAME, $cap, 'secupress_scanners', '__secupress_scanners', 'dashicons-shield-alt' );

	// Sub-menus.
	add_submenu_page( 'secupress_scanners', __( 'Scanners', 'secupress' ), __( 'Scanners', 'secupress' ) . $count, $cap, 'secupress_scanners', '__secupress_scanners' );
	add_submenu_page( 'secupress_scanners', __( 'Modules', 'secupress' ),  __( 'Modules', 'secupress' ),           $cap, 'secupress_modules',  '__secupress_modules' );
	add_submenu_page( 'secupress_scanners', __( 'Settings' ),              __( 'Settings' ),                       $cap, 'secupress_settings', '__secupress_global_settings' );

	end( $menu );
	$key = key( $menu );
	$menu[ $key ][0] .= $count;
}


/*------------------------------------------------------------------------------------------------*/
/* SETTINGS PAGES =============================================================================== */
/*------------------------------------------------------------------------------------------------*/

/**
 * Settings page.
 *
 * @since 1.0
 */
function __secupress_global_settings() {
	if ( ! class_exists( 'SecuPress_Settings' ) ) {
		secupress_require_class( 'settings' );
	}
	if ( ! class_exists( 'SecuPress_Settings_Global' ) ) {
		secupress_require_class( 'settings', 'global' );
	}

	SecuPress_Settings_Global::get_instance()->print_page();
}


/**
 * Modules page.
 *
 * @since 1.0
 */
function __secupress_modules() {
	if ( ! class_exists( 'SecuPress_Settings' ) ) {
		secupress_require_class( 'settings' );
	}
	if ( ! class_exists( 'SecuPress_Settings_Modules' ) ) {
		secupress_require_class( 'settings', 'modules' );
	}

	SecuPress_Settings_Modules::get_instance()->print_page();
}


/**
 * Scanners page.
 *
 * @since 1.0
 */
function __secupress_scanners() {
	$times   = array_filter( (array) get_site_option( SECUPRESS_SCAN_TIMES ) );
	$reports = array();
	$last_pc = -1;

	if ( $times ) {
		foreach ( $times as $time ) {
			$icon = 'right';

			if ( $last_pc > -1 ) {
				if ( $last_pc < $time['percent'] ) {
					$icon = 'up';
				} elseif ( $last_pc > $time['percent'] ) {
					$icon = 'down';
				}
			}

			$last_pc = $time['percent'];

			$reports[] = sprintf(
				'<li data-percent="%1$d"><span class="dashicons mini dashicons-arrow-%2$s-alt2" aria-hidden="true"></span><strong>%3$s (%1$d %%)</strong> <span class="timeago">%4$s</span></li>',
				$time['percent'],
				$icon,
				$time['grade'],
				sprintf( __( '%s ago' ), human_time_diff( $time['time'] ) )
			);
		}

		$reports = array_reverse( $reports );
	}

	$boxes = array(
		'score' => array(
			__( 'Your Score', 'secupress' ),
			'<canvas id="status_chart" width="300" height="300"></canvas>
			<div class="score_info2">
				<span class="letter">&ndash;</span>
				<span class="percent">(0 %)</span>
				<div class="score_results">' .
					sprintf( __( '%s: ', 'secupress' ), '<strong>' . __( 'Latest Reports', 'secupress' ) . '</strong>' ) . '<br>
					<ul>' .
						implode( "\n", $reports ) .
					'</ul>
				</div>
			</div>
			<div class="legend">
				<span class="status-good"><span class="secupress-dashicon dashicons dashicons-shield-alt" aria-hidden="true"></span> ' . __( 'Good', 'secupress' ) . '</span> |
				<span class="status-bad"><span class="secupress-dashicon dashicons dashicons-shield-alt" aria-hidden="true"></span> ' . __( 'Bad', 'secupress' ) . '</span> |
				<span class="status-warning"><span class="secupress-dashicon dashicons dashicons-shield-alt" aria-hidden="true"></span> ' . __( 'Warning', 'secupress' ) . '</span> |
				<span class="status-notscannedyet"><span class="secupress-dashicon dashicons dashicons-shield-alt" aria-hidden="true"></span> ' . __( 'Not scanned yet', 'secupress' ) . '</span>
			</div>
			<div id="tweeterA" class="hidden">
				<hr>
				<span class="dashicons dashicons-twitter"></span>
				<i>' . __( 'Wow! My website just got an A security grade using SecuPress, what about yours?', 'secupress' ) . '</i>
				<a class="button button-small" href="https://twitter.com/intent/tweet?via=secupress&amp;url=' . urlencode( esc_url_raw( 'http://secupress.fr&text=' . __( 'Wow! My website just got an A security grade using SecuPress, what about yours?', 'secupress' ) ) ) . '">Tweet &raquo;</a>
			</div>',
		),
	);
	?>
	<div class="wrap">
		<?php secupress_admin_heading( __( 'Scanners', 'secupress' ) ); ?>

		<div class="secupress-wrapper">
			<?php
			foreach ( $boxes as $id => $box ) {
				secupress_sidebox( array( 'id' => $id, 'title' => $box[0], 'content' => $box[1] ) );
			}
			?>
			<button class="button button-primary button-secupress-scan hide-if-no-js" type="button" data-nonce="<?php echo esc_attr( wp_create_nonce( 'secupress-update-oneclick-scan-date' ) ); ?>">
				<?php _e( 'One Click Scan', 'secupress' ); ?>
			</button>

			<button class="button button-primary button-secupress-fix hide-if-no-js" type="button">
				<?php _e( 'One Click Fix', 'secupress' ); ?>
			</button>


			<div class="square-filter priorities hide-if-no-js">
				<button type="button" class="active" data-type="all"><?php _ex( 'All Priorities', 'priority', 'secupress' ); ?></button><?php
				?><button type="button" data-type="high"><?php _ex( 'High Priority', 'priority', 'secupress' ); ?></button><?php
				?><button type="button" data-type="medium"><?php _ex( 'Medium Priority', 'priority', 'secupress' ); ?></button><?php
				?><button type="button" data-type="low"><?php _ex( 'Low Priority', 'priority', 'secupress' ); ?></button>
			</div>

			<div class="square-filter statuses hide-if-no-js">
				<button type="button" class="active" data-type="all"><?php _ex( 'All Statuses', 'priority', 'secupress' ); ?></button><?php
				?><button type="button" data-type="good"><?php _ex( 'Good Status', 'priority', 'secupress' ); ?></button><?php
				?><button type="button" data-type="warning"><?php _ex( 'Warning Status', 'priority', 'secupress' ); ?></button><?php
				?><button type="button" data-type="bad"><?php _ex( 'Bad Status', 'priority', 'secupress' ); ?></button><?php
				?><button type="button" data-type="notscannedyet"><?php _ex( 'Not Scanned Yet', 'priority', 'secupress' ); ?></button>
			</div>

			<?php
			secupress_scanners_template();

			wp_nonce_field( 'secupress_score', 'secupress_score', false );
			?>
		</div>

	</div>
	<?php
}


/*------------------------------------------------------------------------------------------------*/
/* TOOLS ======================================================================================== */
/*------------------------------------------------------------------------------------------------*/

/**
 * Print the settings page title.
 *
 * @since 1.0
 *
 * @param (string) $title The title.
 */
function secupress_admin_heading( $title = '' ) {
	$heading_tag = secupress_wp_version_is( '4.3-alpha' ) ? 'h1' : 'h2';
	printf( '<%1$s>%2$s <sup>%3$s</sup> %4$s</%1$s>', $heading_tag, SECUPRESS_PLUGIN_NAME, SECUPRESS_VERSION, $title );
}


/**
 * Print the scanners page content.
 *
 * @since 1.0
 */
function secupress_scanners_template() {
	secupress_require_class( 'scan' );

	$is_subsite   = is_multisite() && ! is_network_admin();
	$heading_tag  = secupress_wp_version_is( '4.4-alpha' ) ? 'h2' : 'h3';
	// Allowed tags in "Learn more" contents.
	$allowed_tags = array(
		'a'      => array( 'href' => array(),'title' => array(), 'target' => array() ),
		'abbr'   => array( 'title' => array() ),
		'code'   => array(),
		'em'     => array(),
		'strong' => array(),
		'ul'     => array(),
		'ol'     => array(),
		'li'     => array(),
		'p'      => array(),
		'br'     => array(),
	);
	// Actions the user needs to perform for a fix.
	$fix_actions     = SecuPress_Scan::get_and_delete_fix_actions();
	// Auto-scans: scans that will be executed on page load.
	$autoscans       = SecuPress_Scan::get_and_delete_autoscans();

	if ( ! $is_subsite ) {
		$secupress_tests = secupress_get_tests();
		$scanners        = secupress_get_scanners();
		$fixes           = secupress_get_scanner_fixes();

		// Store the scans in 3 variables. They will be used to order the scans by status: 'bad', 'warning', 'notscannedyet', 'good'.
		$bad_scans     = array();
		$warning_scans = array();
		$good_scans    = array();

		if ( ! empty( $scanners ) ) {
			foreach ( $scanners as $class_name_part => $details ) {
				if ( 'bad' === $details['status'] ) {
					$bad_scans[ $class_name_part ] = $details['status'];
				} elseif ( 'warning' === $details['status'] ) {
					$warning_scans[ $class_name_part ] = $details['status'];
				} elseif ( 'good' === $details['status'] ) {
					$good_scans[ $class_name_part ] = $details['status'];
				}
			}
		}
	} else {
		$secupress_tests = array( secupress_get_tests_for_ms_scanner_fixes() );
		$sites           = secupress_get_results_for_ms_scanner_fixes();
		$site_id         = get_current_blog_id();
		$scanners        = array();
		$fixes           = array();

		foreach ( $sites as $test => $site_data ) {
			if ( ! empty( $site_data[ $site_id ] ) ) {
				$scanners[ $test ] = ! empty( $site_data[ $site_id ]['scan'] ) ? $site_data[ $site_id ]['scan'] : array();
				$fixes[ $test ]    = ! empty( $site_data[ $site_id ]['fix'] )  ? $site_data[ $site_id ]['fix']  : array();
			}
		}
	}
	?>
	<div id="secupress-tests">
		<?php
		foreach ( $secupress_tests as $prio_key => $class_name_parts ) {
			$i = 0;
			?>
			<div class="table-prio-all<?php echo $is_subsite ? '' : " table-prio-$prio_key"; ?>">

				<?php
				if ( ! $is_subsite ) {
					$prio_data = SecuPress_Scan::get_priorities( $prio_key );
					?>
					<div class="prio-<?php echo $prio_key; ?>">
						<?php echo '<' . $heading_tag . '>' . $prio_data['title'] . '</' . $heading_tag . '>'; ?>
						<?php echo $prio_data['description']; ?>
					</div>
					<?php
				}
				?>

				<table class="wp-list-table widefat">
					<thead>
						<tr>
							<th scope="col" class="secupress-desc"><?php _e( 'Description', 'secupress' ); ?></th>
							<th scope="col" class="secupress-scan-status" data-sort="string"><?php _e( 'Scan Status', 'secupress' ); ?></th>
							<th scope="col" class="secupress-scan-result"><?php _e( 'Scan Result', 'secupress' ); ?></th>
							<th scope="col" class="secupress-fix-status"><?php _e( 'Fix Status', 'secupress' ); ?></th>
							<th scope="col" class="secupress-fix-result"><?php _e( 'Fix Result', 'secupress' ); ?></th>
						</tr>
					</thead>

					<tfoot>
						<tr>
							<th scope="col" class="secupress-desc"><?php _e( 'Description', 'secupress' ); ?></th>
							<th scope="col" class="secupress-scan-status"><?php _e( 'Scan Status', 'secupress' ); ?></th>
							<th scope="col" class="secupress-scan-result"><?php _e( 'Scan Result', 'secupress' ); ?></th>
							<th scope="col" class="secupress-fix-status"><?php _e( 'Fix Status', 'secupress' ); ?></th>
							<th scope="col" class="secupress-fix-result"><?php _e( 'Fix Result', 'secupress' ); ?></th>
						</tr>
					</tfoot>

					<tbody>
					<?php
					$class_name_parts = array_combine( array_map( 'strtolower', $class_name_parts ), $class_name_parts );

					if ( ! $is_subsite ) {
						foreach ( $class_name_parts as $option_name => $class_name_part ) {
							if ( ! file_exists( secupress_class_path( 'scan', $class_name_part ) ) ) {
								unset( $class_name_parts[ $option_name ] );
								continue;
							}

							secupress_require_class( 'scan', $class_name_part );
						}

						// For this priority, order the scans by status: 'bad', 'warning', 'notscannedyet', 'good'.
						$this_prio_bad_scans     = array_intersect_key( $class_name_parts, $bad_scans );
						$this_prio_warning_scans = array_intersect_key( $class_name_parts, $warning_scans );
						$this_prio_good_scans    = array_intersect_key( $class_name_parts, $good_scans );
						$class_name_parts        = array_diff_key( $class_name_parts, $this_prio_bad_scans, $this_prio_warning_scans, $this_prio_good_scans );
						$class_name_parts        = array_merge( $this_prio_bad_scans, $this_prio_warning_scans, $class_name_parts, $this_prio_good_scans );
						unset( $this_prio_bad_scans, $this_prio_warning_scans, $this_prio_good_scans );
					} else {
						foreach ( $class_name_parts as $option_name => $class_name_part ) {
							// Display only scanners where we have a scan result or a fix to be done.
							if ( empty( $scanners[ $option_name ] ) && empty( $fixes[ $option_name ] ) || ! file_exists( secupress_class_path( 'scan', $option_name ) ) ) {
								unset( $class_name_parts[ $option_name ] );
								continue;
							}

							secupress_require_class( 'scan', $class_name_part );
						}
					}

					// Print the rows.
					foreach ( $class_name_parts as $option_name => $class_name_part ) {
						++$i;
						$class_name   = 'SecuPress_Scan_' . $class_name_part;
						$current_test = $class_name::get_instance();
						$referer      = urlencode( esc_url_raw( self_admin_url( 'admin.php?page=secupress_scanners' . ( $is_subsite ? '' : '#' . $class_name_part ) ) ) );
						$css_class    = ' type-' . sanitize_key( $class_name::$type );

						if ( $is_subsite ) {
							$css_class .= 0 === $i % 2 ? '' : ' alternate';
						} else {
							$css_class .= 0 === $i % 2 ? ' alternate-2' : ' alternate-1';
						}

						// Scan.
						$status_text  = ! empty( $scanners[ $option_name ]['status'] ) ? secupress_status( $scanners[ $option_name ]['status'] )    : secupress_status( 'notscannedyet' );
						$status_class = ! empty( $scanners[ $option_name ]['status'] ) ? sanitize_html_class( $scanners[ $option_name ]['status'] ) : 'notscannedyet';
						$scan_nonce   = 'secupress_scanner_' . $class_name_part . ( $is_subsite ? '-' . $site_id : '' );
						$scan_nonce   = wp_nonce_url( admin_url( 'admin-post.php?action=secupress_scanner&test=' . $class_name_part . '&_wp_http_referer=' . $referer . ( $is_subsite ? '&for-current-site=1&site=' . $site_id : '' ) ), $scan_nonce );
						$css_class   .= ' status-' . $status_class;
						$css_class   .= isset( $autoscans[ $class_name_part ] ) ? ' autoscan' : '';
						$css_class   .= false === $current_test::$fixable || 'pro' === $current_test::$fixable && ! secupress_is_pro() ? ' not-fixable' : '';

						if ( ! empty( $scanners[ $option_name ]['msgs'] ) ) {
							$scan_message = secupress_format_message( $scanners[ $option_name ]['msgs'], $class_name_part );
						} else {
							$scan_message = '&#175;';
						}

						// Fix.
						$fix_status_text  = ! empty( $fixes[ $option_name ]['status'] ) && 'good' !== $fixes[ $option_name ]['status'] ? secupress_status( $fixes[ $option_name ]['status'] ) : '&#160;';
						$fix_css_class    = ! empty( $fixes[ $option_name ]['status'] ) ? ' status-' . sanitize_html_class( $fixes[ $option_name ]['status'] ) : ' status-cantfix';
						$fix_nonce        = 'secupress_fixit_' . $class_name_part . ( $is_subsite ? '-' . $site_id : '' );
						$fix_nonce        = wp_nonce_url( admin_url( 'admin-post.php?action=secupress_fixit&test=' . $class_name_part . '&_wp_http_referer=' . $referer . ( $is_subsite ? '&for-current-site=1&site=' . $site_id : '' ) ), $fix_nonce );

						if ( ! empty( $fixes[ $option_name ]['msgs'] ) && 'good' !== $status_class ) {
							$fix_message = secupress_format_message( $fixes[ $option_name ]['msgs'], $class_name_part );
						} else {
							$fix_message = '';
						}
						?>
						<tr id="<?php echo $class_name_part; ?>" class="secupress-item-all secupress-item-<?php echo $class_name_part; ?> type-all status-all<?php echo $css_class; ?>">
							<th scope="row">
								<?php echo $class_name::$title; ?>
								<div class="secupress-row-actions">
									<span class="hide-if-no-js">
										<button type="button" class="secupress-details link-like" data-test="<?php echo $class_name_part; ?>" title="<?php esc_attr_e( 'Get details', 'secupress' ); ?>"><?php _e( 'Learn more', 'secupress' ); ?></button>
									</span>
								</div>
							</th>
							<td class="secupress-scan-status">
								<div class="secupress-status"><?php echo $status_text; ?></div>

								<div class="secupress-row-actions">
									<a class="button button-secondary button-small secupress-scanit" href="<?php echo esc_url( $scan_nonce ); ?>"><?php _ex( 'Scan', 'scan a test', 'secupress' ); ?></a>
								</div>
							</td>
							<td class="secupress-scan-result">
								<?php echo $scan_message; ?>
							</td>
							<td class="secupress-fix-status<?php echo $fix_css_class; ?>">
								<div class="secupress-status"><?php echo $fix_status_text; ?></div>

								<div class="secupress-row-actions">
									<?php
									if ( true === $current_test::$fixable || 'pro' === $current_test::$fixable && secupress_is_pro() ) {
										?>
										<a class="button button-secondary button-small secupress-fixit<?php echo $current_test::$delayed_fix ? ' delayed-fix' : '' ?>" href="<?php echo esc_url( $fix_nonce ); ?>"><?php _e( 'Fix it!', 'secupress' ); ?></a>
										<div class="secupress-row-actions">
											<span class="hide-if-no-js">
												<button type="button" class="secupress-details-fix link-like" data-test="<?php echo $class_name_part; ?>" title="<?php esc_attr_e( 'Get fix details', 'secupress' ); ?>"><?php _e( 'Learn more', 'secupress' ); ?></button>
											</span>
										</div>
										<?php
									} elseif ( 'pro' === $current_test::$fixable ) { // //// #. ?>
										<button type="button" class="button button-secondary button-small secupress-go-pro"><?php _e( 'Pro Upgrade', 'secupress' ); ?></button>
										<?php
									} else { // Really not fixable by the plugin.
										echo '<em>(';
										_e( 'Cannot be fixed automatically.', 'secupress' );
										echo '</em>)';
									}
									?>
								</div>
							</td>
							<td class="secupress-fix-result">
								<?php echo $fix_message; ?>
							</td>
						</tr>
						<?php
						if ( $class_name_part === $fix_actions[0] ) {
							$fix_actions = explode( ',', $fix_actions[1] );
							$fix_actions = array_combine( $fix_actions, $fix_actions );
							$fix_actions = $current_test->get_required_fix_action_template_parts( $fix_actions );

							if ( $fix_actions ) { ?>
								<tr class="test-fix-action">
									<td colspan="5">
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<h3><?php echo _n( 'This action requires your attention', 'These actions require your attention', count( $fix_actions ), 'secupress' ); ?></h3>
											<?php
											echo implode( '', $fix_actions );
											submit_button( __( 'Fix it!', 'secupress' ) );
											$current_test->for_current_site( $is_subsite )->get_fix_action_fields( array_keys( $fix_actions ) );
											?>
										</form>
									</td>
								</tr>
								<?php
							}

							$fix_actions = array( 0 => false );
						}
						?>
						<tr id="details-<?php echo $class_name_part; ?>" class="details hide-if-js">
							<td colspan="5">
								<?php _e( 'Scan Details: ', 'secupress' ); ?>
								<span class="details-content"><?php echo wp_kses( $current_test::$more, $allowed_tags ); ?></span>
							</td>
						</tr>
						<tr id="details-fix-<?php echo $class_name_part; ?>" class="details hide-if-js">
							<td colspan="5">
								<?php _e( 'Fix Details: ', 'secupress' ); ?>
								<span class="details-content"><?php echo wp_kses( $current_test::$more_fix, $allowed_tags ); ?></span>
							</td>
						</tr>
						<?php
					}
					?>
					</tbody>
				</table>

			</div>
			<?php
		} // foreach prio
		?>
	</div>
	<?php
}


/**
 * Get a scan or fix status, formatted with icon and human readable text.
 *
 * @since 1.0
 *
 * @param (string) $status The status code.
 *
 * @return (string) Formatted status.
 */
function secupress_status( $status ) {
	$template = '<span class="dashicons dashicons-shield-alt secupress-dashicon" aria-hidden="true"></span> %s';

	switch ( $status ) :
		case 'bad':
			return wp_sprintf( $template, __( 'Bad', 'secupress' ) );
		case 'good':
			return wp_sprintf( $template, __( 'Good', 'secupress' ) );
		case 'warning':
			return wp_sprintf( $template, __( 'Warning', 'secupress' ) );
		case 'cantfix':
			return '&#160;';
		default:
			return wp_sprintf( $template, __( 'Not scanned yet', 'secupress' ) );
	endswitch;
}


/**
 * Print a box with title.
 *
 * @since 1.0
 *
 * @param (array) $args An array containing the box title, content and id.
 */
function secupress_sidebox( $args ) {
	$args = wp_parse_args( $args, array(
		'id'      => '',
		'title'   => 'Missing',
		'content' => 'Missing',
	) );

	echo '<div class="secupress-postbox postbox" id="' . $args['id'] . '">';
		echo '<h3 class="hndle"><span><b>' . $args['title'] . '</b></span></h3>';
		echo'<div class="inside">' . $args['content'] . '</div>';
	echo "</div>\n";
}
