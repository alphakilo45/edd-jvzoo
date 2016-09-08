<?php
/**
 * Plugin Name: Easy Digital Downloads - JVZoo
 * Plugin URI: https://caffeinepressmedia.com/downloads/easy-digital-downloads-jvzoo/
 * Description: Adds JVZoo integration to Easy Digital Downloads to allow the automatic sending of a digital download link to a customer that makes a purchase through JVZoo
 * Version: 1.5.0
 * Author: Adam Kreiss
 * Author URI: https://caffeinepressmedia.com
 *
 * @package         CPM\WarriorPlus
 * @author          Adam Kreiss
 * @copyright       Copyright (c) 2016
*/

class EDD_JVZoo {

    const DEBUG = false;

    //////////////////
    // Constants
    //////////////////
    const DOMAIN = 'edd-jvzoo';

    const OPTIONSGRP = 'edd-jvzoo-options';
    const OPTIONSNAME = 'edd-jvzoo';
    const OPTIONS_AMOUNT_CENTS = 0;

    // The query parameters in the JVZoo IPN we need to be able to look at
    const QPARAM_CCUSTNAME = 'ccustname';
    const QPARAM_CCUSTEMAIL = 'ccustemail';
    const QPARAM_CTRANSACTION = 'ctransaction';
    const QPARAM_CTRANSAMOUNT = 'ctransamount';
    const QPARAM_CTRANSRECEIPT = 'ctransreceipt';
    const QPARAM_JVZOO = 'jvzooipn';
    const QPARAM_EDDID = 'eddid';
    const QPARAM_JVZOOLOG = 'jvzoolog';

    const QVALUE_SALE = 'SALE';
    const QVALUE_JVZOO = 'ipn';

