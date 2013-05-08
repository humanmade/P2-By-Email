<?php

class P2BE_Emails extends P2_By_Email {

	private $sent_post_key = 'p2be_sent_timestamp';

	public function __construct() {
		add_action( 'p2be_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {

		// Don't send notifications when importing
		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING )
			return;

		// Send emails for new posts and comments
		add_action( 'publish_post', array( $this, 'queue_post_notifications' ) );
		add_action( 'wp_insert_comment', array( $this, 'queue_comment_notifications' ) );

		// Disable the default core notification (filter ignores __return_false)
		add_filter( 'pre_option_comments_notify', '__return_zero' );

	}

	/**
	 * Queue notifications for a post
	 *
	 * @todo If there are more than X emails to send, queue some immediate wp-cron jobs
	 */
	public function queue_post_notifications( $post ) {

		$post = get_post( $post );

		// Only send one email; don't send again if update post triggers 'publish_post'
		if ( apply_filters( 'p2be_emails_sent_post', get_post_meta( $post->ID, $this->sent_post_key, true ), $post->ID ) )
			return;
		else
			update_post_meta( $post->ID, $this->sent_post_key, time() );

		$users = get_users();
		foreach( $users as $user ) {

			if ( $post->post_author == $user->ID ) {
				if ( ! apply_filters( 'p2be_emails_send_notif_to_author', false, 'post', $user ) )
					continue;
			}

			$user_options = P2_By_Email()->extend->settings->get_user_notification_options( $user->ID );
			if ( 'all' == $user_options['posts']
				|| ( 'yes' == $user_options['mentions'] && $this->is_user_mentioned( $user, $post->post_content ) ) )
					$this->send_post_notification( $post, $user );
		}
	}

	/**
	 * Queue notifications for a comment
	 */
	public function queue_comment_notifications( $comment_id ) {

		$comment = get_comment( $comment_id );

		if ( 1 != $comment->comment_approved )
			return;

		$users = get_users();
		foreach( $users as $user ) {

			if ( $comment->user_id == $user->ID ) {
				if ( ! apply_filters( 'p2be_emails_send_notif_to_author', false, 'comment', $user ) )
					continue;
			}

			$user_options = P2_By_Email()->extend->settings->get_user_notification_options( $user->ID );
			if ( 'all' == $user_options['comments']
				|| ( 'yes' == $user_options['mentions'] && $this->is_user_mentioned( $user, $comment->comment_content ) ) )

			$this->send_comment_notification( $comment_id, $user );
		}
	}

	/**
	 * Send a notification to a user about a post
	 */
	public function send_post_notification( $post, $user ) {

		$remove_texturize = remove_filter( 'the_title', 'wptexturize' );
		$subject = sprintf( '[New post] %s', apply_filters( 'the_title', get_the_title( $post->ID ) ) );
		if ( $remove_texturize )
			add_filter( 'the_title', 'wptexturize' );
		$subject = apply_filters( 'p2be_notification_subject', $subject, 'post', $post );

		$post->post_content = $this->add_user_mention( $user, $post->post_content );

		$message = $this->get_email_message_post( $post );
		$message = apply_filters( 'p2be_notification_message', $message, 'post', $post );

		$mail_args = array(
				'type'        => 'post',
				'id'          => $post->ID,
			);
		wp_mail( $user->user_email, $subject, $message, $this->get_email_headers( $mail_args ) );
	}

	/**
	 * Send a notification to a user about a comment
	 */
	public function send_comment_notification( $comment_id, $user ) {

		$comment = get_comment( $comment_id );

		$remove_texturize = remove_filter( 'the_title', 'wptexturize' );
		$subject = sprintf( '[New comment] %s', apply_filters( 'the_title', get_the_title( $comment->comment_post_ID ) ) );
		if ( $remove_texturize )
			add_filter( 'the_title', 'wptexturize' );
		$subject = apply_filters( 'p2be_notification_subject', $subject, 'comment', $comment );

		$comment->comment_content = $this->add_user_mention( $user, $comment->comment_content );

		$message = $this->get_email_message_comment( $comment );
		$message = apply_filters( 'p2be_notification_message', $message, 'comment', $comment );

		$mail_args = array(
				'type'        => 'comment',
				'id'          => $comment_id,
			);
		wp_mail( $user->user_email, $subject, $message, $this->get_email_headers( $mail_args ) );

	}

