<?php
/**
 * Plugin Name: Vital Stock
 * Plugin URI: https://www.vitalseeds.com/
 * Description: Custom stock management for Vital Seeds - handles large packet stock thresholds and wholesale coupon auto-apply
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

// Load wholesale coupon functionality
require_once __DIR__ . '/includes/wholesale_coupon.php';
