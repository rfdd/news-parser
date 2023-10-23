<?php
/**
 * Plugin Name: POST-GENERATOR
 * Version: 1.0
 */

include_once('config.php');

class APIContentIntegration {

    private $textEndpoint = "https://jsonplaceholder.typicode.com/posts";
    private $unsplashEndpoint = "https://api.unsplash.com/photos/random";
    private $unsplashAPIKey;
    private $logs = [];

    public function __construct() {
        $config = include('config.php');
        $this->unsplashAPIKey = $config['unsplashAPIKey'];
    }

    /**
     * Fetches an image URL from Unsplash.
     * 
     * @return string|false The image URL or false if the fetch fails.
     */
    public function fetchImageFromUnsplash() {
        $args = array(
            'headers' => array(
                'Authorization' => 'Client-ID ' . $this->unsplashAPIKey
            )
        );
        $response = wp_remote_get($this->unsplashEndpoint, $args);
        
        if (is_wp_error($response)) {
            $this->logs[] = "Failed to fetch image from Unsplash.";
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if(isset($data['urls']['regular'])) {
            $this->logs[] = "Successfully fetched image from Unsplash.";
            return $data['urls']['regular'];
        } else {
            $this->logs[] = "Failed to retrieve the image URL from Unsplash response.";
            return false;
        }
    }

    /**
     * Fetches text content from JSONPlaceholder.
     * 
     * @return array|false An array of text content or false if the fetch fails.
     */
    public function fetchTexts() {
        $response = wp_remote_get($this->textEndpoint);
        
        if (is_wp_error($response)) {
            $this->logs[] = "Failed to fetch text content.";
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $this->logs[] = "Successfully fetched text content.";
        return $data;
    }

    /**
     * Creates WordPress posts using fetched image and text.
     * 
     * @return bool True if posts are created successfully, false otherwise.
     */
    public function createPosts() {
        $imageUrl = $this->fetchImageFromUnsplash();
        $texts = $this->fetchTexts();

        if (!$imageUrl || !$texts) {
            $this->logs[] = "Failed to gather all necessary content for posts.";
            return false;
        }

        foreach ($texts as $text) {
            $post_arr = array(
                'post_title'    => $text['title'],
                'post_content'  => $text['body'] . '<img src="' . $imageUrl . '">',
                'post_status'   => 'publish'
            );
            wp_insert_post($post_arr);
        }

        $this->logs[] = "Posts created successfully.";
        return true;
    }

    /**
     * Gets the logs.
     * 
     * @return array An array of logs.
     */
    public function getLogs() {
        return $this->logs;
    }
}

// Adding a menu item in the admin panel
function api_content_integration_menu() {
    add_menu_page(
        'POST-GENERATOR',
        'POST-GENERATOR',
        'manage_options',
        'post-generator',
        'display_integration_page'
    );
}
add_action('admin_menu', 'api_content_integration_menu');

/**
 * Displays the integration page in the admin panel.
 */
function display_integration_page() {
    $integrator = new APIContentIntegration();

    echo '<h1>POST-GENERATOR</h1>';

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $success = $integrator->createPosts();
        if ($success) {
            echo '<p>Posts created successfully!</p>';
        } else {
            echo '<p>Failed to create posts.</p>';
        }

        foreach ($integrator->getLogs() as $log) {
            echo "<p>$log</p>";
        }
    }

    echo '<form method="post">';
    echo '<input type="submit" value="submit" name="fetch_content" />';
    echo '</form>';
}
