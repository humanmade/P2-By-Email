=== P2 By Email ===
Contributors: danielbachhuber, humanmade
Tags: gtd, productivity, workflow, p2
Requires at least: 3.3
Tested up to: 3.6-alpha
Stable tag: 0.0

Use P2? Use email? Use both!

== Description ==

P2 By Email enables you to use P2 By Email:

* Get instant notifications when posts or comments are published.
* Ensure you're always notified when your username is mentioned.
* Reply to posts or comments by email.
* Create new posts with a secret email address.

Perfect for communicating with your team while on the go.

Users can change their communication preferences from their profile settings.

== Installation ==

Want to get started using the plugin? Follow these steps:

1. Download and install the plugin in your plugins directory.
1. Activate the plugin.

By default, all users will receive all post and comment notifications.

Enabling reply / post by email takes a few more steps:

1. Register a Gmail or similar email account that supports IMAP.
1. Add the code snippet below with account details to your theme's functions.php file.
1. Install [wp-cli](http://wp-cli.org/) and set up a cron job to regularly call `wp p2-by-email ingest-emails`.

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

Feel free to ask a question in the forums!

== Changelog ==

= 1.0 (???. ??, ????) =

* Initial release