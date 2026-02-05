jQuery(document).ready(function($) {
    'use strict';
    
    // Single Product Import
    $('#single-import-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $statusDiv = $('#single-import-status');
        var $submitBtn = $form.find('button[type="submit"]');
        var productUrl = $('#product-url').val().trim();
        var brandId = $('#single-brand-select').val() || 0;
        
        if (!productUrl) {
            showStatus($statusDiv, 'error', d8austinImporter.strings.invalid_url);
            return;
        }
        
        // Disable submit button
        $submitBtn.prop('disabled', true).addClass('loading');
        $submitBtn.find('.dashicons').addClass('dashicons-update');
        
        // Clear previous status
        $statusDiv.html('').removeClass('success error');
        
        // Show importing message
        showStatus($statusDiv, 'info', d8austinImporter.strings.importing);
        
        // Make AJAX request
        $.ajax({
            url: d8austinImporter.ajax_url,
            type: 'POST',
            data: {
                action: 'd8austin_import_product',
                nonce: d8austinImporter.nonce,
                product_url: productUrl,
                brand_id: brandId
            },
            success: function(response) {
                if (response.success) {
                    showStatus($statusDiv, 'success', response.data.message);
                    
                    // Add link to edit product
                    if (response.data.edit_url) {
                        var editLink = '<br><a href="' + response.data.edit_url + '" class="button button-small" style="margin-top: 10px;">Edit Product</a>';
                        $statusDiv.append(editLink);
                    }
                    
                    // Clear the form
                    $form[0].reset();
                    
                    // Reload page after 2 seconds to update history
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showStatus($statusDiv, 'error', response.data.message || d8austinImporter.strings.error);
                }
            },
            error: function(xhr, status, error) {
                showStatus($statusDiv, 'error', d8austinImporter.strings.error + ' (' + error + ')');
            },
            complete: function() {
                // Re-enable submit button
                $submitBtn.prop('disabled', false).removeClass('loading');
                $submitBtn.find('.dashicons').removeClass('dashicons-update');
            }
        });
    });
    
    // Multiple Products Import
    $('#multiple-import-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $statusDiv = $('#multiple-import-status');
        var $progressDiv = $('#multiple-import-progress');
        var $submitBtn = $form.find('button[type="submit"]');
        var productUrls = $('#product-urls').val().trim().split('\n');
        var brandId = $('#multiple-brand-select').val() || 0;
        
        // Filter out empty lines
        productUrls = productUrls.filter(function(url) {
            return url.trim() !== '';
        });
        
        if (productUrls.length === 0) {
            showStatus($statusDiv, 'error', d8austinImporter.strings.invalid_url);
            return;
        }
        
        // Disable submit button
        $submitBtn.prop('disabled', true).addClass('loading');
        
        // Clear previous status
        $statusDiv.html('').removeClass('success error');
        $progressDiv.show();
        
        // Initialize progress
        var total = productUrls.length;
        var current = 0;
        updateProgress(0, total);
        
        // Show importing message
        showStatus($statusDiv, 'info', 'Starting import of ' + total + ' products...');
        
        // Make AJAX request
        $.ajax({
            url: d8austinImporter.ajax_url,
            type: 'POST',
            data: {
                action: 'd8austin_import_multiple',
                nonce: d8austinImporter.nonce,
                product_urls: productUrls,
                brand_id: brandId
            },
            success: function(response) {
                if (response.success) {
                    var results = response.data;
                    var successCount = results.success.length;
                    var failedCount = results.failed.length;
                    
                    // Update progress
                    updateProgress(total, total);
                    
                    // Build status message
                    var statusHtml = '<div class="import-results">';
                    statusHtml += '<h3>Import Complete</h3>';
                    statusHtml += '<p><strong>Successfully imported:</strong> ' + successCount + ' products</p>';
                    
                    if (failedCount > 0) {
                        statusHtml += '<p><strong>Failed:</strong> ' + failedCount + ' products</p>';
                    }
                    
                    // Show successful imports
                    if (successCount > 0) {
                        statusHtml += '<h4>Successfully Imported:</h4><ul>';
                        results.success.forEach(function(item) {
                            statusHtml += '<li>' + item.title + '</li>';
                        });
                        statusHtml += '</ul>';
                    }
                    
                    // Show failed imports
                    if (failedCount > 0) {
                        statusHtml += '<h4>Failed Imports:</h4><ul class="import-errors">';
                        results.failed.forEach(function(item) {
                            statusHtml += '<li><strong>' + item.url + '</strong><br><span class="error-msg">' + item.error + '</span></li>';
                        });
                        statusHtml += '</ul>';
                    }
                    
                    statusHtml += '</div>';
                    
                    $statusDiv.html(statusHtml);
                    
                    if (failedCount > 0) {
                        $statusDiv.addClass('warning');
                    } else {
                        $statusDiv.addClass('success');
                    }
                    
                    // Clear the form
                    $form[0].reset();
                    
                    // Reload page after 3 seconds to update history
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    showStatus($statusDiv, 'error', response.data.message || d8austinImporter.strings.error);
                }
            },
            error: function(xhr, status, error) {
                showStatus($statusDiv, 'error', d8austinImporter.strings.error + ' (' + error + ')');
            },
            complete: function() {
                // Re-enable submit button
                $submitBtn.prop('disabled', false).removeClass('loading');
            }
        });
    });
    
    /**
     * Show status message
     */
    function showStatus($element, type, message) {
        $element.removeClass('success error warning info').addClass(type);
        $element.html('<p>' + message + '</p>');
    }
    
    /**
     * Update progress bar
     */
    function updateProgress(current, total) {
        var percentage = (current / total) * 100;
        $('#multiple-import-progress .progress-fill').css('width', percentage + '%');
        $('#multiple-import-progress .current').text(current);
        $('#multiple-import-progress .total').text(total);
    }
});