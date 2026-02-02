<?php
/**
 * Product Importer Class
 * Handles importing scraped data into WooCommerce
 */

class D8Austin_Product_Importer {
    
    /**
     * Import product into WooCommerce
     */
    public function import_product($product_data) {
        if (empty($product_data['title'])) {
            return new WP_Error('invalid_data', 'Product title is required');
        }
        
        // Check if product already exists by SKU
        if (!empty($product_data['sku'])) {
            $existing_id = wc_get_product_id_by_sku($product_data['sku']);
            if ($existing_id) {
                return $this->update_product($existing_id, $product_data);
            }
        }
        
        return $this->create_product($product_data);
    }
    
    /**
     * Create new product
     */
    private function create_product($product_data) {
        $product = new WC_Product_Simple();
        
        // Set basic data
        $product->set_name($product_data['title']);
        $product->set_status('draft'); // Create as draft for review
        
        if (!empty($product_data['sku'])) {
            $product->set_sku($product_data['sku']);
        }
        
        if (!empty($product_data['price'])) {
            $product->set_regular_price($product_data['price']);
            $product->set_price($product_data['price']);
        }
        
        if (!empty($product_data['description'])) {
            $product->set_description($product_data['description']);
        }
        
        if (!empty($product_data['short_description'])) {
            $product->set_short_description($product_data['short_description']);
        }
        
        // Save product
        $product_id = $product->save();
        
        if (!$product_id) {
            return new WP_Error('create_failed', 'Failed to create product');
        }
        
        // Import images
        if (!empty($product_data['images'])) {
            $this->import_images($product_id, $product_data['images']);
        }
        
        // Save source URL as meta
        if (!empty($product_data['source_url'])) {
            update_post_meta($product_id, '_d8austin_source_url', $product_data['source_url']);
            update_post_meta($product_id, '_d8austin_imported_date', current_time('mysql'));
        }
        
        return $product_id;
    }
    
    /**
     * Update existing product
     */
    private function update_product($product_id, $product_data) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return new WP_Error('product_not_found', 'Product not found');
        }
        
        // Update basic data
        $product->set_name($product_data['title']);
        
        if (!empty($product_data['price'])) {
            $product->set_regular_price($product_data['price']);
            $product->set_price($product_data['price']);
        }
        
        if (!empty($product_data['description'])) {
            $product->set_description($product_data['description']);
        }
        
        if (!empty($product_data['short_description'])) {
            $product->set_short_description($product_data['short_description']);
        }
        
        $product->save();
        
        // Update images
        if (!empty($product_data['images'])) {
            $this->import_images($product_id, $product_data['images']);
        }
        
        // Update meta
        update_post_meta($product_id, '_d8austin_source_url', $product_data['source_url']);
        update_post_meta($product_id, '_d8austin_updated_date', current_time('mysql'));
        
        return $product_id;
    }
    
    /**
     * Import product images
     */
    private function import_images($product_id, $images) {
        if (empty($images)) {
            return;
        }
        
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $image_ids = array();
        
        foreach ($images as $index => $image_data) {
            $image_url = is_array($image_data) ? $image_data['url'] : $image_data;
            $alt_text = is_array($image_data) ? $image_data['alt'] : '';
            
            $attachment_id = $this->upload_image($image_url, $product_id, $alt_text);
            
            if ($attachment_id && !is_wp_error($attachment_id)) {
                $image_ids[] = $attachment_id;
            }
        }
        
        if (!empty($image_ids)) {
            $product = wc_get_product($product_id);
            
            // Set first image as featured
            $product->set_image_id($image_ids[0]);
            
            // Set remaining images as gallery
            if (count($image_ids) > 1) {
                $product->set_gallery_image_ids(array_slice($image_ids, 1));
            }
            
            $product->save();
        }
    }
    
    /**
     * Upload single image
     */
    private function upload_image($image_url, $product_id, $alt_text = '') {
        // Check if image already exists
        $existing_id = $this->get_existing_image_id($image_url);
        if ($existing_id) {
            return $existing_id;
        }
        
        // Download image
        $tmp_file = download_url($image_url);
        
        if (is_wp_error($tmp_file)) {
            error_log('D8Austin Importer: Failed to download image - ' . $tmp_file->get_error_message());
            return false;
        }
        
        // Get filename
        $filename = basename(parse_url($image_url, PHP_URL_PATH));
        
        // Prepare file array
        $file_array = array(
            'name' => $filename,
            'tmp_name' => $tmp_file
        );
        
        // Upload file
        $attachment_id = media_handle_sideload($file_array, $product_id);
        
        // Clean up tmp file
        if (file_exists($tmp_file)) {
            @unlink($tmp_file);
        }
        
        if (is_wp_error($attachment_id)) {
            error_log('D8Austin Importer: Failed to upload image - ' . $attachment_id->get_error_message());
            return false;
        }
        
        // Set alt text
        if (!empty($alt_text)) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
        }
        
        // Store source URL
        update_post_meta($attachment_id, '_d8austin_source_url', $image_url);
        
        return $attachment_id;
    }
    
    /**
     * Check if image already exists by source URL
     */
    private function get_existing_image_id($image_url) {
        global $wpdb;
        
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = '_d8austin_source_url' 
            AND meta_value = %s 
            LIMIT 1",
            $image_url
        ));
        
        return $attachment_id ? intval($attachment_id) : false;
    }
    
    /**
     * Import multiple products
     */
    public function import_multiple($products_data) {
        $results = array();
        
        foreach ($products_data as $product_data) {
            $result = $this->import_product($product_data);
            $results[] = array(
                'product_data' => $product_data,
                'result' => $result
            );
        }
        
        return $results;
    }
}