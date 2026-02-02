<?php
/**
 * Product Scraper Class
 * Handles scraping product data from D8Austin.com
 */

class D8Austin_Product_Scraper {
    
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
        
        $product_data = array(
            'title' => $this->extract_title($xpath),
            'price' => $this->extract_price($xpath),
            'description' => $this->extract_description($xpath),
            'short_description' => $this->extract_short_description($xpath),
            'images' => $this->extract_images($xpath),
            'source_url' => $url,
            'sku' => $this->generate_sku_from_url($url)
        );
        
        return $product_data;
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
     * Extract product price
     */
    private function extract_price($xpath) {
        // Try WooCommerce price element
        $price_nodes = $xpath->query("//p[@class='price']//span[@class='woocommerce-Price-amount amount']");
        if ($price_nodes->length > 0) {
            $price_text = $price_nodes->item(0)->textContent;
            // Extract numeric value
            preg_match('/[\d,.]+/', $price_text, $matches);
            if (!empty($matches[0])) {
                return str_replace(',', '', $matches[0]);
            }
        }
        
        // Try alternative price selectors
        $price_nodes = $xpath->query("//*[contains(@class, 'price')]");
        foreach ($price_nodes as $node) {
            $text = $node->textContent;
            preg_match('/\$?[\d,.]+/', $text, $matches);
            if (!empty($matches[0])) {
                return str_replace(array('$', ','), '', $matches[0]);
            }
        }
        
        return 0;
    }
    
    /**
     * Extract product description
     */
    private function extract_description($xpath) {
        $description = '';
        
        // Try to find the RTE content div
        $desc_nodes = $xpath->query("//div[contains(@class, 'rte')]");
        if ($desc_nodes->length > 0) {
            $description = $this->get_inner_html($desc_nodes->item(0));
        } else {
            // Fallback to WooCommerce description
            $desc_nodes = $xpath->query("//div[contains(@class, 'wc_description')]//div[contains(@class, 'et_pb_module_inner')]");
            if ($desc_nodes->length > 0) {
                $description = $this->get_inner_html($desc_nodes->item(0));
            }
        }
        
        return $this->clean_html($description);
    }
    
    /**
     * Extract short description
     */
    private function extract_short_description($xpath) {
        $desc_nodes = $xpath->query("//div[contains(@class, 'rte')]//p");
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
        
        // Try WooCommerce gallery images
        $img_nodes = $xpath->query("//div[contains(@class, 'woocommerce-product-gallery__image')]//img");
        
        foreach ($img_nodes as $img) {
            $src = $img->getAttribute('src');
            $data_src = $img->getAttribute('data-src');
            $large_image = $img->getAttribute('data-large_image');
            
            // Prefer larger image
            $image_url = $large_image ?: ($data_src ?: $src);
            
            if (!empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
                $images[] = array(
                    'url' => $image_url,
                    'alt' => $img->getAttribute('alt')
                );
            }
        }
        
        // Remove duplicates
        $images = array_unique($images, SORT_REGULAR);
        
        return $images;
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