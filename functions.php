<?php
/**
 * Astra Child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Astra Child
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define( 'CHILD_THEME_ASTRA_CHILD_VERSION', '1.0.0' );

/**
 * Enqueue styles
 */
function child_enqueue_styles() {

	wp_enqueue_style( 'astra-child-theme-css', get_stylesheet_directory_uri() . '/style.css', array('astra-theme-css'), CHILD_THEME_ASTRA_CHILD_VERSION, 'all' );

}

add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );

// 1. customize ACF path
add_filter('acf/settings/path', 'my_acf_settings_path');
function my_acf_settings_path( $path ) {
    // update path
    $path = get_stylesheet_directory() . '/inc/acf/';
    // return
    return $path;
}
// 2. customize ACF dir
add_filter('acf/settings/dir', 'my_acf_settings_dir');
function my_acf_settings_dir( $dir ) {
    // update path
    $dir = get_stylesheet_directory_uri() . '/inc/acf/';
    // return
    return $dir;
}
// 3. Hide ACF field group menu item
//add_filter('acf/settings/show_admin', '__return_false');

// 4. Include ACF
include_once( get_stylesheet_directory() . '/inc/acf/acf.php' );

// Turn on ACF Options Page
if( function_exists('acf_add_options_page') ) {

    acf_add_options_page(array(
        'page_title' 	=> 'Universal Product Settings',
        'menu_title'	=> 'Product Settings',
        'menu_slug' 	=> 'universal-product-settings',
        'capability'	=> 'edit_posts',
        'redirect'		=> true
    ));
}

// Adding the grouped product ID custom hidden field data in Cart object
add_filter( 'woocommerce_add_cart_item_data', 'save_custom_fields_data_to_cart', 20, 2 );
function save_custom_fields_data_to_cart( $cart_item_data, $product_id ) {
    if( ! empty($_REQUEST['add-to-cart']) && $product_id != $_REQUEST['add-to-cart']
    && is_numeric($_REQUEST['add-to-cart']) ){
        $group_prod = wc_get_product($_REQUEST['add-to-cart']);
        if ( ! $group_prod->is_type( 'grouped' ) )
            return $cart_item_data; // Exit

        $cart_item_data['grouped_product'] = array(
            'id' => $_REQUEST['add-to-cart'],
            'name' => $group_prod->get_name(),
            'link' => $group_prod->get_permalink(),
            'visible' => $group_prod->is_visible(),
        );

        // Below statement make sure every add to cart action as unique line item
        $cart_item_data['grouped_product']['unique_key'] = md5( microtime().rand() );
    }
    return $cart_item_data;
}


// Add the parent grouped product name to cart items names
add_filter( 'woocommerce_cart_item_name', 'custom_product_title_name', 20, 3 );
function custom_product_title_name( $cart_item_name, $cart_item, $cart_item_key ){
    // The product object from cart item
    $product = $cart_item['data'];
    $product_permalink = $product->is_visible() ? $product->get_permalink( $cart_item ) : '';

    // The parent product name and data
    if( isset( $cart_item['grouped_product'] ) ){
        $group_product = $cart_item['grouped_product'];
        $group_prod_link = $group_product['link'];

        if ( ! $group_prod_link )
            return $group_product['name'] . ' > ' . $product->get_name();
        else
            return sprintf(
                '<a href="%s">%s</a><br>%s',
                esc_url( $group_prod_link ),
                $group_product['name'],
                $product->get_name(),
            );
    }
    else
        return $cart_item_name;
}

// Save grouped product data in order item meta
add_action( 'woocommerce_checkout_create_order_line_item', 'added_grouped_order_item_meta', 20, 4 );
function added_grouped_order_item_meta( $item, $cart_item_key, $values, $order ) {
    if( isset($values['grouped_product']) ){
        $item_id = $item->get_id();
        $grouped_data = $values['grouped_product'];
        unset($grouped_data['unique_key']);
        $item->update_meta_data( '_grouped_product', $grouped_data );
    }
}

