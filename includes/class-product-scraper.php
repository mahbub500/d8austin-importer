<?php
/**
 * Improved Product Scraper Class
 * Handles scraping product data including variations from D8Austin.com
 */

class D8Austin_Product_Scraper_Improved {
    
    private $base_domain = 'd8austin.com';
    
    /**
     * Scrape product data from URL
     */
    public function scrape_product($url) {
        if (!$this->is_valid_url($url)) {
            return false;
        }
        
        $html = $this->fetch_page($url);
        if (!$html) {
            return false;
        }
        
        return $this->parse_product_data($html, $url);
    }
    
    /**
     * Validate URL
     */
    private function is_valid_url($url) {
        if (empty($url)) {
            return false;
        }
        
        $parsed = parse_url($url);
        if (!isset($parsed['host'])) {
            return false;
        }
        
        return strpos($parsed['host'], $this->base_domain) !== false;
    }
    
    /**
     * Fetch page content
     */
    private function fetch_page($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            )
        ));
        
        if (is_wp_error($response)) {
            error_log('D8Austin Importer: Failed to fetch page - ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        return $body;
    }
    
    /**
     * Parse product data from HTML
     */
    private function parse_product_data($html, $url) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Extract variations data from the page
        $variations = $this->extract_variations($html, $xpath);
        
        $product_data = array(
            'title' => $this->extract_title($xpath),
            'price' => $this->extract_price($xpath),
            'sale_price' => $this->extract_sale_price($xpath),
            'regular_price' => $this->extract_regular_price($xpath),
            'description' => $this->extract_description($xpath),
            'short_description' => $this->extract_short_description($xpath),
            'images' => $this->extract_images($xpath),
            'source_url' => $url,
            'sku' => $this->generate_sku_from_url($url),
            'product_type' => !empty($variations) ? 'variable' : 'simple',
            'variations' => $variations,
            'attributes' => $this->extract_attributes($xpath, $variations)
        );
        
        return $product_data;
    }
    
    /**
     * Extract variations from JavaScript data
     */
    private function extract_variations($html, $xpath) {
        $variations = array();
        
        // Look for the data-product_variations attribute in the form
        if (preg_match('/data-product_variations=(["\'])(.+?)\1/s', $html, $matches)) {
            $json_string = html_entity_decode($matches[2], ENT_QUOTES);
            $variations_data = json_decode($json_string, true);
            
            if (is_array($variations_data) && !empty($variations_data)) {
                foreach ($variations_data as $variation) {
                    $variations[] = array(
                        'attributes' => $variation['attributes'],
                        'sku' => isset($variation['sku']) ? $variation['sku'] : '',
                        'price' => isset($variation['display_price']) ? $variation['display_price'] : 0,
                        'regular_price' => isset($variation['display_regular_price']) ? $variation['display_regular_price'] : 0,
                        'stock_status' => isset($variation['is_in_stock']) && $variation['is_in_stock'] ? 'instock' : 'outofstock',
                        'image' => isset($variation['image']['url']) ? $variation['image']['url'] : '',
                        'image_alt' => isset($variation['image']['alt']) ? $variation['image']['alt'] : '',
                        'variation_id' => isset($variation['variation_id']) ? $variation['variation_id'] : 0,
                        'display_name' => isset($variation['display_name']) ? $variation['display_name'] : ''
                    );
                }
            }
        }
        
        return $variations;
    }
    
    /**
     * Extract product attributes from the variations
     */
    private function extract_attributes($xpath, $variations) {
        $attributes = array();
        
        if (empty($variations)) {
            return $attributes;
        }
        
        // Get attribute names and options from the select element
        $select_nodes = $xpath->query("//select[contains(@name, 'attribute_')]");
        
        foreach ($select_nodes as $select) {
            $attribute_name = $select->getAttribute('name');
            $attribute_name = str_replace('attribute_', '', $attribute_name);
            
            // Get label
            $label_nodes = $xpath->query("//label[@for='" . $select->getAttribute('id') . "']");
            $label = $label_nodes->length > 0 ? trim($label_nodes->item(0)->textContent) : $attribute_name;
            
            // Get options
            $option_nodes = $xpath->query(".//option[@value!='']", $select);
            $options = array();
            
            foreach ($option_nodes as $option) {
                $value = $option->getAttribute('value');
                $text = trim($option->textContent);
                if (!empty($value)) {
                    $options[] = array(
                        'slug' => $value,
                        'name' => $text
                    );
                }
            }
            
            if (!empty($options)) {
                $attributes[] = array(
                    'name' => $label,
                    'slug' => $attribute_name,
                    'options' => $options,
                    'visible' => true,
                    'variation' => true
                );
            }
        }
        
        return $attributes;
    }
    
    /**
     * Extract product title
     */
    private function extract_title($xpath) {
        // Try h1 first
        $title_nodes = $xpath->query("//h1");
        if ($title_nodes->length > 0) {
            return trim($title_nodes->item(0)->textContent);
        }
        
        // Fallback to title tag
        $title_nodes = $xpath->query("//title");
        if ($title_nodes->length > 0) {
            $title = trim($title_nodes->item(0)->textContent);
            // Remove site name if present
            $title = preg_replace('/ - .*$/', '', $title);
            return $title;
        }
        
        return 'Untitled Product';
    }
    
    /**
     * Extract product price (sale price if available)
     */
    private function extract_price($xpath) {
        // Try to get sale price first (ins tag)
        $price_nodes = $xpath->query("//p[@class='price']//ins//span[@class='woocommerce-Price-amount amount']");
        if ($price_nodes->length > 0) {
            $price_text = $price_nodes->item(0)->textContent;
            preg_match('/[\d,.]+/', $price_text, $matches);
            if (!empty($matches[0])) {
                return str_replace(',', '', $matches[0]);
            }
        }
        
        // Fallback to regular price
        return $this->extract_regular_price($xpath);
    }
    
    /**
     * Extract sale price
     */
    private function extract_sale_price($xpath) {
        $price_nodes = $xpath->query("//p[@class='price']//ins//span[@class='woocommerce-Price-amount amount']");
        if ($price_nodes->length > 0) {
            $price_text = $price_nodes->item(0)->textContent;
            preg_match('/[\d,.]+/', $price_text, $matches);
            if (!empty($matches[0])) {
                return str_replace(',', '', $matches[0]);
            }
        }
        return null;
    }
    
    /**
     * Extract regular price
     */
    private function extract_regular_price($xpath) {
        // Try del tag first (original price when on sale)
        $price_nodes = $xpath->query("//p[@class='price']//del//span[@class='woocommerce-Price-amount amount']");
        if ($price_nodes->length > 0) {
            $price_text = $price_nodes->item(0)->textContent;
            preg_match('/[\d,.]+/', $price_text, $matches);
            if (!empty($matches[0])) {
                return str_replace(',', '', $matches[0]);
            }
        }
        
        // Try regular price element
        $price_nodes = $xpath->query("//p[@class='price']//span[@class='woocommerce-Price-amount amount']");
        if ($price_nodes->length > 0) {
            $price_text = $price_nodes->item(0)->textContent;
            preg_match('/[\d,.]+/', $price_text, $matches);
            if (!empty($matches[0])) {
                return str_replace(',', '', $matches[0]);
            }
        }
        
        return 0;
    }
    
    /**
     * Extract product description
     */
    private function extract_description($xpath) {
        $description = '';
        
        // Try to find the description in et_pb_wc_description module
        $desc_nodes = $xpath->query("//div[contains(@class, 'et_pb_wc_description')]//div[contains(@class, 'et_pb_module_inner')]");
        if ($desc_nodes->length > 0) {
            $description = $this->get_inner_html($desc_nodes->item(0));
        }
        
        return $this->clean_html($description);
    }
    
    /**
     * Extract short description
     */
    private function extract_short_description($xpath) {
        $desc_nodes = $xpath->query("//div[contains(@class, 'et_pb_wc_description')]//p");
        if ($desc_nodes->length > 0) {
            $first_para = trim($desc_nodes->item(0)->textContent);
            // Limit to reasonable length
            if (strlen($first_para) > 300) {
                $first_para = substr($first_para, 0, 297) . '...';
            }
            return $first_para;
        }
        
        return '';
    }
    
    /**
     * Extract product images
     */
    private function extract_images($xpath) {
        $images = array();
        
        // Get all gallery images
        $img_nodes = $xpath->query("//div[contains(@class, 'woocommerce-product-gallery__image')]//a");
        
        foreach ($img_nodes as $link) {
            $href = $link->getAttribute('href');
            $img = $xpath->query(".//img", $link)->item(0);
            
            if ($img) {
                $image_url = $href ?: $img->getAttribute('data-large_image') ?: $img->getAttribute('src');
                
                if (!empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
                    $images[] = array(
                        'url' => $image_url,
                        'alt' => $img->getAttribute('alt')
                    );
                }
            }
        }
        
        // Remove duplicates
        $unique_images = array();
        $seen_urls = array();
        
        foreach ($images as $image) {
            if (!in_array($image['url'], $seen_urls)) {
                $unique_images[] = $image;
                $seen_urls[] = $image['url'];
            }
        }
        
        return $unique_images;
    }
    
    /**
     * Generate SKU from URL
     */
    private function generate_sku_from_url($url) {
        $path = parse_url($url, PHP_URL_PATH);
        $slug = basename($path);
        $sku = strtoupper(preg_replace('/[^a-z0-9]/i', '', $slug));
        return substr($sku, 0, 20);
    }
    
    /**
     * Get inner HTML of a node
     */
    private function get_inner_html($node) {
        $innerHTML = '';
        $children = $node->childNodes;
        foreach ($children as $child) {
            $innerHTML .= $node->ownerDocument->saveHTML($child);
        }
        return $innerHTML;
    }
    
    /**
     * Clean HTML content
     */
    private function clean_html($html) {
        // Remove script and style tags
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
        
        // Remove empty tags
        $html = preg_replace('/<(\w+)[^>]*>\s*<\/\1>/', '', $html);
        
        // Clean up whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        
        return trim($html);
    }
}