    /*
     * Constructor for class.  Performs setup / integration with WordPress
     */
    public function __construct() {
        if ( ! defined( 'EDD_JVZOO_PLUGIN_DIR' ) ) {
            define( 'EDD_JVZOO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        }

        require_once EDD_JVZOO_PLUGIN_DIR . 'includes/class-cpm-license-handler.php';
        include_once( EDD_JVZOO_PLUGIN_DIR . 'includes/settings.php' );
        include_once( EDD_JVZOO_PLUGIN_DIR . 'includes/metabox.php' );

        // Setup hooks
        add_action( 'template_redirect', array( $this, 'checkForIPNRequest' ) );
        add_filter( 'query_vars', array( $this, 'addJVZooQueryVariables' ) );

        // Instantiate the licensing / updater. Must be placed in the main plugin file
        if( class_exists( 'CPM_License' ) ) {
            new CPM_License( __FILE__, 'Easy Digital Downloads - JVZoo', '1.5.1', 'Caffeine Press Media');
        }
    }

    //////////////////
    // Public methods
    //////////////////

    /*
     * Add the query parameters we need to pay attention to to the params
     * Wordpress will retrieve
     */
    public function addJVZooQueryVariables( $public_query_vars ) {
        $public_query_vars[] = self::QPARAM_CCUSTEMAIL;
        $public_query_vars[] = self::QPARAM_CCUSTNAME;
        $public_query_vars[] = self::QPARAM_CTRANSACTION;
        $public_query_vars[] = self::QPARAM_JVZOO;
        $public_query_vars[] = self::QPARAM_EDDID;
        $public_query_vars[] = self::QPARAM_JVZOOLOG;

        return $public_query_vars;
    }

    /*
     * Determines if the incoming request is a JVZoo IPN request we want to look at
     */
    public function checkForIPNRequest() {
        // If we find the appropriate JVZoo parameter in the query then we have a match
        // and want to redirect to our custom listener
        if ( get_query_var( self::QPARAM_JVZOO ) == self::QVALUE_JVZOO ) {
            $this->processJVZooRequest();
        }
    }

    //////////////////
    // Private methods
    //////////////////

    /*
     * Function that can be used to verify that the IPN request received is a
     * valid JVZoo request
     **/
    public function jvzipnVerification() {
        //  Get the JVZoo key
        global $edd_options;
        $edd_jvzoo_secret_key = isset( $edd_options['edd_jvzoo_secret_key'] ) ? trim( $edd_options['edd_jvzoo_secret_key'] ) : '';

        $pop       = "";
        $ipnFields = array();
        foreach ( $_POST AS $key => $value ) {
            if ( $key == "cverify" ) {
                continue;
            }
            $ipnFields[] = $key;
        }
        sort( $ipnFields );
        foreach ( $ipnFields as $field ) {
            // if Magic Quotes are enabled $_POST[$field] will need to be
            // un-escaped before being appended to $pop
            $pop = $pop . $_POST[ $field ] . "|";
        }
        $pop          = $pop . $edd_jvzoo_secret_key;
        $shaValue     = sha1( mb_convert_encoding( $pop, "UTF-8" ) );
        $calcedVerify = strtoupper( substr( $shaValue, 0, 8 ) );

        $this->debug( $calcedVerify );

        return $calcedVerify == $_POST["cverify"];
    }

    /*
     * Process a JVZoo response - this includes validating the the notification
     * as well as actually completing the manual purchase
     */
    private function processJVZooRequest() {
        global $edd_options;

        // Trash any slashes in the WordPress POST array
        $_POST = stripslashes_deep( $_POST );

        if ( $this->jvzipnVerification() ) {
            $this->debug( 'Processing...' );
            $transactionType = ( $_POST[ self::QPARAM_CTRANSACTION ] );
            $tranID          = trim( $_POST[ self::QPARAM_CTRANSRECEIPT ] );
            if ( $transactionType == 'SALE' ) {
                // Populate the EDD payment object
                $productID = get_query_var( self::QPARAM_EDDID );
                $price     = ( $_POST[ self::QPARAM_CTRANSAMOUNT ] );
                $name      = $_POST[ self::QPARAM_CCUSTNAME ];
                $email     = $_POST[ self::QPARAM_CCUSTEMAIL ];

                $edd_jvzoo_trans_calc = isset( $edd_options['edd_jvzoo_trans_amount_format'] ) ? trim( $edd_options['edd_jvzoo_trans_amount_format'] ) : '';
                if ( $edd_jvzoo_trans_calc == self::OPTIONS_AMOUNT_CENTS ) {
                    $price = $price / 100;
                }
                // Attempt to find a user to match this transaction to
                $user = get_user_by( 'email', $email );

                // If the option to create a new user is selected then check for an existing user and if there isn't one,
                // create a new one
                $edd_jvzoo_create_new_user = isset( $edd_options['edd_jvzoo_create_new_user'] ) ? $edd_options['edd_jvzoo_create_new_user'] : false;
                if ( ! $user && $edd_jvzoo_create_new_user ) {
                    if ( null == username_exists( $email ) ) {

                        // Generate the password and create the user
                        $password    = wp_generate_password( 12, false );
                        $username    = $email;
                        $new_user_id = wp_create_user( $username, $password, $email );

                        // Set the nickname
                        wp_update_user(
                            array(
                                'ID'         => $new_user_id,
                                'nickname'   => $name,
                                'first_name' => $name,
                                'last_name'  => ''
                            )
                        );

                        // Set the role
                        $user = new WP_User( $new_user_id );

                        // Email the user
                        $this->mailToNewUser( $name, $email, $username, $password );
                    }
                }

                // Set the user info on the purchase (potentially with the user we just created
                $user_info = array(
                    'email'      => $email,
                    'first_name' => $name,
                    'last_name'  => '',
                    'id'         => $user != null ? $user->ID : '',
                    'discount'   => null
                );

                // If variable pricing is being used on the product then match on the package number as well for tracking purposes
                $item_options = array();
                if ( isset( $_GET['edd_pn'] ) ) {
                    $edd_package_number = absint( $_GET['edd_pn'] );
                    $price_id           = $edd_package_number - 1;
                    $item_options       = array( array( 'price_id' => $price_id ) );
                }

                $cart_details[] = array(
                    'id'          => $productID,
                    'name'        => get_the_title( $productID ),
                    'item_number' => array(
                        'id'      => $productID,
                        'options' => $item_options,
                    ),
                    'price'       => $price,
                    'quantity'    => 1,
                    'tax'         => 0,
                    'in_bundle'   => 0
                );

                $payment_data = array(
                    'price'        => $price,
                    'user_email'   => $email,
	                'date'         => date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ),
                    'purchase_key' => strtolower( md5( uniqid() ) ),
                    'currency'     => edd_get_currency(),
                    'user_info'    => $user_info,
                    'cart_details' => $cart_details,
                    'status'       => 'pending',
                    'downloads'    => array(
                        'download' => array(
                            'id' => $productID
                        )
                    )
                );

                // Record the pending payment
                $this->debug( 'Inserting payment' );
                $payment = edd_insert_payment( $payment_data );
                $this->debug( 'Payment ID: ' . $payment );
                if ( $payment ) {
                    update_post_meta( $payment, '_edd_jvzoo_tranid', $tranID );

                    if ( get_query_var( self::QPARAM_JVZOOLOG ) == 1 ) {
                        edd_insert_payment_note( $payment, 'JVZoo POST URL: ' . print_r( $_POST, true ) );
                    }

                    edd_insert_payment_note( $payment, 'JVZoo Transaction ID: ' . $tranID );
                    edd_set_payment_transaction_id( $payment, $tranID );


                    // Before we publish the post make sure JVZoo didn't already notify us of this payment
                    $args     = array(
                        'post_type'  => 'edd_payment',
                        'meta_query' => array(
                            array(
                                'key'     => '_edd_jvzoo_tranid',
                                'value'   => $tranID,
                                'compare' => '='
                            )
                        )
                    );
                    $payments = get_posts( $args );

                    // If we found any then make sure it's a valid payment and delete this one
                    $foundPost = false;
                    foreach ( $payments as $payment_post ) {
                        if ( $payment_post->ID != $payment && $payment_post->post_status = 'publish' ) {
                            $foundPost = true;
                        }
                    }
                    if ( $foundPost ) {
                        // Delete this purchase because it would be a duplicate
                        $this->debug( 'Duplicate payment received' );
                        edd_delete_purchase( $payment );
                    } else {
                        edd_update_payment_status( $payment, 'publish' );
                    }

                    // Empty the shopping cart
                    edd_empty_cart();
                }
            } else if ( $transactionType == 'RFND' || $transactionType == 'CGBK'
                        || $transactionType == 'INSF'
            ) {

                // Find the correct payment history post based on the JVZoo transaction receipt ID
                if ( $tranID == null ) {
                    return;
                } else {
                    // Find any posts that match that (expecting only one)
                    $args     = array(
                        'post_type'  => 'edd_payment',
                        'meta_query' => array(
                            array(
                                'key'     => '_edd_jvzoo_tranid',
                                'value'   => $tranID,
                                'compare' => '='
                            )
                        )
                    );
                    $payments = get_posts( $args );

                    // We shouldn't have multiple payments for a single JVZoo purchase but if we do - refund them all
                    foreach ( $payments as $payment_post ) {
                        $paymentID = $payment_post->ID;

                        edd_insert_payment_note( $paymentID, sprintf( __( 'JVZoo Payment #%s Refunded', 'edd' ), $tranID ) );
                        edd_update_payment_status( $paymentID, 'refunded' );
                    }
                }
            }
        }

        // Stop any further processing as we've handled everything
        exit;
    }

    /**
     * Send the 'new user' message to a new customer
     *
     * @global object $edd_options
     *
     * @param string $name
     * @param string $email
     * @param string $username
     * @param string $password
     */
    public function mailToNewUser( $name, $email, $username, $password )#new function added by oneTarek
    {
        global $edd_options;

        // Setup the email message
        $from_email     = isset( $edd_options['from_email'] ) ? $edd_options['from_email'] : get_option( 'admin_email' );
        $from_name      = isset( $edd_options['from_name'] ) ? $edd_options['from_name'] : get_bloginfo( 'name' );
        $message        = $edd_options['edd_jvzoo_new_user_email_message'];
        $message_footer = edd_get_email_body_footer();
        $message_header = edd_get_email_body_header();
        $subject        = trim( $edd_options['edd_jvzoo_new_user_email_subject'] );

        // Replace any tokens in the email
        $message = str_replace( '{name}', $name, $message );
        $message = str_replace( '{username}', $username, $message );
        $message = str_replace( '{password}', $password, $message );
        $message = str_replace( '{email}', $email, $message );
        $message = wpautop( $message );

        // Concatenate the message parts together
        $message = $message_header . $message . $message_footer;

        // Send the message
        $headers = "From: " . stripslashes_deep( html_entity_decode( $from_name, ENT_COMPAT, 'UTF-8' ) ) . " <$from_email>\r\n";
        $headers .= "Reply-To: " . $from_email . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=utf-8\r\n";
        wp_mail( $email, $subject, $message, $headers );

    }

    /*
     * Utility debug method that logs to Wordpress logs
     */
    public function debug( $log ) {
        if ( true === WP_DEBUG && true === self::DEBUG ) {
            if ( is_array( $log ) || is_object( $log ) ) {
                error_log( print_r( $log, true ) );
            } else {
                error_log( $log );
            }
        }
    }
}

/**
 * The main function responsible for returning the one true EDD_JVZoo
 * instance to functions everywhere
 *
 * @since       1.0.0
 * @return      \EDD_JVZoo The one true EDD_JVZoo
 */
function EDD_JVZoo_load() {
    if( ! class_exists( 'Easy_Digital_Downloads' ) ) {
        if( ! class_exists( 'EDD_Extension_Activation' ) ) {
            require_once __DIR__ . '/includes/class-extension-activation.php';
        }

        $activation = new EDD_Extension_Activation( plugin_dir_path( __FILE__ ), basename( __FILE__ ) );
        $activation->run();

        return null;
    } else {
        return new EDD_JVZoo();
    }
}
add_action( 'plugins_loaded', 'EDD_JVZoo_load' );
