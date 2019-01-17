<?php
class DigiWalletInstall
{
    /**
     * install db when active plugin
     * - create new db
     */
    public static function install_db()
    {
        global $wpdb;
        $digiwalletTbl = $wpdb->prefix . DIGIWALLET_TABLE_NAME;
        if(!$wpdb->get_var("SHOW TABLES LIKE '$digiwalletTbl'") == $digiwalletTbl) {
            self::create_digiwallet_db();
        }
    }

    /**
     * Create woocommerce_digiwallet table
     */
    public static function create_digiwallet_db()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . DIGIWALLET_TABLE_NAME . " (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `cart_id` int(11) NOT NULL DEFAULT '0',
        `order_id` varchar(11) NOT NULL DEFAULT '0',
        `rtlo` int(11) NOT NULL,
        `paymethod` varchar(8) NOT NULL DEFAULT 'IDE',
        `transaction_id` varchar(100) NOT NULL,
        `more` varchar(255) NULL,
        `message` varchar(255) NULL,
        UNIQUE KEY id (id),
        KEY `cart_id` (`cart_id`),
        KEY `transaction_id` (`transaction_id`)
        ) " . $charset_collate . ";";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
