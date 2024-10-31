<?php include 'includes/functions.php'; ?>
<?php
/*
*Plugin Name: Printrove Integration for WooCommerce
*Description: Sync Orders with the Printrove Merchant Panel
*Author: Printrove
* Author URI: https://printrove.com
* Developer: Pankaj Bokdia
*Version: 2.0.0
*/

add_action( 'admin_menu', 'ptfwc_pt_add_menu_iframe' );

function ptfwc_pt_add_menu_iframe() {
	add_menu_page('Printrove for WooCommerce', 'Printrove for WooCommerce','manage_options','ptfwc_page_iframe','ptfwc_page_iframe','dashicons-admin-generic',200 );
}

function ptfwc_page_iframe(){
    echo '<iframe src="https://merchants.printrove.com" style="
    display: block;
    width: 100vw;
    height: 95vh;
    max-width: 100%;
    margin: 0;
    padding: 0;
    border: 0 none;
    box-sizing: border-box;">
    </iframe>';
}

add_filter( 'woocommerce_billing_fields' , 'ptfwc_pt_validate_checkout_fields' );
add_filter( 'woocommerce_default_address_fields' , 'ptfwc_pt_custom_override_checkout_fields_labels' );
add_filter( 'woocommerce_checkout_fields' , 'ptfwc_pt_add_landmark' );
add_action( 'woocommerce_admin_order_data_after_billing_address', 'ptfwc_pt_display_landmark_in_admin__order_view', 10, 1 );