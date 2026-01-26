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
 * Customize the minimum spend error message for wholesale coupon
 *
 * Replaces "The minimum spend for coupon 'wholesale' is X" with a friendlier message
 * showing how much more needs to be spent.
 *
 * @param string    $error_message The original error message
 * @param int       $error_code    The error code
 * @param WC_Coupon $coupon        The coupon object
 * @return string Modified error message
 */
function vs_customize_wholesale_minimum_spend_message( $error_message, $error_code, $coupon ) {
	if ( $error_code !== WC_Coupon::E_WC_COUPON_MIN_SPEND_LIMIT_NOT_MET ) {
		return $error_message;
	}

	if ( ! in_array( $coupon->get_code(), vs_get_wholesale_coupon_codes(), true ) ) {
		return $error_message;
	}

	if ( ! is_user_logged_in() ) {
		return $error_message;
	}

	$minimum_spend = $coupon->get_minimum_amount();
	$cart_total    = WC()->cart->get_displayed_subtotal();
	$remaining     = $minimum_spend - $cart_total;

	if ( $remaining <= 0 ) {
		return $error_message;
	}

	return sprintf(
		/* translators: %s: amount remaining to spend */
		__( 'Wholesale coupon applied - spend %s more for discount', 'vital-stock' ),
		wc_price( $remaining )
	);
}
add_filter( 'woocommerce_coupon_error', 'vs_customize_wholesale_minimum_spend_message', 10, 3 );
