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
//if( function_exists('acf_add_options_page') ) {

 //   acf_add_options_page(array(
 //       'page_title' 	=> 'Theme General Settings',
 //       'menu_title'	=> 'Theme Settings',
 //       'menu_slug' 	=> 'theme-general-settings',
 //       'capability'	=> 'edit_posts',
 //       'redirect'		=> true
 //   ));
//}

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