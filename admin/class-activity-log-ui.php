<?php
class SPB_Activity_Log_UI {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add export functionality
        add_action('admin_init', array($this, 'handle_export'));
    }
    
    /**
     * Render the Activity Log page
     */
    public function render_page() {
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'simple-page-builder'));
        }
        
        global $wpdb;
        
        $table_logs = $wpdb->prefix . SPB_TABLE_ACTIVITY_LOGS;
        $table_keys = $wpdb->prefix . SPB_TABLE_API_KEYS;
        
        // Get filters
        $filters = $this->get_filters();
        
        // Build query with filters
        $where = array('1=1');
        $values = array();
        
        if (!empty($filters['status_code'])) {
            if ($filters['status_code'] === 'success') {
                $where[] = 'status_code BETWEEN 200 AND 299';
            } elseif ($filters['status_code'] === 'error') {
                $where[] = 'status_code >= 400';
            }
        }
        
        if (!empty($filters['api_key_id'])) {
            $where[] = 'api_key_id = %d';
            $values[] = intval($filters['api_key_id']);
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= %s';
            $values[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= %s';
            $values[] = $filters['date_to'] . ' 23:59:59';
        }
        
        if (!empty($filters['search'])) {
            $where[] = '(endpoint LIKE %s OR ip_address LIKE %s OR id = %d)';
            $values[] = '%' . $wpdb->esc_like($filters['search']) . '%';
            $values[] = '%' . $wpdb->esc_like($filters['search']) . '%';
            $values[] = intval($filters['search']);
        }
        
        // Get total count for pagination
        $count_query = "SELECT COUNT(*) FROM $table_logs";
        if (count($where) > 1) {
            $count_query .= " WHERE " . implode(' AND ', $where);
            if (!empty($values)) {
                $count_query = $wpdb->prepare($count_query, $values);
            }
        }
        
        $total_items = $wpdb->get_var($count_query);
        
        // Pagination
        $per_page = 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Get logs with pagination
        $query = "SELECT l.*, k.key_name 
                  FROM $table_logs l 
                  LEFT JOIN $table_keys k ON l.api_key_id = k.id";
        
        if (count($where) > 1) {
            $query .= " WHERE " . implode(' AND ', $where);
        }
        
        $query .= " ORDER BY l.created_at DESC LIMIT %d OFFSET %d";
        
        $values[] = $per_page;
        $values[] = $offset;
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        $logs = $wpdb->get_results($query);
        
        // Get API keys for filter dropdown
        $api_keys = $wpdb->get_results("SELECT id, key_name FROM $table_keys ORDER BY key_name");
        
        ?>
        <div class="spb-activity-log-page">
            <div class="spb-header">
                <h2><?php _e('API Activity Log', 'simple-page-builder'); ?></h2>
                <p class="description">
                    <?php _e('Monitor all API requests and their status.', 'simple-page-builder'); ?>
                </p>
            </div>
            
            <!-- Filters Card -->
            <div class="spb-card">
                <h3><?php _e('Filter Logs', 'simple-page-builder'); ?></h3>
                <form method="get" action="" class="spb-filter-form">
                    <input type="hidden" name="page" value="simple-page-builder">
                    <input type="hidden" name="tab" value="activity-log">
                    
                    <div class="spb-filter-grid">
                        <div class="spb-form-group">
                            <label for="status_code"><?php _e('Status', 'simple-page-builder'); ?></label>
                            <select id="status_code" name="status_code" class="regular-text">
                                <option value=""><?php _e('All Statuses', 'simple-page-builder'); ?></option>
                                <option value="success" <?php selected($filters['status_code'], 'success'); ?>>
                                    <?php _e('Success (2xx)', 'simple-page-builder'); ?>
                                </option>
                                <option value="error" <?php selected($filters['status_code'], 'error'); ?>>
                                    <?php _e('Error (4xx, 5xx)', 'simple-page-builder'); ?>
                                </option>
                            </select>
                        </div>
                        
                        <div class="spb-form-group">
                            <label for="api_key_id"><?php _e('API Key', 'simple-page-builder'); ?></label>
                            <select id="api_key_id" name="api_key_id" class="regular-text">
                                <option value=""><?php _e('All API Keys', 'simple-page-builder'); ?></option>
                                <?php foreach ($api_keys as $key): ?>
                                    <option value="<?php echo esc_attr($key->id); ?>" 
                                            <?php selected($filters['api_key_id'], $key->id); ?>>
                                        <?php echo esc_html($key->key_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="spb-form-group">
                            <label for="date_from"><?php _e('Date From', 'simple-page-builder'); ?></label>
                            <input type="date" 
                                   id="date_from" 
                                   name="date_from" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($filters['date_from']); ?>">
                        </div>
                        
                        <div class="spb-form-group">
                            <label for="date_to"><?php _e('Date To', 'simple-page-builder'); ?></label>
                            <input type="date" 
                                   id="date_to" 
                                   name="date_to" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr($filters['date_to']); ?>">
                        </div>
                        
                        <div class="spb-form-group">
                            <label for="search"><?php _e('Search', 'simple-page-builder'); ?></label>
                            <input type="text" 
                                   id="search" 
                                   name="search" 
                                   class="regular-text" 
                                   placeholder="<?php esc_attr_e('Endpoint, IP, or ID', 'simple-page-builder'); ?>"
                                   value="<?php echo esc_attr($filters['search']); ?>">
                        </div>
                    </div>
                    
                    <div class="spb-filter-actions">
                        <button type="submit" class="button button-primary">
                            <?php _e('Apply Filters', 'simple-page-builder'); ?>
                        </button>
                        <a href="?page=simple-page-builder&tab=activity-log" class="button">
                            <?php _e('Clear Filters', 'simple-page-builder'); ?>
                        </a>
                        <button type="submit" 
                                name="export" 
                                value="csv" 
                                class="button">
                            <?php _e('Export as CSV', 'simple-page-builder'); ?>
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Logs Table Card -->
            <div class="spb-card">
                <div class="spb-card-header">
                    <h3><?php _e('API Requests', 'simple-page-builder'); ?></h3>
                    <span class="spb-badge">
                        <?php printf(__('Total: %d', 'simple-page-builder'), $total_items); ?>
                    </span>
                </div>
                
                <?php if (empty($logs)): ?>
                    <div class="spb-empty-state">
                        <p><?php _e('No activity logs found.', 'simple-page-builder'); ?></p>
                        <p><?php _e('API requests will appear here once they are made.', 'simple-page-builder'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="spb-table-responsive">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Time', 'simple-page-builder'); ?></th>
                                    <th><?php _e('API Key', 'simple-page-builder'); ?></th>
                                    <th><?php _e('Endpoint', 'simple-page-builder'); ?></th>
                                    <th><?php _e('Status', 'simple-page-builder'); ?></th>
                                    <th><?php _e('Pages', 'simple-page-builder'); ?></th>
                                    <th><?php _e('Time (ms)', 'simple-page-builder'); ?></th>
                                    <th><?php _e('IP Address', 'simple-page-builder'); ?></th>
                                    <th><?php _e('Actions', 'simple-page-builder'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>
                                            <?php echo date_i18n('Y-m-d H:i:s', strtotime($log->created_at)); ?>
                                        </td>
                                        <td>
                                            <?php if ($log->key_name): ?>
                                                <a href="<?php echo esc_url(add_query_arg(array(
                                                    'page' => 'simple-page-builder',
                                                    'tab' => 'api-keys',
                                                    'view_key' => $log->api_key_id
                                                ), admin_url('tools.php'))); ?>">
                                                    <?php echo esc_html($log->key_name); ?>
                                                </a>
                                            <?php else: ?>
                                                <em><?php _e('Unknown', 'simple-page-builder'); ?></em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <code><?php echo esc_html($log->endpoint); ?></code>
                                            <br>
                                            <small class="text-muted"><?php echo esc_html($log->method); ?></small>
                                        </td>
                                        <td>
                                            <?php echo $this->get_status_badge($log->status_code); ?>
                                        </td>
                                        <td>
                                            <?php echo intval($log->pages_created); ?>
                                        </td>
                                        <td>
                                            <?php echo floatval($log->response_time); ?>
                                        </td>
                                        <td>
                                            <code><?php echo esc_html($log->ip_address); ?></code>
                                            <?php if ($log->user_agent): ?>
                                                <br>
                                                <small class="text-muted" title="<?php echo esc_attr($log->user_agent); ?>">
                                                    <?php echo esc_html(substr($log->user_agent, 0, 50)); ?>...
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                    class="button button-small spb-view-log-btn"
                                                    data-log-id="<?php echo esc_attr($log->id); ?>">
                                                <?php _e('View Details', 'simple-page-builder'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_items > $per_page): ?>
                        <div class="spb-pagination">
                            <?php
                            $total_pages = ceil($total_items / $per_page);
                            $pagination_args = array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => __('&laquo;', 'simple-page-builder'),
                                'next_text' => __('&raquo;', 'simple-page-builder'),
                                'total' => $total_pages,
                                'current' => $current_page
                            );
                            
                            echo paginate_links($pagination_args);
                            ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Statistics Card -->
            <div class="spb-card">
                <h3><?php _e('Statistics', 'simple-page-builder'); ?></h3>
                <?php $this->render_statistics($filters); ?>
            </div>
            
            <!-- Log Details Modal -->
            <div id="spb-log-modal" class="spb-modal" style="display: none;">
                <div class="spb-modal-content spb-modal-lg">
                    <div class="spb-modal-header">
                        <h3><?php _e('Request Details', 'simple-page-builder'); ?></h3>
                        <button type="button" class="spb-modal-close">&times;</button>
                    </div>
                    <div class="spb-modal-body">
                        <div id="spb-log-details"></div>
                    </div>
                    <div class="spb-modal-footer">
                        <button type="button" class="button button-primary spb-modal-close">
                            <?php _e('Close', 'simple-page-builder'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get filters from request
     */
    private function get_filters() {
        return array(
            'status_code' => isset($_GET['status_code']) ? sanitize_text_field($_GET['status_code']) : '',
            'api_key_id' => isset($_GET['api_key_id']) ? intval($_GET['api_key_id']) : '',
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
            'search' => isset($_GET['search']) ? sanitize_text_field($_GET['search']) : ''
        );
    }
    
    /**
     * Get status badge for HTTP code
     */
    private function get_status_badge($status_code) {
        if ($status_code >= 200 && $status_code < 300) {
            $class = 'spb-badge-success';
        } elseif ($status_code >= 400 && $status_code < 500) {
            $class = 'spb-badge-warning';
        } elseif ($status_code >= 500) {
            $class = 'spb-badge-danger';
        } else {
            $class = 'spb-badge-secondary';
        }
        
        return '<span class="spb-badge ' . $class . '">' . intval($status_code) . '</span>';
    }
    
    /**
     * Render statistics
     */
    private function render_statistics($filters) {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . SPB_TABLE_ACTIVITY_LOGS;
        $table_keys = $wpdb->prefix . SPB_TABLE_API_KEYS;
        
        // Build where clause for filters
        $where = array('1=1');
        $values = array();
        
        if (!empty($filters['status_code'])) {
            if ($filters['status_code'] === 'success') {
                $where[] = 'status_code BETWEEN 200 AND 299';
            } elseif ($filters['status_code'] === 'error') {
                $where[] = 'status_code >= 400';
            }
        }
        
        if (!empty($filters['api_key_id'])) {
            $where[] = 'api_key_id = %d';
            $values[] = intval($filters['api_key_id']);
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= %s';
            $values[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= %s';
            $values[] = $filters['date_to'] . ' 23:59:59';
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Get statistics
        $stats_query = "SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status_code BETWEEN 200 AND 299 THEN 1 ELSE 0 END) as successful_requests,
            SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as failed_requests,
            AVG(response_time) as avg_response_time,
            SUM(pages_created) as total_pages_created,
            COUNT(DISTINCT api_key_id) as unique_api_keys,
            COUNT(DISTINCT ip_address) as unique_ips
            FROM $table_logs
            WHERE $where_clause";
        
        if (!empty($values)) {
            $stats_query = $wpdb->prepare($stats_query, $values);
        }
        
        $stats = $wpdb->get_row($stats_query);
        
        // Get recent activity
        $recent_query = "SELECT 
            DATE(created_at) as date,
            COUNT(*) as requests,
            SUM(pages_created) as pages
            FROM $table_logs
            WHERE $where_clause
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC";
        
        if (!empty($values)) {
            $recent_query = $wpdb->prepare($recent_query, $values);
        }
        
        $recent_activity = $wpdb->get_results($recent_query);
        
        ?>
        <div class="spb-stats-grid">
            <div class="spb-stat-card">
                <div class="spb-stat-value"><?php echo intval($stats->total_requests); ?></div>
                <div class="spb-stat-label"><?php _e('Total Requests', 'simple-page-builder'); ?></div>
            </div>
            <div class="spb-stat-card">
                <div class="spb-stat-value"><?php echo intval($stats->successful_requests); ?></div>
                <div class="spb-stat-label"><?php _e('Successful', 'simple-page-builder'); ?></div>
            </div>
            <div class="spb-stat-card">
                <div class="spb-stat-value"><?php echo intval($stats->failed_requests); ?></div>
                <div class="spb-stat-label"><?php _e('Failed', 'simple-page-builder'); ?></div>
            </div>
            <div class="spb-stat-card">
                <div class="spb-stat-value"><?php echo intval($stats->total_pages_created); ?></div>
                <div class="spb-stat-label"><?php _e('Pages Created', 'simple-page-builder'); ?></div>
            </div>
            <div class="spb-stat-card">
                <div class="spb-stat-value"><?php echo round(floatval($stats->avg_response_time), 2); ?>ms</div>
                <div class="spb-stat-label"><?php _e('Avg Response Time', 'simple-page-builder'); ?></div>
            </div>
            <div class="spb-stat-card">
                <div class="spb-stat-value"><?php echo intval($stats->unique_api_keys); ?></div>
                <div class="spb-stat-label"><?php _e('Unique API Keys', 'simple-page-builder'); ?></div>
            </div>
        </div>
        
        <?php if (!empty($recent_activity)): ?>
            <h4><?php _e('Last 7 Days Activity', 'simple-page-builder'); ?></h4>
            <div class="spb-activity-chart">
                <table class="widefat fixed">
                    <thead>
                        <tr>
                            <th><?php _e('Date', 'simple-page-builder'); ?></th>
                            <th><?php _e('Requests', 'simple-page-builder'); ?></th>
                            <th><?php _e('Pages Created', 'simple-page-builder'); ?></th>
                            <th><?php _e('Success Rate', 'simple-page-builder'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_activity as $activity): 
                            // Get success rate for this day
                            $day_stats_query = "SELECT 
                                COUNT(*) as total,
                                SUM(CASE WHEN status_code BETWEEN 200 AND 299 THEN 1 ELSE 0 END) as successful
                                FROM $table_logs
                                WHERE DATE(created_at) = %s";
                            
                            if (!empty($values)) {
                                $day_stats_query .= " AND " . substr($where_clause, 6); // Remove '1=1 AND '
                                $day_stats = $wpdb->get_row($wpdb->prepare($day_stats_query, array_merge(array($activity->date), $values)));
                            } else {
                                $day_stats = $wpdb->get_row($wpdb->prepare($day_stats_query, $activity->date));
                            }
                            
                            $success_rate = $day_stats->total > 0 ? round(($day_stats->successful / $day_stats->total) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td><?php echo date_i18n('M j, Y', strtotime($activity->date)); ?></td>
                                <td><?php echo intval($activity->requests); ?></td>
                                <td><?php echo intval($activity->pages); ?></td>
                                <td>
                                    <div class="spb-progress-bar">
                                        <div class="spb-progress-fill" style="width: <?php echo $success_rate; ?>%"></div>
                                        <span><?php echo $success_rate; ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Handle CSV export
     */
    public function handle_export() {
        if (!isset($_GET['export']) || $_GET['export'] !== 'csv' || !isset($_GET['page']) || $_GET['page'] !== 'simple-page-builder') {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to export data.', 'simple-page-builder'));
        }
        
        global $wpdb;
        
        $table_logs = $wpdb->prefix . SPB_TABLE_ACTIVITY_LOGS;
        $table_keys = $wpdb->prefix . SPB_TABLE_API_KEYS;
        
        // Get filters
        $filters = $this->get_filters();
        
        // Build query with filters
        $where = array('1=1');
        $values = array();
        
        if (!empty($filters['status_code'])) {
            if ($filters['status_code'] === 'success') {
                $where[] = 'status_code BETWEEN 200 AND 299';
            } elseif ($filters['status_code'] === 'error') {
                $where[] = 'status_code >= 400';
            }
        }
        
        if (!empty($filters['api_key_id'])) {
            $where[] = 'api_key_id = %d';
            $values[] = intval($filters['api_key_id']);
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= %s';
            $values[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= %s';
            $values[] = $filters['date_to'] . ' 23:59:59';
        }
        
        $query = "SELECT 
            l.id,
            l.created_at,
            k.key_name,
            l.endpoint,
            l.method,
            l.status_code,
            l.pages_created,
            l.response_time,
            l.ip_address,
            l.user_agent
            FROM $table_logs l
            LEFT JOIN $table_keys k ON l.api_key_id = k.id";
        
        if (count($where) > 1) {
            $query .= " WHERE " . implode(' AND ', $where);
        }
        
        $query .= " ORDER BY l.created_at DESC";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        $logs = $wpdb->get_results($query);
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="api-activity-log-' . date('Y-m-d') . '.csv"');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fwrite($output, "\xEF\xBB\xBF");
        
        // Add headers
        fputcsv($output, array(
            'ID',
            'Timestamp',
            'API Key',
            'Endpoint',
            'Method',
            'Status Code',
            'Pages Created',
            'Response Time (ms)',
            'IP Address',
            'User Agent'
        ));
        
        // Add data
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log->id,
                $log->created_at,
                $log->key_name,
                $log->endpoint,
                $log->method,
                $log->status_code,
                $log->pages_created,
                $log->response_time,
                $log->ip_address,
                $log->user_agent
            ));
        }
        
        fclose($output);
        exit;
    }
}