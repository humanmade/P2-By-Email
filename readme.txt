=== P2 By Email ===
Contributors: danielbachhuber, humanmade
Tags: gtd, productivity, workflow, p2, email notifications
Requires at least: 3.4
Tested up to: 3.6-alpha
Stable tag: 1.0

Use P2? Use email? Use both!

== Description ==

P2 By Email enables you to use P2 by email:

* Get instant notifications when posts or comments are published.
* Ensure you're always notified when your username is mentioned.
* Reply to posts or comments by email.
* Create new posts with a secret email address.

Perfect for communicating with your team while on the go.

Users can change their communication preferences from the default of all posts and comments using profile settings. A special setting can ensure they always receive an email when their username is mentioned.

Want another feature added? [Send us a pull request](https://github.com/humanmade/P2-By-Email/) and we'll consider it. Reply by email depends on a [young email reply parsing class](https://github.com/humanmade/What-The-Email) — there's the chance a comment will appear oddly, and we welcome improvements to the regex.

== Installation ==

Want to get started using the plugin? Follow these steps:

1. Download and install the plugin in your plugins directory.
1. Activate the plugin.
1. Profit!

By default, all users will receive all post and comment notifications.

Enabling posting or replying by email takes a few more steps:

1. Register a Gmail or similar email account that supports IMAP.
1. Add the code snippet below with account details to your theme's functions.php file. It tells P2 By Email that you're set up to use post or reply by email.
1. Install [wp-cli](http://wp-cli.org/) and set up a system cron job to regularly call `wp p2-by-email ingest-emails`.

`add_filter( 'p2be_email_replies_enabled', '__return_true' );
add_filter( 'p2be_emails_reply_to_email', function( $email ) {
	return 'YOURACCOUNT@gmail.com';
});
add_filter( 'p2be_imap_connection_details', function( $details ) {

	$details['host'] = '{imap.gmail.com:993/imap/ssl/novalidate-cert}';
	$details['username'] = 'YOURACCOUNT@gmail.com';
	$details['password'] = 'PASSWORD';

	return $details;
} );`

== Frequently Asked Questions ==

None yet... Feel free to ask a question in the forums!

== Changelog ==

= 1.0 (May 8, 2013) =

* Initial release. Email notifications for posts, comments, and mentions; post and reply by email with special configuration.
