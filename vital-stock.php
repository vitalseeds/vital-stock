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

// Load large packet stock management
require_once __DIR__ . '/includes/large_packet_stock.php';

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
