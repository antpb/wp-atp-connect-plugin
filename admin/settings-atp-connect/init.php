<?php
//Register assets for Settings
add_action('init', function () {
    $handle = 'settings-atp-connect';
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
						'unified_session_data' => get_option( 'unified_session_data', false ),
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
    if ('settings_page_settings-atp-connect' != $hook) {
        return;
    }
    wp_enqueue_script('settings-atp-connect');
});

//Register Settings menu page
add_action('admin_menu', function () {
	add_options_page(
		__('ATP Settings', 'atp-connect'),
		__('ATP Settings', 'atp-connect'),
		'manage_options',
		'settings-atp-connect',
		function () {
			// React root
			echo '<div id="settings"></div>';
		}
	);
});

function encrypt_data_post($value = ""){
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

function decrypt_data_post($value = ""){
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

add_action('rest_api_init', 'register_custom_post_route');

function register_custom_post_route() {
    register_rest_route('my-plugin/v1', '/handlepost', array(
        'methods' => 'POST',
        'callback' => 'handle_post',
    ));
}

function handle_post(WP_REST_Request $request) {
    $post = $request->get_param('post');

    // Retrieve the encrypted login data from the options table
    $encrypted_login_data = get_option('unified_session_data', []);
    $username = $encrypted_login_data['username'];
    $password = decrypt_data_post($encrypted_login_data['password']);
    $host = $encrypted_login_data['host'];
    $postTypes = $encrypted_login_data['postTypes'];

    $at_protocol_login_result = create_post_with_jwt($username, $password, $post, $host);

	if (is_wp_error($at_protocol_login_result)) {
        return new WP_Error('invalid_nonce', 'Invalid nonce', array('status' => 403));
    }

	// log the session to the error logs
	// error_log(print_r($at_protocol_login_result, true));
	
    // Return success response with session data
    return new WP_REST_Response(array('success' => true, 'session' => $at_protocol_login_result['session']), 200);
}

function create_post_with_jwt($username, $password, $post, $host) {
    // Set up the login request
    $login_url = "{$host}/xrpc/com.atproto.server.createSession";
    $login_data = array(
        'identifier' => $username,
        'password' => $password
    );
    $login_args = array(
        'body' => json_encode($login_data),
        'headers' => array(
            'Content-Type' => 'application/json',
            'Content-Length' => strlen(json_encode($login_data))
		),
		'sslverify'   => false
    );
    
	
    $login_response = wp_remote_post($login_url, $login_args);

	// Extract the JWT and repo from the login response
    $response_body = json_decode($login_response['body']);
    $jwt = $response_body->accessJwt;
    $repo = $response_body->did;

	// convert $post to string
	$post = (string) $post;
	// Extract links
	$link_pattern = '/(^|\s|\()((https?:\/\/[\S]+)|((?<domain>[a-z][a-z0-9]*(\.[a-z0-9]+)+)[\S]*))/i';
	preg_match_all($link_pattern, $post, $matches, PREG_SET_ORDER);
    $facets = array();
	$embed_data = array();
	foreach ($matches as $match) {
		$uri = $match[2];
		if (!strpos($uri, 'http') === 0) {
			$uri = 'https://' . $uri;
		}
		$start = strpos($post, $match[2]);
		$end = $start + strlen($match[2]);
	
		$link_card_data = fetch_wordpress_post_data($uri, $jwt, $host);
		if ($link_card_data) {
			$external_data = array(
				'$type' => 'app.bsky.embed.external#external',
				'uri' => $link_card_data['uri'],
				'title' => $link_card_data['title'],
				'description' => $link_card_data['description'],
			);

			if( null !== $link_card_data['thumb']) {
				$blob_ref = $link_card_data['thumb'];
				$external_data['thumb'] = $blob_ref;
			}

			$embed_data = array(
				'$type' => 'app.bsky.embed.external#main',
				'external' => $external_data,
			);
								
			$facets[] = array(
				'index' => array(
					'byteStart' => $start,
					'byteEnd' => $end
				),
				'features' => array(
					array(
						'$type' => 'app.bsky.richtext.facet#link',
						'uri' => $uri
					)
				)
			);
		} else {
			$facets[] = array(
				'index' => array(
					'byteStart' => $start,
					'byteEnd' => $end
				),
				'features' => array(
					array(
						'$type' => 'app.bsky.richtext.facet#link',
						'uri' => $uri
					)
				)
			);
		}
	}
		
    // Set up the post request
    $post_url = "{$host}/xrpc/com.atproto.repo.createRecord";

	// if no embed data do not include embed
	if (empty($embed_data)) {
		$post_data = array(
			'repo' => $repo,
			'collection' => 'app.bsky.feed.post',
			'record' => array(
				'$type' => 'app.bsky.feed.post',
				'createdAt' => date('c'),
				'text' => $post,
				'facets' => $facets
			)
		);
	} else {
	// log embed data
	// error_log(print_r($embed_data, true));
    $post_data = array(
        'repo' => $repo,
        'collection' => 'app.bsky.feed.post',
        'record' => array(
            '$type' => 'app.bsky.feed.post',
            'createdAt' => date('c'),
            'text' => $post,
            'facets' => $facets,
			'embed' => $embed_data
        )
    );
	}
    $post_args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $jwt,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($post_data),
		'sslverify'   => false
    );
    
    // Send the post request
    $post_response = wp_remote_post($post_url, $post_args);
	error_log(print_r($post_response, true));

    // Return the post response
    return $post_response;
}

function fetch_wordpress_post_data($url, $jwt, $host) {
    $post_id = url_to_postid($url);

    if ($post_id === 0) {
        return null;
    }

    $post = get_post($post_id);

    if (!$post) {
        return null;
    }

    $link_card_data = array(
        'uri' => $url,
        'title' => $post->post_title,
        'description' => $post->post_excerpt,
    );

    $thumbnail_id = get_post_thumbnail_id($post_id);
    if ($thumbnail_id) {
        $thumbnail = wp_get_attachment_image_src($thumbnail_id, 'full');    
        if ($thumbnail) {
            $imageUrl = $thumbnail[0];
            $imageBlobData = uploadImageAndGetBlobId($imageUrl, $jwt, $host);
			if ($imageBlobData !== null) {
				$blob_ref = array(
					'$type' => 'blob',
					'ref' => array(
						'$link' => $imageBlobData['link']
					),
					'mimeType' => $imageBlobData['mimeType'],
					'size' => $imageBlobData['size']
				);
				$link_card_data['thumb'] = $blob_ref;
			}
        }
    }

    return $link_card_data;
}

function uploadImageAndGetBlobId($imageUrl, $jwt, $host) {
    $imageData = file_get_contents($imageUrl);
    if ($imageData === false) {
        error_log("Failed to get image data from URL: " . $imageUrl);
        return null;
    }

    $upload_url = "{$host}/xrpc/com.atproto.repo.uploadBlob";
    $upload_args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $jwt,
            'Content-Type' => 'image/jpeg',
            'Accept' => 'application/json'
        ),
        'body' => $imageData,
		'sslverify'   => false
    );

    // Send the image upload request
    $upload_response = wp_remote_post($upload_url, $upload_args);

    // Extract the blob ID from the upload response
    if (is_wp_error($upload_response)) {
        error_log("Image upload failed: " . $upload_response->get_error_message());
        return null;
    }
    $response_body = json_decode($upload_response['body'], true);
    $blobId = isset($response_body['blob']['ref']['$link']) ? $response_body['blob']['ref']['$link'] : null;

	return array(
		'link' => $blobId,
		'size' => $response_body['blob']['size'],
		'mimeType' => $response_body['blob']['mimeType']
	);
}

