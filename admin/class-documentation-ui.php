<?php
class SPB_Documentation_UI {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Render the Documentation page
     */
    public function render_page() {
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'simple-page-builder'));
        }
        
        $api_url = get_rest_url(null, 'pagebuilder/v1/create-pages');
        $health_url = get_rest_url(null, 'pagebuilder/v1/health');
        
        ?>
        <div class="spb-documentation-page">
            <div class="spb-header">
                <h2><?php _e('API Documentation', 'simple-page-builder'); ?></h2>
                <p class="description">
                    <?php _e('Complete documentation for using the Page Builder API.', 'simple-page-builder'); ?>
                </p>
            </div>
            
            <!-- Quick Start Card -->
            <div class="spb-card">
                <h3><?php _e('Quick Start', 'simple-page-builder'); ?></h3>
                
                <div class="spb-alert spb-alert-info">
                    <p>
                        <strong><?php _e('Base URL:', 'simple-page-builder'); ?></strong>
                        <code><?php echo esc_url(get_rest_url()); ?></code>
                    </p>
                </div>
                
                <h4><?php _e('1. Generate an API Key', 'simple-page-builder'); ?></h4>
                <ol>
                    <li><?php _e('Go to Tools → Page Builder → API Keys', 'simple-page-builder'); ?></li>
                    <li><?php _e('Click "Generate New API Key"', 'simple-page-builder'); ?></li>
                    <li><?php _e('Save the key immediately - it will only be shown once!', 'simple-page-builder'); ?></li>
                </ol>
                
                <h4><?php _e('2. Make Your First Request', 'simple-page-builder'); ?></h4>
                <pre><code>curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY_HERE" \
  -d '{
    "pages": [
      {
        "title": "About Us",
        "content": "&lt;p&gt;Welcome to our website!&lt;/p&gt;",
        "status": "publish"
      }
    ]
  }' \
  <?php echo esc_url($api_url); ?></code></pre>
            </div>
            
            <!-- API Endpoints Card -->
            <div class="spb-card">
                <h3><?php _e('API Endpoints', 'simple-page-builder'); ?></h3>
                
                <div class="spb-endpoint">
                    <h4>POST <?php echo esc_html($api_url); ?></h4>
                    <p class="description"><?php _e('Create one or multiple pages in bulk.', 'simple-page-builder'); ?></p>
                    
                    <h5><?php _e('Authentication', 'simple-page-builder'); ?></h5>
                    <p><?php _e('Include your API key in the request headers:', 'simple-page-builder'); ?></p>
                    <pre><code>X-API-Key: YOUR_API_KEY_HERE</code></pre>
                    
                    <h5><?php _e('Request Body', 'simple-page-builder'); ?></h5>
                    <pre><code>{
  "pages": [
    {
      "title": "Page Title (required)",
      "content": "Page content in HTML",
      "excerpt": "Short excerpt",
      "status": "publish", // draft, publish, pending, private
      "template": "page-template.php",
      "menu_order": 0,
      "meta": {
        "custom_field": "value"
      },
      "taxonomies": {
        "category": ["Uncategorized"]
      }
    }
  ]
}</code></pre>
                </div>
                
                <div class="spb-endpoint">
                    <h4>GET <?php echo esc_html($health_url); ?></h4>
                    <p class="description"><?php _e('Check API health status (no authentication required).', 'simple-page-builder'); ?></p>
                </div>
            </div>
            
            <!-- Response Examples Card -->
            <div class="spb-card">
                <h3><?php _e('Response Examples', 'simple-page-builder'); ?></h3>
                
                <h4><?php _e('Success Response', 'simple-page-builder'); ?></h4>
                <pre><code>{
  "success": true,
  "request_id": "req_abc123xyz",
  "message": "Created 2 pages, 1 failed",
  "data": {
    "total_requested": 3,
    "total_created": 2,
    "total_failed": 1,
    "created_pages": [
      {
        "id": 123,
        "title": "About Us",
        "url": "https://example.com/about",
        "edit_url": "https://example.com/wp-admin/post.php?post=123&action=edit",
        "status": "publish"
      },
      {
        "id": 124,
        "title": "Contact",
        "url": "https://example.com/contact",
        "edit_url": "https://example.com/wp-admin/post.php?post=124&action=edit",
        "status": "publish"
      }
    ],
    "errors": [
      {
        "index": 2,
        "title": "Test Page",
        "error": "Page title is required"
      }
    ],
    "response_time_ms": 452.34
  }
}</code></pre>
                
                <h4><?php _e('Error Response', 'simple-page-builder'); ?></h4>
                <pre><code>{
  "success": false,
  "request_id": "req_def456uvw",
  "error": {
    "code": "authentication_failed",
    "message": "Invalid API key or insufficient permissions"
  },
  "response_time_ms": 12.45
}</code></pre>
            </div>
            
            <!-- Error Codes Card -->
            <div class="spb-card">
                <h3><?php _e('Error Codes', 'simple-page-builder'); ?></h3>
                
                <div class="spb-table-responsive">
                    <table class="widefat fixed">
                        <thead>
                            <tr>
                                <th><?php _e('HTTP Status', 'simple-page-builder'); ?></th>
                                <th><?php _e('Error Code', 'simple-page-builder'); ?></th>
                                <th><?php _e('Description', 'simple-page-builder'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="spb-badge spb-badge-danger">401</span></td>
                                <td><code>missing_api_key</code></td>
                                <td><?php _e('API key is missing from request headers.', 'simple-page-builder'); ?></td>
                            </tr>
                            <tr>
                                <td><span class="spb-badge spb-badge-danger">401</span></td>
                                <td><code>authentication_failed</code></td>
                                <td><?php _e('Invalid API key, expired, or insufficient permissions.', 'simple-page-builder'); ?></td>
                            </tr>
                            <tr>
                                <td><span class="spb-badge spb-badge-danger">429</span></td>
                                <td><code>rate_limit_exceeded</code></td>
                                <td><?php _e('Rate limit exceeded for this API key.', 'simple-page-builder'); ?></td>
                            </tr>
                            <tr>
                                <td><span class="spb-badge spb-badge-warning">400</span></td>
                                <td><code>invalid_data</code></td>
                                <td><?php _e('Invalid request data or missing required fields.', 'simple-page-builder'); ?></td>
                            </tr>
                            <tr>
                                <td><span class="spb-badge spb-badge-warning">400</span></td>
                                <td><code>too_many_pages</code></td>
                                <td><?php _e('Exceeded maximum pages per request (default: 100).', 'simple-page-builder'); ?></td>
                            </tr>
                            <tr>
                                <td><span class="spb-badge spb-badge-danger">500</span></td>
                                <td><code>internal_error</code></td>
                                <td><?php _e('Internal server error. Check WordPress logs.', 'simple-page-builder'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Webhooks Card -->
            <div class="spb-card">
                <h3><?php _e('Webhooks', 'simple-page-builder'); ?></h3>
                
                <p><?php _e('When pages are successfully created via API, a webhook notification can be sent to a configured URL.', 'simple-page-builder'); ?></p>
                
                <h4><?php _e('Webhook Payload', 'simple-page-builder'); ?></h4>
                <pre><code>{
  "event": "pages_created",
  "timestamp": "2025-10-07T14:30:00Z",
  "request_id": "req_abc123xyz",
  "api_key_name": "Production Server",
  "api_key_id": 5,
  "total_pages": 3,
  "pages": [
    {
      "id": 123,
      "title": "About Us",
      "url": "http://site.com/about",
      "edit_url": "http://site.com/wp-admin/post.php?post=123&action=edit",
      "status": "publish"
    }
  ],
  "site_url": "http://site.com",
  "site_name": "My Website"
}</code></pre>
                
                <h4><?php _e('Signature Verification', 'simple-page-builder'); ?></h4>
                <p><?php _e('If a webhook secret is configured, verify the signature:', 'simple-page-builder'); ?></p>
                <pre><code>// PHP example
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$secret = 'YOUR_WEBHOOK_SECRET';

$expected_signature = hash_hmac('sha256', $payload, $secret);
$is_valid = hash_equals($expected_signature, $signature);</code></pre>
                
                <div class="spb-alert spb-alert-warning">
                    <p>
                        <strong><?php _e('Important:', 'simple-page-builder'); ?></strong>
                        <?php _e('Always verify webhook signatures to ensure the request came from your WordPress site.', 'simple-page-builder'); ?>
                    </p>
                </div>
            </div>
            
            <!-- Rate Limiting Card -->
            <div class="spb-card">
                <h3><?php _e('Rate Limiting', 'simple-page-builder'); ?></h3>
                
                <ul>
                    <li><?php _e('Default rate limit: 100 requests per hour per API key', 'simple-page-builder'); ?></li>
                    <li><?php _e('Can be adjusted per key or globally in settings', 'simple-page-builder'); ?></li>
                    <li><?php _e('Rate limits are reset hourly', 'simple-page-builder'); ?></li>
                    <li><?php _e('Exceeding limits returns HTTP 429 with retry-after header', 'simple-page-builder'); ?></li>
                </ul>
                
                <h4><?php _e('Headers', 'simple-page-builder'); ?></h4>
                <pre><code>X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1665147600</code></pre>
            </div>
            
            <!-- Client Libraries Card -->
            <div class="spb-card">
                <h3><?php _e('Client Libraries', 'simple-page-builder'); ?></h3>
                
                <h4><?php _e('Python', 'simple-page-builder'); ?></h4>
                <pre><code>import requests
import json

api_key = "YOUR_API_KEY"
api_url = "<?php echo esc_url($api_url); ?>"

headers = {
    "Content-Type": "application/json",
    "X-API-Key": api_key
}

data = {
    "pages": [
        {
            "title": "New Page",
            "content": "&lt;p&gt;Page content&lt;/p&gt;",
            "status": "publish"
        }
    ]
}

response = requests.post(api_url, headers=headers, json=data)
print(response.json())</code></pre>
                
                <h4><?php _e('JavaScript/Node.js', 'simple-page-builder'); ?></h4>
                <pre><code>const fetch = require('node-fetch');

const apiKey = 'YOUR_API_KEY';
const apiUrl = '<?php echo esc_url($api_url); ?>';

const data = {
  pages: [
    {
      title: 'New Page',
      content: '&lt;p&gt;Page content&lt;/p&gt;',
      status: 'publish'
    }
  ]
};

fetch(apiUrl, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-API-Key': apiKey
  },
  body: JSON.stringify(data)
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));</code></pre>
                
                <h4><?php _e('PHP', 'simple-page-builder'); ?></h4>
                <pre><code>$apiKey = 'YOUR_API_KEY';
