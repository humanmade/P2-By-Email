<?php

class P2BE_Settings extends P2_By_Email {

	private $options_key = 'p2be_settings';
	public $default_options = array(
				'posts'        => 'all',
				'comments'     => 'all',
				'mentions'     => 'yes',
			);

	public function __construct() {
		add_action( 'p2be_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {

		add_action( 'edit_user_profile', array( $this, 'user_profile_fields' ) );
		add_action( 'show_user_profile', array( $this, 'user_profile_fields' ) );

		add_action( 'personal_options_update', array( $this, 'save_user_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_profile_fields' ) );
	}

	public function user_profile_fields( $user ) {

		$user_options = $this->get_user_notification_options( $user->ID );
?>
<h3>P2 By Email</h3>
	<table class="form-table">
		<tr>
			<th><label for="p2be-posts">Posts</label></th>
			<td>
				<select id="p2be-posts" name="p2be-posts">
				<?php foreach( array( 'all' => 'Send me an email for every new post', 'none' => "Don't send me new post emails" ) as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $user_options['posts'] ); ?>><?php echo esc_attr( $label ); ?></option>
				<?php endforeach; ?>
				</select>
				<?php if ( P2_By_Email()->extend->email_replies->is_enabled() ) : ?>
				<?php $user_secret_email = apply_filters( 'p2be_emails_reply_to_email', '', 'user', $user->ID ); ?>
				<p class="description">Tip: Create new posts by emailing this secret address: <a href="<?php echo esc_url( 'mailto:' . $user_secret_email ); ?>"><?php echo esc_html( $user_secret_email ); ?></a>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><label for="p2be-comments">Comments</label></th>
			<td>
				<select id="p2be-comments" name="p2be-comments">
				<?php foreach( array( 'all' => 'Send me an email for every new comment', 'none' => "Don't send me new comment emails" ) as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $user_options['comments'] ); ?>><?php echo esc_attr( $label ); ?></option>
				<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th><label for="p2be-mentions">Mentions</label></th>
			<td>
				<select id="p2be-mentions" name="p2be-mentions">
				<?php foreach( array( 'yes' => 'Make sure I get an email if someone @mentions my username', 'no' => "Respect my post and comment notification settings" ) as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $user_options['mentions'] ); ?>><?php echo esc_attr( $label ); ?></option>
				<?php endforeach; ?>
				</select>
			</td>
		</tr>
	</table>
<?php
	}

	public function save_user_profile_fields( $user_id ) {

		if ( ! current_user_can( 'edit_user', $user_id ) )
			return;

		$user_options = $this->default_options;
		if ( isset( $_POST['p2be-posts'] ) && 'all' != $_POST['p2be-posts'] )
			$user_options['posts'] = 'none';

		if ( isset( $_POST['p2be-comments'] ) && 'all' != $_POST['p2be-comments'] )
			$user_options['comments'] = 'none';

		if ( isset( $_POST['p2be-mentions'] ) && 'yes' != $_POST['p2be-mentions'] )
			$user_options['mentions'] = 'no';

		update_user_meta( $user_id, $this->options_key, $user_options );
		return;
	}

	public function get_user_notification_options( $user_id ) {
		return array_merge( $this->default_options, (array)get_user_meta( $user_id, $this->options_key, true ) );
	}


}

P2_By_Email()->extend->settings = new P2BE_Settings();