function getImageBase64($imageUrl) {
    $imageData = file_get_contents($imageUrl);
    if ($imageData === false) {
        return false;
    }
    $imageBase64 = base64_encode($imageData);
    return $imageBase64;
}


function handle_post_publish($new_status, $old_status, $post) {
	$atp_settings = get_option( 'unified_session_data', false );
	// comma separate $atp_settings['postTypes'] value and if post type is not in the list, return
	$post_types = explode(',', $atp_settings['postTypes']);
	//error log post types
	error_log(print_r($post_types, true));
	error_log(print_r($post->post_type, true));
	if (!in_array($post->post_type, $post_types)) {
		return;
	} 
    if ('publish' !== $new_status || 'publish' === $old_status) {
        return;
    }

    $title = $post->post_title;
    $excerpt = $post->post_excerpt; // Use excerpt instead of content
    $permalink = get_permalink($post->ID);

    $combined_content = $title . "\n" . $permalink;

    $request = new WP_REST_Request('POST');
    $request->set_param('post', $combined_content);

    $response = handle_post($request);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log("Something went wrong: $error_message");
    } else {
        // Handle the success response from the server
        $response_body = $response->get_data();
        if ($response_body['success']) {
            // Do something with the success response
            // ...
			// error_log("OK: " . $response_body['message']);

        } else {
            // Handle the error response from the server
            // error_log("Error: " . $response_body['message']);
        }
    }
}
add_action('transition_post_status', 'handle_post_publish', 10, 3);