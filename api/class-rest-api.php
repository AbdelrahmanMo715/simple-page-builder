<?php
class SPB_Rest_API {
    
    private static $instance = null;
    private $namespace = 'pagebuilder/v1';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_filter('rest_pre_serve_request', array($this, 'log_api_request'), 10, 4);
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Main endpoint for creating pages
        register_rest_route($this->namespace, '/create-pages', array(
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_pages'),
                'permission_callback' => array($this, 'check_permission'),
                'args' => $this->get_endpoint_args()
            ),
            'schema' => array($this, 'get_schema')
        ));
        
        // Health check endpoint (no auth required)
        register_rest_route($this->namespace, '/health', array(
            'methods' => 'GET',
            'callback' => array($this, 'health_check'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Permission callback for create-pages endpoint
     */
    public function check_permission($request) {
        $auth = SPB_Api_Auth::get_instance();
        $api_key = $auth->get_current_api_key();
        
        if (!$api_key) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access this endpoint.', 'simple-page-builder'),
                array('status' => 401)
            );
        }
        
        return true;
    }
    
    /**
     * Main endpoint: Create bulk pages
     */
    public function create_pages($request) {
        // Start timing
        $start_time = microtime(true);
        
        // Get API key data
        $auth = SPB_Api_Auth::get_instance();
        $api_key = $auth->get_current_api_key();
        
        // Generate request ID for tracking
        $request_id = 'req_' . wp_generate_password(12, false);
        
        // Get parameters
        $params = $request->get_params();
        $pages_data = isset($params['pages']) ? $params['pages'] : array();
        
        // Validate pages data
        if (empty($pages_data) || !is_array($pages_data)) {
            return $this->error_response(
                'invalid_data',
                __('No pages data provided or invalid format', 'simple-page-builder'),
                400,
                $request_id,
                $api_key->id,
                $start_time
            );
        }
        
        // Limit maximum pages per request
        $max_pages = apply_filters('spb_max_pages_per_request', 100);
        if (count($pages_data) > $max_pages) {
            return $this->error_response(
                'too_many_pages',
                sprintf(__('Maximum %d pages per request allowed', 'simple-page-builder'), $max_pages),
                400,
                $request_id,
                $api_key->id,
                $start_time
            );
        }
        
        // Process pages
        $created_pages = array();
        $errors = array();
        
        foreach ($pages_data as $index => $page_data) {
            $result = $this->create_single_page($page_data, $api_key->id);
            
            if (is_wp_error($result)) {
                $errors[] = array(
                    'index' => $index,
                    'title' => isset($page_data['title']) ? $page_data['title'] : 'Untitled',
                    'error' => $result->get_error_message()
                );
            } else {
                $created_pages[] = $result;
            }
        }
        
        // Calculate response time
        $response_time = round((microtime(true) - $start_time) * 1000, 2); // in milliseconds
        
        // Prepare response
        $response_data = array(
            'success' => true,
            'request_id' => $request_id,
            'message' => sprintf(
                __('Created %d pages, %d failed', 'simple-page-builder'),
                count($created_pages),
                count($errors)
            ),
            'data' => array(
                'total_requested' => count($pages_data),
                'total_created' => count($created_pages),
                'total_failed' => count($errors),
                'created_pages' => $created_pages,
                'errors' => $errors,
                'response_time_ms' => $response_time
            )
        );
        
        // Log successful request
        $this->log_request(
            $api_key->id,
            'POST /create-pages',
            200,
            $params,
            $response_data,
            count($created_pages),
            $response_time,
            $request_id
        );
        
        // Trigger webhook notification
        if (!empty($created_pages)) {
            $this->trigger_webhook_notification($request_id, $api_key, $created_pages);
        }
        
        return rest_ensure_response($response_data);
    }
    
    /**
     * Create a single page
     */
    private function create_single_page($page_data, $api_key_id) {
        // Default page arguments
        $defaults = array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_author' => $this->get_author_id($api_key_id),
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        );
        
        // Validate required fields
        if (empty($page_data['title'])) {
            return new WP_Error('missing_title', __('Page title is required', 'simple-page-builder'));
        }
        
        // Prepare page arguments
        $page_args = array(
            'post_title' => sanitize_text_field($page_data['title']),
            'post_content' => isset($page_data['content']) ? wp_kses_post($page_data['content']) : '',
            'post_excerpt' => isset($page_data['excerpt']) ? sanitize_text_field($page_data['excerpt']) : '',
            'post_status' => isset($page_data['status']) ? $this->validate_status($page_data['status']) : 'publish',
            'menu_order' => isset($page_data['menu_order']) ? intval($page_data['menu_order']) : 0
        );
        
        // Merge with defaults
        $page_args = wp_parse_args($page_args, $defaults);
        
        // Set page template if provided
        if (isset($page_data['template']) && $this->is_valid_template($page_data['template'])) {
            $page_args['meta_input']['_wp_page_template'] = sanitize_text_field($page_data['template']);
        }
        
        // Allow filtering of page arguments
        $page_args = apply_filters('spb_page_arguments', $page_args, $page_data, $api_key_id);
        
        // Insert the page
        $page_id = wp_insert_post($page_args, true);
        
        if (is_wp_error($page_id)) {
            return $page_id;
        }
        
        // Add custom meta fields if provided
        if (isset($page_data['meta']) && is_array($page_data['meta'])) {
            foreach ($page_data['meta'] as $meta_key => $meta_value) {
                // Only allow certain meta keys or use filter to validate
                if (apply_filters('spb_allow_meta_key', true, $meta_key, $api_key_id)) {
                    update_post_meta($page_id, sanitize_key($meta_key), $this->sanitize_meta_value($meta_value));
                }
            }
        }
        
        // Add taxonomy terms if provided
        if (isset($page_data['taxonomies']) && is_array($page_data['taxonomies'])) {
            foreach ($page_data['taxonomies'] as $taxonomy => $terms) {
                if (taxonomy_exists($taxonomy)) {
                    wp_set_post_terms($page_id, (array)$terms, sanitize_text_field($taxonomy));
                }
            }
        }
        
        // Log page creation in post meta
        update_post_meta($page_id, '_spb_created_via_api', true);
        update_post_meta($page_id, '_spb_api_key_id', $api_key_id);
        update_post_meta($page_id, '_spb_created_at', current_time('mysql'));
        
        // Return page data
        return array(
            'id' => $page_id,
            'title' => get_the_title($page_id),
            'url' => get_permalink($page_id),
            'edit_url' => get_edit_post_link($page_id, 'url'),
            'status' => get_post_status($page_id)
        );
    }
    
    /**
     * Health check endpoint
     */
    public function health_check() {
        global $wpdb;
        
        $status = array(
            'status' => 'healthy',
            'timestamp' => current_time('mysql'),
            'plugin_version' => SPB_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'api_enabled' => true,
            'total_api_keys' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}" . SPB_TABLE_API_KEYS . " WHERE status = 'active'"),
            'total_pages_created' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'page' 
                 AND ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_spb_created_via_api')"
            ))
        );
        
        return rest_ensure_response($status);
    }
    
    /**
     * Get endpoint arguments for validation
     */
    private function get_endpoint_args() {
        return array(
            'pages' => array(
                'required' => true,
                'type' => 'array',
                'description' => __('Array of pages to create', 'simple-page-builder'),
                'items' => array(
                    'type' => 'object',
                    'properties' => array(
                        'title' => array(
                            'type' => 'string',
                            'required' => true,
                            'description' => __('Page title', 'simple-page-builder')
                        ),
                        'content' => array(
                            'type' => 'string',
                            'description' => __('Page content (HTML allowed)', 'simple-page-builder')
                        ),
                        'excerpt' => array(
                            'type' => 'string',
                            'description' => __('Page excerpt', 'simple-page-builder')
                        ),
                        'status' => array(
                            'type' => 'string',
                            'enum' => array('draft', 'publish', 'pending', 'private'),
                            'default' => 'publish',
                            'description' => __('Page status', 'simple-page-builder')
                        ),
                        'template' => array(
                            'type' => 'string',
                            'description' => __('Page template', 'simple-page-builder')
                        ),
                        'menu_order' => array(
                            'type' => 'integer',
                            'description' => __('Page order in menu', 'simple-page-builder')
                        ),
                        'meta' => array(
                            'type' => 'object',
                            'description' => __('Custom meta fields', 'simple-page-builder')
                        ),
                        'taxonomies' => array(
                            'type' => 'object',
                            'description' => __('Taxonomy terms to assign', 'simple-page-builder')
                        )
                    )
                )
            )
        );
    }
    
    /**
     * Get JSON schema for the endpoint
     */
    public function get_schema() {
        return array(
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'Create Pages',
            'description' => __('Create multiple pages in bulk', 'simple-page-builder'),
            'type' => 'object',
            'properties' => array(
                'pages' => array(
                    'description' => __('Array of pages to create', 'simple-page-builder'),
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'title' => array(
                                'type' => 'string',
                                'description' => __('Page title', 'simple-page-builder')
                            )
                        ),
                        'required' => array('title')
                    )
                )
            ),
            'required' => array('pages')
        );
    }
    
    /**
     * Helper methods for validation and processing
     */
    private function validate_status($status) {
        $allowed_statuses = array('draft', 'publish', 'pending', 'private');
        return in_array($status, $allowed_statuses) ? $status : 'publish';
    }
    
    private function is_valid_template($template) {
        $templates = wp_get_theme()->get_page_templates();
        return array_key_exists($template, $templates);
    }
    
    private function sanitize_meta_value($value) {
        if (is_array($value)) {
            return array_map(array($this, 'sanitize_meta_value'), $value);
        }
        return sanitize_text_field($value);
    }
    
    private function get_author_id($api_key_id) {
        // Get the user ID associated with the API key, or use default
        global $wpdb;
        
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}" . SPB_TABLE_API_KEYS . " WHERE id = %d",
            $api_key_id
        ));
        
        if ($user_id) {
            return $user_id;
        }
        
        // Default to first admin user
        $admins = get_users(array('role' => 'administrator', 'number' => 1));
        return $admins ? $admins[0]->ID : 1;
    }
    
    /**
     * Error response helper
     */
    private function error_response($code, $message, $status, $request_id, $api_key_id, $start_time) {
        $response_time = round((microtime(true) - $start_time) * 1000, 2);
        
        $error_data = array(
            'success' => false,
            'request_id' => $request_id,
            'error' => array(
                'code' => $code,
                'message' => $message
            ),
            'response_time_ms' => $response_time
        );
        
        // Log error
        $this->log_request(
            $api_key_id,
            'POST /create-pages',
            $status,
            $_POST,
            $error_data,
            0,
            $response_time,
            $request_id
        );
        
        return new WP_Error($code, $message, array('status' => $status, 'data' => $error_data));
    }
    
    /**
     * Log API request
     */
    private function log_request($api_key_id, $endpoint, $status, $request_body, $response_body, $pages_created, $response_time, $request_id = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . SPB_TABLE_ACTIVITY_LOGS;
        
        $data = array(
            'api_key_id' => $api_key_id,
            'endpoint' => $endpoint,
            'method' => 'POST',
            'status_code' => $status,
            'request_body' => json_encode($request_body),
            'response_body' => json_encode($response_body),
            'pages_created' => $pages_created,
            'response_time' => $response_time,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => current_time('mysql')
        );
        
        if ($request_id) {
            // We'll store request_id in response_body for now, or add a column if needed
            $response_data = json_decode($data['response_body'], true);
            $response_data['request_id'] = $request_id;
            $data['response_body'] = json_encode($response_data);
        }
        
        $wpdb->insert($table_name, $data);
        
        // Return the log ID
        return $wpdb->insert_id;
    }
    
    /**
     * Get client IP (duplicate from auth class)
     */
    private function get_client_ip() {
        // Same implementation as in SPB_Api_Auth
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key])) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }
        return '0.0.0.0';
    }
    
    /**
     * Trigger webhook notification
     */
    private function trigger_webhook_notification($request_id, $api_key, $created_pages) {
        // We'll implement this in the webhook handler class
        do_action('spb_trigger_webhook', $request_id, $api_key, $created_pages);
    }
    
    /**
     * Log all API requests
     */
    public function log_api_request($served, $result, $request, $rest_server) {
        // This is called for all REST API requests
        // We could log all requests here if needed
        return $served;
    }
}