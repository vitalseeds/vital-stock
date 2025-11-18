<?php
/**
 * Plugin Name: Vital Stock
 * Plugin URI: https://www.vitalseeds.com/
 * Description: Custom stock management for Vital Seeds - handles large packet stock thresholds and notifications
 * Version: 1.0.0
 * Author: Vital Seeds
 * Author URI: https://www.vitalseeds.com/
 * License: GPL2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Large pack minimum stock_quantity 5
 *
 * This defines the custom stock threshold for large packet variations.
 * When stock reaches this level or below, the product is considered out of stock
 * and the add to cart button is hidden.
 */

// TODO: replace constant with a WC option
define('VS_LARGE_PACKET__NO_STOCK_AMOUNT', 5);

/**
 * Check if a product is a large packet variation
 *
 * @param WC_Product $product The product to check
 * @return bool True if product is a large packet variation
 */
function vs_product_is_large_packet($product)
{
	return $product->get_type() == 'variation' && $product->get_attributes()["pa_size"] == "large";
}

/**
 * Filter stock status for large packets
 *
 * Large packets are considered in stock only if quantity is above the custom threshold (5)
 * instead of the default WooCommerce threshold (0).
 *
 * @param bool $status Current stock status
 * @param WC_Product $product The product being checked
 * @return bool Modified stock status
 */
function vs_get_large_pack_is_in_stock($status, $product)
{
	if (
		vs_product_is_large_packet($product) &&
		$product->get_stock_quantity() > VS_LARGE_PACKET__NO_STOCK_AMOUNT
	) {
		return true;
	}
	return $status;
}
add_filter('woocommerce_product_is_in_stock', 'vs_get_large_pack_is_in_stock', 10, 2);


/**
 * Prevent stock notifications for large packets above threshold
 *
 * This filter prevents both low stock and out-of-stock admin notifications from being
 * sent when stock is above the custom threshold (5). Notifications will only be sent
 * when stock reaches 5 or below, matching the stock visibility behavior.
 *
 * @param bool $default Whether to send notification (default behavior)
 * @param int $product_id The product ID
 * @return bool Whether to send the notification
 */
function vs_large_packet_stock_notification($default, $product_id)
{
	$product = wc_get_product($product_id);
	if (!vs_product_is_large_packet($product)) {
		return $default;
	}

	$stock_quantity = $product->get_stock_quantity();

	// Only send stock notifications when stock reaches the large packet threshold (5) or below
	if ($stock_quantity > VS_LARGE_PACKET__NO_STOCK_AMOUNT) {
		return false;
	}
	return $default;
}
add_filter('woocommerce_should_send_no_stock_notification', 'vs_large_packet_stock_notification', 10, 2);
add_filter('woocommerce_should_send_low_stock_notification', 'vs_large_packet_stock_notification', 10, 2);

/**
 * Auto-apply wholesale coupon for BACS users
 *
 * Automatically applies the 'wholesale' coupon to the cart for users with the BACS role.
 * The coupon is applied when the cart is loaded and removed if the user doesn't have BACS role.
 */

/**
 * Automatically apply wholesale coupon for BACS users
 */
function vs_auto_apply_wholesale_coupon()
{
	// Only run if WooCommerce is active and user is logged in
	if (!function_exists('wc_current_user_has_role') || !is_user_logged_in()) {
		return;
	}

	// Check if user has BACS role
	$is_bacs_user = wc_current_user_has_role('bacs');
	$coupon_code = 'wholesale';

	// Get applied coupons
	$applied_coupons = WC()->cart->get_applied_coupons();

	if ($is_bacs_user) {
		// Apply coupon if not already applied
		if (!in_array($coupon_code, $applied_coupons)) {
			WC()->cart->apply_coupon($coupon_code);
		}
	} else {
		// Remove coupon if user doesn't have BACS role but coupon is applied
		if (in_array($coupon_code, $applied_coupons)) {
			WC()->cart->remove_coupon($coupon_code);
		}
	}
}
add_action('woocommerce_before_cart', 'vs_auto_apply_wholesale_coupon');
add_action('woocommerce_before_checkout_form', 'vs_auto_apply_wholesale_coupon');

/**
 * Prevent BACS users from manually removing the wholesale coupon
 *
 * @param bool $removed Whether the coupon was removed
 * @param string $coupon_code The coupon code being removed
 * @return bool Modified removal status
 */
function vs_prevent_wholesale_coupon_removal($removed, $coupon_code)
{
	if ($coupon_code === 'wholesale' && wc_current_user_has_role('bacs')) {
		// Re-apply the coupon immediately
		WC()->cart->apply_coupon($coupon_code);
		wc_add_notice(__('The wholesale discount is automatically applied to your account.'), 'notice');
		return false;
	}
	return $removed;
}
add_filter('woocommerce_removed_coupon', 'vs_prevent_wholesale_coupon_removal', 10, 2);
