<?php
/**
 * HPOS (High-Performance Order Storage) compatibility helper.
 * Provides methods that work with both the legacy wp_posts-based storage
 * and the new custom orders tables introduced in WooCommerce 8.2+.
 */

namespace rnwcinv\utilities;

class HPOSHelper
{
    /**
     * Check if WooCommerce HPOS (Custom Order Tables) is enabled.
     *
     * @return bool
     */
    public static function is_hpos_enabled()
    {
        return class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')
            && method_exists('\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled')
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    /**
     * Get order meta in an HPOS-compatible way.
     * Uses WC_Order CRUD which abstracts the storage backend.
     * Falls back to get_post_meta if the order cannot be loaded.
     *
     * @param int    $order_id
     * @param string $meta_key
     * @param bool   $single
     * @return mixed
     */
    public static function get_order_meta($order_id, $meta_key, $single = true)
    {
        $order = wc_get_order($order_id);
        if ($order) {
            return $order->get_meta($meta_key, $single);
        }
        // Fallback for non-order post types or invalid IDs
        return get_post_meta($order_id, $meta_key, $single);
    }

    /**
     * Update order meta in an HPOS-compatible way.
     * Uses WC_Order CRUD which abstracts the storage backend.
     * Falls back to update_post_meta if the order cannot be loaded.
     *
     * @param int    $order_id
     * @param string $meta_key
     * @param mixed  $meta_value
     * @return bool|int
     */
    public static function update_order_meta($order_id, $meta_key, $meta_value)
    {
        $order = wc_get_order($order_id);
        if ($order) {
            $order->update_meta_data($meta_key, $meta_value);
            $order->save();
            return true;
        }
        // Fallback
        return update_post_meta($order_id, $meta_key, $meta_value);
    }

    /**
     * Get the admin edit link for an order.
     * Returns the correct URL for both HPOS and legacy screens.
     *
     * @param int $order_id
     * @return string
     */
    public static function get_order_edit_link($order_id)
    {
        if (self::is_hpos_enabled()) {
            return admin_url('admin.php?page=wc-orders&action=edit&id=' . absint($order_id));
        }
        return get_edit_post_link($order_id);
    }

    /**
     * Get the orders table name.
     * Returns wc_orders when HPOS is active, wp_posts otherwise.
     *
     * @return string
     */
    public static function get_orders_table()
    {
        global $wpdb;
        if (self::is_hpos_enabled()) {
            return $wpdb->prefix . 'wc_orders';
        }
        return $wpdb->posts;
    }

    /**
     * Get the order meta table name.
     * Returns wc_orders_meta when HPOS is active, wp_postmeta otherwise.
     *
     * @return string
     */
    public static function get_orders_meta_table()
    {
        global $wpdb;
        if (self::is_hpos_enabled()) {
            return $wpdb->prefix . 'wc_orders_meta';
        }
        return $wpdb->postmeta;
    }

    /**
     * Get the column name used to reference the order ID in the meta table.
     * Returns 'order_id' for HPOS, 'post_id' for legacy.
     *
     * @return string
     */
    public static function get_meta_order_id_column()
    {
        return self::is_hpos_enabled() ? 'order_id' : 'post_id';
    }

    /**
     * Get the column name used to reference the order ID in the orders table.
     * Returns 'id' for HPOS, 'ID' for legacy wp_posts.
     *
     * @return string
     */
    public static function get_orders_id_column()
    {
        return self::is_hpos_enabled() ? 'id' : 'ID';
    }
}