// Display grouped product linked names in order items (+ email notifications)
add_filter( 'woocommerce_order_item_name', 'custom_order_item_name', 20, 3 );
function custom_order_item_name( $item_name, $item, $is_visible ) {
    $product = $item->get_product();
    $product_id = $item->get_product_id();
    $product_permalink = $is_visible ? $product->get_permalink( $item ) : '';
    $grouped_data = wc_get_order_item_meta( $item->get_id(), '_grouped_product', true );
    if( empty($grouped_data) ){
        $item_name = $product_permalink ? sprintf(
            '<a href="%s">%s</a>',
            esc_url( $product_permalink),
            $item->get_name()
        ) : $item->get_name();
    } else {
        $item_name = $product_permalink ? sprintf(
            '<a href="%s">%s</a><br>%s',
            esc_url( $group_prod_link ),
            $group_product['name'],
            $product->get_name()
        ) : '<a href=' . $grouped_data['link'] . '> ' .$grouped_data['name'] . ' </a><br> ' . $item->get_name();
    }
    return $item_name;
}

// Display on backend order edit pages
add_action( 'woocommerce_before_order_itemmeta', 'backend_order_item_name_grouped', 20, 3 );
function backend_order_item_name_grouped( $item_id, $item, $product ){
    if( ! ( is_admin() && $item->is_type('line_item') ) ) return;

    $grouped_data = wc_get_order_item_meta( $item_id, '_grouped_product', true );
    if( empty($grouped_data) ) return;
    $product_link = admin_url( 'post.php?post=' . $grouped_data['id'] . '&action=edit' );
    $grouped_name_html = '<a href="' . esc_url( $grouped_data['link'] ) . '" class="wc-order-item-name">' . esc_html( $grouped_data['name'] ) . '</a>';
    echo '<br><div class="wc-order-item-name">
        <small><strong>'.__('Group').':</strong></small><br>
        ' . $grouped_name_html . '
    </div>';
}

/**
 * @snippet       Add First & Last Name to My Account Register Form - WooCommerce
 * @how-to        Get CustomizeWoo.com FREE
 * @author        Rodolfo Melogli
 * @compatible    WC 3.9
 * @donate $9     https://businessbloomer.com/bloomer-armada/
 */
  
///////////////////////////////
// 1. ADD FIELDS
  
add_action( 'woocommerce_register_form_start', 'bbloomer_add_name_woo_account_registration' );
  
function bbloomer_add_name_woo_account_registration() {
    ?>
  
    <p class="form-row form-row-first">
    <label for="reg_billing_first_name"><?php _e( 'First name', 'woocommerce' ); ?> <span class="required">*</span></label>
    <input type="text" class="input-text" name="billing_first_name" id="reg_billing_first_name" value="<?php if ( ! empty( $_POST['billing_first_name'] ) ) esc_attr_e( $_POST['billing_first_name'] ); ?>" />
    </p>
  
    <p class="form-row form-row-last">
    <label for="reg_billing_last_name"><?php _e( 'Last name', 'woocommerce' ); ?> <span class="required">*</span></label>
    <input type="text" class="input-text" name="billing_last_name" id="reg_billing_last_name" value="<?php if ( ! empty( $_POST['billing_last_name'] ) ) esc_attr_e( $_POST['billing_last_name'] ); ?>" />
    </p>
  
    <div class="clear"></div>
  
    <?php
}
  
///////////////////////////////
// 2. VALIDATE FIELDS
  
add_filter( 'woocommerce_registration_errors', 'bbloomer_validate_name_fields', 10, 3 );
  
function bbloomer_validate_name_fields( $errors, $username, $email ) {
    if ( isset( $_POST['billing_first_name'] ) && empty( $_POST['billing_first_name'] ) ) {
        $errors->add( 'billing_first_name_error', __( '<strong>Error</strong>: First name is required!', 'woocommerce' ) );
    }
    
    if ( isset( $_POST['billing_last_name'] ) && empty( $_POST['billing_last_name'] ) ) {
        $errors->add( 'billing_last_name_error', __( '<strong>Error</strong>: Last name is required!', 'woocommerce' ) );
    }
    return $errors;
}
  
///////////////////////////////
// 3. SAVE FIELDS
  
add_action( 'woocommerce_created_customer', 'bbloomer_save_name_fields' );
  
