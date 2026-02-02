/**
 * D8Austin Product Importer - Admin JavaScript
 */

(function($) {
    'use strict';
    
    // Single product import
    $('#single-import-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const $status = $('#single-import-status');
        const productUrl = $('#product-url').val().trim();
        
        // Validate URL
        if (!productUrl) {
            showMessage($status, d8austinImporter.strings.invalid_url, 'error');
            return;
        }
        
        // Disable form
        $form.addClass('loading');
        $button.prop('disabled', true);
        $status.html('');
        
        // Show loading message
        showMessage($status, d8austinImporter.strings.importing, 'info');
        
        // Send AJAX request
        $.ajax({
            url: d8austinImporter.ajax_url,
            type: 'POST',
            data: {
                action: 'd8austin_import_product',
                nonce: d8austinImporter.nonce,
                product_url: productUrl
            },
            success: function(response) {
                if (response.success) {
                    const message = `
                        <strong>${d8austinImporter.strings.success}</strong><br>
                        <a href="${response.data.edit_url}" target="_blank">View Product in WooCommerce</a>
                    `;
                    showMessage($status, message, 'success');
                    
                    // Clear form
                    $form[0].reset();
                    
                    // Reload page after 2 seconds to show in history
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showMessage($status, response.data.message || d8austinImporter.strings.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage($status, d8austinImporter.strings.error + ' ' + error, 'error');
            },
            complete: function() {
                $form.removeClass('loading');
                $button.prop('disabled', false);
            }
        });
    });
    
    // Multiple products import
    $('#multiple-import-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const $progress = $('#multiple-import-progress');
        const $status = $('#multiple-import-status');
        const productUrlsText = $('#product-urls').val().trim();
        
        // Parse URLs
        const productUrls = productUrlsText
            .split('\n')
            .map(url => url.trim())
            .filter(url => url.length > 0);
        
        if (productUrls.length === 0) {
            showMessage($status, d8austinImporter.strings.invalid_url, 'error');
            return;
        }
        
        // Disable form
        $form.addClass('loading');
        $button.prop('disabled', true);
        $status.html('');
        
        // Show progress bar
        $progress.show();
        updateProgress(0, productUrls.length);
        
        // Import products sequentially
        importProductsSequentially(productUrls, $progress, $status, function() {
            $form.removeClass('loading');
            $button.prop('disabled', false);
            
            // Reload page after 3 seconds
            setTimeout(function() {
                location.reload();
            }, 3000);
        });
    });
    
    /**
     * Import products one by one
     */
    function importProductsSequentially(urls, $progress, $status, callback) {
        let current = 0;
        const results = {
            success: [],
            failed: []
        };
        
        function importNext() {
            if (current >= urls.length) {
                // All done
                displayResults(results, $status);
                callback();
                return;
            }
            
            const url = urls[current];
            
            $.ajax({
                url: d8austinImporter.ajax_url,
                type: 'POST',
                data: {
                    action: 'd8austin_import_product',
                    nonce: d8austinImporter.nonce,
                    product_url: url
                },
                success: function(response) {
                    if (response.success) {
                        results.success.push({
                            url: url,
                            edit_url: response.data.edit_url
                        });
                    } else {
                        results.failed.push({
                            url: url,
                            error: response.data.message
                        });
                    }
                },
                error: function(xhr, status, error) {
                    results.failed.push({
                        url: url,
                        error: error
                    });
                },
                complete: function() {
                    current++;
                    updateProgress(current, urls.length);
                    
                    // Small delay between requests
                    setTimeout(importNext, 500);
                }
            });
        }
        
        // Start importing
        importNext();
    }
    
    /**
     * Update progress bar
     */
    function updateProgress(current, total) {
        const percentage = (current / total) * 100;
        $('#multiple-import-progress .progress-fill').css('width', percentage + '%');
        $('#multiple-import-progress .current').text(current);
        $('#multiple-import-progress .total').text(total);
    }
    
    /**
     * Display import results
     */
    function displayResults(results, $status) {
        let html = '';
        
        if (results.success.length > 0) {
            html += '<div class="notice notice-success"><p><strong>Successfully imported ' + results.success.length + ' product(s):</strong></p>';
            results.success.forEach(function(item) {
                html += '<div class="success-item">';
                html += '✓ <a href="' + item.edit_url + '" target="_blank">' + item.url + '</a>';
                html += '</div>';
            });
            html += '</div>';
        }
        
        if (results.failed.length > 0) {
            html += '<div class="notice notice-error"><p><strong>Failed to import ' + results.failed.length + ' product(s):</strong></p>';
            results.failed.forEach(function(item) {
                html += '<div class="error-item">';
                html += '✗ ' + item.url + '<br><small>' + item.error + '</small>';
                html += '</div>';
            });
            html += '</div>';
        }
        
        $status.html(html);
    }
    
    /**
     * Show status message
     */
    function showMessage($container, message, type) {
        const noticeClass = type === 'success' ? 'notice-success' : 
                          type === 'error' ? 'notice-error' : 
                          'notice-info';
        
        const html = '<div class="notice ' + noticeClass + '"><p>' + message + '</p></div>';
        $container.html(html);
    }
    
})(jQuery);