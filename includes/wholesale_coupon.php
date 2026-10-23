<?php
/**
 * Wholesale Coupon Auto-Apply
 *
 * Automatically applies and manages wholesale coupons for users with wholesale roles.
 * Prevents removal and customizes minimum spend messages.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wholesale tiers configuration
 * Higher discount tiers should be listed first (checked in order)
 */
const VS_WHOLESALE_TIERS = [
	'wholesale_33' => [
		'coupon'   => 'wholesale-33',
		'discount' => 33,
	],
	'wholesale_25' => [
		'coupon'   => 'wholesale',
		'discount' => 25,
	],
];

/**
 * Get the wholesale tier for the current user
 *
 * @return array|false Associative array with 'role', 'coupon', 'discount' keys, or false if not wholesale
 */
function vs_get_wholesale_tier_for_user() {
	if ( ! function_exists( 'wc_current_user_has_role' ) || ! is_user_logged_in() ) {
		return false;
	}

	foreach ( VS_WHOLESALE_TIERS as $role => $tier ) {
		if ( wc_current_user_has_role( $role ) ) {
			return array_merge( array( 'role' => $role ), $tier );
		}
	}

	return false;
}

/**
 * Get all wholesale coupon codes
 *
 * @return array List of coupon codes
 */
function vs_get_wholesale_coupon_codes() {
	return array_column( VS_WHOLESALE_TIERS, 'coupon' );
}

/**
 * Automatically apply wholesale coupon for wholesale users
 *
 * Applies the appropriate wholesale coupon when wholesale users view their cart or checkout.
 */
function vs_auto_apply_wholesale_coupon() {
	$tier = vs_get_wholesale_tier_for_user();

	if ( ! $tier ) {
		return;
	}

	$applied_coupons = WC()->cart->get_applied_coupons();

	if ( ! in_array( $tier['coupon'], $applied_coupons, true ) ) {
		WC()->cart->apply_coupon( $tier['coupon'] );
	}
}
add_action( 'woocommerce_before_cart', 'vs_auto_apply_wholesale_coupon' );
add_action( 'woocommerce_before_checkout_form', 'vs_auto_apply_wholesale_coupon' );

/**
 * Prevent wholesale users from manually removing their wholesale coupon
 *
 * Re-applies the coupon and shows a notice explaining it is automatic.
 *
 * @param string $coupon_code The coupon code being removed
 */
function vs_prevent_wholesale_coupon_removal( $coupon_code ) {
	$tier = vs_get_wholesale_tier_for_user();

	if ( ! $tier || $coupon_code !== $tier['coupon'] ) {
		return;
	}

	WC()->cart->apply_coupon( $coupon_code );
	wc_add_notice( __( 'The wholesale discount is automatically applied to your account.', 'vital-stock' ), 'notice' );
}
add_action( 'woocommerce_removed_coupon', 'vs_prevent_wholesale_coupon_removal' );

/**
 * Reword the checkout coupon prompt for wholesale users
 *
 * The wholesale coupon is auto-applied, so the standard "Have a coupon?" prompt
 * is reworded to make clear that any further coupon is an additional one. The
 * link keeps the showcoupon class so WooCommerce's checkout.js still toggles
 * the coupon form.
 *
 * @param string $message The original prompt HTML
 * @return string Modified prompt HTML
 */
function vs_wholesale_checkout_coupon_message( $message ) {
	if ( ! vs_get_wholesale_tier_for_user() ) {
		return $message;
	}

	return '<a href="#" role="button" aria-label="' . esc_attr__( 'Enter your coupon code', 'vital-stock' ) . '" aria-controls="woocommerce-checkout-form-coupon" aria-expanded="false" class="showcoupon">'
		. esc_html__( 'Add another coupon', 'vital-stock' ) . '</a>';
}
add_filter( 'woocommerce_checkout_coupon_message', 'vs_wholesale_checkout_coupon_message' );

/**
 * Customize the minimum spend error message for wholesale coupon
 *
 * Replaces the standard WooCommerce minimum spend error with a friendlier message
 * that shows how much more needs to be spent to activate the discount.
 * Only customizes the message if the user is actually eligible to use the coupon.
 *
 * Changes "The minimum spend for coupon 'wholesale' is £50" to:
 * "Wholesale coupon applied - spend £15.00 more for discount"
 *
 * @param string $error_message The original error message
 * @param int $error_code The error code
 * @param WC_Coupon $coupon The coupon object
 * @return string Modified error message
 */
function vs_customize_wholesale_minimum_spend_message($error_message, $error_code, $coupon)
{
	// Only customize for wholesale coupons and minimum spend errors
	if (!in_array($coupon->get_code(), vs_get_wholesale_coupon_codes()) || $error_code !== WC_Coupon::E_WC_COUPON_MIN_SPEND_LIMIT_NOT_MET) {
		return $error_message;
	}

	// Check if user can actually use the wholesale coupon
	if (!is_user_logged_in()) {
		return $error_message;
	}

	$minimum_spend = $coupon->get_minimum_amount();
	$cart_total = WC()->cart->get_displayed_subtotal();

	// Calculate how much more is needed
	$remaining = $minimum_spend - $cart_total;

	if ($remaining > 0) {
		return sprintf(
			__('Wholesale coupon applied - spend %s more for discount', 'vital-stock'),
			wc_price($remaining)
		);
	}

	return $error_message;
}
add_filter('woocommerce_coupon_error', 'vs_customize_wholesale_minimum_spend_message', 10, 3);
