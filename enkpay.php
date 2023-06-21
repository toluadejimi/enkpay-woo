<?php

if (!defined("ABSPATH")) {
    exit();
}
/**
 * Plugin Name: Enkpay Payment Gateway
 * Author: Enkpay
 *Description: This plugin adds the secure payment gateway with Enkvave purchase guarantee to WooCommerce.
 * Version: 1.0.0
 * Author URI: https://enkwave.com/
 * Author Email: info@enkwave.com
 * Requires at least: 6.0.0
 * Requires PHP: 7.4
 * WC requires at least: 7.3
 * WC tested up to: 7.3
 */

define("WOO_GSAMA_GATEWAY_DIR", trailingslashit(plugin_dir_path(__FILE__)));

require_once WOO_GSAMA_GATEWAY_DIR . "action.php";

add_action(
    "woocommerce_before_checkout_form",
    function () {
        $sama = new WC_GSama();
        $message = $sama->before_payment_description;
        wc_print_notice($message);
    },
    1
);
