<?php
/**
 * External product add to cart
 *
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Change the product url
global $product;

// Check if it has pros ASIN
$asin = get_post_meta($product->id, '_pros_ASIN', true);
if($asin) {
    $productUrl = site_url() . '?product=' . $product->id;
} else {
    $productUrl = esc_url( $product_url );
}
?>

<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

    <p class="cart">
        <a target="_blank" href="<?php echo esc_url( $productUrl ); ?>" rel="nofollow" class="single_add_to_cart_button button alt"><?php echo $button_text; ?></a>
    </p>

<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>