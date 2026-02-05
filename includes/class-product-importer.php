<?php
/**
 * Improved Product Importer Class
 * Handles importing both simple and variable products with variations into WooCommerce
 */

class D8Austin_Product_Importer_Improved {
    
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
     * Create new product (simple or variable)
     */
    private function create_product($product_data) {
        $is_variable = $product_data['product_type'] === 'variable' && !empty($product_data['variations']);
        
        if ($is_variable) {
            return $this->create_variable_product($product_data);
        } else {
            return $this->create_simple_product($product_data);
        }
    }
    
    /**
     * Create simple product
     */
    private function create_simple_product($product_data) {
        $product = new WC_Product_Simple();
        
        // Set basic data
        $product->set_name($product_data['title']);
        $product->set_status('draft'); // Create as draft for review
        
        if (!empty($product_data['sku'])) {
            $product->set_sku($product_data['sku']);
        }
        
        if (!empty($product_data['regular_price'])) {
            $product->set_regular_price($product_data['regular_price']);
        }
        
        if (!empty($product_data['sale_price'])) {
            $product->set_sale_price($product_data['sale_price']);
            $product->set_price($product_data['sale_price']);
        } elseif (!empty($product_data['price'])) {
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
        
        // Assign brand if provided
        if (!empty($product_data['brand_id'])) {
            $this->assign_brand($product_id, $product_data['brand_id']);
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
     * Create variable product with variations
     */
    private function create_variable_product($product_data) {
        $product = new WC_Product_Variable();
        
        // Set basic data
        $product->set_name($product_data['title']);
        $product->set_status('draft');
        
        if (!empty($product_data['sku'])) {
            $product->set_sku($product_data['sku']);
        }
        
        if (!empty($product_data['description'])) {
            $product->set_description($product_data['description']);
        }
        
        if (!empty($product_data['short_description'])) {
            $product->set_short_description($product_data['short_description']);
        }
        
        // Save product first to get ID
        $product_id = $product->save();
        
        if (!$product_id) {
            return new WP_Error('create_failed', 'Failed to create variable product');
        }
        
        // Assign brand if provided
        if (!empty($product_data['brand_id'])) {
            $this->assign_brand($product_id, $product_data['brand_id']);
        }
        
        // Import main product images
        if (!empty($product_data['images'])) {
            $this->import_images($product_id, $product_data['images']);
        }
        
        // Create product attributes
        if (!empty($product_data['attributes'])) {
            $this->create_product_attributes($product_id, $product_data['attributes']);
        }
        
        // Create variations
        if (!empty($product_data['variations'])) {
            $this->create_variations($product_id, $product_data['variations']);
        }
        
        // Save source URL as meta
        if (!empty($product_data['source_url'])) {
            update_post_meta($product_id, '_d8austin_source_url', $product_data['source_url']);
            update_post_meta($product_id, '_d8austin_imported_date', current_time('mysql'));
        }
        
        // Sync the product (important for variable products)
        WC_Product_Variable::sync($product_id);
        
        return $product_id;
    }
    
    /**
     * Assign brand to product
     */
    private function assign_brand($product_id, $brand_id) {
        // Get the brand term
        $term = get_term($brand_id);
        
        if (is_wp_error($term) || !$term) {
            return false;
        }
        
        // Assign the brand term to the product
        wp_set_object_terms($product_id, $brand_id, $term->taxonomy, false);
        
        return true;
    }
    
    /**
     * Create product attributes for variable product
     */
    private function create_product_attributes($product_id, $attributes_data) {
        $product = wc_get_product($product_id);
        $attributes = array();
        
        foreach ($attributes_data as $attr_data) {
            $attribute = new WC_Product_Attribute();
            
            // Check if this is a global attribute or custom
            $taxonomy = wc_attribute_taxonomy_name($attr_data['slug']);
            
            // For simplicity, we'll create custom attributes
            // You could extend this to create/use global attributes
            $attribute->set_name($attr_data['name']);
            $attribute->set_options(array_column($attr_data['options'], 'name'));
            $attribute->set_visible($attr_data['visible']);
            $attribute->set_variation($attr_data['variation']);
            
            $attributes[] = $attribute;
        }
        
        $product->set_attributes($attributes);
        $product->save();
    }
    
    /**
     * Create product variations
     */
    private function create_variations($product_id, $variations_data) {
        foreach ($variations_data as $variation_data) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            
            // Set variation attributes
            $attributes = array();
            foreach ($variation_data['attributes'] as $key => $value) {
                // Remove 'attribute_' prefix if present
                $attribute_name = str_replace('attribute_', '', $key);
                $attributes[$attribute_name] = $value;
            }
            $variation->set_attributes($attributes);
            
            // Set prices
            if (!empty($variation_data['regular_price'])) {
                $variation->set_regular_price($variation_data['regular_price']);
            }
            
            if (!empty($variation_data['price'])) {
                $variation->set_sale_price($variation_data['price']);
                $variation->set_price($variation_data['price']);
            }
            
            // Set SKU
            if (!empty($variation_data['sku'])) {
                $variation->set_sku($variation_data['sku']);
            }
            
            // Set stock status
            if (!empty($variation_data['stock_status'])) {
                $variation->set_stock_status($variation_data['stock_status']);
            }
            
            // Set description if available
            if (!empty($variation_data['display_name'])) {
                $variation->set_description($variation_data['display_name']);
            }
            
            // Save variation
            $variation_id = $variation->save();
            
            // Import variation image if available
            if (!empty($variation_data['image'])) {
                $image_id = $this->upload_image($variation_data['image'], $product_id, $variation_data['image_alt']);
                if ($image_id && !is_wp_error($image_id)) {
                    $variation->set_image_id($image_id);
                    $variation->save();
                }
            }
            
            // Store original variation ID as meta
            if (!empty($variation_data['variation_id'])) {
                update_post_meta($variation_id, '_d8austin_original_variation_id', $variation_data['variation_id']);
            }
        }
    }
    
    /**
     * Update existing product
     */
    private function update_product($product_id, $product_data) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return new WP_Error('product_not_found', 'Product not found');
        }
        
        // Check if we're converting between simple and variable
        $is_variable = $product_data['product_type'] === 'variable' && !empty($product_data['variations']);
        $current_is_variable = $product->is_type('variable');
        
        if ($is_variable !== $current_is_variable) {
            // Product type has changed, delete and recreate
            wp_delete_post($product_id, true);
            return $this->create_product($product_data);
        }
        
        // Update basic data
        $product->set_name($product_data['title']);
        
        if (!empty($product_data['description'])) {
            $product->set_description($product_data['description']);
        }
        
        if (!empty($product_data['short_description'])) {
            $product->set_short_description($product_data['short_description']);
        }
        
        if ($is_variable) {
            // Update attributes
            if (!empty($product_data['attributes'])) {
                $this->create_product_attributes($product_id, $product_data['attributes']);
            }
            
            // Delete existing variations
            $existing_variations = $product->get_children();
            foreach ($existing_variations as $variation_id) {
                wp_delete_post($variation_id, true);
            }
            
            // Create new variations
            if (!empty($product_data['variations'])) {
                $this->create_variations($product_id, $product_data['variations']);
            }
            
            // Sync the product
            WC_Product_Variable::sync($product_id);
        } else {
            // Update simple product prices
            if (!empty($product_data['regular_price'])) {
                $product->set_regular_price($product_data['regular_price']);
            }
            
            if (!empty($product_data['sale_price'])) {
                $product->set_sale_price($product_data['sale_price']);
                $product->set_price($product_data['sale_price']);
            } elseif (!empty($product_data['price'])) {
                $product->set_price($product_data['price']);
            }
        }
        
        $product->save();
        
        // Update brand if provided
        if (!empty($product_data['brand_id'])) {
            $this->assign_brand($product_id, $product_data['brand_id']);
        }
        
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