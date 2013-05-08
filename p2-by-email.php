<?php
/*
Plugin Name: P2 By Email
Version: 1.0
Description: For those who like to interact with P2 by email.
Author: danielbachhuber, humanmade
Author URI: http://hmn.md/
Plugin URI: http://wordpress.org/extend/plugins/p2-by-email/
Text Domain: p2-by-email
Domain Path: /languages
*/

class P2_By_Email {

	private $data;

	private static $instance;

	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new P2_By_Email;
			self::$instance->setup_globals();
			self::$instance->includes();
			self::$instance->setup_actions();
		}
		return self::$instance;
	}

	private function __construct() {
		/** Prevent the class from being loaded more than once **/
	}

	public function __isset( $key ) {
		return isset( $this->data[$key] );
	}

	public function __get( $key ) {
		return isset( $this->data[$key] ) ? $this->data[$key] : null;
	}

	public function __set( $key, $value ) {
		$this->data[$key] = $value;
	}

	private function setup_globals() {

		$this->file           = __FILE__;
		$this->basename       = apply_filters( 'p2be_plugin_basenname', plugin_basename( $this->file ) );
		$this->plugin_dir     = apply_filters( 'p2be_plugin_dir_path',  plugin_dir_path( $this->file ) );
		$this->plugin_url     = apply_filters( 'p2be_plugin_dir_url',   plugin_dir_url ( $this->file ) );

		$this->extend         = new stdClass();

	}

	private function includes() {

		require_once( $this->plugin_dir . 'inc/class-p2be-emails.php' );
		require_once( $this->plugin_dir . 'inc/class-p2be-email-replies.php' );
		require_once( $this->plugin_dir . 'inc/class-p2be-settings.php' );

		require_once( $this->plugin_dir . 'inc/what-the-email/what-the-email.php' );

		if ( defined('WP_CLI') && WP_CLI )
			require_once( $this->plugin_dir . 'inc/class-p2be-wp-cli.php' );
	}

	private function setup_actions() {

		do_action_ref_array( 'p2be_after_setup_actions', array( &$this ) );
	}

	protected function get_following_post( $post_id ) {

		return wp_list_pluck( get_users(), 'user_login' );
	}

	protected function get_template( $template, $vars = array() ) {

		$template_path = dirname( __FILE__ ) . '/templates/' . $template . '.php';

		ob_start();
		if ( file_exists( $template_path ) ) {
			extract( $vars );
			include $template_path;
		}

		return wpautop( ob_get_clean() );
	}

	/**
	 * Get a default From name for this site
	 */
	protected function get_default_from_name() {
		return apply_filters( 'p2be_emails_from_name', get_bloginfo( 'name') );
	}

	/**
	 * Get a default From email address for this domain
	 */
	protected function get_default_from_address() {
		return $this->get_domain_email_address( 'noreply' );
	}

	/**
	 * Get a fake email address for this domain
	 *
	 * @param string       $mailbox         A fake mailbox
	 * @return string      $email_address   A fake email address at this domain
	 */
	protected function get_domain_email_address( $mailbox ) {
		return $mailbox . '@' . rtrim( str_replace( 'http://', '', home_url() ), '/' );
	}

}

function P2_By_Email() {
	return P2_By_Email::get_instance();
}
add_action( 'plugins_loaded', 'P2_By_Email' );