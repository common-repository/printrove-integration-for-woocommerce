<?php
global $wpdb;


//View Unsynched Orders as Table
function ptfwc_pt_view_orders() {

  if(isset($_GET['p_id'])){
    $select_p_id = sanitize_text_field($_GET['p_id']);
    ptfwc_send_order_to_pt($select_p_id);
    header('Location:'.admin_url().'admin.php?page=ptfwc_pt_view_orders');
    echo '<h3>Order Number '.$select_p_id.' has been pushed to Printrove</h3>';
  }
	global $wpdb;
	$table_name = $wpdb->prefix . 'pt_orders';
	$query = $wpdb->prepare("SELECT * from $table_name WHERE order_sync_status = 'order_unsynched'", RID);
	// require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	$orders = $wpdb->get_results($query);

	if($orders){
    $i = 0;
    foreach($orders as $order){
      $order_id = $order->order_number;
      $order = new WC_Order( $order_id );
      $order_status = $order->status;
      if($order_status == 'processing'){
        $i = $i + 1;
        break;
      }
    }
		echo ('
      <style>
        #customers {
        font-family: "Trebuchet MS", Arial, Helvetica, sans-serif;
        border-collapse: collapse;
        width: 80%;
        margin: auto;
        }

        #customers td, #customers th {
        border: 1px solid #ddd;
        padding: 8px;
        }

        #customers tr:nth-child(even){background-color: #f2f2f2;}

        #customers tr:hover {background-color: #ddd;}

        #customers th {
        padding-top: 12px;
        padding-bottom: 12px;
        text-align: left;
        background-color: #504CAF;
        color: white;
        }
        #customers button {
          color: white;
          background-color: #504CAF;
        }

        #customers button:hover {background-color: #302d69;}

        #customers a.button {
            -webkit-appearance: button;
            -moz-appearance: button;
            appearance: button;

            text-decoration: none;
            color: initial;
        }

        </style>');


if($i != 0){
        echo ('<h3>Push Orders to Printrove</h3>
          <table id="customers">
          <tr>
            <th>Order Number</th>
            <th>WooCommerce Order Status</th>
            <th>Actions</th>
          </tr>');
    foreach ($orders as $order){
      $order_id = $order->order_number;
      $order = new WC_Order( $order_id );
      $order_status = $order->status;
      if($order_status == 'processing'){
        echo '<tr>';
        echo '<td><a class="btn btn-primary" href = "'.admin_url().'post.php?post='.$order_id.'&action=edit">'.$order_id.'</a></td>';
        echo '<td>'.$order_status.'</td>';
        echo '<td><a href = "'.admin_url().'admin.php?page=ptfwc_pt_view_orders&p_id='.$order->order_number.'" class = "button">Push to Printrove</a></td>';
        echo '</tr>';
      }
    }
echo '</table>';
} else {
    echo "<h3>No Orders to be Pushed to Printrove</h3>";
  }
} else {
	echo "<h3>No Orders to be Pushed to Printrove</h3>";
  }
}

// Create the database tables for pt_orders and pt_api_key and set api_key as unset
function ptfwc_pt_create_database_on_install() {
  global $wpdb;
	$table_name = $wpdb->prefix . 'pt_orders';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		order_number varchar(255) NULL,
		order_sync_status varchar(255) NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

  $table_name = $wpdb->prefix . 'pt_api_key';

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		api_key varchar(255) NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

  global $wpdb;
  $table_name = $wpdb->prefix . 'pt_api_key';
  $wpdb->insert(
    $table_name,
    array(
      'api_key' => "unset",
    )
  );

}
//End Table Creation



