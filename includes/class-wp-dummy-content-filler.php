<?php
/**
 * Main plugin class
 */
class WP_Dummy_Content_Filler {
    
    private static $instance = null;
    private $faker = null;
    
    // Available Faker data types
    private $faker_types = [
        'text' => 'Text (Sentence)',
        'paragraphs' => 'Text (Paragraphs)',
        'words' => 'Text (Words)',
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone Number',
        'address' => 'Address',
        'city' => 'City',
        'country' => 'Country',
        'zipcode' => 'ZIP Code',
        'number' => 'Number (1-100)',
        'price' => 'Price (10-1000)',
        'date' => 'Date',
        'boolean' => 'Boolean (Yes/No)',
        'url' => 'URL',
        'image_url' => 'Image URL',
        'color' => 'Color',
        'hex_color' => 'Hex Color',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'company' => 'Company Name',
    ];
    
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
        // Admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Handle form submissions
        add_action('admin_init', [$this, 'handle_actions']);
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // AJAX handlers
        add_action('wp_ajax_wpdcf_get_post_meta', [$this, 'ajax_get_post_meta']);
        add_action('wp_ajax_wpdcf_get_dummy_posts', [$this, 'ajax_get_dummy_posts']);
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'wp-dummy-content-filler') !== false) {
            wp_enqueue_script(
                'wp-dummy-content-filler-admin',
                WP_DUMMY_CONTENT_FILLER_PLUGIN_URL . 'assets/js/admin.js',
                ['jquery'],
                WP_DUMMY_CONTENT_FILLER_VERSION,
                true
            );
            
            wp_enqueue_style(
                'wp-dummy-content-filler-admin',
                WP_DUMMY_CONTENT_FILLER_PLUGIN_URL . 'assets/css/admin.css',
                [],
                WP_DUMMY_CONTENT_FILLER_VERSION
            );
            
            // Localize script
            wp_localize_script('wp-dummy-content-filler-admin', 'wpdcf_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpdcf_ajax_nonce')
            ]);
        }
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'WP Dummy Content Filler',
            'Dummy Content',
            'manage_options',
            'wp-dummy-content-filler',
            [$this, 'render_post_types_page'],
            'dashicons-edit',
            30
        );
        
        add_submenu_page(
            'wp-dummy-content-filler',
            'Post Types',
            'Post Types',
            'manage_options',
            'wp-dummy-content-filler',
            [$this, 'render_post_types_page']
        );
        
        add_submenu_page(
            'wp-dummy-content-filler',
            'Users',
            'Users',
            'manage_options',
            'wp-dummy-content-filler-users',
            [$this, 'render_users_page']
        );
        
        if (class_exists('WooCommerce')) {
            add_submenu_page(
                'wp-dummy-content-filler',
                'Products',
                'Products',
                'manage_options',
                'wp-dummy-content-filler-products',
                [$this, 'render_products_page']
            );
        }
        
        add_submenu_page(
            'wp-dummy-content-filler',
            'Settings',
            'Settings',
            'manage_options',
            'wp-dummy-content-filler-settings',
            [$this, 'render_settings_page']
        );
    }
    
    public function handle_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Generate posts
        if (isset($_POST['generate_posts']) && wp_verify_nonce($_POST['_wpnonce'], 'generate_dummy_posts')) {
            $this->generate_dummy_posts();
        }
        
        // Clear dummy posts for specific post type
        if (isset($_GET['clear_dummy_posts']) && isset($_GET['post_type']) && wp_verify_nonce($_GET['_wpnonce'], 'clear_dummy_posts')) {
            $post_type = sanitize_text_field($_GET['post_type']);
            $deleted_count = $this->clear_dummy_posts($post_type);
            
            set_transient('dummy_content_results', [
                'message' => sprintf(
                    'Successfully deleted %d %s.',
                    $deleted_count,
                    _n('post', 'posts', $deleted_count)
                )
            ], 30);
            
            wp_redirect(add_query_arg(['page' => 'wp-dummy-content-filler'], admin_url('admin.php')));
            exit;
        }
        
        // Generate users
        if (isset($_POST['generate_users']) && wp_verify_nonce($_POST['_wpnonce'], 'generate_dummy_users')) {
            $this->generate_dummy_users();
        }
        
        // Clear dummy users
        if (isset($_GET['clear_dummy_users']) && wp_verify_nonce($_GET['_wpnonce'], 'clear_dummy_users')) {
            $deleted_count = $this->clear_dummy_users();
            
            set_transient('dummy_user_results', [
                'message' => sprintf(
                    'Successfully deleted %d %s.',
                    $deleted_count,
                    _n('user', 'users', $deleted_count)
                )
            ], 30);
            
            wp_redirect(add_query_arg(['page' => 'wp-dummy-content-filler-users'], admin_url('admin.php')));
            exit;
        }
    }
    
    private function get_faker() {
        if (null === $this->faker) {
            // Check if Faker is available via Composer
            if (class_exists('Faker\Factory')) {
                $this->faker = Faker\Factory::create();
            } else {
                // Fallback to basic random data if Faker not available
                $this->faker = false;
            }
        }
        return $this->faker;
    }
    
    private function generate_dummy_posts() {
        $post_type = sanitize_text_field($_POST['post_type'] ?? 'post');
        $count = intval($_POST['post_count'] ?? 5);
        $with_images = isset($_POST['with_images']);
        $create_taxonomies = isset($_POST['create_taxonomies']);
        
        // Get post meta configurations
        $post_meta_config = [];
        if (isset($_POST['post_meta']) && is_array($_POST['post_meta'])) {
            foreach ($_POST['post_meta'] as $meta_key => $config) {
                if (!empty($config['type'])) {
                    $post_meta_config[$meta_key] = [
                        'type' => sanitize_text_field($config['type']),
                        'value' => isset($config['value']) ? sanitize_text_field($config['value']) : ''
                    ];
                }
            }
        }
        
        // Get taxonomy configurations
        $taxonomy_config = [];
        if (isset($_POST['taxonomies']) && is_array($_POST['taxonomies'])) {
            foreach ($_POST['taxonomies'] as $taxonomy => $config) {
                if (isset($config['create']) && $config['create'] === 'yes') {
                    $taxonomy_config[$taxonomy] = [
                        'create' => 'yes',
                        'count' => isset($config['count']) ? intval($config['count']) : 3,
                        'assign' => isset($config['assign']) ? intval($config['assign']) : 2
                    ];
                }
            }
        }
        
        $results = ['success' => 0, 'failed' => 0, 'taxonomies_created' => 0];
        
        // First create taxonomies if requested
        $created_terms = [];
        if ($create_taxonomies && !empty($taxonomy_config)) {
            foreach ($taxonomy_config as $taxonomy => $config) {
                $terms = $this->create_dummy_terms($taxonomy, $config['count']);
                $created_terms[$taxonomy] = $terms;
                $results['taxonomies_created'] += count($terms);
            }
        }
        
        // Generate posts
        for ($i = 0; $i < $count; $i++) {
            $post_id = $this->create_dummy_post($post_type, $with_images, $post_meta_config, $created_terms, $taxonomy_config);
            
            if ($post_id && !is_wp_error($post_id)) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }
        
        // Store results in transient for display
        set_transient('dummy_content_results', [
            'message' => sprintf(
                'Successfully generated %d %s with %d taxonomy terms. Failed: %d',
                $results['success'],
                _n('post', 'posts', $results['success']),
                $results['taxonomies_created'],
                $results['failed']
            )
        ], 30);
        
        wp_redirect(add_query_arg(['page' => 'wp-dummy-content-filler'], admin_url('admin.php')));
        exit;
    }
    
    private function create_dummy_post($post_type = 'post', $with_images = false, $meta_config = [], $created_terms = [], $taxonomy_config = []) {
        $faker = $this->get_faker();
        
        $post_data = [
            'post_title'   => $faker ? $faker->sentence(6) : 'Dummy Post ' . time() . ' ' . wp_rand(1000, 9999),
            'post_content' => $faker ? $faker->paragraphs(3, true) : 'This is dummy content for testing purposes.',
            'post_excerpt' => $faker ? $faker->paragraph() : 'This is a dummy excerpt.',
            'post_status'  => 'publish',
            'post_type'    => $post_type,
            'post_author'  => get_current_user_id(),
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if ($post_id && !is_wp_error($post_id)) {
            // Add our meta key to identify dummy posts
            update_post_meta($post_id, WP_DUMMY_CONTENT_FILLER_META_KEY, '1');
            
            // Add featured image if requested
            if ($with_images) {
                $this->attach_featured_image($post_id);
            }
            
            // Assign taxonomies
            if (!empty($created_terms)) {
                foreach ($created_terms as $taxonomy => $terms) {
                    if (!empty($terms)) {
                        $assign_count = isset($taxonomy_config[$taxonomy]['assign']) ? 
                                       $taxonomy_config[$taxonomy]['assign'] : 2;
                        $assign_count = min($assign_count, count($terms));
                        $selected_terms = array_rand(array_flip($terms), $assign_count);
                        if (!is_array($selected_terms)) {
                            $selected_terms = [$selected_terms];
                        }
                        wp_set_post_terms($post_id, $selected_terms, $taxonomy);
                    }
                }
            }
            
            // Add configured post meta
            foreach ($meta_config as $meta_key => $config) {
                $meta_value = $this->generate_faker_value($config['type'], $config['value']);
                if ($meta_value !== '') {
                    update_post_meta($post_id, $meta_key, $meta_value);
                }
            }
        }
        
        return $post_id;
    }
    
    private function create_dummy_terms($taxonomy, $count = 3) {
        $faker = $this->get_faker();
        $created_terms = [];
        
        for ($i = 0; $i < $count; $i++) {
            $term_name = $faker ? $faker->words(2, true) : 'Term ' . ($i + 1);
            $term_slug = sanitize_title($term_name . '-' . wp_rand(100, 999));
            
            $term = wp_insert_term($term_name, $taxonomy, [
                'slug' => $term_slug,
                'description' => $faker ? $faker->sentence() : 'Dummy term description'
            ]);
            
            if (!is_wp_error($term)) {
                $created_terms[] = $term['term_id'];
                
                // Add meta to identify dummy terms
                add_term_meta($term['term_id'], WP_DUMMY_CONTENT_FILLER_META_KEY, '1');
            }
        }
        
        return $created_terms;
    }
    
    private function generate_faker_value($type, $custom_value = '') {
        $faker = $this->get_faker();
        
        if (!$faker) {
            return $custom_value ?: '';
        }
        
        switch ($type) {
            case 'text':
                return $faker->sentence();
            case 'paragraphs':
                return $faker->paragraphs(3, true);
            case 'words':
                return $faker->words(5, true);
            case 'name':
                return $faker->name();
            case 'email':
                return $faker->email();
            case 'phone':
                return $faker->phoneNumber();
            case 'address':
                return $faker->address();
            case 'city':
                return $faker->city();
            case 'country':
                return $faker->country();
            case 'zipcode':
                return $faker->postcode();
            case 'number':
                return $faker->numberBetween(1, 100);
            case 'price':
                return $faker->randomFloat(2, 10, 1000);
            case 'date':
                return $faker->date();
            case 'boolean':
                return $faker->boolean() ? 'yes' : 'no';
            case 'url':
                return $faker->url();
            case 'image_url':
                return $faker->imageUrl();
            case 'color':
                return $faker->colorName();
            case 'hex_color':
                return $faker->hexColor();
            case 'latitude':
                return $faker->latitude();
            case 'longitude':
                return $faker->longitude();
            case 'company':
                return $faker->company();
            default:
                return $custom_value ?: '';
        }
    }
    
    private function attach_featured_image($post_id) {
        $image_dir = WP_DUMMY_CONTENT_FILLER_PLUGIN_DIR . 'assets/img/';
        
        if (!file_exists($image_dir)) {
            return false;
        }
        
        $images = glob($image_dir . 'wp_dummy_content_filler_img_*.{jpg,jpeg,png,gif}', GLOB_BRACE);
        
        if (empty($images)) {
            return false;
        }
        
        $random_image = $images[array_rand($images)];
        $filename = basename($random_image);
        
        // Check if image already exists in media library
        $existing_image = get_page_by_title($filename, OBJECT, 'attachment');
        
        if ($existing_image) {
            set_post_thumbnail($post_id, $existing_image->ID);
            return true;
        }
        
        // Upload image to media library
        $upload_file = wp_upload_bits($filename, null, file_get_contents($random_image));
        
        if (!$upload_file['error']) {
            $wp_filetype = wp_check_filetype($filename, null);
            $attachment = [
                'post_mime_type' => $wp_filetype['type'],
                'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
                'post_content' => '',
                'post_status' => 'inherit'
            ];
            
            $attachment_id = wp_insert_attachment($attachment, $upload_file['file']);
            
            if (!is_wp_error($attachment_id)) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload_file['file']);
                wp_update_attachment_metadata($attachment_id, $attachment_data);
                set_post_thumbnail($post_id, $attachment_id);
                return true;
            }
        }
        
        return false;
    }
    
    private function get_post_meta_keys($post_type = 'post') {
        global $wpdb;
        
        // Get registered meta keys from field plugins
        $meta_keys = [];
        
        // 1. Check for ACF fields
        if (function_exists('acf_get_field_groups')) {
            $field_groups = acf_get_field_groups(['post_type' => $post_type]);
            foreach ($field_groups as $field_group) {
                $fields = acf_get_fields($field_group['key']);
                if ($fields) {
                    foreach ($fields as $field) {
                        $meta_keys[$field['name']] = $field['label'] ?? $field['name'];
                    }
                }
            }
        }
        
        // 2. Check for CMB2 fields
        if (class_exists('CMB2')) {
            $cmb2_boxes = CMB2_Boxes::get_all();
            foreach ($cmb2_boxes as $cmb_id => $cmb) {
                $object_types = $cmb->prop('object_types');
                if (in_array($post_type, (array)$object_types)) {
                    $fields = $cmb->prop('fields');
                    if ($fields) {
                        foreach ($fields as $field) {
                            if (isset($field['id'])) {
                                $meta_keys[$field['id']] = $field['name'] ?? $field['id'];
                            }
                        }
                    }
                }
            }
        }
        
        // 3. Get existing meta keys from database (excluding hidden ones)
        if (empty($meta_keys)) {
            $db_meta_keys = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT pm.meta_key
                FROM {$wpdb->postmeta} pm
                LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE p.post_type = %s
                AND pm.meta_key NOT LIKE '\_%'
                AND pm.meta_key NOT IN ('_edit_lock', '_edit_last')
                ORDER BY pm.meta_key",
                $post_type
            ));
            
            foreach ($db_meta_keys as $key) {
                $meta_keys[$key] = $key;
            }
        }
        
        return $meta_keys;
    }
    
    private function get_post_taxonomies($post_type = 'post') {
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        $available_taxonomies = [];
        
        foreach ($taxonomies as $taxonomy) {
            if ($taxonomy->public && $taxonomy->show_ui) {
                $available_taxonomies[$taxonomy->name] = $taxonomy->label;
            }
        }
        
        return $available_taxonomies;
    }
    
    private function clear_dummy_posts($post_type = 'post') {
        $args = [
            'post_type'      => $post_type,
            'posts_per_page' => -1,
            'meta_key'       => WP_DUMMY_CONTENT_FILLER_META_KEY,
            'meta_value'     => '1',
            'fields'         => 'ids',
        ];
        
        $dummy_posts = get_posts($args);
        
        foreach ($dummy_posts as $post_id) {
            wp_delete_post($post_id, true);
        }
        
        return count($dummy_posts);
    }
    
    private function generate_dummy_users() {
        $count = intval($_POST['user_count'] ?? 5);
        $role = sanitize_text_field($_POST['user_role'] ?? 'subscriber');
        
        $results = ['success' => 0, 'failed' => 0];
        $faker = $this->get_faker();
        
        for ($i = 0; $i < $count; $i++) {
            $username = $faker ? $faker->userName : 'dummyuser_' . uniqid();
            $email = $faker ? $faker->email : $username . '@example.com';
            
            $user_id = wp_create_user($username, 'password', $email);
            
            if (!is_wp_error($user_id)) {
                $user = new WP_User($user_id);
                $user->set_role($role);
                
                // Add our meta key
                update_user_meta($user_id, WP_DUMMY_CONTENT_FILLER_META_KEY, '1');
                
                // Add some dummy user meta
                if ($faker) {
                    update_user_meta($user_id, 'first_name', $faker->firstName);
                    update_user_meta($user_id, 'last_name', $faker->lastName);
                    update_user_meta($user_id, 'description', $faker->paragraph());
                }
                
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }
        
        set_transient('dummy_user_results', [
            'message' => sprintf(
                'Successfully generated %d %s. Failed: %d',
                $results['success'],
                _n('user', 'users', $results['success']),
                $results['failed']
            )
        ], 30);
        
        wp_redirect(add_query_arg(['page' => 'wp-dummy-content-filler-users'], admin_url('admin.php')));
        exit;
    }
    
    private function clear_dummy_users() {
        $args = [
            'meta_key'   => WP_DUMMY_CONTENT_FILLER_META_KEY,
            'meta_value' => '1',
            'fields'     => 'ids',
        ];
        
        $dummy_users = get_users($args);
        
        foreach ($dummy_users as $user_id) {
            if ($user_id != 1) { // Don't delete admin user
                wp_delete_user($user_id);
            }
        }
        
        return count($dummy_users);
    }
    
    public function ajax_get_post_meta() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'wpdcf_ajax_nonce')) {
            wp_die('Unauthorized');
        }
        
        $post_type = sanitize_text_field($_POST['post_type']);
        $meta_keys = $this->get_post_meta_keys($post_type);
        $taxonomies = $this->get_post_taxonomies($post_type);
        
        ob_start();
        ?>
        <div class="post-meta-section">
            <h3>Post Content</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">Post Title</th>
                    <td>
                        <label>
                            <input type="checkbox" name="generate_title" value="1" checked disabled>
                            Auto-generate title
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Post Content</th>
                    <td>
                        <label>
                            <input type="checkbox" name="generate_content" value="1" checked disabled>
                            Auto-generate content
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Post Excerpt</th>
                    <td>
                        <label>
                            <input type="checkbox" name="generate_excerpt" value="1" checked>
                            Auto-generate excerpt
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Featured Image</th>
                    <td>
                        <label>
                            <input type="checkbox" name="with_images" value="1">
                            Add featured images from plugin assets
                        </label>
                    </td>
                </tr>
            </table>
            
            <?php if (!empty($taxonomies)): ?>
            <h3>Taxonomies</h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Taxonomy</th>
                        <th>Create Terms?</th>
                        <th>Number of Terms</th>
                        <th>Assign per Post</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($taxonomies as $taxonomy_slug => $taxonomy_label): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($taxonomy_label); ?></strong><br>
                                <small><?php echo esc_html($taxonomy_slug); ?></small>
                            </td>
                            <td>
                                <select name="taxonomies[<?php echo esc_attr($taxonomy_slug); ?>][create]">
                                    <option value="no">No</option>
                                    <option value="yes">Yes</option>
                                </select>
                            </td>
                            <td>
                                <input type="number" name="taxonomies[<?php echo esc_attr($taxonomy_slug); ?>][count]" 
                                       min="1" max="20" value="3" style="width: 80px;">
                            </td>
                            <td>
                                <input type="number" name="taxonomies[<?php echo esc_attr($taxonomy_slug); ?>][assign]" 
                                       min="1" max="10" value="2" style="width: 80px;">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            
            <?php if (!empty($meta_keys)): ?>
            <h3>Custom Post Meta Fields</h3>
            <p class="description">Configure how each custom field should be filled:</p>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Field Name</th>
                        <th>Meta Key</th>
                        <th>Faker Data Type</th>
                        <th>Custom Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($meta_keys as $meta_key => $field_label): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($field_label); ?></strong>
                            </td>
                            <td>
                                <code><?php echo esc_html($meta_key); ?></code>
                                <input type="hidden" name="post_meta[<?php echo esc_attr($meta_key); ?>][key]" value="<?php echo esc_attr($meta_key); ?>">
                            </td>
                            <td>
                                <select name="post_meta[<?php echo esc_attr($meta_key); ?>][type]">
                                    <option value="">-- Leave Empty --</option>
                                    <?php foreach ($this->faker_types as $type_value => $type_label): ?>
                                        <option value="<?php echo esc_attr($type_value); ?>">
                                            <?php echo esc_html($type_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="post_meta[<?php echo esc_attr($meta_key); ?>][value]" 
                                       placeholder="Custom value (optional)" style="width: 100%;">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <h3>Custom Post Meta Fields</h3>
            <p class="description">No custom fields found for this post type.</p>
            <?php endif; ?>
        </div>
        <?php
        wp_send_json_success(ob_get_clean());
    }
    
    public function ajax_get_dummy_posts() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $post_type = !empty($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : '';
        
        $args = [
            'post_type'      => $post_type ?: 'any',
            'posts_per_page' => 50,
            'meta_key'       => WP_DUMMY_CONTENT_FILLER_META_KEY,
            'meta_value'     => '1',
        ];
        
        $dummy_posts = get_posts($args);
        
        if (empty($dummy_posts)) {
            wp_send_json_error('No dummy posts found.');
        }
        
        ob_start();
        ?>
        <p>Found <?php echo count($dummy_posts); ?> dummy posts.</p>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Post Type</th>
                    <th>Taxonomies</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dummy_posts as $post): 
                    $taxonomies = get_object_taxonomies($post->post_type);
                    $post_terms = [];
                    foreach ($taxonomies as $taxonomy) {
                        $terms = get_the_terms($post->ID, $taxonomy);
                        if ($terms && !is_wp_error($terms)) {
                            foreach ($terms as $term) {
                                $post_terms[] = $term->name;
                            }
                        }
                    }
                ?>
                    <tr>
                        <td><?php echo esc_html($post->ID); ?></td>
                        <td>
                            <strong><?php echo esc_html($post->post_title); ?></strong>
                            <?php if (has_post_thumbnail($post->ID)): ?>
                                <span class="dashicons dashicons-format-image" title="Has featured image"></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(get_post_type_object($post->post_type)->labels->singular_name); ?></td>
                        <td>
                            <?php if (!empty($post_terms)): ?>
                                <?php echo esc_html(implode(', ', $post_terms)); ?>
                            <?php else: ?>
                                <em>None</em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(get_the_date('', $post)); ?></td>
                        <td>
                            <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>" class="button button-small">Edit</a>
                            <a href="<?php echo esc_url(get_permalink($post->ID)); ?>" class="button button-small" target="_blank">View</a>
                            <a href="<?php echo esc_url(admin_url('post.php?action=delete&amp;post=' . $post->ID . '&amp;_wpnonce=' . wp_create_nonce('delete-post_' . $post->ID))); ?>" class="button button-small button-danger" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        wp_send_json_success(ob_get_clean());
    }
    
    public function render_post_types_page() {
        $post_types = get_post_types(['public' => true], 'objects');
        $selected_post_type = sanitize_text_field($_POST['post_type'] ?? 'post');
        
        ?>
        <div class="wrap wp-dummy-content-filler">
            <h1>Dummy Content Filler - Post Types</h1>
            
            <?php $this->show_results_message(); ?>
            
            <h2 class="nav-tab-wrapper">
                <a href="#generate-tab" class="nav-tab nav-tab-active">Generate Posts</a>
                <a href="#manage-tab" class="nav-tab">Manage Dummy Posts</a>
            </h2>
            
            <div id="generate-tab" class="tab-content active">
                <form method="post" action="" id="generate-posts-form">
                    <?php wp_nonce_field('generate_dummy_posts'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Post Type</th>
                            <td>
                                <select name="post_type" id="post-type-selector">
                                    <?php foreach ($post_types as $type): ?>
                                        <option value="<?php echo esc_attr($type->name); ?>" <?php selected($selected_post_type, $type->name); ?>>
                                            <?php echo esc_html($type->label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Number of Posts</th>
                            <td>
                                <input type="number" name="post_count" min="1" max="100" value="5">
                            </td>
                        </tr>
                    </table>
                    
                    <div id="post-meta-configuration">
                        <!-- Loaded via AJAX -->
                    </div>
                    
                    <p class="submit">
                        <input type="submit" name="generate_posts" class="button button-primary" value="Generate Posts">
                    </p>
                </form>
            </div>
            
            <div id="manage-tab" class="tab-content">
                <h3>Manage Dummy Posts</h3>
                <div class="filter-section">
                    <form method="get" action="" class="filter-dummy-posts">
                        <input type="hidden" name="page" value="wp-dummy-content-filler">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Filter by Post Type</th>
                                <td>
                                    <select name="filter_post_type" id="filter-post-type">
                                        <option value="">All Post Types</option>
                                        <?php foreach ($post_types as $type): ?>
                                            <option value="<?php echo esc_attr($type->name); ?>">
                                                <?php echo esc_html($type->label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" id="apply-filter" class="button">Apply Filter</button>
                                </td>
                            </tr>
                        </table>
                    </form>
                </div>
                
                <div id="dummy-posts-list">
                    <p>Select a post type and click "Apply Filter" to see dummy posts.</p>
                </div>
                
                <div id="delete-section" style="display:none;">
                    <h4>Delete Dummy Posts</h4>
                    <form method="get" action="">
                        <input type="hidden" name="page" value="wp-dummy-content-filler">
                        <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('clear_dummy_posts'); ?>">
                        <input type="hidden" name="clear_dummy_posts" value="1">
                        <p>
                            <select name="post_type" id="delete-post-type" required>
                                <option value="">Select post type to delete</option>
                                <?php foreach ($post_types as $type): ?>
                                    <option value="<?php echo esc_attr($type->name); ?>">
                                        <?php echo esc_html($type->label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="button button-danger" onclick="return confirm('Are you sure? This will delete ALL dummy posts of selected type.')">Delete All Dummy Posts</button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function render_users_page() {
        ?>
        <div class="wrap wp-dummy-content-filler">
            <h1>Dummy Content Filler - Users</h1>
            
            <?php
            $results = get_transient('dummy_user_results');
            if ($results) {
                delete_transient('dummy_user_results');
                echo '<div class="notice notice-success"><p>' . esc_html($results['message']) . '</p></div>';
            }
            ?>
            
            <h2 class="nav-tab-wrapper">
                <a href="#generate-users-tab" class="nav-tab nav-tab-active">Generate Users</a>
                <a href="#manage-users-tab" class="nav-tab">Manage Dummy Users</a>
            </h2>
            
            <div id="generate-users-tab" class="tab-content active">
                <form method="post" action="">
                    <?php wp_nonce_field('generate_dummy_users'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Number of Users</th>
                            <td>
                                <input type="number" name="user_count" min="1" max="50" value="5">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">User Role</th>
                            <td>
                                <select name="user_role">
                                    <?php
                                    $roles = wp_roles()->get_names();
                                    foreach ($roles as $role_value => $role_name) {
                                        echo '<option value="' . esc_attr($role_value) . '">' . esc_html($role_name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="generate_users" class="button button-primary" value="Generate Users">
                    </p>
                </form>
            </div>
            
            <div id="manage-users-tab" class="tab-content">
                <h3>Dummy Users Created by Plugin</h3>
                <?php
                $dummy_users = get_users([
                    'meta_key'   => WP_DUMMY_CONTENT_FILLER_META_KEY,
                    'meta_value' => '1',
                ]);
                
                if ($dummy_users) {
                    echo '<p>Found ' . count($dummy_users) . ' dummy users.</p>';
                    echo '<table class="widefat fixed striped">';
                    echo '<thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Name</th><th>Role</th><th>Actions</th></tr></thead>';
                    echo '<tbody>';
                    
                    foreach ($dummy_users as $user) {
                        $first_name = get_user_meta($user->ID, 'first_name', true);
                        $last_name = get_user_meta($user->ID, 'last_name', true);
                        $full_name = trim($first_name . ' ' . $last_name);
                        
                        echo '<tr>';
                        echo '<td>' . esc_html($user->ID) . '</td>';
                        echo '<td>' . esc_html($user->user_login) . '</td>';
                        echo '<td>' . esc_html($user->user_email) . '</td>';
                        echo '<td>' . esc_html($full_name ?: 'N/A') . '</td>';
                        echo '<td>' . esc_html(implode(', ', $user->roles)) . '</td>';
                        echo '<td>';
                        echo '<a href="' . esc_url(admin_url('user-edit.php?user_id=' . $user->ID)) . '" class="button button-small">Edit</a> ';
                        echo '<a href="' . esc_url(admin_url('profile.php?user_id=' . $user->ID)) . '" class="button button-small" target="_blank">View Profile</a>';
                        echo '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody></table>';
                    
                    $nonce = wp_create_nonce('clear_dummy_users');
                    echo '<p style="margin-top: 20px;"><a href="' . esc_url(add_query_arg([
                        'page' => 'wp-dummy-content-filler-users',
                        'clear_dummy_users' => '1',
                        '_wpnonce' => $nonce
                    ], admin_url('admin.php'))) . '" class="button button-danger" onclick="return confirm(\'Are you sure? This will delete ALL dummy users except admin.\')">Delete All Dummy Users</a></p>';
                } else {
                    echo '<p>No dummy users found.</p>';
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    public function render_products_page() {
        ?>
        <div class="wrap">
            <h1>Dummy Content Filler - WooCommerce Products</h1>
            <p>WooCommerce product generation will be implemented in Phase 2.</p>
            <p>This tab will allow generation of dummy WooCommerce products with variations, categories, and attributes.</p>
        </div>
        <?php
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>WP Dummy Content Filler - Settings</h1>
            <div class="card" style="max-width: 600px;">
                <h2 class="title">Welcome to Plugin Settings</h2>
                <p>This is the settings page for WP Dummy Content Filler plugin.</p>
                <p>More settings and options will be added in future updates.</p>
            </div>
        </div>
        <?php
    }
    
    private function show_results_message() {
        $results = get_transient('dummy_content_results');
        if ($results) {
            delete_transient('dummy_content_results');
            echo '<div class="notice notice-success"><p>' . esc_html($results['message']) . '</p></div>';
        }
    }
}