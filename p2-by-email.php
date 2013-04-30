<?php
/*
Plugin Name: P2 By Email
Version: 0.1-alpha
Description: For those who like to interact with P2 by email.
Author: danielbachhuber
Author URI: http://danielbachhuber.com/
Plugin URI: PLUGIN SITE HERE
Text Domain: p2-by-email
Domain Path: /languages
*/

/**
 * @todo:
 * - Send a HTML-ified email notification on new posts and comments
 * - Allow emails to be sent from a Gmail account, and replied to directly
 * - Create a new post by email
 */


class P2_By_Email {

	private static $instance;

	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new P2_By_Email;
			self::$instance->setup_globals();
			self::$instance->setup_actions();
		}
		return self::$instance;
	}

	private function __construct() {
		/** Prevent the class from being loaded more than once **/
	}

	private function setup_globals() {

	}

	private function setup_actions() {

		// Send emails for new posts and comments
		add_action( 'publish_post', array( self::$instance, 'queue_post_notifications' ) );

	}

	private function get_following_post( $post_id ) {

		return wp_list_pluck( get_users(), 'user_email' );
	}

	public function queue_post_notifications( $post_id ) {

		$following_emails = $this->get_following_post( $post_id );

		foreach( $following_emails as $following_email ) {
			$this->send_post_notification( $post_id, $following_email );
		}
	}

	private function get_email_headers() {

		$from_email = 'noreply@' . rtrim( str_replace( 'http://', '', home_url() ), '/' );
		$headers = sprintf( 'From: %s <%s>', get_bloginfo( 'name'), $from_email ) . PHP_EOL;
		return $headers;
	}

	/**
	 * Get the message text for an email
	 *
	 * @param object|int       $post      Post we'd like message text for
	 */
	private function get_email_message_post( $post ) {

		if ( is_int( $post ) )
			$post = get_post( $post );

		$message = apply_filters( 'the_content', $post->post_content );
		return $message;
	}

	private function send_post_notification( $post_id, $email ) {

		$subject = sprintf( '[New post] %s', apply_filters( 'the_title', get_the_title( $post_id ) ) );
		$subject = apply_filters( 'p2be_notification_subject', $subject, 'post', $post_id );

		$message = $this->get_email_message_post( $post_id );
		$message = apply_filters( 'p2be_notification_message', $message, 'post', $post_id );

		wp_mail( $email, $subject, $message, $this->get_email_headers() );
	}

}

function P2_By_Email() {
	return P2_By_Email::get_instance();
}
add_action( 'plugins_loaded', 'P2_By_Email' );