//Send Order to printrove
 function ptfwc_send_order_to_pt( $order_id ){
     // get order object and order details
     $order = new WC_Order( $order_id );
     $landmark = get_post_meta( $order->get_id(), '_billing_address_3', true );
     $email = $order->billing_email;
     $phone = $order->billing_phone;
     $phone = str_replace(' ', '', $phone);
     $phone = substr($phone, -10);
     $shipping_type = $order->get_shipping_method();
     $shipping_cost = $order->get_total_shipping();
     $order_note = $order->get_customer_note();
     $order_total_retail_price = $order->get_total();
     $mode_of_payment = $order->get_payment_method();
     if($mode_of_payment == 'cod'){
       $shipping_mode = 'cod';
     } else {
       $shipping_mode = 'std_shipping';
     }



     // set the address fields
     $user_id = $order->user_id;
     $address_fields = array('country',
         'title',
         'first_name',
         'last_name',
         'company',
         'address_1',
         'address_2',
         'city',
         'state',
         'postcode');

     $address = array();
     if(is_array($address_fields)){
         foreach($address_fields as $field){
           $var = 'billing_'.$field;
           $address['billing_'.$field] = $order->$var;
           $var = 'shipping_'.$field;
           $address['shipping_'.$field] = $order->$var;
             // $address['billing_'.$field] = get_user_meta( $user_id, 'billing_'.$field, true );
             // $address['shipping_'.$field] = get_user_meta( $user_id, 'shipping_'.$field, true );
         }
     }

     // get product details
     $items = $order->get_items();
     $item_name = array();
     $item_qty = array();
     $item_price = array();
     $item_sku = array();

     foreach( $items as $key => $item){
         $item_name[] = $item['name'];
         $item_qty[] = $item['qty'];
         $item_price[] = $item['line_total'];
         $product = $order->get_product_from_item( $item );
 				$i = 1;
 				while($i <= $item['qty']){
 					$item_sku[] = array('sku' => $product->get_sku());
          $item_price[] = array('item_price' => $product->get_price());
 					$i++;
 				}
     }
         // setup the data which has to be sent
         $api_returns = ptfwc_check_pt_api_key();
         foreach ($api_returns as $api){
         $api_from_table = $api->api_key;
       }
     $data = array(
                  'api_key' => $api_from_table,
                  'order_total' => $order_total_retail_price,
                  'email' => $email,
                  'mobile' => $phone,
                  'customer_name' => $address['billing_first_name'].' '.$address['billing_last_name'],
                  'customer_address_first_line' => $address['billing_address_1'],
                  'customer_address_second_line' => $address['billing_address_2'],
                  'landmark' => $landmark,
                  'pincode' => $address['billing_postcode'],
                  'order_comments' => '',
                  'order_provider' => 'wp',
                  'order_shipping_type' => $shipping_mode,
                  'external_order_id' => $order_id,
                  'order_produtcs' => $item_sku,
             );

         $target_url = "https://datacenter.printrove.com/place_order";
     
        $response = wp_remote_post ($target_url,array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(),
            'body' => $data,
            'cookies' => array()
            ));
        
         // the handle response
         if (is_wp_error( $response )) {
            ptfwc_pt_convert_order_to_unsynched($order_id);
         } else {
            ptfwc_pt_convert_order_to_synched($order_id);
         }
  }


//Function to save order number and sync status to database
function ptfwc_pt_save_order_to_database($oid){
  global $wpdb;
  $table_name = $wpdb->prefix . 'pt_orders';

  $query = $wpdb->prepare("SELECT * from $table_name", RID);
	// require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	$orders = $wpdb->get_results($query);
  $check = 0;
  foreach($orders as $order){
    $order_id_in_table = $order->order_number;
    if($oid == $order_id_in_table){
      $check = 1;
    }
  }

  if($check == 1){
  }
  else {
  $wpdb->insert(
    $table_name,
    array(
      'order_number' => $oid,
      'order_sync_status' => "order_unsynched",
      )
    );
  }
}




function ptfwc_pt_convert_order_to_unsynched($oid){
  global $wpdb;
  $table_name = $wpdb->prefix . 'pt_orders';
  $query = $wpdb->prepare( "UPDATE {$table_name} SET order_sync_status = 'order_unsynched' WHERE order_number = $oid", RID );
  $wpdb->get_results($query);
}

function ptfwc_pt_convert_order_to_synched($oid){
  global $wpdb;
  $table_name = $wpdb->prefix . 'pt_orders';
  $query = $wpdb->prepare( "UPDATE {$table_name} SET order_sync_status = 'order_synched' WHERE order_number = $oid", RID );
  $wpdb->get_results($query);
}

function ptfwc_save_pt_api_key($a_key){
  global $wpdb;
  $table_name = $wpdb->prefix . 'pt_api_key';
  $query = $wpdb->prepare( "UPDATE {$table_name} SET api_key = '$a_key' WHERE id = '1'", RID );
  $wpdb->get_results($query);
}

