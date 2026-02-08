jQuery(document).ready(function ($) {
    // Tab switching
    $('.nav-tab-wrapper a').click(function (e) {
        e.preventDefault();
        var tab = $(this).attr('href');

        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.tab-content').removeClass('active');
        $(tab).addClass('active');

        // Load dummy posts list when manage tab is shown
        if (tab === '#manage-tab') {
            loadDummyPosts();
        }

        // Load post meta when generate tab is shown
        if (tab === '#generate-tab') {
            loadPostMeta();
        }
    });

    // Load post meta on page load
    if ($('#generate-tab').hasClass('active')) {
        loadPostMeta();
    }

    // Load post meta when post type changes
    $('#post-type-selector').change(function () {
        loadPostMeta();
    });

    function loadPostMeta() {
        var postType = $('#post-type-selector').val();

        $.ajax({
            url: wpdcf_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wpdcf_get_post_meta',
                post_type: postType,
                nonce: wpdcf_ajax.nonce
            },
            beforeSend: function () {
                $('#post-meta-configuration').html('<div class="loading"><p>Loading post configuration...</p></div>');
            },
            success: function (response) {
                if (response.success) {
                    $('#post-meta-configuration').html(response.data);
                } else {
                    $('#post-meta-configuration').html('<div class="error"><p>Error loading post configuration.</p></div>');
                }
            },
            error: function () {
                $('#post-meta-configuration').html('<div class="error"><p>Error loading post configuration.</p></div>');
            }
        });
    }

    // Filter dummy posts
    $('#apply-filter').click(function () {
        loadDummyPosts();
    });

    // Load dummy posts via AJAX
    function loadDummyPosts() {
        var postType = $('#filter-post-type').val();

        $.ajax({
            url: wpdcf_ajax.ajax_url,
            type: 'GET',
            data: {
                action: 'wpdcf_get_dummy_posts',
                post_type: postType
            },
            beforeSend: function () {
                $('#dummy-posts-list').html('<div class="loading"><p>Loading dummy posts...</p></div>');
            },
            success: function (response) {
                if (response.success) {
                    $('#dummy-posts-list').html(response.data);
                    $('#delete-section').show();
                } else {
                    $('#dummy-posts-list').html('<div class="notice notice-warning"><p>' + response.data + '</p></div>');
                    $('#delete-section').hide();
                }
            },
            error: function () {
                $('#dummy-posts-list').html('<div class="error"><p>Error loading posts.</p></div>');
                $('#delete-section').hide();
            }
        });
    }

    // Set delete post type from filter
    $('#filter-post-type').change(function () {
        $('#delete-post-type').val($(this).val());
    });

    // Auto-load posts when manage tab is shown
    if ($('#manage-tab').hasClass('active')) {
        loadDummyPosts();
    }

    // Add confirmation for individual post deletion
    $(document).on('click', '.button-danger', function (e) {
        if ($(this).text().indexOf('Delete') !== -1 && !$(this).hasClass('confirmed')) {
            e.preventDefault();
            var href = $(this).attr('href');
            if (confirm('Are you sure? This will delete the post and all its meta data.')) {
                $(this).addClass('confirmed');
                window.location.href = href;
            }
        }
    });
});


// Product-specific functionality
if ($('#generate-products-tab').length) {
    // Product tab functionality
    $('.nav-tab-wrapper a').click(function (e) {
        e.preventDefault();
        var tab = $(this).attr('href');

        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.tab-content').removeClass('active');
        $(tab).addClass('active');

        // Load product meta when generate tab is shown
        if (tab === '#generate-products-tab') {
            loadProductMeta();
        }

        // Load dummy products when manage tab is shown
        if (tab === '#manage-products-tab') {
            loadDummyProducts();
        }
    });

    function loadProductMeta() {
        $.ajax({
            url: wpdcf_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wpdcf_get_product_meta',
                nonce: wpdcf_ajax.nonce
            },
            beforeSend: function () {
                $('#product-meta-configuration').html('<div class="loading"><p>Loading product configuration...</p></div>');
            },
            success: function (response) {
                if (response.success) {
                    $('#product-meta-configuration').html(response.data);
                } else {
                    $('#product-meta-configuration').html('<div class="error"><p>Error loading product configuration.</p></div>');
                }
            },
            error: function () {
                $('#product-meta-configuration').html('<div class="error"><p>Error loading product configuration.</p></div>');
            }
        });
    }

    function loadDummyProducts() {
        $.ajax({
            url: wpdcf_ajax.ajax_url,
            type: 'GET',
            data: {
                action: 'wpdcf_get_dummy_products'
            },
            beforeSend: function () {
                $('#dummy-products-list').html('<div class="loading"><p>Loading dummy products...</p></div>');
            },
            success: function (response) {
                if (response.success) {
                    $('#dummy-products-list').html(response.data);
                    $('#delete-section').show();
                } else {
                    $('#dummy-products-list').html('<div class="notice notice-warning"><p>' + response.data + '</p></div>');
                    $('#delete-section').hide();
                }
            },
            error: function () {
                $('#dummy-products-list').html('<div class="error"><p>Error loading products.</p></div>');
                $('#delete-section').hide();
            }
        });
    }

    // Auto-load products when manage tab is shown
    if ($('#manage-products-tab').hasClass('active')) {
        loadDummyProducts();
    }
}