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
 * - @mentions force an email to be sent to a user, if the user exists. Otherwise, bold the user's login
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

	/**
	 * Queue notifications for a post
	 *
	 * @todo If there are more than X emails to send, queue some immediate wp-cron jobs
	 */
	public function queue_post_notifications( $post_id ) {

		$following_emails = $this->get_following_post( $post_id );

		foreach( $following_emails as $following_email ) {
			$this->send_post_notification( $post_id, $following_email );
		}
	}

	public function send_post_notification( $post_id, $email ) {

		$subject = sprintf( '[New post] %s', apply_filters( 'the_title', get_the_title( $post_id ) ) );
		$subject = apply_filters( 'p2be_notification_subject', $subject, 'post', $post_id );

		$message = $this->get_email_message_post( $post_id );
		$message = apply_filters( 'p2be_notification_message', $message, 'post', $post_id );

		wp_mail( $email, $subject, $message, $this->get_email_headers() );
	}

	private function get_email_headers() {

		$from_email = 'noreply@' . rtrim( str_replace( 'http://', '', home_url() ), '/' );
		$headers = sprintf( 'From: %s <%s>', get_bloginfo( 'name'), $from_email ) . PHP_EOL;
		$headers .= 'Content-type: text/html' . PHP_EOL;
		return $headers;
	}

	/**
	 * Get the message text for an email
	 *
	 * @param object|int       $p         Post we'd like message text for
	 */
	private function get_email_message_post( $p ) {
		global $post;

		if ( is_int( $p ) )
			$post = get_post( $p );
		else
			$post = $p;

		setup_postdata( $post );

		$show_title = true;
		if ( function_exists( 'p2_excerpted_title' ) ) {
			if ( $post->post_title == p2_title_from_content( $post->post_content ) )
				$show_title = false;
		}

		$vars = compact( 'post', 'show_title' );
		$message = $this->get_template( 'post', $vars );

		return $message;
	}

	private function get_template( $template, $vars = array() ) {

		$template_path = dirname( __FILE__ ) . '/templates/' . $template . '.php';

		ob_start();
		if ( file_exists( $template_path ) ) {
			extract( $vars );
			include $template_path;
		}

		return wpautop( ob_get_clean() );
	}

}

function P2_By_Email() {
	return P2_By_Email::get_instance();
}
add_action( 'plugins_loaded', 'P2_By_Email' );