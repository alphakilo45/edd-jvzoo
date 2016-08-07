<?php

/**
 * Registers the JVZoo settings in the Settings -> Extension tab
 * *
 * @since       1.0
 * @param 	$settings array the existing plugin settings
 * @return      array
 */

function edd_jvzoo_settings( $settings ) {
	$jvzoo_settings = array(
		array(
			'id' => 'edd_jvzoo_header',
			'name' => '<strong>' . __('JVZoo', 'edd_jvzoo') . '</strong>',
			'desc' => '',
			'type' => 'header',
			'size' => 'regular'
		),
		array(
			'id' => 'edd_jvzoo_secret_key',
			'name' => __('JVZIPN Secret Key', 'edd_jvzoo'),
			'desc' => __('Enter the JVZIPN Secret Key for your JVZoo account. This value is found on the My Accounts page in JVZoo', 'edd_jvzoo'),
			'type' => 'text',
			'size' => 'regular',
		),
		array(
			'id' => 'edd_jvzoo_trans_amount_format',
			'name' => __('Transaction Amount Format', 'edd_jvzoo'),
			'desc' => __('Select the format you receive transaction amounts from JVZoo in.  This should be set to \'Cents\' by default but can be switched to \'Dollars\' if you notice the purchase price small on transactions.', 'edd_jvzoo'),
			'type' => 'select',
			'options' => array('Cents', 'Dollars')
		),
		array(
			'id' => 'edd_jvzoo_create_new_user',
			'name' => __('Create New User On Purchase', 'edd_jvzoo'),
			'desc' => __('Check if you would like a new user created when a purchase is made through JVZoo.', 'edd_sl'),
			'type' => 'checkbox'
		),
		array(
			'id' => 'edd_jvzoo_new_user_email_subject',
			'name' => __('New User Email Subject', 'edd_jvzoo'),
			'type' => 'text',
			'size' => 'large',
			'std'  => __( 'Enter email subject line', 'edd_jvzoo' )
		),
		array(
			'id' => 'edd_jvzoo_new_user_email_message',
			'name' => __('New User Email Message', 'edd_jvzoo'),
			'type' => 'textarea',
			'desc' => __('Enter the email message you\'d like your customers to receive when a new user account is created for them. Use template tags below to customize the email.', 'edd_jvzoo') . '<br/>' .
				'{name} - ' . __( 'The customer\'s name', 'edd_jvzoo' ) . '<br/>' .
				'{username} - ' . __( 'The new username for the user account', 'edd_jvzoo' ) . '<br/>' .
				'{password} - ' . __( 'The password for the user account', 'edd_jvzoo' ) . '<br/>' .
				'{email} - ' . __( 'The customer\'s email account that has been used for new user account.', 'edd_jvzoo' ) . '<br/>' .
				__( 'These emails will be sent automatically when a new user account is created in response to receiving an IPN notification from JVZoo.  Users are matched on the email address received from JVZoo.  If a user exists on your site with a matching email address then no new account will be created.', 'edd_jvzoo' ),
			'std' => __( "Hello {name},\n\n We have created a new user account for you. Log-in to your account with following information.\n\n Login URL: \n\n Username: {username} \n\n Password: {password}", "edd_jvzoo" )
		)
	);

	return array_merge( $settings, $jvzoo_settings );

}
add_filter('edd_settings_extensions', 'edd_jvzoo_settings');
