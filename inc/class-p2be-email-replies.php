<?php

class P2BE_Email_Replies extends P2_By_Email {

	private $secret_key = 'p2be_object_secret';
	private $orig_body_key = 'p2be_orig_body';

	public function __construct() {
		add_action( 'p2be_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {

		add_action( 'init', array( $this, 'action_init' ) );
	}

	public function action_init() {
		
		// Make sure there are the appropriate connection details in place
		if ( ! $this->is_enabled() )
			return;

		add_filter( 'p2be_emails_reply_to_name', array( $this, 'filter_reply_to_name' ), 11, 3 );
		add_filter( 'p2be_emails_reply_to_email', array( $this, 'filter_reply_to_email' ), 11, 3 );
	}

	public function is_enabled() {
		return apply_filters( 'p2be_email_replies_enabled', false );
	}

	/**
	 * Add a Reply-To name so the header is added
	 */
	public function filter_reply_to_name( $value, $type, $id ) {
		if ( ! empty( $value ) )
			return $value;
		else
			return get_bloginfo( 'name' );
	}

	/**
	 * Filter a secret key into the reply to email
	 */
	public function filter_reply_to_email( $value, $type, $id ) {

		$address_secret = '+';
		if ( 'post' == $type )
			$address_secret .= 'p' . (int)$id;
		else if ( 'comment' == $type )
			$address_secret .= 'c' . (int)$id;
		else if ( 'user' == $type )
			$address_secret .= 'u' . (int)$id;

		// @todo maybe hash the user_email so addresses are unique to email addresses
		$address_secret .= '-' . $this->get_object_secret( $type, $id );

		$value = str_replace( '@', $address_secret . '@', $value );
		return $value;
	}

	/**
	 * Get the secret for the object
	 */
	private function get_object_secret( $type, $id ) {

		$secret = get_metadata( $type, $id, $this->secret_key, true );
		if ( ! $secret ) {
			$secret = wp_generate_password( 8, false );
			update_metadata( $type, $id, $this->secret_key, $secret );
		}
		return $secret;
	}

	/**
	 * Parse the data from an object secret
	 */
	private function parse_object_secret( $secret ) {

		$parsed_key = array(
				'type'    => '',
				'id'      => '',
			);
		$wp_error = new WP_Error( 'invalid-secret', 'Secret key is invalid.' );

		$secret_pieces = explode( '-', $secret );
		if ( count( $secret_pieces ) != 2 )
			return $wp_error;

		if ( ! preg_match( '#(p|c|u)([\d]+)#', $secret_pieces[0], $matches ) )
			return $wp_error;

		if ( 'p' == $matches[1] )
			$parsed_key['type'] = 'post';
		else if ( 'c' == $matches[1] )
			$parsed_key['type'] = 'comment';
		else if ( 'u' == $matches[1] )
			$parsed_key['type'] = 'user';
		else
			return $wp_error;

		$secret_key = get_metadata( $parsed_key['type'], $matches[2], $this->secret_key, true );
		if ( $secret_pieces[1] === $secret_key ) {
			$parsed_key['id'] = $matches[2];
			return $parsed_key;
		} else {
			return $wp_error;
		}
	}

	/**
	 * Ingest emails in an SMTP email box
	 */
	public function ingest_emails( $connection_details ) {

		if ( ! function_exists( 'imap_open' ) )
			return new WP_Error( 'missing-requirement', 'PHP5-IMAP needs to be installed before you can ingest emails' );

		$inbox = $connection_details['inbox'];
		$archive = $connection_details['archive'];

		$this->imap_connection = imap_open( $connection_details['host'] . 'INBOX', $connection_details['username'], $connection_details['password'] );
		if ( ! $this->imap_connection )
			return new WP_Error( 'connection-error', __( 'Error connecting to mailbox', 'p2-by-email' ) );

		// Check to see if the archive mailbox exists, and create it if it doesn't
		$mailboxes = imap_getmailboxes( $this->imap_connection, $connection_details['host'], '*' );
		if ( ! wp_filter_object_list( $mailboxes, array( 'name' => $connection_details['host'] . $archive ) ) )
			imap_createmailbox( $this->imap_connection, $connection_details['host'] . $archive );

		// Make sure here are new emails to process
		$email_count = imap_num_msg( $this->imap_connection );

		if ( $email_count < 1 )
			return 'No new emails to process.';

		$emails = imap_search( $this->imap_connection, 'ALL', SE_UID );

		// Process each new email and put it in the archive mailbox when done
		$success = 0;
		foreach( array_reverse( $emails ) as $email_uid ) {
			$email = new stdClass;
			$email->headers = imap_headerinfo( $this->imap_connection, imap_msgno( $this->imap_connection, $email_uid ) );
			$email->structure = imap_fetchstructure( $this->imap_connection, $email_uid, FT_UID );
			$email->body = $this->get_body_from_connection( $this->imap_connection, $email_uid );

			// @todo Confirm this a message we want to process
			$ret = $this->process_email( $email );
			// If it was successful, move the email to the archive
			if ( $ret && ! is_wp_error( $ret ) ) {
				$this->move_email( $email_uid, $archive );
				$success++;
			} else if ( is_wp_error( $ret ) ) {

				switch( $ret->get_error_code() ) {
					// Replying email address doesn't match any known users
					case 'invalid-user':

						$email_address = $this->parse_sender_email_from_headers( $email->headers );
						if ( is_wp_error( $email_address ) )
							break;

						$subject = "We're sorry, but there was an error delivering your message";
						$message = array(
							'Hi,',
							PHP_EOL,
							'There was an error delivering your message: ' . $ret->get_error_message(),
							PHP_EOL,
							"For your convenience, here's the original:",
							PHP_EOL,
							$this->get_reply_from_email( $email )
							);

						$headers = sprintf( 'From: %s <%s>', 
							$this->get_default_from_name(), 
							$this->get_default_from_address() );
					
						wp_mail( $email_address, $subject, implode( PHP_EOL, $message ), $headers );
						$this->move_email( $email_uid, $archive );
						break;
				}
			} 
		}
		imap_close( $this->imap_connection, CL_EXPUNGE );
		
		return sprintf( __( 'Processed %d emails', 'p2-by-email' ), $success );
	}

	/**
	 * Given an email object, maybe add a reply or create a new post
	 */
	private function process_email( $email ) {

		if ( empty( $email->headers->to ) )
			return new WP_Error( 'incorrect-headers', 'Email headers are missing or incorrect.' );

		$to_address = array_shift( $email->headers->to )->mailbox;
		$key = array_pop( explode( '+', $to_address ) );

		$parsed_key = $this->parse_object_secret( $key );
		if ( is_wp_error( $parsed_key ) )
			return $parsed_key;

		$user = $this->get_user_from_email( $email );
		if ( is_wp_error( $user ) )
			return $user;

		$message = $this->get_reply_from_email( $email );
		$message = wp_filter_post_kses( $message );

		switch ( $parsed_key['type'] ) {
			case 'post':
			case 'comment':
				if ( 'post' == $parsed_key['type'] ) {
					$post_id = $parsed_key['id'];
					$comment_parent = 0;
				} else {
					$post_id = get_comment( $parsed_key['id'] )->comment_post_ID;
					$comment_parent = $parsed_key['id'];
				}

				$comment = array(
					'comment_post_ID'        => $post_id,
					'comment_author'         => $user->display_name,
					'comment_author_email'   => $user->user_email,
					'comment_author_url'     => $user->user_url,
					'comment_content'        => $message,
					'comment_parent'         => $comment_parent,
					'user_id'                => $user->ID,
				);
				$comment_id = wp_insert_comment( $comment );

				// Store the original body just in case
				update_metadata( 'comment', $comment_id, $this->orig_body_key, wp_filter_post_kses( $email->body ) );
				break;
			case 'user':

				$post_format = 'status';
				if ( ! empty( $email->headers->subject ) ) {
					$post_title = sanitize_text_field( $email->headers->subject );
					$post_format = 'standard';
				} else if ( function_exists( 'p2_title_from_content' ) ) {
					$post_title = p2_title_from_content( $message );
				} else {
					$post_title = '';
				}

				$post = array(
						'post_author'         => (int)$parsed_key['id'],
						'post_content'        => $message,
						'post_title'          => $post_title,
						'post_type'           => 'post',
						'post_status'         => 'publish',
					);
				$post_id = wp_insert_post( $post );

				set_post_format( $post_id, $post_format );

				// Store the original body just in case
				update_metadata( 'post', $post_id, $this->orig_body_key, wp_filter_post_kses( $email->body ) );
				break;
		}

		return true;
	}

	/**
	 * Move an email to a new mailbox
	 *
	 * @param int          $email_uid        UID for the email
	 * @param string       $mailbox          Where the email should end up
	 */
	private function move_email( $email_uid, $mailbox ) {
		return imap_mail_move( $this->imap_connection, imap_msgno( $this->imap_connection, $email_uid ), $mailbox );
	}

		/**
	 * Parse the sender as WordPress user from email headers
	 */
	private function get_user_from_email( $email ) {

		$email_address = $this->parse_sender_email_from_headers( $email->headers );

		$user = get_user_by( 'email', $email_address );
		if ( $user )
			return $user;
		else
			return new WP_Error( 'invalid-user', 'No user exists with this email address.' );
	}

	/**
	 * Parse the sending email address from the headers
	 */
	private function parse_sender_email_from_headers( $headers ) {

		if ( empty( $headers->from ) && empty( $headers->sender ) )
			return new WP_Error( 'invalid-sender', 'Sender from headers are empty.' );

		$sender = array_shift( $headers->from );
		if ( empty( $sender ) )
			$sender = array_shift( $headers->sender );
		if ( empty( $sender->mailbox ) || empty( $sender->host ) )
			return new WP_Error( 'invalid-sender', 'Sender from headers are missing.' );

		return sanitize_email( $sender->mailbox . '@' . $sender->host );
	}

	/**
	 * Parse the reply from the email
	 */
	private function get_reply_from_email( $email ) {
		$what_the_email = new What_The_Email( $email );
		$message = quoted_printable_decode( $what_the_email->get_reply() );
		return $message;
	}

	/**
	 * Get the email text body and/or attachments given an IMAP resource
	 */
	private function get_body_from_connection( $connection, $num, $type = 'text/plain' ) {
		// Hacky way to get the email body. We should support more MIME types in the future
		$body = imap_fetchbody( $connection, $num, 1.1, FT_UID );
		if ( empty( $body ) )
			$body = imap_fetchbody( $connection, $num, 1, FT_UID );
		return $body;
	}

}

P2_By_Email()->extend->email_replies = new P2BE_Email_Replies();