<?php 
function register_unified_session_route() {
    register_rest_route('your_namespace/v1', '/unified_session/', array(
        'methods' => 'GET',
        'callback' => 'get_unified_session_data',
        'permission_callback' => 'is_user_logged_in',
    ));

    register_rest_route('your_namespace/v1', '/unified_session/', array(
        'methods' => 'POST',
        'callback' => 'store_unified_session_data',
        'permission_callback' => 'is_user_logged_in',
    ));
}
add_action('rest_api_init', 'register_unified_session_route');

function get_unified_session_data() {
    $user_id = get_current_user_id();
    $unified_session_data = get_user_meta($user_id, 'unified_session_data', true);
    return rest_ensure_response($unified_session_data);
}

function store_unified_session_data(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $session_data = $request->get_param('session_data');

    if (!$session_data) {
        return new WP_Error('missing_session_data', 'Session data is required', array('status' => 400));
    }

    update_user_meta($user_id, 'unified_session_data', $session_data);

    return rest_ensure_response(array('success' => true));
}

// Add this code to your functions.php file

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/image-proxy', array(
        'methods' => 'POST',
        'callback' => 'load_base64_image',
    ));
});
  
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
