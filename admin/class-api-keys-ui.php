<?php
class SPB_Api_Keys_UI {
    
    private static $instance = null;
    private $keys_manager;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->keys_manager = SPB_Api_Keys_Manager::get_instance();
        
        // Add admin notices
        add_action('admin_notices', array($this, 'display_admin_notices'));
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'tools_page_simple-page-builder') {
            return;
        }
        
        wp_enqueue_script('clipboard');
        wp_enqueue_style('spb-admin-style', SPB_PLUGIN_URL . 'admin/css/admin-style.css', array(), SPB_VERSION);
        wp_enqueue_script('spb-admin-script', SPB_PLUGIN_URL . 'admin/js/admin-script.js', array('jquery', 'clipboard'), SPB_VERSION, true);
        
        wp_localize_script('spb-admin-script', 'spb_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spb_admin_nonce'),
            'copy_text' => __('Copy', 'simple-page-builder'),
            'copied_text' => __('Copied!', 'simple-page-builder')
        ));
    }
    
    /**
     * Render the API Keys management page
     */
    public function render_page() {
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'simple-page-builder'));
        }
        
        // Get all API keys
        $api_keys = $this->keys_manager->get_api_keys();
        
        // Get key to view details
        $view_key_id = isset($_GET['view_key']) ? intval($_GET['view_key']) : 0;
        $view_key = $view_key_id ? $this->keys_manager->get_api_key($view_key_id) : null;
        
        ?>
        <div class="spb-api-keys-page">
            <div class="spb-header">
                <h2><?php _e('API Keys Management', 'simple-page-builder'); ?></h2>
                <p class="description">
                    <?php _e('Generate and manage API keys for external applications to create pages.', 'simple-page-builder'); ?>
                </p>
            </div>
            
            <?php if ($view_key): ?>
                <?php $this->render_key_details($view_key); ?>
            <?php else: ?>
                <div class="spb-keys-grid">
                    <!-- Generate New Key Card -->
                    <div class="spb-card spb-generate-card">
                        <h3><?php _e('Generate New API Key', 'simple-page-builder'); ?></h3>
                        
                        <form method="post" action="" class="spb-generate-form">
                            <?php wp_nonce_field('spb_admin_action', '_wpnonce'); ?>
                            <input type="hidden" name="spb_action" value="generate_key">
                            
                            <div class="spb-form-group">
                                <label for="key_name">
                                    <?php _e('Key Name', 'simple-page-builder'); ?>
                                    <span class="required">*</span>
                                </label>
                                <input type="text" 
                                       id="key_name" 
                                       name="key_name" 
                                       class="regular-text" 
                                       required
                                       placeholder="<?php esc_attr_e('e.g., Production Server, Mobile App', 'simple-page-builder'); ?>">
                                <p class="description">
                                    <?php _e('A descriptive name to identify this key', 'simple-page-builder'); ?>
                                </p>
                            </div>
                            
                            <div class="spb-form-group">
                                <label for="expiration_days">
                                    <?php _e('Expires After', 'simple-page-builder'); ?>
                                </label>
                                <select id="expiration_days" name="expiration_days" class="regular-text">
                                    <option value="0"><?php _e('Never expires', 'simple-page-builder'); ?></option>
                                    <option value="30"><?php _e('30 days', 'simple-page-builder'); ?></option>
                                    <option value="60"><?php _e('60 days', 'simple-page-builder'); ?></option>
                                    <option value="90"><?php _e('90 days', 'simple-page-builder'); ?></option>
                                    <option value="180"><?php _e('180 days', 'simple-page-builder'); ?></option>
                                    <option value="365"><?php _e('1 year', 'simple-page-builder'); ?></option>
                                </select>
                            </div>
                            
                            <div class="spb-form-group">
                                <label for="rate_limit">
                                    <?php _e('Rate Limit (requests/hour)', 'simple-page-builder'); ?>
                                </label>
                                <input type="number" 
                                       id="rate_limit" 
                                       name="rate_limit" 
                                       class="regular-text" 
                                       min="1" 
                                       max="10000" 
                                       value="100">
                                <p class="description">
                                    <?php _e('Maximum requests allowed per hour', 'simple-page-builder'); ?>
                                </p>
                            </div>
                            
                            <div class="spb-form-actions">
                                <button type="submit" class="button button-primary">
                                    <?php _e('Generate API Key', 'simple-page-builder'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- API Keys List -->
                    <div class="spb-card spb-keys-list-card">
                        <div class="spb-card-header">
                            <h3><?php _e('API Keys', 'simple-page-builder'); ?></h3>
                            <span class="spb-badge"><?php echo count($api_keys); ?></span>
                        </div>
                        
                        <?php if (empty($api_keys)): ?>
                            <div class="spb-empty-state">
                                <p><?php _e('No API keys generated yet.', 'simple-page-builder'); ?></p>
                                <p><?php _e('Generate your first key to get started.', 'simple-page-builder'); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="spb-table-responsive">
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th class="check-column"><input type="checkbox" id="spb-select-all"></th>
                                            <th><?php _e('Key Name', 'simple-page-builder'); ?></th>
                                            <th><?php _e('API Key', 'simple-page-builder'); ?></th>
                                            <th><?php _e('Status', 'simple-page-builder'); ?></th>
                                            <th><?php _e('Created', 'simple-page-builder'); ?></th>
                                            <th><?php _e('Last Used', 'simple-page-builder'); ?></th>
                                            <th><?php _e('Requests', 'simple-page-builder'); ?></th>
                                            <th><?php _e('Actions', 'simple-page-builder'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($api_keys as $key): ?>
                                            <?php 
                                            // Get the full API key (decrypted from database)
                                            $full_key = $this->keys_manager->get_full_api_key($key->id);
                                            ?>
                                            <tr>
                                                <th scope="row" class="check-column">
                                                    <input type="checkbox" name="key_ids[]" value="<?php echo esc_attr($key->id); ?>">
                                                </th>
                                                <td>
                                                    <strong><?php echo esc_html($key->key_name); ?></strong>
                                                    <?php if ($key->user_name): ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php printf(__('By: %s', 'simple-page-builder'), esc_html($key->user_name)); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="spb-key-display-wrapper">
                                                        <?php if ($full_key): ?>
                                                            <div class="spb-full-key-display">
                                                                <code class="spb-full-key" id="spb-full-key-<?php echo esc_attr($key->id); ?>">
                                                                    <?php echo esc_html($full_key); ?>
                                                                </code>
                                                                <button type="button" 
                                                                        class="button button-small spb-copy-key-btn" 
                                                                        data-clipboard-target="#spb-full-key-<?php echo esc_attr($key->id); ?>"
                                                                        data-key-id="<?php echo esc_attr($key->id); ?>">
                                                                    <span class="dashicons dashicons-clipboard"></span>
                                                                </button>
                                                            </div>
                                                        <?php else: ?>
                                                            <code><?php echo esc_html($key->api_key_preview); ?></code>
                                                            <small class="spb-text-muted">
                                                                <?php _e('(Full key not available)', 'simple-page-builder'); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php echo $this->get_status_badge($key->status); ?>
                                                    <?php if ($key->is_expired ?? false): ?>
                                                        <span class="spb-badge spb-badge-warning">
                                                            <?php _e('Expired', 'simple-page-builder'); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo esc_html($key->created_at_formatted); ?>
                                                    <?php if ($key->expires_at_formatted !== __('Never', 'simple-page-builder')): ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php printf(__('Expires: %s', 'simple-page-builder'), esc_html($key->expires_at_formatted)); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo esc_html($key->last_used_human); ?>
                                                </td>
                                                <td>
                                                    <?php echo intval($key->request_count); ?>
                                                </td>
                                                <td>
                                                    <div class="spb-action-buttons">
                                                        <a href="<?php echo esc_url(add_query_arg(array(
                                                            'page' => 'simple-page-builder',
                                                            'tab' => 'api-keys',
                                                            'view_key' => $key->id
                                                        ), admin_url('tools.php'))); ?>"
                                                           class="button button-small">
                                                            <?php _e('View', 'simple-page-builder'); ?>
                                                        </a>
                                                        
                                                        <?php if ($key->status === 'active'): ?>
                                                            <button type="button" 
                                                                    class="button button-small button-warning spb-revoke-btn"
                                                                    data-key-id="<?php echo esc_attr($key->id); ?>"
                                                                    data-key-name="<?php echo esc_attr($key->key_name); ?>">
                                                                <?php _e('Revoke', 'simple-page-builder'); ?>
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" 
                                                                    class="button button-small spb-delete-btn"
                                                                    data-key-id="<?php echo esc_attr($key->id); ?>"
                                                                    data-key-name="<?php echo esc_attr($key->key_name); ?>">
                                                                <?php _e('Delete', 'simple-page-builder'); ?>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Bulk Actions -->
                            <div class="spb-bulk-actions">
                                <select id="spb-bulk-action" class="spb-bulk-select">
                                    <option value=""><?php _e('Bulk Actions', 'simple-page-builder'); ?></option>
                                    <option value="revoke"><?php _e('Revoke Selected', 'simple-page-builder'); ?></option>
                                    <option value="delete"><?php _e('Delete Selected', 'simple-page-builder'); ?></option>
                                </select>
                                <button type="button" id="spb-bulk-apply" class="button">
                                    <?php _e('Apply', 'simple-page-builder'); ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Confirmation Modal -->
        <div id="spb-confirm-modal" class="spb-modal" style="display: none;">
            <div class="spb-modal-content">
                <div class="spb-modal-header">
                    <h3 id="spb-confirm-title"></h3>
                    <button type="button" class="spb-modal-close">&times;</button>
                </div>
                <div class="spb-modal-body">
                    <p id="spb-confirm-message"></p>
                </div>
                <div class="spb-modal-footer">
                    <button type="button" class="button spb-modal-close">
                        <?php _e('Cancel', 'simple-page-builder'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="spb-confirm-action">
                        <?php _e('Confirm', 'simple-page-builder'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render key details view
     */
    private function render_key_details($key) {
        $stats = $this->keys_manager->get_key_statistics($key->id);
        
        // Get the full API key
        $full_key = $this->keys_manager->get_full_api_key($key->id);
        
        ?>
        <div class="spb-key-details">
            <div class="spb-details-header">
                <a href="<?php echo esc_url(remove_query_arg('view_key')); ?>" class="button">
                    &larr; <?php _e('Back to Keys', 'simple-page-builder'); ?>
                </a>
                <h2><?php echo esc_html($key->key_name); ?></h2>
                <div class="spb-key-status">
                    <?php echo $this->get_status_badge($key->status); ?>
                    <?php if ($key->is_expired ?? false): ?>
                        <span class="spb-badge spb-badge-warning">
                            <?php _e('Expired', 'simple-page-builder'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="spb-details-grid">
                <!-- Key Information Card -->
                <div class="spb-card">
                    <h3><?php _e('Key Information', 'simple-page-builder'); ?></h3>
                    <table class="spb-info-table">
                        <tr>
                            <th><?php _e('Key ID', 'simple-page-builder'); ?></th>
                            <td>
                                <?php if ($full_key): ?>
                                    <div class="spb-full-key-display">
                                        <code class="spb-full-key" id="spb-details-key-<?php echo esc_attr($key->id); ?>">
                                            <?php echo esc_html($full_key); ?>
                                        </code>
                                        <button type="button" 
                                                class="button button-small spb-copy-key-btn" 
                                                data-clipboard-target="#spb-details-key-<?php echo esc_attr($key->id); ?>"
                                                data-key-id="<?php echo esc_attr($key->id); ?>">
                                            <span class="dashicons dashicons-clipboard"></span>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <code><?php echo esc_html($key->api_key_preview); ?></code>
                                    <small class="spb-text-muted">
                                        <?php _e('(Full key not available)', 'simple-page-builder'); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Created', 'simple-page-builder'); ?></th>
                            <td><?php echo esc_html($key->created_at_formatted); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Created By', 'simple-page-builder'); ?></th>
                            <td>
                                <?php echo esc_html($key->user_name); ?>
                                <?php if ($key->user_email): ?>
                                    <br><small><?php echo esc_html($key->user_email); ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Expires', 'simple-page-builder'); ?></th>
                            <td>
                                <?php echo esc_html($key->expires_at_formatted); ?>
                                <?php if ($key->expires_at_formatted !== __('Never', 'simple-page-builder')): ?>
                                    <br><small>(<?php printf(__('%s remaining', 'simple-page-builder'), esc_html($key->expires_in)); ?>)</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Last Used', 'simple-page-builder'); ?></th>
                            <td><?php echo esc_html($key->last_used_human); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Permissions', 'simple-page-builder'); ?></th>
                            <td>
                                <?php foreach ($key->permissions_array as $permission): ?>
                                    <span class="spb-badge spb-badge-info">
                                        <?php echo esc_html($permission); ?>
                                    </span>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Rate Limit', 'simple-page-builder'); ?></th>
                            <td><?php echo intval($key->rate_limit_hourly); ?> <?php _e('requests/hour', 'simple-page-builder'); ?></td>
                        </tr>
                    </table>
                    
                    <div class="spb-key-actions">
                        <?php if ($key->status === 'active'): ?>
                            <button type="button" 
                                    class="button button-primary spb-regenerate-secret-btn"
                                    data-key-id="<?php echo esc_attr($key->id); ?>">
                                <?php _e('Regenerate Secret Key', 'simple-page-builder'); ?>
                            </button>
                            <button type="button" 
                                    class="button button-warning spb-revoke-btn"
                                    data-key-id="<?php echo esc_attr($key->id); ?>"
                                    data-key-name="<?php echo esc_attr($key->key_name); ?>">
                                <?php _e('Revoke Key', 'simple-page-builder'); ?>
                            </button>
                        <?php else: ?>
                            <button type="button" 
                                    class="button button-danger spb-delete-btn"
                                    data-key-id="<?php echo esc_attr($key->id); ?>"
                                    data-key-name="<?php echo esc_attr($key->key_name); ?>">
                                <?php _e('Delete Key', 'simple-page-builder'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statistics Card -->
                <div class="spb-card">
                    <h3><?php _e('Usage Statistics', 'simple-page-builder'); ?></h3>
                    <div class="spb-stats-grid">
                        <div class="spb-stat-card">
                            <div class="spb-stat-value"><?php echo intval($stats['total_requests']); ?></div>
                            <div class="spb-stat-label"><?php _e('Total Requests', 'simple-page-builder'); ?></div>
                        </div>
                        <div class="spb-stat-card">
                            <div class="spb-stat-value"><?php echo intval($stats['total_pages_created']); ?></div>
                            <div class="spb-stat-label"><?php _e('Pages Created', 'simple-page-builder'); ?></div>
                        </div>
                        <div class="spb-stat-card">
                            <div class="spb-stat-value"><?php echo intval($stats['successful_requests']); ?></div>
                            <div class="spb-stat-label"><?php _e('Successful', 'simple-page-builder'); ?></div>
                        </div>
                        <div class="spb-stat-card">
                            <div class="spb-stat-value"><?php echo intval($stats['failed_requests']); ?></div>
                            <div class="spb-stat-label"><?php _e('Failed', 'simple-page-builder'); ?></div>
                        </div>
                        <div class="spb-stat-card">
                            <div class="spb-stat-value"><?php echo intval($stats['requests_today']); ?></div>
                            <div class="spb-stat-label"><?php _e('Today', 'simple-page-builder'); ?></div>
                        </div>
                        <div class="spb-stat-card">
                            <div class="spb-stat-value"><?php echo intval($stats['requests_this_month']); ?></div>
                            <div class="spb-stat-label"><?php _e('This Month', 'simple-page-builder'); ?></div>
                        </div>
                    </div>
                    
                    <div class="spb-stat-details">
                        <h4><?php _e('Recent Activity', 'simple-page-builder'); ?></h4>
                        <?php $this->render_recent_activity($key->id); ?>
                    </div>
                </div>
            </div>
            
            <!-- Usage Instructions -->
            <div class="spb-card">
                <h3><?php _e('Usage Instructions', 'simple-page-builder'); ?></h3>
                <div class="spb-usage-instructions">
                    <p><?php _e('Use this API key in the X-API-Key header of your requests:', 'simple-page-builder'); ?></p>
                    <?php if ($full_key): ?>
                        <pre><code>curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-API-Key: <?php echo esc_html($full_key); ?>" \
  -d '{"pages":[{"title":"My Page","content":"Page content"}]}' \
  <?php echo esc_url(get_rest_url(null, 'pagebuilder/v1/create-pages')); ?></code></pre>
                    <?php else: ?>
                        <pre><code>curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY_HERE" \
  -d '{"pages":[{"title":"My Page","content":"Page content"}]}' \
  <?php echo esc_url(get_rest_url(null, 'pagebuilder/v1/create-pages')); ?></code></pre>
                        <div class="spb-alert spb-alert-info">
                            <p>
                                <strong><?php _e('Note:', 'simple-page-builder'); ?></strong>
                                <?php _e('The full API key is not available for display.', 'simple-page-builder'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render recent activity for a key
     */
    private function render_recent_activity($key_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . SPB_TABLE_ACTIVITY_LOGS;
        
        $activities = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE api_key_id = %d 
             ORDER BY created_at DESC 
             LIMIT 10",
            $key_id
        ));
        
        if (empty($activities)) {
            echo '<p>' . __('No recent activity', 'simple-page-builder') . '</p>';
            return;
        }
        
        echo '<ul class="spb-activity-list">';
        foreach ($activities as $activity) {
            $response = json_decode($activity->response_body, true);
            $status_class = $activity->status_code >= 400 ? 'spb-status-error' : 'spb-status-success';
            
            echo '<li class="spb-activity-item">';
            echo '<span class="spb-activity-time">' . date_i18n('H:i', strtotime($activity->created_at)) . '</span>';
            echo '<span class="spb-activity-endpoint">' . esc_html($activity->endpoint) . '</span>';
            echo '<span class="spb-activity-status ' . $status_class . '">' . intval($activity->status_code) . '</span>';
            echo '<span class="spb-activity-pages">' . intval($activity->pages_created) . ' ' . __('pages', 'simple-page-builder') . '</span>';
            echo '<span class="spb-activity-time">' . floatval($activity->response_time) . 'ms</span>';
            echo '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * Get status badge HTML
     */
    private function get_status_badge($status) {
        $badge_classes = array(
            'active' => 'spb-badge-success',
            'revoked' => 'spb-badge-danger',
            'expired' => 'spb-badge-warning'
        );
        
        $class = isset($badge_classes[$status]) ? $badge_classes[$status] : 'spb-badge-secondary';
        $label = ucfirst($status);
        
        return '<span class="spb-badge ' . $class . '">' . esc_html($label) . '</span>';
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'simple-page-builder') {
            return;
        }
        
        // Display error notices from transient
        $error_message = get_transient('spb_admin_error_' . get_current_user_id());
        if ($error_message) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($error_message); ?></p>
            </div>
            <?php
            delete_transient('spb_admin_error_' . get_current_user_id());
        }
        
        // Display success notices
        if (isset($_GET['generated']) && $_GET['generated'] === '1') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('API key generated successfully! You can find it in the table below with a copy button.', 'simple-page-builder'); ?></p>
            </div>
            <?php
        }
        
        if (isset($_GET['revoked']) && $_GET['revoked'] === '1') {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php _e('API key revoked successfully.', 'simple-page-builder'); ?></p>
            </div>
            <?php
        }
        
        if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('API key deleted successfully.', 'simple-page-builder'); ?></p>
            </div>
            <?php
        }
    }
}