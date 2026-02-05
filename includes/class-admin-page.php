<?php
/**
 * Admin Page Class
 * Handles the admin interface for the plugin
 */

class D8Austin_Admin_Page {
    
    /**
     * Render admin page
     */
    public static function render_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Get import history
        $import_history = self::get_import_history();
        
        // Get available brands
        $plugin = D8Austin_Importer_Plugin::get_instance();
        $brands = $plugin->get_product_brands();
        
        ?>
        <div class="wrap d8austin-importer-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="d8austin-importer-container">
                
                <!-- Single Product Import -->
                <div class="d8austin-card">
                    <h2><?php _e('Import Single Product', 'd8austin-importer'); ?></h2>
                    <p><?php _e('Enter a product URL from d8austin.com to import it to your WooCommerce store.', 'd8austin-importer'); ?></p>
                    
                    <form id="single-import-form" class="d8austin-form">
                        <div class="form-group">
                            <label for="product-url"><?php _e('Product URL', 'd8austin-importer'); ?></label>
                            <input 
                                type="url" 
                                id="product-url" 
                                name="product_url" 
                                class="regular-text" 
                                placeholder="https://www.d8austin.com/product-page/product-name"
                                required
                            >
                        </div>
                        
                        <?php if (!empty($brands)) : ?>
                        <div class="form-group">
                            <label for="single-brand-select"><?php _e('Brand (Optional)', 'd8austin-importer'); ?></label>
                            <select id="single-brand-select" name="brand_id" class="regular-text">
                                <option value="0"><?php _e('-- No Brand --', 'd8austin-importer'); ?></option>
                                <?php foreach ($brands as $brand) : ?>
                                    <option value="<?php echo esc_attr($brand['id']); ?>">
                                        <?php echo esc_html($brand['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e('Select a brand to assign to this product (optional).', 'd8austin-importer'); ?>
                            </p>
                        </div>
                        <?php else : ?>
                        <div class="notice notice-info inline" style="margin: 15px 0;">
                            <p>
                                <strong><?php _e('Note:', 'd8austin-importer'); ?></strong>
                                <?php 
                                printf(
                                    __('No brands found. You can create brands in %s.', 'd8austin-importer'),
                                    '<a href="' . admin_url('edit-tags.php?taxonomy=product_brand&post_type=product') . '" target="_blank">' . __('Product Brands', 'd8austin-importer') . '</a>'
                                );
                                ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Import Product', 'd8austin-importer'); ?>
                        </button>
                        
                        <div id="single-import-status" class="import-status"></div>
                    </form>
                </div>
                
                <!-- Multiple Products Import -->
                <div class="d8austin-card">
                    <h2><?php _e('Import Multiple Products', 'd8austin-importer'); ?></h2>
                    <p><?php _e('Enter multiple product URLs (one per line) to import them in batch.', 'd8austin-importer'); ?></p>
                    
                    <form id="multiple-import-form" class="d8austin-form">
                        <div class="form-group">
                            <label for="product-urls"><?php _e('Product URLs (one per line)', 'd8austin-importer'); ?></label>
                            <textarea 
                                id="product-urls" 
                                name="product_urls" 
                                rows="8" 
                                class="large-text" 
                                placeholder="https://www.d8austin.com/product-page/product-1
https://www.d8austin.com/product-page/product-2
https://www.d8austin.com/product-page/product-3"
                                required
                            ></textarea>
                            <p class="description">
                                <?php _e('Paste one product URL per line. The import will process each URL sequentially.', 'd8austin-importer'); ?>
                            </p>
                        </div>
                        
                        <?php if (!empty($brands)) : ?>
                        <div class="form-group">
                            <label for="multiple-brand-select"><?php _e('Brand (Optional)', 'd8austin-importer'); ?></label>
                            <select id="multiple-brand-select" name="brand_id" class="regular-text">
                                <option value="0"><?php _e('-- No Brand --', 'd8austin-importer'); ?></option>
                                <?php foreach ($brands as $brand) : ?>
                                    <option value="<?php echo esc_attr($brand['id']); ?>">
                                        <?php echo esc_html($brand['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e('Select a brand to assign to all imported products (optional).', 'd8austin-importer'); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Import All Products', 'd8austin-importer'); ?>
                        </button>
                        
                        <div id="multiple-import-progress" class="import-progress" style="display: none;">
                            <div class="progress-bar">
                                <div class="progress-fill"></div>
                            </div>
                            <p class="progress-text">
                                <span class="current">0</span> / <span class="total">0</span> products imported
                            </p>
                        </div>
                        
                        <div id="multiple-import-status" class="import-status"></div>
                    </form>
                </div>
                
                <!-- Import History -->
                <div class="d8austin-card">
                    <h2><?php _e('Recent Imports', 'd8austin-importer'); ?></h2>
                    
                    <?php if (!empty($import_history)) : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Product Name', 'd8austin-importer'); ?></th>
                                    <th><?php _e('Brand', 'd8austin-importer'); ?></th>
                                    <th><?php _e('Source URL', 'd8austin-importer'); ?></th>
                                    <th><?php _e('Imported Date', 'd8austin-importer'); ?></th>
                                    <th><?php _e('Actions', 'd8austin-importer'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($import_history as $product) : ?>
                                    <tr>
                                        <td>
                                            <strong>
                                                <a href="<?php echo esc_url(get_edit_post_link($product->ID)); ?>">
                                                    <?php echo esc_html($product->post_title); ?>
                                                </a>
                                            </strong>
                                        </td>
                                        <td>
                                            <?php 
                                            $brand_terms = self::get_product_brand_terms($product->ID);
                                            if (!empty($brand_terms)) {
                                                echo esc_html($brand_terms[0]->name);
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $source_url = get_post_meta($product->ID, '_d8austin_source_url', true);
                                            if ($source_url) :
                                            ?>
                                                <a href="<?php echo esc_url($source_url); ?>" target="_blank">
                                                    <?php echo esc_html(self::truncate_url($source_url, 50)); ?>
                                                    <span class="dashicons dashicons-external"></span>
                                                </a>
                                            <?php else : ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $imported_date = get_post_meta($product->ID, '_d8austin_imported_date', true);
                                            if ($imported_date) {
                                                echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($imported_date)));
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo esc_url(get_edit_post_link($product->ID)); ?>" class="button button-small">
                                                <?php _e('Edit', 'd8austin-importer'); ?>
                                            </a>
                                            <a href="<?php echo esc_url(get_permalink($product->ID)); ?>" class="button button-small" target="_blank">
                                                <?php _e('View', 'd8austin-importer'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="no-items"><?php _e('No products imported yet.', 'd8austin-importer'); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Instructions -->
                <div class="d8austin-card">
                    <h2><?php _e('How to Use', 'd8austin-importer'); ?></h2>
                    <ol class="instructions-list">
                        <li>
                            <strong><?php _e('Find a product on d8austin.com', 'd8austin-importer'); ?></strong>
                            <p><?php _e('Navigate to the product page you want to import.', 'd8austin-importer'); ?></p>
                        </li>
                        <li>
                            <strong><?php _e('Copy the product URL', 'd8austin-importer'); ?></strong>
                            <p><?php _e('Copy the full URL from your browser address bar.', 'd8austin-importer'); ?></p>
                        </li>
                        <li>
                            <strong><?php _e('Select a brand (optional)', 'd8austin-importer'); ?></strong>
                            <p><?php _e('Choose a brand to assign to the product, or leave it unassigned.', 'd8austin-importer'); ?></p>
                        </li>
                        <li>
                            <strong><?php _e('Paste and import', 'd8austin-importer'); ?></strong>
                            <p><?php _e('Paste the URL into the form above and click "Import Product".', 'd8austin-importer'); ?></p>
                        </li>
                        <li>
                            <strong><?php _e('Review and publish', 'd8austin-importer'); ?></strong>
                            <p><?php _e('Imported products are saved as drafts. Review and publish when ready.', 'd8austin-importer'); ?></p>
                        </li>
                    </ol>
                    
                    <div class="notice notice-info inline">
                        <p>
                            <strong><?php _e('Note:', 'd8austin-importer'); ?></strong>
                            <?php _e('This plugin imports product information including title, price, description, and images. All imported products are created as drafts for your review before publishing.', 'd8austin-importer'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get import history
     */
    private static function get_import_history($limit = 20) {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $limit,
            'meta_query' => array(
                array(
                    'key' => '_d8austin_imported_date',
                    'compare' => 'EXISTS'
                )
            ),
            'orderby' => 'meta_value',
            'order' => 'DESC',
            'meta_key' => '_d8austin_imported_date'
        );
        
        $query = new WP_Query($args);
        return $query->posts;
    }
    
    /**
     * Get product brand terms
     */
    private static function get_product_brand_terms($product_id) {
        // Check for common brand taxonomies
        $brand_taxonomies = array(
            'product_brand',
            'pwb-brand',
            'yith_product_brand',
            'product-brand',
        );
        
        foreach ($brand_taxonomies as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                $terms = wp_get_post_terms($product_id, $taxonomy);
                if (!is_wp_error($terms) && !empty($terms)) {
                    return $terms;
                }
            }
        }
        
        return array();
    }
    
    /**
     * Truncate URL for display
     */
    private static function truncate_url($url, $length = 50) {
        if (strlen($url) <= $length) {
            return $url;
        }
        
        $url = str_replace('https://', '', $url);
        $url = str_replace('http://', '', $url);
        
        if (strlen($url) > $length) {
            return substr($url, 0, $length - 3) . '...';
        }
        
        return $url;
    }
}