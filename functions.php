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
        'page_title' 	=> 'Theme General Settings',
        'menu_title'	=> 'Theme Settings',
        'menu_slug' 	=> 'theme-general-settings',
        'capability'	=> 'edit_posts',
        'redirect'		=> true
    ));
}

/**
* WooCommerce: show all product attributes, separated by comma, on cart page
*/
function isa_woo_cart_attribute_values( $cart_item, $cart_item_key ) {
  
    $item_data = $cart_item_key['data'];
    $attributes = $item_data->get_attributes();
      
    if ( ! $attributes ) {
        return $cart_item;
    }
      
    $out = $cart_item . '<br />';
      
    $count = count( $attributes );
      
    $i = 0;
    foreach ( $attributes as $attribute ) {
   
        // skip variations
        if ( $attribute->get_variation() ) {
             continue;
        }
   
        $name = $attribute->get_name();          
        if ( $attribute->is_taxonomy() ) {
 
            $product_id = $item_data->get_id();
            $terms = wp_get_post_terms( $product_id, $name, 'all' );
               
            // get the taxonomy
            $tax = $terms[0]->taxonomy;
               
            // get the tax object
            $tax_object = get_taxonomy($tax);
               
            // get tax label
            if ( isset ( $tax_object->labels->singular_name ) ) {
                $tax_label = $tax_object->labels->singular_name;
            } elseif ( isset( $tax_object->label ) ) {
                $tax_label = $tax_object->label;
                // Trim label prefix since WC 3.0
                $label_prefix = 'Product ';
                if ( 0 === strpos( $tax_label,  $label_prefix ) ) {
                    $tax_label = substr( $tax_label, strlen( $label_prefix ) );
                }
            }
            // Uncomment to show attribute category  $out .= $tax_label . ': ';
 
            $tax_terms = array();              
            foreach ( $terms as $term ) {
                $single_term = esc_html( $term->name );
                array_push( $tax_terms, $single_term );
            }
            $out .= implode(', ', $tax_terms);
              
            if ( $count > 1 && ( $i < ($count - 1) ) ) {
                $out .= ', ';
            }
          
            $i++;
            // end for taxonomies
      
        } else {
  
            // not a taxonomy
              
            // Uncomment to show attribute category $out .= $name . ': ';
            $out .= esc_html( implode( ', ', $attribute->get_options() ) );
          
            if ( $count > 1 && ( $i < ($count - 1) ) ) {
                $out .= ', ';
            }
          
            $i++;
              
        }
    }
    echo $out;
}
add_filter( 'woocommerce_cart_item_name', isa_woo_cart_attribute_values, 10, 2 );