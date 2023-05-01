<?php
//Register assets for Settings
add_action('init', function () {
    $handle = 'settings';
    if( file_exists(dirname(__FILE__, 3). "/build/admin-page-$handle.asset.php" ) ){
        $assets = include dirname(__FILE__, 3). "/build/admin-page-$handle.asset.php";
        $dependencies = $assets['dependencies'];
        wp_register_script(
            $handle,
            plugins_url("/build/admin-page-$handle.js", dirname(__FILE__, 2)),
            $dependencies,
            $assets['version']
        );
		// Localize the script with wpApiSettings
		wp_localize_script($handle, 'wpApiSettings', array(
			'nonce' => wp_create_nonce('wp_rest'),
		));	
    }
});

//Register API Route to read and update settings.
add_action('rest_api_init', function (){
    //Register route
    register_rest_route( 'atp-connect/v1' , '/settings/', [
        //Endpoint to get settings from
        [
            'methods' => ['GET'],
            'callback' => function($request){
                return rest_ensure_response( [
                    'data' => [
                        'enabled' => false,
                    ]
                ], 200);
            },
            'permission_callback' => function(){
                return current_user_can('manage_options');
            }
        ],
        //Endpoint to update settings at
        [
            'methods' => ['POST'],
            'callback' => function($request){
                return rest_ensure_response( $request->get_params(), 200);
            },
            'permission_callback' => function(){
                return current_user_can('manage_options');
            }
        ]
    ]);
});

//Enqueue assets for Settings on admin page only
add_action('admin_enqueue_scripts', function ($hook) {
    if ('toplevel_page_settings' != $hook) {
        return;
    }
    wp_enqueue_script('settings');
});

//Register Settings menu page
add_action('admin_menu', function () {
    add_menu_page(
        __('Settings', 'atp-connect'),
        __('Settings', 'atp-connect'),
        'manage_options',
        'settings',
        function () {
            //React root
            echo '<div id="settings"></div>';
        }
    );
});

add_action('rest_api_init', 'register_custom_login_route');

function register_custom_login_route() {
    register_rest_route('my-plugin/v1', '/login', array(
        'methods' => 'POST',
        'callback' => 'handle_login_request',
    ));
}

function handle_login_request(WP_REST_Request $request) {
    $username = $request->get_param('username');
    $password = $request->get_param('password');

    // Perform WordPress user authentication
    $user = wp_authenticate($username, $password);

    if (is_wp_error($user)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Invalid credentials'), 401);
    }

    // Perform AT Protocol authentication
    // Replace this part with the actual AT Protocol login process
    $at_protocol_login_result = agent_login($username, $password);

    if (!$at_protocol_login_result['success']) {
        return new WP_REST_Response(array('success' => false, 'message' => 'AT Protocol login failed'), 500);
    }

    // Return success response with session data
    return new WP_REST_Response(array('success' => true, 'session' => $at_protocol_login_result['session']), 200);
}

function agent_login($username, $password) {
    // Replace this with the actual AT Protocol login process
    // using agent.login() or agent.createAccount() methods
    return array('success' => true, 'session' => array());
}
