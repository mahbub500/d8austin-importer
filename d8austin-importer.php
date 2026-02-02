<?php
/**
 * Plugin Name: D8Austin Product Importer
 * Plugin URI: https://yoursite.com
 * Description: Import products from D8Austin.com to your WooCommerce store
 * Version: 1.1.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * License: GPL v2 or later
 * Text Domain: d8austin-importer
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('D8AUSTIN_IMPORTER_VERSION', '1.1.0');
define('D8AUSTIN_IMPORTER_FILE', __FILE__);
define('D8AUSTIN_IMPORTER_PATH', plugin_dir_path(__FILE__));
define('D8AUSTIN_IMPORTER_URL', plugin_dir_url(__FILE__));

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'd8austin_importer_wc_missing_notice');
    return;
}

function d8austin_importer_wc_missing_notice() {
    ?>
    <div class="error">
        <p><?php _e('D8Austin Product Importer requires WooCommerce to be installed and active.', 'd8austin-importer'); ?></p>
    </div>
    <?php
}

// Include required files
require_once D8AUSTIN_IMPORTER_PATH . 'includes/class-product-scraper.php';
require_once D8AUSTIN_IMPORTER_PATH . 'includes/class-product-importer.php';
require_once D8AUSTIN_IMPORTER_PATH . 'includes/class-admin-page.php';

// Initialize the plugin
class D8Austin_Importer_Plugin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_d8austin_import_product', array($this, 'ajax_import_product'));
        add_action('wp_ajax_d8austin_import_multiple', array($this, 'ajax_import_multiple'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('D8Austin Importer', 'd8austin-importer'),
            __('D8Austin Importer', 'd8austin-importer'),
            'manage_woocommerce',
            'd8austin-importer',
            array('D8Austin_Admin_Page', 'render_admin_page'),
            'dashicons-download',
            56
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_d8austin-importer' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'd8austin-importer-admin',
            D8AUSTIN_IMPORTER_URL . 'assets/css/admin.css',
            array(),
            D8AUSTIN_IMPORTER_VERSION
        );
        
        wp_enqueue_script(
            'd8austin-importer-admin',
            D8AUSTIN_IMPORTER_URL . 'assets/js/admin.js',
            array('jquery'),
            D8AUSTIN_IMPORTER_VERSION,
            true
        );
        
        wp_localize_script('d8austin-importer-admin', 'd8austinImporter', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('d8austin_importer_nonce'),
            'strings' => array(
                'importing' => __('Importing...', 'd8austin-importer'),
                'success' => __('Product imported successfully!', 'd8austin-importer'),
                'error' => __('Import failed. Please check the URL and try again.', 'd8austin-importer'),
                'invalid_url' => __('Please enter a valid product URL.', 'd8austin-importer'),
            )
        ));
    }
    
    public function ajax_import_product() {
        check_ajax_referer('d8austin_importer_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $product_url = isset($_POST['product_url']) ? esc_url_raw($_POST['product_url']) : '';
        
        if (empty($product_url)) {
            wp_send_json_error(array('message' => 'Product URL is required'));
        }
        
        try {
            // CORRECTED: Use scraper to scrape, then importer to import
            $scraper = new D8Austin_Product_Scraper_Improved();
            $product_data = $scraper->scrape_product($product_url);
            
            if (!$product_data) {
                wp_send_json_error(array('message' => 'Failed to scrape product data'));
            }
            
            // Now use importer to import the scraped data
            $importer = new D8Austin_Product_Importer_Improved();
            $product_id = $importer->import_product($product_data);
            
            if (is_wp_error($product_id)) {
                wp_send_json_error(array('message' => $product_id->get_error_message()));
            }
            
            wp_send_json_success(array(
                'message' => 'Product imported successfully',
                'product_id' => $product_id,
                'edit_url' => admin_url('post.php?post=' . $product_id . '&action=edit')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function ajax_import_multiple() {
        check_ajax_referer('d8austin_importer_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $product_urls = isset($_POST['product_urls']) ? $_POST['product_urls'] : array();
        
        if (empty($product_urls) || !is_array($product_urls)) {
            wp_send_json_error(array('message' => 'No product URLs provided'));
        }
        
        $results = array(
            'success' => array(),
            'failed' => array()
        );
        
        // CORRECTED: Create both scraper and importer instances
        $scraper = new D8Austin_Product_Scraper_Improved();
        $importer = new D8Austin_Product_Importer_Improved();
        
        foreach ($product_urls as $url) {
            $url = esc_url_raw(trim($url));
            if (empty($url)) {
                continue;
            }
            
            try {
                // First scrape the product
                $product_data = $scraper->scrape_product($url);
                
                if ($product_data) {
                    // Then import it
                    $product_id = $importer->import_product($product_data);
                    
                    if (!is_wp_error($product_id)) {
                        $results['success'][] = array(
                            'url' => $url,
                            'product_id' => $product_id,
                            'title' => $product_data['title']
                        );
                    } else {
                        $results['failed'][] = array(
                            'url' => $url,
                            'error' => $product_id->get_error_message()
                        );
                    }
                } else {
                    $results['failed'][] = array(
                        'url' => $url,
                        'error' => 'Failed to scrape product data'
                    );
                }
            } catch (Exception $e) {
                $results['failed'][] = array(
                    'url' => $url,
                    'error' => $e->getMessage()
                );
            }
        }
        
        wp_send_json_success($results);
    }
}

// Initialize the plugin
function d8austin_importer_init() {
    D8Austin_Importer_Plugin::get_instance();
}
add_action('plugins_loaded', 'd8austin_importer_init');

// Activation hook
register_activation_hook(__FILE__, 'd8austin_importer_activate');
function d8austin_importer_activate() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('This plugin requires WooCommerce to be installed and active.', 'd8austin-importer'));
    }
}