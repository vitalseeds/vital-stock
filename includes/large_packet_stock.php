<?php
/**
 * Large Packet Stock Management
 *
 * Custom stock threshold handling for large packet variations.
 * Large packets are considered out of stock at quantity 5 instead of 0.
 */

// Exit if accessed directly
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
	if ($product->get_type() != 'variation') {
		return false;
	}

	$attributes = $product->get_attributes();
	if (is_array($attributes) && isset($attributes['pa_size'])) {
		return $attributes["pa_size"] == "large";
	}
	return false;
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
