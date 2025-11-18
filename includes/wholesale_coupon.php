<?php
/**
 * Wholesale Coupon Auto-Apply
 *
 * Automatically applies and manages the 'wholesale' coupon for users with the BACS role.
 * Prevents removal and customizes minimum spend messages.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Automatically apply wholesale coupon for BACS users
 *
 * Applies the 'wholesale' coupon when BACS users view their cart or checkout.
 * Removes the coupon for non-BACS users if somehow applied.
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
 * If a BACS user tries to remove the wholesale coupon, it's immediately re-applied
 * and a notice is shown explaining it's automatically applied to their account.
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

/**
 * Customize the minimum spend error message for wholesale coupon
 *
 * Replaces the standard WooCommerce minimum spend error with a friendlier message
 * that shows how much more needs to be spent to activate the discount.
 *
 * Changes "The minimum spend for coupon 'wholesale' is £50" to:
 * "Wholesale coupon is applied (minimum spend £50.00) - spend £15.00 more for discount"
 *
 * @param string $error_message The original error message
 * @param int $error_code The error code
 * @param WC_Coupon $coupon The coupon object
 * @return string Modified error message
 */
function vs_customize_wholesale_minimum_spend_message($error_message, $error_code, $coupon)
{
	// Only customize for the wholesale coupon and minimum spend errors
	if ($coupon->get_code() !== 'wholesale' || $error_code !== WC_Coupon::E_WC_COUPON_MIN_SPEND_LIMIT_NOT_MET) {
		return $error_message;
	}

	$minimum_spend = $coupon->get_minimum_amount();
	$cart_total = WC()->cart->get_displayed_subtotal();

	// Calculate how much more is needed
	$remaining = $minimum_spend - $cart_total;

	if ($remaining > 0) {
		return sprintf(
			__('Wholesale coupon applied - spend %s more for discount', 'vital-stock'),
			// __('Wholesale coupon is applied (minimum spend %s) - spend %s more for discount', 'vital-stock'),
			wc_price($minimum_spend),
			wc_price($remaining)
		);
	}

	return $error_message;
}
add_filter('woocommerce_coupon_error', 'vs_customize_wholesale_minimum_spend_message', 10, 3);