function bbloomer_save_name_fields( $customer_id ) {
    if ( isset( $_POST['billing_first_name'] ) ) {
        update_user_meta( $customer_id, 'billing_first_name', sanitize_text_field( $_POST['billing_first_name'] ) );
        update_user_meta( $customer_id, 'first_name', sanitize_text_field($_POST['billing_first_name']) );
    }
    if ( isset( $_POST['billing_last_name'] ) ) {
        update_user_meta( $customer_id, 'billing_last_name', sanitize_text_field( $_POST['billing_last_name'] ) );
        update_user_meta( $customer_id, 'last_name', sanitize_text_field($_POST['billing_last_name']) );
    }
  
}

remove_filter('authenticate', 'wp_authenticate_username_password', 20);

add_filter('authenticate', function($user, $email, $password){

    //Check for empty fields
    if(empty($email) || empty ($password)){        
        //create new error object and add errors to it.
        $error = new WP_Error();

        if(empty($email)){ //No email
            $error->add('empty_username', __('<strong>ERROR</strong>: Email field is empty.'));
        }
        else if(!filter_var($email, FILTER_VALIDATE_EMAIL)){ //Invalid Email
            $error->add('invalid_username', __('<strong>ERROR</strong>: Email is invalid.'));
        }

        if(empty($password)){ //No password
            $error->add('empty_password', __('<strong>ERROR</strong>: Password field is empty.'));
        }

        return $error;
    }

    //Check if user exists in WordPress database
    $user = get_user_by('email', $email);

    //bad email
    if(!$user){
        $error = new WP_Error();
        $error->add('invalid', __('<strong>ERROR</strong>: Either the email or password you entered is invalid.'));
        return $error;
    }
    else{ //check password
        if(!wp_check_password($password, $user->user_pass, $user->ID)){ //bad password
            $error = new WP_Error();
            $error->add('invalid', __('<strong>ERROR</strong>: Either the email or password you entered is invalid.'));
            return $error;
        }else{
            return $user; //passed
        }
    }
}, 20, 3);

add_filter('gettext', function($text){
    if(in_array($GLOBALS['pagenow'], array('wp-login.php'))){
        if('Username' == $text){
            return 'Email';
        }
    }
    return $text;
}, 20);

add_filter( 'gettext', 'register_text' );
add_filter( 'ngettext', 'register_text' );
function register_text( $translated ) {
    $translated = str_ireplace(
        'Username or Email Address',
        'Email Address',
        $translated
    );
    return $translated;
}

// First, change the required password strength
add_filter( 'woocommerce_min_password_strength', 'reduce_min_strength_password_requirement' );
function reduce_min_strength_password_requirement( $strength ) {
    // 3 => Strong (default) | 2 => Medium | 1 => Weak | 0 => Very Weak (anything).
    return 0; 
}

// function iconic_remove_password_strength() {
//    wp_dequeue_script( 'wc-password-strength-meter' );
// }
// add_action( 'wp_print_scripts', 'iconic_remove_password_strength', 10 );

// change the wording of the password hint.
 add_filter( 'password_hint', 'smarter_password_hint' );
 function smarter_password_hint ( $hint ) {
    $hint = 'Hint: The password should be at least 10 characters long. To make it stronger, use upper and lower case letters, numbers, and symbols like ! " ? $ % ^ & ).';
    return $hint;
}

add_filter( 'gettext', 'change_registration_usename_label', 10, 3 );
function change_registration_usename_label( $translated, $text, $domain ) {
    if( is_account_page() && ! is_wc_endpoint_url() ) {
        if( $text === 'Register' ) {
            $translated = __( 'Create Account', $domain );
        } 
    }

    return $translated;
}

if ( ! function_exists( 'woocommerce_template_loop_product_title' ) ) {

    /**
     * Show the product title in the product loop. By default this is an H2.
     */
    function woocommerce_template_loop_product_title() {
        echo '<h2 class="' . esc_attr( apply_filters( 'woocommerce_product_loop_title_classes', 'woocommerce-loop-product__title' ) ) . '">' . get_the_title() . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<span class="additional-info">';
        if (get_field('subtitle')) : echo '<span class="subtitle">'; the_field('subtitle'); echo '</span>'; endif;
        if (get_field('credits')) : echo '<span class="credits">'; the_field('credits'); echo '</span>'; endif;
        echo '</span>';
    }
}