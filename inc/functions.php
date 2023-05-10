<?php 

function load_base64_image(WP_REST_Request $request) {
	$url = $request->get_json_params()['url'];
  
	if (!function_exists('wp_get_image_editor')) {
	  return new WP_Error('Error', 'WordPress image editor not found.', array('status' => 500));
	}
  
	$response = wp_remote_get($url, array('timeout' => 30));
  
	if (is_wp_error($response)) {
	  return new WP_Error('Error', 'Error fetching image from URL.', array('status' => 500));
	}
  
	$image_data = wp_remote_retrieve_body($response);
	$image_info = getimagesizefromstring($image_data);
	if ($image_info === false) {
	  return new WP_Error('Error', 'Invalid image data.', array('status' => 500));
	}
  
	$base64_image = 'data:image/jpeg;base64,' . preg_replace('/\s+/', '', base64_encode($image_data));
	
	return rest_ensure_response(array('base64Image' => $base64_image));
  }
  
// Register a custom REST API endpoint

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('wp-api');
});

// Register a custom REST API endpoint for login
function register_bluesky_login_route() {
    register_rest_route('atp_connect/v1', '/bluesky_login/', array(
        'methods' => 'POST',
        'callback' => 'handle_bluesky_login',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'register_bluesky_login_route');

// Callback function for the custom REST API endpoint for login
function handle_bluesky_login(WP_REST_Request $request) {
    // Save the provided username and password to the database
    $provided_username = $request->get_param('username');
    $provided_password = $request->get_param('password');
    $provided_host = $request->get_param('host');
    $provided_post_types = $request->get_param('postTypes');

    $encrypted_login_data = [
        'username' => $provided_username,
        'password' => encrypt_data_post($provided_password),
        'host'     => $provided_host,
        'postTypes'     => $provided_post_types,
    ];

    update_option('unified_session_data', $encrypted_login_data);

    return rest_ensure_response(array('success' => true, 'message' => 'Data saved successfully'));
}