	/**
	 * Is the user mentioned in the text?
	 */
	private function is_user_mentioned( $user, $text ) {
		$text = strip_tags( strip_shortcodes( $text ) );
		return (bool)preg_match( '#@' . $user->user_login . '\b#i', $text );
	}

	/**
	 * Highlight the user mention in the text
	 */
	private function add_user_mention( $user, $text ) {
		return preg_replace( '/(\@' . $user->user_login . ')(\b)/i', '<strong>$1</strong>$2', $text, 1 );
	}

	private function get_email_headers( $args ) {

		$headers = array();

		$headers[] = sprintf( 'From: %s <%s>', 
			$this->get_default_from_name(), 
			$this->get_default_from_address() );

		$reply_to_name = apply_filters( 'p2be_emails_reply_to_name', '',  $args['type'], $args['id'] );
		$reply_to_email = apply_filters( 'p2be_emails_reply_to_email', '',  $args['type'], $args['id'] );
		if ( $reply_to_name && $reply_to_email )
			$headers[] = sprintf( 'Reply-To: %s <%s>', $reply_to_name, $reply_to_email );

		// Used for threading emails
		if ( 'post' == $args['type'] ) {
			$headers[] = sprintf( 'Message-ID: <%s>', $this->get_domain_email_address( $args['id'] . '-0' ) );
		} else {
			$post_id = get_comment( $args['id'] )->comment_post_ID;
			$headers[] = sprintf( 'Message-ID: <%s>', $this->get_domain_email_address( $post_id . '-' . $args['id'] ) );
			$headers[] = sprintf( 'In-Reply-To: <%s>', $this->get_domain_email_address( $post_id . '-0'  ) );
				
		}


		$headers[] = 'Content-type: text/html';
		return implode( PHP_EOL, $headers );
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

		$author_wrote = sprintf( '%s <a href="%s">wrote</a>', get_user_by( 'id', $post->post_author )->display_name, get_permalink( $post->ID ) );

		$vars = compact( 'post', 'author_wrote' );
		$message = $this->get_template( 'post', $vars );

		return $message;
	}

	/**
	 * Get the message text for a comment
	 */
	private function get_email_message_comment( $c ) {
		global $comment;

		if ( is_int( $c ) )
			$comment = get_comment( $c );
		else
			$comment = $c;

		$in_reply_to = '%s replied to <a href="%s">%s</a>';
		if ( $comment->comment_parent ) {
			$in_reply_to = sprintf( $in_reply_to, get_comment_author( $comment->comment_ID ), esc_url( get_comment_link( $comment->comment_parent ) ), get_comment_author( $comment->comment_parent ) );
			$quoted_text = $this->get_summary( get_comment( $comment->comment_parent )->comment_content );
		} else {
			$post = get_post( $comment->comment_post_ID );
			$in_reply_to = sprintf( $in_reply_to, get_comment_author( $comment->comment_ID ), esc_url( get_permalink( $comment->comment_post_ID ) ), get_user_by( 'id', $post->post_author )->display_name );
			$quoted_text = $this->get_summary( apply_filters( 'the_content', $post->post_content ) );
		}

		$vars = compact( 'comment', 'in_reply_to', 'quoted_text' );
		$message = $this->get_template( 'comment', $vars );

		return $message;
	}

	/**
	 * Get summary from a set of text
	 */
	private function get_summary( $text ) {

		$text = strip_tags( $text );
		return wpautop( substr( $text, 0, 195 ) );
	}

}

P2_By_Email()->extend->emails = new P2BE_Emails();