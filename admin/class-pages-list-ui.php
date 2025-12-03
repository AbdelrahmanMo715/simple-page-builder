<?php
class SPB_Pages_List_UI {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add bulk actions to pages list
        add_filter('bulk_actions-edit-page', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-edit-page', array($this, 'handle_bulk_actions'), 10, 3);
        
        // Add custom column to pages list
        add_filter('manage_page_posts_columns', array($this, 'add_custom_column'));
        add_action('manage_page_posts_custom_column', array($this, 'render_custom_column'), 10, 2);
        
        // Add filter dropdown
        add_action('restrict_manage_posts', array($this, 'add_filter_dropdown'));
        add_filter('parse_query', array($this, 'filter_query'));
    }
    
    /**
     * Render the Created Pages page
     */
    public function render_page() {
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'simple-page-builder'));
        }
        
        global $wpdb;
        
        // Get pages created via API
        $pages = $this->get_api_created_pages();
        
        // Get statistics
        $stats = $this->get_pages_statistics();
        
        ?>
        <div class="spb-pages-page">
            <div class="spb-header">
                <h2><?php _e('Pages Created via API', 'simple-page-builder'); ?></h2>
                <p class="description">
                    <?php _e('View and manage pages created through the Page Builder API.', 'simple-page-builder'); ?>
                </p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="spb-stats-grid">
                <div class="spb-stat-card">
                    <div class="spb-stat-value"><?php echo intval($stats['total_pages']); ?></div>
                    <div class="spb-stat-label"><?php _e('Total Pages', 'simple-page-builder'); ?></div>
                </div>
                <div class="spb-stat-card">
                    <div class="spb-stat-value"><?php echo intval($stats['published']); ?></div>
                    <div class="spb-stat-label"><?php _e('Published', 'simple-page-builder'); ?></div>
                </div>
                <div class="spb-stat-card">
                    <div class="spb-stat-value"><?php echo intval($stats['drafts']); ?></div>
                    <div class="spb-stat-label"><?php _e('Drafts', 'simple-page-builder'); ?></div>
                </div>
                <div class="spb-stat-card">
                    <div class="spb-stat-value"><?php echo intval($stats['unique_authors']); ?></div>
                    <div class="spb-stat-label"><?php _e('API Keys Used', 'simple-page-builder'); ?></div>
                </div>
                <div class="spb-stat-card">
                    <div class="spb-stat-value"><?php echo date_i18n('M j', strtotime($stats['first_page_date'])); ?></div>
                    <div class="spb-stat-label"><?php _e('First Page', 'simple-page-builder'); ?></div>
                </div>
                <div class="spb-stat-card">
                    <div class="spb-stat-value"><?php echo date_i18n('M j', strtotime($stats['last_page_date'])); ?></div>
                    <div class="spb-stat-label"><?php _e('Last Page', 'simple-page-builder'); ?></div>
                </div>
            </div>
            
            <!-- Pages Table Card -->
            <div class="spb-card">
                <div class="spb-card-header">
                    <h3><?php _e('Pages List', 'simple-page-builder'); ?></h3>
                    <div class="spb-header-actions">
                        <a href="<?php echo admin_url('edit.php?post_type=page&spb_filter=api_created'); ?>" 
                           class="button">
                            <?php _e('View in Pages List', 'simple-page-builder'); ?>
                        </a>
                        <a href="<?php echo admin_url('edit.php?post_type=page'); ?>" class="button">
                            <?php _e('Manage All Pages', 'simple-page-builder'); ?>
                        </a>
                    </div>
                </div>
                
                <?php if (empty($pages)): ?>
                    <div class="spb-empty-state">
                        <p><?php _e('No pages have been created via API yet.', 'simple-page-builder'); ?></p>
                        <p><?php _e('Pages created through the API will appear here.', 'simple-page-builder'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="spb-table-responsive">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Page Title', 'simple-page-builder'); ?></th>
                                    <th><?php _e('Status', 'simple-page-builder'); ?></th>
                                    <th><?php _e('Created', 'simple-page-builder'); ?></th>
                                    <th><?php _e('Created By', 'simple-page-builder'); ?></th>
                                    <th><?php _e('API Key', 'simple-page-builder'); ?></th>
                                    <th><?php _e('Actions', 'simple-page-builder'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pages as $page): ?>
                                    <tr>
                                        <td>
                                            <strong>
                                                <a href="<?php echo get_edit_post_link($page->ID); ?>">
                                                    <?php echo esc_html($page->post_title); ?>
                                                </a>
                                            </strong>
                                            <br>
                                            <small>
                                                <a href="<?php echo get_permalink($page->ID); ?>" target="_blank">
                                                    <?php echo esc_url(get_permalink($page->ID)); ?>
                                                </a>
                                            </small>
                                        </td>
                                        <td>
                                            <?php echo $this->get_status_badge($page->post_status); ?>
                                        </td>
                                        <td>
                                            <?php echo date_i18n('Y-m-d H:i', strtotime($page->post_date)); ?>
                                        </td>
                                        <td>
                                            <?php
                                            $author = get_user_by('id', $page->post_author);
                                            echo $author ? esc_html($author->display_name) : __('Unknown', 'simple-page-builder');
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $api_key_id = get_post_meta($page->ID, '_spb_api_key_id', true);
                                            if ($api_key_id) {
                                                $api_key = $this->get_api_key_name($api_key_id);
                                                if ($api_key) {
                                                    echo '<a href="' . esc_url(add_query_arg(array(
                                                        'page' => 'simple-page-builder',
                                                        'tab' => 'api-keys',
                                                        'view_key' => $api_key_id
                                                    ), admin_url('tools.php'))) . '">';
                                                    echo esc_html($api_key);
                                                    echo '</a>';
                                                } else {
                                                    echo '<em>' . __('Unknown', 'simple-page-builder') . '</em>';
                                                }
                                            } else {
                                                echo '<em>' . __('Not recorded', 'simple-page-builder') . '</em>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="spb-action-buttons">
                                                <a href="<?php echo get_edit_post_link($page->ID); ?>" 
                                                   class="button button-small">
                                                    <?php _e('Edit', 'simple-page-builder'); ?>
                                                </a>
                                                <a href="<?php echo get_permalink($page->ID); ?>" 
                                                   target="_blank"
                                                   class="button button-small">
                                                    <?php _e('View', 'simple-page-builder'); ?>
                                                </a>
                                                <?php if (current_user_can('delete_post', $page->ID)): ?>
                                                    <a href="<?php echo get_delete_post_link($page->ID); ?>" 
                                                       class="button button-small button-warning"
                                                       onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this page?', 'simple-page-builder'); ?>');">
                                                        <?php _e('Delete', 'simple-page-builder'); ?>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php
                    $total_pages = wp_count_posts('page');
                    $api_pages_count = $this->count_api_created_pages();
                    
                    if ($api_pages_count > 20):
                        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                        $total_pages_count = ceil($api_pages_count / 20);
                        
                        if ($total_pages_count > 1):
                        ?>
                        <div class="spb-pagination">
                            <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => __('&laquo;', 'simple-page-builder'),
                                'next_text' => __('&raquo;', 'simple-page-builder'),
                                'total' => $total_pages_count,
                                'current' => $current_page,
                                'type' => 'plain'
                            ));
                            ?>
                        </div>
                        <?php
                        endif;
                    endif;
                    ?>
                <?php endif; ?>
            </div>
            
            <!-- Recent Activity Card -->
            <div class="spb-card">
                <h3><?php _e('Recent API Page Creation Activity', 'simple-page-builder'); ?></h3>
                <?php $this->render_recent_activity(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get pages created via API
     */
    private function get_api_created_pages($limit = 20) {
        $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        
        $args = array(
            'post_type' => 'page',
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => $limit,
            'paged' => $paged,
            'meta_query' => array(
                array(
                    'key' => '_spb_created_via_api',
                    'value' => '1',
                    'compare' => '='
                )
            ),
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        return get_posts($args);
    }
    
    /**
     * Count API created pages
     */
    private function count_api_created_pages() {
        $args = array(
            'post_type' => 'page',
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_spb_created_via_api',
                    'value' => '1',
                    'compare' => '='
                )
            )
        );
        
        $query = new WP_Query($args);
        return $query->found_posts;
    }
    
    /**
     * Get pages statistics
     */
    private function get_pages_statistics() {
        global $wpdb;
        
        $stats = array(
            'total_pages' => 0,
            'published' => 0,
            'drafts' => 0,
            'pending' => 0,
            'private' => 0,
            'unique_authors' => 0,
            'unique_api_keys' => 0,
            'first_page_date' => null,
            'last_page_date' => null
        );
        
        // Get all pages created via API
        $pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_spb_created_via_api',
                    'value' => '1',
                    'compare' => '='
                )
            ),
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        if (empty($pages)) {
            return $stats;
        }
        
        $stats['total_pages'] = count($pages);
        $stats['first_page_date'] = end($pages)->post_date;
        $stats['last_page_date'] = $pages[0]->post_date;
        
        $authors = array();
        $api_keys = array();
        
        foreach ($pages as $page) {
            // Count by status
            switch ($page->post_status) {
                case 'publish':
                    $stats['published']++;
                    break;
                case 'draft':
                    $stats['drafts']++;
                    break;
                case 'pending':
                    $stats['pending']++;
                    break;
                case 'private':
                    $stats['private']++;
                    break;
            }
            
            // Collect unique authors
            if (!in_array($page->post_author, $authors)) {
                $authors[] = $page->post_author;
            }
            
            // Collect unique API keys
            $api_key_id = get_post_meta($page->ID, '_spb_api_key_id', true);
            if ($api_key_id && !in_array($api_key_id, $api_keys)) {
                $api_keys[] = $api_key_id;
            }
        }
        
        $stats['unique_authors'] = count($authors);
        $stats['unique_api_keys'] = count($api_keys);
        
        return $stats;
    }
    
    /**
     * Get API key name
     */
    private function get_api_key_name($key_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . SPB_TABLE_API_KEYS;
        
        $key = $wpdb->get_row($wpdb->prepare(
            "SELECT key_name FROM $table_name WHERE id = %d",
            $key_id
        ));
        
        return $key ? $key->key_name : false;
    }
    
    /**
     * Get status badge
     */
    private function get_status_badge($status) {
        $badges = array(
            'publish' => array('class' => 'spb-badge-success', 'label' => __('Published', 'simple-page-builder')),
            'draft' => array('class' => 'spb-badge-secondary', 'label' => __('Draft', 'simple-page-builder')),
            'pending' => array('class' => 'spb-badge-warning', 'label' => __('Pending', 'simple-page-builder')),
            'private' => array('class' => 'spb-badge-info', 'label' => __('Private', 'simple-page-builder'))
        );
        
        $badge = isset($badges[$status]) ? $badges[$status] : array('class' => 'spb-badge-secondary', 'label' => ucfirst($status));
        
        return '<span class="spb-badge ' . $badge['class'] . '">' . $badge['label'] . '</span>';
    }
    
    /**
     * Render recent activity
     */
    private function render_recent_activity() {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . SPB_TABLE_ACTIVITY_LOGS;
        $table_keys = $wpdb->prefix . SPB_TABLE_API_KEYS;
        
        $activities = $wpdb->get_results(
            "SELECT l.*, k.key_name 
             FROM $table_logs l 
             LEFT JOIN $table_keys k ON l.api_key_id = k.id 
             WHERE l.endpoint = 'POST /create-pages' 
             AND l.pages_created > 0 
             ORDER BY l.created_at DESC 
             LIMIT 10"
        );
        
        if (empty($activities)) {
            echo '<p>' . __('No recent page creation activity.', 'simple-page-builder') . '</p>';
            return;
        }
        
        echo '<ul class="spb-activity-list">';
        foreach ($activities as $activity) {
            $response = json_decode($activity->response_body, true);
            $pages_created = isset($response['data']['total_created']) ? $response['data']['total_created'] : $activity->pages_created;
            
            echo '<li class="spb-activity-item">';
            echo '<span class="spb-activity-time">' . date_i18n('H:i', strtotime($activity->created_at)) . '</span>';
            echo '<span class="spb-activity-endpoint">' . ($activity->key_name ? esc_html($activity->key_name) : '<em>' . __('Unknown', 'simple-page-builder') . '</em>') . '</span>';
            echo '<span class="spb-activity-status ' . ($activity->status_code >= 400 ? 'spb-status-error' : 'spb-status-success') . '">' . intval($activity->status_code) . '</span>';
            echo '<span class="spb-activity-pages">' . intval($pages_created) . ' ' . __('pages', 'simple-page-builder') . '</span>';
            echo '<span class="spb-activity-time">' . floatval($activity->response_time) . 'ms</span>';
            echo '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * Add bulk actions to pages list
     */
    public function add_bulk_actions($bulk_actions) {
        $bulk_actions['mark_as_api_created'] = __('Mark as API Created', 'simple-page-builder');
        $bulk_actions['mark_as_manual'] = __('Mark as Manual', 'simple-page-builder');
        return $bulk_actions;
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $doaction, $post_ids) {
        if ($doaction === 'mark_as_api_created') {
            foreach ($post_ids as $post_id) {
                update_post_meta($post_id, '_spb_created_via_api', '1');
                update_post_meta($post_id, '_spb_api_key_id', '0');
                update_post_meta($post_id, '_spb_created_at', current_time('mysql'));
            }
            $redirect_to = add_query_arg('bulk_marked_api_created', count($post_ids), $redirect_to);
        } elseif ($doaction === 'mark_as_manual') {
            foreach ($post_ids as $post_id) {
                delete_post_meta($post_id, '_spb_created_via_api');
                delete_post_meta($post_id, '_spb_api_key_id');
                delete_post_meta($post_id, '_spb_created_at');
            }
            $redirect_to = add_query_arg('bulk_marked_manual', count($post_ids), $redirect_to);
        }
        
        return $redirect_to;
    }
    
    /**
     * Add custom column to pages list
     */
    public function add_custom_column($columns) {
        $columns['created_via'] = __('Created Via', 'simple-page-builder');
        return $columns;
    }
    
    /**
     * Render custom column
     */
    public function render_custom_column($column, $post_id) {
        if ($column === 'created_via') {
            $is_api_created = get_post_meta($post_id, '_spb_created_via_api', true);
            
            if ($is_api_created) {
                $api_key_id = get_post_meta($post_id, '_spb_api_key_id', true);
                $api_key_name = $this->get_api_key_name($api_key_id);
                
                echo '<span class="spb-badge spb-badge-success">' . __('API', 'simple-page-builder') . '</span>';
                
                if ($api_key_name) {
                    echo '<br><small>' . esc_html($api_key_name) . '</small>';
                }
                
                $created_at = get_post_meta($post_id, '_spb_created_at', true);
                if ($created_at) {
                    echo '<br><small>' . date_i18n('Y-m-d H:i', strtotime($created_at)) . '</small>';
                }
            } else {
                echo '<span class="spb-badge spb-badge-secondary">' . __('Manual', 'simple-page-builder') . '</span>';
            }
        }
    }
    
    /**
     * Add filter dropdown to pages list
     */
    public function add_filter_dropdown($post_type) {
        if ($post_type !== 'page') {
            return;
        }
        
        $selected = isset($_GET['spb_filter']) ? $_GET['spb_filter'] : '';
        
        ?>
        <select name="spb_filter" id="spb_filter">
            <option value=""><?php _e('All Creation Methods', 'simple-page-builder'); ?></option>
            <option value="api_created" <?php selected($selected, 'api_created'); ?>>
                <?php _e('API Created', 'simple-page-builder'); ?>
            </option>
            <option value="manual" <?php selected($selected, 'manual'); ?>>
                <?php _e('Manual', 'simple-page-builder'); ?>
            </option>
        </select>
        <?php
    }
    
    /**
     * Filter query based on selection
     */
    public function filter_query($query) {
        global $pagenow;
        
        if ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'page' && isset($_GET['spb_filter'])) {
            if ($_GET['spb_filter'] === 'api_created') {
                $query->query_vars['meta_key'] = '_spb_created_via_api';
                $query->query_vars['meta_value'] = '1';
            } elseif ($_GET['spb_filter'] === 'manual') {
                $query->query_vars['meta_query'] = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_spb_created_via_api',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => '_spb_created_via_api',
                        'value' => '1',
                        'compare' => '!='
                    )
                );
            }
        }
    }
}