function ptfwc_check_pt_api_key(){
  global $wpdb;
	$table_name = $wpdb->prefix . 'pt_api_key';
	$query = $wpdb->prepare("SELECT * from $table_name WHERE id = '1'", RID);
	// require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	$api_keys = $wpdb->get_results($query);
  return $api_keys;
}



function ptfwc_pt_settings(){

  if (isset($_POST['submit'])) {
  		$pt_api_key = sanitize_text_field($_POST['pt_api_key']);
      ptfwc_save_pt_api_key($pt_api_key);
  }
echo ('
	<h3>Enter API Key</h3>

	<style>
input[type=text], select {
    width: 50%;
    padding: 12px 20px;
    margin: 8px 0;
    display: inline-block;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
}

input[type=submit] {
    width: 20%;
    background-color: #4CAF50;
    color: white;
    padding: 14px 20px;
    margin: 8px 0;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

input[type=submit]:hover {
    background-color: #45a049;
}
</style>');

  $api_returns = ptfwc_check_pt_api_key();

  foreach ($api_returns as $api){
    $api_from_table = $api->api_key;
    if($api_from_table == 'unset'){
      echo('<form action="" method = "post">
        <input type="text" id="pt_api_key" name="pt_api_key" placeholder="Paste Printrove API key here" >
        <br>
        <input type="submit" name="submit" value="Save">
      </form>');
    } else {
    echo('<form action="" method = "post">
      <input type="text" id="pt_api_key" name="pt_api_key" value='.$api_from_table.' >
      <br>
      <input type="submit" name="submit" value="Update">
    </form>');
  }
  }
  echo '<br>
  <h3>To view your Printrove API key: </h3>
  <ol>
  <li>Visit <a href = "https://merchants.printrove.com" target="_blank">merchants.printrove.com</a> and login with your credentials</li>
  <li>Click on Profile and then API Keys.</li>
  <li>Click on the Generate button to generate a new API Key</li>
  <li>Copy and paste the key in the text box above.</li>
  </ol>';

}

function ptfwc_my_update_notice() {
  $api_returns = ptfwc_check_pt_api_key();

  foreach ($api_returns as $api){
    $api_from_table = $api->api_key;
    if($api_from_table == 'unset'){
      global $pagenow;
      if ( $pagenow != 'admin.php' ){
      echo '<div class="notice notice-warning is-dismissible">
          <p>Printrove API Key has not been filled. <a href = "'.admin_url().'admin.php?page=ptfwc_pt_settings">Click here</a> to update the API Key to sync orders with Printrove!</p>
      </div>';
    }
    }
}}

// Delete table when deactivate
function ptfwc_my_plugin_remove_database() {
     global $wpdb;
     $table_name1 = $wpdb->prefix . 'pt_api_key';
     $table_name2 = $wpdb->prefix . 'pt_orders';
     $sql = "DROP TABLE IF EXISTS $table_name1,$table_name2;";
     $wpdb->query($sql);
   }



//Checkout Fields
function ptfwc_pt_add_landmark( $fields ) {
        $fields['billing']['billing_address_3'] = array(
           'label'     => __('Address Line 3 (Max 30 Chars)', 'woocommerce'),
       'placeholder'   => _x('Landmark', 'placeholder', 'woocommerce'),
       'required'  => false,
       'class'     => array('form-row-wide'),
       'maxlength' => 30,
       'priority' => 60,
       'clear'     => true
        );

        return $fields;
   }


//Validating the Chcekout Fields
function ptfwc_pt_validate_checkout_fields( $fields ) {
$fields['billing_postcode']['maxlength'] = 6;
// $fields['billing_phone']['maxlength'] = 10;
$fields['billing_address_1']['maxlength'] = 30;
$fields['billing_address_2']['maxlength'] = 30;
return $fields;
}


function ptfwc_pt_custom_override_checkout_fields_labels( $address_fields ) {
     $address_fields['address_2']['required'] = true;
     $address_fields['address_1']['label'] = "Address Line 1 (Max 30 Chars)";
     $address_fields['address_2']['label'] = "Address Line 2 (Max 30 Chars)";
     $address_fields['address_1']['placeholder'] = "Address Line 1";
     $address_fields['address_2']['placeholder'] = "Address Line 2";
     return $address_fields;
}


function ptfwc_pt_display_landmark_in_admin__order_view($order){
    echo '<p><strong>'.__('Landmark').':</strong> ' . get_post_meta( $order->get_id(), '_billing_address_3', true ) . '</p>';
}
