<?php 
function get_encrypted_login_data() {
    $encrypted_login_data = get_option('unified_session_data', []);

    // Decrypt the data before returning it
    $decrypted_data = array(
        'username' => three_decrypt($encrypted_login_data['username']),
        'password' => three_decrypt($encrypted_login_data['password']),
    );

    return rest_ensure_response($decrypted_data);
}

function store_encrypted_login_data(WP_REST_Request $request) {
    $username = $request->get_param('username');
    $password = $request->get_param('password');

    if (!$username || !$password) {
        return new WP_Error('missing_credentials', 'Username and password are required', array('status' => 400));
    }

    // Encrypt the username and password
    $encrypted_username = three_encrypt($username);
    $encrypted_password = three_encrypt($password);

    $encrypted_login_data = array('username' => $encrypted_username, 'password' => $encrypted_password);

    update_option('unified_session_data', $encrypted_login_data);

    return rest_ensure_response(array('success' => true));
}

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/image-proxy', array(
        'methods' => 'POST',
        'callback' => 'load_base64_image',
    ));
});

function encrypt_data($value = ""){
    if( empty( $value ) ) {
        return $value;
    }
    
    $output = null;
    $secret_key = defined('AUTH_KEY') ? AUTH_KEY : "";
    $secret_iv = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : "";
    $key = hash('sha256',$secret_key);
    $iv = substr(hash('sha256',$secret_iv),0,16);
    return base64_encode(openssl_encrypt($value,"AES-256-CBC",$key,0,$iv));
}

function decrypt_data($value = ""){
    if( empty( $value ) ) {
        return $value;
    }

    $output = null;
    $secret_key = defined('AUTH_KEY') ? AUTH_KEY : "";
    $secret_iv = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : "";
    $key = hash('sha256',$secret_key);
    $iv = substr(hash('sha256',$secret_iv),0,16);

    return openssl_decrypt(base64_decode($value),"AES-256-CBC",$key,0,$iv);
}

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
  