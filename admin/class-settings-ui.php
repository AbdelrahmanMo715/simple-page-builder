<?php
class SPB_Settings_UI {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Handle settings save
        add_action('admin_init', array($this, 'handle_settings_save'));
        
        // Test webhook AJAX
        add_action('wp_ajax_spb_test_webhook', array($this, 'ajax_test_webhook'));
    }
    
    /**
     * Render the Settings page
     */
    public function render_page() {
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'simple-page-builder'));
        }
        
        $settings = get_option('spb_settings', array());
        $defaults = array(
            'api_enabled' => true,
            'rate_limit' => 100,
            'default_expiration' => 30,
            'webhook_url' => '',
            'webhook_secret' => '',
            'webhook_enabled' => false,
            'log_retention' => 90,
            'auto_cleanup' => true,
            'enable_request_signing' => false
        );
        
        $settings = wp_parse_args($settings, $defaults);
        
        ?>
        <div class="spb-settings-page">
            <div class="spb-header">
                <h2><?php _e('Plugin Settings', 'simple-page-builder'); ?></h2>
                <p class="description">
                    <?php _e('Configure the behavior of the Page Builder API.', 'simple-page-builder'); ?>
                </p>
            </div>
            
            <form method="post" action="" class="spb-settings-form">
                <?php wp_nonce_field('spb_save_settings', '_spb_nonce'); ?>
                
                <!-- General Settings -->
                <div class="spb-card">
                    <h3><?php _e('General Settings', 'simple-page-builder'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="api_enabled"><?php _e('API Status', 'simple-page-builder'); ?></label>
                            </th>
                            <td>
                                <label class="spb-switch">
                                    <input type="checkbox" 
                                           id="api_enabled" 
                                           name="api_enabled" 
                                           value="1" 
                                           <?php checked($settings['api_enabled'], true); ?>>
                                    <span class="spb-slider"></span>
                                </label>
                                <p class="description">
                                    <?php _e('Enable or disable the API globally. When disabled, all API requests will be rejected.', 'simple-page-builder'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="rate_limit"><?php _e('Default Rate Limit', 'simple-page-builder'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="rate_limit" 
                                       name="rate_limit" 
                                       class="regular-text" 
                                       min="1" 
                                       max="10000" 
                                       value="<?php echo esc_attr($settings['rate_limit']); ?>">
                                <p class="description">
                                    <?php _e('Default maximum requests per hour per API key. Can be overridden per key.', 'simple-page-builder'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="default_expiration"><?php _e('Default Key Expiration', 'simple-page-builder'); ?></label>
                            </th>
                            <td>
                                <select id="default_expiration" name="default_expiration" class="regular-text">
                                    <option value="0" <?php selected($settings['default_expiration'], 0); ?>>
                                        <?php _e('Never expires', 'simple-page-builder'); ?>
                                    </option>
                                    <option value="7" <?php selected($settings['default_expiration'], 7); ?>>
                                        <?php _e('7 days', 'simple-page-builder'); ?>
                                    </option>
                                    <option value="30" <?php selected($settings['default_expiration'], 30); ?>>
                                        <?php _e('30 days', 'simple-page-builder'); ?>
                                    </option>
                                    <option value="60" <?php selected($settings['default_expiration'], 60); ?>>
                                        <?php _e('60 days', 'simple-page-builder'); ?>
                                    </option>
                                    <option value="90" <?php selected($settings['default_expiration'], 90); ?>>
                                        <?php _e('90 days', 'simple-page-builder'); ?>
                                    </option>
                                    <option value="180" <?php selected($settings['default_expiration'], 180); ?>>
                                        <?php _e('180 days', 'simple-page-builder'); ?>
                                    </option>
                                    <option value="365" <?php selected($settings['default_expiration'], 365); ?>>
                                        <?php _e('1 year', 'simple-page-builder'); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <?php _e('Default expiration period for new API keys. Can be overridden when generating keys.', 'simple-page-builder'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Webhook Settings -->
                <div class="spb-card">
                    <h3><?php _e('Webhook Settings', 'simple-page-builder'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="webhook_enabled"><?php _e('Enable Webhooks', 'simple-page-builder'); ?></label>
                            </th>
                            <td>
                                <label class="spb-switch">
                                    <input type="checkbox" 
                                           id="webhook_enabled" 
                                           name="webhook_enabled" 
                                           value="1" 
                                           <?php checked($settings['webhook_enabled'], true); ?>>
                                    <span class="spb-slider"></span>
                                </label>
                                <p class="description">
                                    <?php _e('Enable webhook notifications when pages are created via API.', 'simple-page-builder'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="webhook_url"><?php _e('Webhook URL', 'simple-page-builder'); ?></label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="webhook_url" 
                                       name="webhook_url" 
                                       class="large-text" 
                                       placeholder="https://example.com/webhook"
                                       value="<?php echo esc_url($settings['webhook_url']); ?>">
                                <p class="description">
                                    <?php _e('URL to send webhook notifications to. Must be HTTPS.', 'simple-page-builder'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="webhook_secret"><?php _e('Webhook Secret', 'simple-page-builder'); ?></label>
                            </th>
                            <td>
                                <div class="spb-secret-input">
                                    <input type="password" 
                                           id="webhook_secret" 
                                           name="webhook_secret" 
                                           class="regular-text" 
                                           value="<?php echo esc_attr($settings['webhook_secret']); ?>"
                                           placeholder="<?php esc_attr_e('Leave empty to keep current secret', 'simple-page-builder'); ?>">
                                    <button type="button" 
                                            class="button spb-generate-secret"
                                            data-target="#webhook_secret">
                                        <?php _e('Generate', 'simple-page-builder'); ?>
                                    </button>
                                </div>
                                <p class="description">
                                    <?php _e('Secret key used to sign webhook payloads. Changing this will invalidate existing signatures.', 'simple-page-builder'); ?>
                                </p>
                                
                                <?php if (empty($settings['webhook_secret'])): ?>
                                    <div class="spb-alert spb-alert-warning">
                                        <p>
                                            <?php _e('No webhook secret set. Webhook signatures will not be generated.', 'simple-page-builder'); ?>
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <div class="spb-alert spb-alert-info">
                                        <p>
                                            <?php _e('Webhook secret is set. Use this to verify signatures on your receiving end.', 'simple-page-builder'); ?>
                                        </p>
                                        <p>
                                            <strong><?php _e('Verification method:', 'simple-page-builder'); ?></strong>
                                            <?php _e('Compare X-Webhook-Signature header with HMAC-SHA256 hash of payload using this secret.', 'simple-page-builder'); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"></th>
                            <td>
                                <button type="button" 
                                        id="spb-test-webhook" 
                                        class="button"
                                        <?php echo empty($settings['webhook_url']) ? 'disabled' : ''; ?>>
                                    <?php _e('Test Webhook', 'simple-page-builder'); ?>
                                </button>
                                <span id="spb-test-result" class="spb-test-result"></span>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Security Settings -->
                <div class="spb-card">
                    <h3><?php _e('Security Settings', 'simple-page-builder'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="enable_request_signing"><?php _e('Request Signing', 'simple-page-builder'); ?></label>
                            </th>
                            <td>
                                <label class="spb-switch">
                                    <input type="checkbox" 
                                           id="enable_request_signing" 
                                           name="enable_request_signing" 
                                           value="1" 
                                           <?php checked($settings['enable_request_signing'], true); ?>>
                                    <span class="spb-slider"></span>
                                </label>
                                <p class="description">
                                    <?php _e('Require API requests to be signed with timestamp and nonce to prevent replay attacks. (Advanced)', 'simple-page-builder'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="allowed_ips"><?php _e('Allowed IP Ranges', 'simple-page-builder'); ?></label>
                            </th>
                            <td>
                                <textarea id="allowed_ips" 
                                          name="allowed_ips" 
                                          class="large-text" 
                                          rows="3"
                                          placeholder="192.168.1.0/24&#10;10.0.0.0/8"><?php echo esc_textarea($settings['allowed_ips'] ?? ''); ?></textarea>
                                <p class="description">
                                    <?php _e('Optional: Restrict API access to specific IP ranges (one per line, CIDR notation). Leave empty to allow all IPs.', 'simple-page-builder'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Logging & Cleanup -->
                <div class="spb-card">
                    <h3><?php _e('Logging & Cleanup', 'simple-page-builder'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="log_retention"><?php _e('Log Retention Period', 'simple-page-builder'); ?></label>
                            </th>
                            <td>
                                <select id="log_retention" name="log_retention" class="regular-text">
                                    <option value="7" <?php selected($settings['log_retention'], 7); ?>>
                                        <?php _e('7 days', 'simple-page-builder'); ?>
                                    </option>
                                    <option value="30" <?php selected($settings['log_retention'], 30); ?>>
                                        <?php _e('30 days', 'simple-page-builder'); ?>
                                    </option>
                                    <option value="90" <?php selected($settings['log_retention'], 90); ?>>
                                        <?php _e('90 days', 'simple-page-builder'); ?>
                                    </option>
                                    <option value="180" <?php selected($settings['log_retention'], 180); ?>>
                                        <?php _e('180 days', 'simple-page-builder'); ?>
                                    </option>
                                    <option value="365" <?php selected($settings['log_retention'], 365); ?>>
                                        <?php _e('1 year', 'simple-page-builder'); ?>
                                    </option>
                                    <option value="0" <?php selected($settings['log_retention'], 0); ?>>
                                        <?php _e('Keep forever', 'simple-page-builder'); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <?php _e('How long to keep activity logs. Older logs will be automatically deleted.', 'simple-page-builder'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="auto_cleanup"><?php _e('Auto Cleanup', 'simple-page-builder'); ?></label>
                            </th>
                            <td>
                                <label class="spb-switch">
                                    <input type="checkbox" 
                                           id="auto_cleanup" 
                                           name="auto_cleanup" 
                                           value="1" 
                                           <?php checked($settings['auto_cleanup'], true); ?>>
                                    <span class="spb-slider"></span>
                                </label>
                                <p class="description">
                                    <?php _e('Automatically clean up expired API keys and old logs daily.', 'simple-page-builder'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Submit Button -->
                <div class="spb-card">
                    <div class="spb-form-actions">
                        <button type="submit" 
                                name="save_settings" 
                                class="button button-primary button-large">
                            <?php _e('Save Settings', 'simple-page-builder'); ?>
                        </button>
                        
                        <button type="submit" 
                                name="reset_settings" 
                                class="button button-secondary"
                                onclick="return confirm('<?php esc_js_e('Are you sure you want to reset all settings to defaults?', 'simple-page-builder'); ?>');">
                            <?php _e('Reset to Defaults', 'simple-page-builder'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Handle settings save
     */
    public function handle_settings_save() {
        if (!isset($_POST['save_settings']) && !isset($_POST['reset_settings'])) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (!isset($_POST['_spb_nonce']) || !wp_verify_nonce($_POST['_spb_nonce'], 'spb_save_settings')) {
            wp_die(__('Security check failed.', 'simple-page-builder'));
        }
        
        if (isset($_POST['reset_settings'])) {
            // Reset to defaults
            $defaults = array(
                'api_enabled' => true,
                'rate_limit' => 100,
                'default_expiration' => 30,
                'webhook_url' => '',
                'webhook_secret' => wp_generate_password(64, false),
                'webhook_enabled' => false,
                'log_retention' => 90,
                'auto_cleanup' => true,
                'enable_request_signing' => false,
                'allowed_ips' => ''
            );
            
            update_option('spb_settings', $defaults);
            
            wp_redirect(add_query_arg(array(
                'page' => 'simple-page-builder',
                'tab' => 'settings',
                'settings-reset' => '1'
            ), admin_url('tools.php')));
            exit;
        }
        
        // Save settings
        $settings = get_option('spb_settings', array());
        
        // General settings
        $settings['api_enabled'] = isset($_POST['api_enabled']);
        $settings['rate_limit'] = isset($_POST['rate_limit']) ? max(1, intval($_POST['rate_limit'])) : 100;
        $settings['default_expiration'] = isset($_POST['default_expiration']) ? intval($_POST['default_expiration']) : 30;
        
        // Webhook settings
        $settings['webhook_enabled'] = isset($_POST['webhook_enabled']);
        $settings['webhook_url'] = isset($_POST['webhook_url']) ? esc_url_raw($_POST['webhook_url']) : '';
        
        // Only update webhook secret if provided (don't overwrite with empty)
        if (isset($_POST['webhook_secret']) && !empty($_POST['webhook_secret'])) {
            $settings['webhook_secret'] = sanitize_text_field($_POST['webhook_secret']);
        } elseif (!isset($settings['webhook_secret']) || empty($settings['webhook_secret'])) {
            // Generate new secret if none exists
            $settings['webhook_secret'] = wp_generate_password(64, false);
        }
        
        // Security settings
        $settings['enable_request_signing'] = isset($_POST['enable_request_signing']);
        $settings['allowed_ips'] = isset($_POST['allowed_ips']) ? sanitize_textarea_field($_POST['allowed_ips']) : '';
        
        // Logging settings
        $settings['log_retention'] = isset($_POST['log_retention']) ? intval($_POST['log_retention']) : 90;
        $settings['auto_cleanup'] = isset($_POST['auto_cleanup']);
        
        update_option('spb_settings', $settings);
        
        wp_redirect(add_query_arg(array(
            'page' => 'simple-page-builder',
            'tab' => 'settings',
            'settings-saved' => '1'
        ), admin_url('tools.php')));
        exit;
    }
    
    /**
     * AJAX: Test webhook
     */
    public function ajax_test_webhook() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'simple-page-builder')));
        }
        
        $settings = get_option('spb_settings', array());
        
        if (empty($settings['webhook_url'])) {
            wp_send_json_error(array('message' => __('Webhook URL is not set', 'simple-page-builder')));
        }
        
        // Prepare test payload
        $payload = array(
            'event' => 'test',
            'timestamp' => current_time('c'),
            'request_id' => 'test_' . wp_generate_password(8, false),
            'api_key_name' => 'Test',
            'total_pages' => 2,
            'pages' => array(
                array(
                    'id' => 999,
                    'title' => 'Test Page 1',
                    'url' => home_url('/test-page-1')
                ),
                array(
                    'id' => 1000,
                    'title' => 'Test Page 2',
                    'url' => home_url('/test-page-2')
                )
            )
        );
        
        // Send test webhook
        $response = wp_remote_post($settings['webhook_url'], array(
            'method' => 'POST',
            'timeout' => 10,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => hash_hmac('sha256', json_encode($payload), $settings['webhook_secret'] ?? '')
            ),
            'body' => json_encode($payload)
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => $response->get_error_message(),
                'code' => $response->get_error_code()
            ));
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code >= 200 && $status_code < 300) {
            wp_send_json_success(array(
                'message' => sprintf(__('Webhook sent successfully. Status: %d', 'simple-page-builder'), $status_code),
                'status' => $status_code,
                'response' => substr($body, 0, 200) // First 200 chars
            ));
        } else {
            wp_send_json_error(array(
                'message' => sprintf(__('Webhook failed with status: %d', 'simple-page-builder'), $status_code),
                'status' => $status_code,
                'response' => substr($body, 0, 200)
            ));
        }
    }
}