$apiUrl = '<?php echo esc_url($api_url); ?>';

$data = [
    'pages' => [
        [
            'title' => 'New Page',
            'content' => '&lt;p&gt;Page content&lt;/p&gt;',
            'status' => 'publish'
        ]
    ]
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-Key: ' . $apiKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$result = json_decode($response, true);

curl_close($ch);

print_r($result);</code></pre>
            </div>
            
            <!-- Support Card -->
            <div class="spb-card">
                <h3><?php _e('Support & Troubleshooting', 'simple-page-builder'); ?></h3>
                
                <h4><?php _e('Common Issues', 'simple-page-builder'); ?></h4>
                <dl>
                    <dt><?php _e('API returning 401', 'simple-page-builder'); ?></dt>
                    <dd><?php _e('Check that the API key is correct and active. Verify it\'s in the X-API-Key header.', 'simple-page-builder'); ?></dd>
                    
                    <dt><?php _e('Pages not appearing', 'simple-page-builder'); ?></dt>
                    <dd><?php _e('Check the status field. "draft" pages won\'t be publicly visible.', 'simple-page-builder'); ?></dd>
                    
                    <dt><?php _e('Webhooks not firing', 'simple-page-builder'); ?></dt>
                    <dd><?php _e('Verify webhook URL is correct and accessible. Check the webhook logs.', 'simple-page-builder'); ?></dd>
                    
                    <dt><?php _e('Rate limit errors', 'simple-page-builder'); ?></dt>
                    <dd><?php _e('Increase the rate limit in key settings or implement request batching.', 'simple-page-builder'); ?></dd>
                </dl>
                
                <h4><?php _e('Debugging', 'simple-page-builder'); ?></h4>
                <ul>
                    <li><?php _e('Check the Activity Log for detailed request/response information', 'simple-page-builder'); ?></li>
                    <li><?php _e('Enable WordPress debug logging for technical details', 'simple-page-builder'); ?></li>
                    <li><?php _e('Test with the Health endpoint to verify API is running', 'simple-page-builder'); ?></li>
                    <li><?php _e('Use the Test Webhook button in Settings to verify webhook delivery', 'simple-page-builder'); ?></li>
                </ul>
            </div>
        </div>
        
        <style>
        .spb-documentation-page .spb-endpoint {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .spb-documentation-page .spb-endpoint:last-child {
            border-bottom: none;
        }
        
        .spb-documentation-page h4 {
            margin-top: 25px;
            margin-bottom: 10px;
            color: #23282d;
        }
        
        .spb-documentation-page h5 {
            margin-top: 20px;
            margin-bottom: 10px;
            color: #666;
        }
        
        .spb-documentation-page pre {
            background: #f6f8fa;
            border: 1px solid #e1e4e8;
            border-radius: 3px;
            padding: 16px;
            overflow: auto;
            font-size: 13px;
            line-height: 1.45;
            margin: 15px 0;
        }
        
        .spb-documentation-page code {
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            font-size: 13px;
        }
        
        .spb-documentation-page dl {
            margin: 15px 0;
        }
        
        .spb-documentation-page dt {
            font-weight: bold;
            margin-top: 10px;
        }
        
        .spb-documentation-page dd {
            margin-left: 20px;
            margin-bottom: 10px;
            color: #666;
        }
        
        .spb-documentation-page ol,
        .spb-documentation-page ul {
            margin: 15px 0 15px 20px;
        }
        
        .spb-documentation-page li {
            margin-bottom: 5px;
        }
        </style>
        <?php
    }
}