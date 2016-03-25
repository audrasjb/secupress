<?php
defined( 'ABSPATH' ) or die( 'Cheatin&#8217; uh?' );

/**
 * Subscription scan class.
 *
 * @package SecuPress
 * @subpackage SecuPress_Scan
 * @since 1.0
 */

class SecuPress_Scan_Subscription extends SecuPress_Scan implements iSecuPress_Scan {

	const VERSION = '1.0';

	/**
	 * @var Singleton The reference to *Singleton* instance of this class
	 */
	protected static $_instance;
	public    static $prio = 'high';


	protected static function init() {
		self::$type  = 'WordPress';
		self::$title = __( 'Check if the subscription settings are set correctly.', 'secupress' );

		if ( ! is_multisite() || is_network_admin() ) {
			self::$more     = __( 'If user registrations are open, the default user role should be Subscriber. Moreover, your registration page should be protected from bots.', 'secupress' );
			self::$more_fix = sprintf(
				__( 'This will activate the option %1$s from the module %2$s.', 'secupress' ),
				'<em>' . __( 'Use a Captcha for everyone', 'secupress' ) . '</em>',
				'<a href="' . esc_url( secupress_admin_url( 'modules', 'users-login' ) ) . '#Use_a_Captcha_for_everyone">' . __( 'Users & Login', 'secupress' ) . '</a>'
			);

			if ( is_network_admin() ) {
				self::$more_fix .= '<br/>' . __( 'If the default user role is not Subscriber in some of your websites, a new page similar to this one will be created in each related site, where administrators will be asked to set the default user role to Subscriber.', 'secupress' );
			} else {
				self::$more_fix .= '<br/>' . __( 'This will also set the default user\'s role to Subscriber.', 'secupress' );
			}
		} else {
			self::$more     = __( 'If user registrations are open, the default user role should be Subscriber.', 'secupress' );
			self::$more_fix = __( 'This will set the default user\'s role to Subscriber.', 'secupress' );
		}
	}


	public static function get_messages( $message_id = null ) {
		$messages = array(
			// good
			0   => __( 'Your subscription settings are set correctly.', 'secupress' ),
			1   => __( 'A captcha module has been activated to block bot registration.', 'secupress' ),
			2   => __( 'The user role for new registrations has been set to <strong>Subscriber</strong>.', 'secupress' ),
			// warning
			100 => __( 'Unable to determine status of your homepage.', 'secupress' ),
			// bad
			200 => __( 'The default role in your installation is <strong>%s</strong> and it should be <strong>Subscriber</strong>, or registrations should be <strong>closed</strong>.', 'secupress' ),
			201 => __( 'The registration page is <strong>not protected</strong> from bots.', 'secupress' ),
			202 => _n_noop( 'The default role is not Subscriber in %s of your sites.', 'The default role is not Subscriber in %s of your sites.', 'secupress' ),
			/* translators: %s is the plugin name. */
			300 => sprintf( __( 'The default role cannot be fixed from here. A new %s menu item has been activated in the relevant site\'s administration area.', 'secupress' ), '<strong>' . SECUPRESS_PLUGIN_NAME . '</strong>' ),
		);

		if ( isset( $message_id ) ) {
			return isset( $messages[ $message_id ] ) ? $messages[ $message_id ] : __( 'Unknown message', 'secupress' );
		}

		return $messages;
	}


	public function scan() {
		global $wp_roles;

		// Subscriptions are closed.
		if ( ! secupress_users_can_register() ) {
			// good
			$this->add_message( 0 );
			return parent::scan();
		}

		// Default role
		if ( $this->is_network_admin() ) {
			$roles = get_site_option( 'secupress_default_role' );
			$blogs = array();

			foreach ( $roles as $blog_id => $role ) {
				if ( 'subscriber' !== $role ) {
					$blogs[] = $blog_id;
				}
			}

			if ( $count = count( $blogs ) ) {
				// bad
				$this->add_message( 202, array( $count, $count ) );
			}
		} else {
			$role = get_option( 'default_role' );

			if ( 'subscriber' !== $role ) {
				// bad
				$role = isset( $wp_roles->role_names[ $role ] ) ? translate_user_role( $wp_roles->role_names[ $role ] ) : __( 'None' );
				$this->add_message( 200, array( $role ) );
			}
		}

		// Bots
		$user_login = 'secupress_' . time();
		$response   = wp_remote_post( wp_registration_url(), array(
			'body' => array(
				'user_login' => $user_login,
				'user_email' => 'secupress_no_mail_SS@fakemail.' . time(),
			),
		) );

		if ( ! is_wp_error( $response ) ) {

			if ( $user_id = username_exists( $user_login ) ) {

				wp_delete_user( $user_id );

				if ( 'failed' === get_transient( 'secupress_registration_test' ) ) {
					// bad
					$this->add_message( 201 );
				}
			}

		} else {
			// warning
			$this->add_message( 100 );
		}

		delete_transient( 'secupress_registration_test' );

		// good
		$this->maybe_set_status( 0 );

		return parent::scan();
	}


	public function fix() {
		global $wp_roles;

		if ( ! secupress_users_can_register() ) {
			return parent::fix();
		}

		// Default role
		if ( $this->is_network_admin() ) {

			$roles  = get_site_option( 'secupress_default_role' );
			$is_bad = false;

			foreach ( $roles as $blog_id => $role ) {
				if ( 'subscriber' !== $role ) {
					$is_bad = true;
					$role   = isset( $wp_roles->role_names[ $role ] ) ? translate_user_role( $wp_roles->role_names[ $role ] ) : __( 'None' );
					$data   = array( $role );
					// Add a scan message for each sub-site with wrong role.
					$this->add_subsite_message( 200, $data, 'scan', $blog_id );
				} else {
					$this->set_empty_data_for_subsite( $blog_id );
				}
			}

			if ( $is_bad ) {
				// cantfix
				$this->add_fix_message( 300 );
			}

		} elseif ( 'subscriber' !== get_option( 'default_role' ) ) {
			update_option( 'default_role', 'subscriber' );
			// good
			$this->add_fix_message( 2 );
		}

		// Bots: use a captcha.
		secupress_activate_submodule( 'users-login', 'login-captcha' );

		// good
		$this->add_fix_message( 1 );

		return parent::fix();
	}
}
