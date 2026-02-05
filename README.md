# D8Austin Product Importer for WordPress

A powerful WordPress plugin that allows you to import products from D8Austin.com directly into your WooCommerce store.

## Features

- ✅ **Single Product Import** - Import one product at a time with a simple URL
- ✅ **Bulk Import** - Import multiple products simultaneously
- ✅ **Automatic Data Extraction** - Automatically scrapes product title, price, description, and images
- ✅ **Image Import** - Downloads and imports all product images to your media library
- ✅ **Draft Mode** - Products are imported as drafts for review before publishing
- ✅ **Import History** - Track all imported products with source URLs and dates
- ✅ **Duplicate Detection** - Prevents duplicate imports using SKU matching
- ✅ **User-Friendly Interface** - Clean, intuitive admin interface

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher

## Installation

### Method 1: Upload via WordPress Admin

1. Download the plugin ZIP file
2. Log in to your WordPress admin panel
3. Navigate to **Plugins** → **Add New**
4. Click **Upload Plugin**
5. Choose the ZIP file and click **Install Now**
6. Click **Activate Plugin**

### Method 2: Manual Installation

1. Download and extract the plugin files
2. Upload the `d8austin-product-importer` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu in WordPress

## Usage

### Importing a Single Product

1. Go to **D8Austin Importer** in your WordPress admin menu
2. In the "Import Single Product" section, paste the product URL from d8austin.com
   - Example: `https://www.d8austin.com/product-page/storz-bickel-mighty-portable-vaporizer`
3. Click **Import Product**
4. Wait for the import to complete
5. Review the imported product in WooCommerce (it will be saved as a draft)
6. Edit and publish when ready

### Importing Multiple Products

1. Go to **D8Austin Importer** in your WordPress admin menu
2. In the "Import Multiple Products" section, paste multiple URLs (one per line)
3. Click **Import All Products**
4. Monitor the progress bar as products are imported
5. Review the results summary
6. Check imported products in WooCommerce

### Viewing Import History

The plugin automatically tracks all imported products. You can view:
- Product names
- Source URLs
- Import dates
- Quick links to edit or view products

## What Gets Imported?

The plugin imports the following data from each product:

- **Product Title** - Main product name
- **Price** - Regular price
- **Description** - Full product description with HTML formatting
- **Short Description** - First paragraph or summary
- **Images** - All product images (main image and gallery images)
- **SKU** - Auto-generated from product URL
- **Source URL** - Saved as metadata for reference

## Plugin Structure

```
d8austin-product-importer/
├── d8austin-product-importer.php     # Main plugin file
├── includes/
│   ├── class-product-scraper.php     # Handles web scraping
│   ├── class-product-importer.php    # Handles WooCommerce import
│   └── class-admin-page.php          # Admin interface
├── assets/
│   ├── css/
│   │   └── admin.css                 # Admin styling
│   └── js/
│       └── admin.js                  # Admin JavaScript
└── README.md                         # This file
```

## Developer Information

### Hooks and Filters

The plugin provides several hooks for developers:

**Actions:**
- `d8austin_before_product_import` - Fires before a product is imported
- `d8austin_after_product_import` - Fires after a product is imported
- `d8austin_product_import_failed` - Fires when import fails

**Filters:**
- `d8austin_product_data` - Filter product data before import
- `d8austin_scraper_timeout` - Modify scraper timeout (default: 30)
- `d8austin_import_status` - Modify product status (default: 'draft')

### Customization Examples

**Change import status to publish:**

```php
add_filter('d8austin_import_status', function($status) {
    return 'publish'; // Auto-publish instead of draft
});
```

**Modify product data before import:**

```php
add_filter('d8austin_product_data', function($data) {
    // Add custom metadata
    $data['custom_field'] = 'custom_value';
    return $data;
});
```

## Troubleshooting

### Import fails with timeout error

- Increase PHP max_execution_time in php.ini
- Reduce the number of products imported at once

### Images not importing

- Check PHP allow_url_fopen setting
- Verify WordPress has write permissions for uploads folder
- Ensure the source website is accessible

### Duplicate products created

- The plugin uses SKU to detect duplicates
- If SKUs are different, products will be created as new
- Check existing product SKUs in WooCommerce

## Security

- All AJAX requests use WordPress nonces for security
- User capability checks ensure only authorized users can import
- Input sanitization and validation on all user inputs
- Escaped output to prevent XSS attacks

## Performance

- Single product import: ~5-10 seconds per product
- Bulk import: Sequential processing with 500ms delay between requests
- Images are downloaded asynchronously
- Uses WordPress transients for caching (if needed)

## Changelog

### Version 1.0.0 (2025-02-02)
- Initial release
- Single product import
- Bulk product import
- Image downloading
- Import history tracking
- Admin interface

## Support

For issues, questions, or feature requests, please contact the plugin developer.

## License

This plugin is licensed under GPL v2 or later.

## Credits

Developed for WordPress/WooCommerce store owners who want to easily import products from D8Austin.com.

## Disclaimer

This plugin is designed to import product information for legitimate business purposes. Please ensure you have the right to import and use product data from the source website. The plugin developers are not responsible for any copyright or trademark violations.