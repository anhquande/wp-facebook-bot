<?php
/*
Plugin Name: Bot For WP
Description: Messenger bot for WordPress
Plugin URI: http://anhquan.de/wordpress/plugins/bot-for-wp
Author: Anh Quan Nguyen
Author URI: http://anhquan.de
Version: 1.0
License: GPL2
 */
if (!defined('ABSPATH')) {
	exit;
}

class Bot_For_WP {
	function __construct() {

    error_log('invoked __construct FROM BOT FOR WORDPRESS');
    $this->welcome = 'Hello, i am a smart bot running on Wordpress and I will answer your question basing on the API.AI';
  
		// Verify Token
		$this->verify_token = 'i_love_lilly_more_than_you';
		// FB URL for posting
		$this->graph_api_url = 'https://graph.facebook.com/v2.6/me/messages?access_token=';
		// Page access token
		$this->access_token = 'EAAHky2SS0DsBAGxkp5se0Wadi8bZBuJ4jXhbOXYtZCpkjF094puPm5iwH7t3DF4IWGZAZBd1u218W6ZAD7ExmkbUfVpk4aGP7DFtu8XZCLUwZCoCJ3LmIoaVZC7ZBYCQRMa6o3cCxfZCCBfo3wibn2BEGvwG7neEEc6jA2m4cMogcSpwZDZD';

		// Add route for facebook to post/get requests
		add_action('rest_api_init', array($this, 'register_routes'));
	}
	function register_routes() {
    // Namespace of our plugin
		$this->app_domain = 'bot-for-wp';
		register_rest_route($this->app_domain, '/bot', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array($this, 'get'),
				'permission_callback' => array($this, 'verify_request'),
			),
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array($this, 'post'),
			),
		));
	}
	function verify_request($request) {
    error_log('verify_request function invoked!');
		$params = $request->get_query_params();
    error_log(print_r($params,true));
		if ($params && isset($params['hub_challenge']) && $params['hub_verify_token'] == $this->verify_token) {
			return true;
		}
		return false;
	}
	function get($request) {
    error_log('get function invoked!');

		$params = $request->get_query_params();
		echo $params['hub_challenge'];
		die();
	}
	function post($request) {
    error_log('post function invoked!');
		$params = $request->get_params();
    error_log(print_r($params,true));
		if ($params && $params['entry']) {
			foreach ((array) $params['entry'] as $entry) {
				if ($entry && $entry['messaging']) {
					foreach ((array) $entry['messaging'] as $message) {
						$this->process_message($message);
					}
				}
			}
		}
		die();
	}
  
  function send_message($data) {
    // Graph URL With Token
		$graph = $this->graph_api_url . $this->access_token;
    error_log('graph '.$graph);
    error_log('data ');
   
		$response = wp_remote_post($graph, $data);
    
    if ( is_wp_error( $response ) ) {
       $error_message = $response->get_error_message();
       error_log("Something went wrong: $error_message");
    } else {
        error_log('response = ');
        error_log(print_r($response,true));
    }

  }
  
  function request_API_AI($question){
    $accessToken = "d9f36939dfa54029a306b472d1e880c5";
		$baseUrl = 'https://api.api.ai/v1/';
    $args = array(
      'headers' => array(
        'Authorization' => 'Bearer '. $accessToken,
        'Content-type' => 'application/json',
      ),
      'body' => json_encode(array(
        "q" => $question,
        "lang"  => 'en'
      )),
      'method' => 'POST'
    );

    $api_request    = $baseUrl.'query/';
    //$api_response = wp_remote_get( $api_request );
    error_log ('ReQUEST at '.$api_request);
    error_log (print_r($args,true));
    $api_response = wp_remote_request( $api_request, $args );

      
    if ( is_wp_error( $api_response ) ) {
       $error_message = $response->get_error_message();
       error_log( "Something went wrong: $error_message");
       return false;
    } else {
        error_log ('API_RESPONE:');
        error_log (print_r($api_response,true));
        $api_data = json_decode( wp_remote_retrieve_body( $api_response ), true );
        
        error_log ('API_BODY:');
        error_log (print_r($api_data,true));
        return $api_data;
    }

  }
  
	function process_message($message) {
    
    error_log('process_message function invoked!');
    
    error_log(print_r($message,true));
		// Facebook alos sends delivevery messages
		// so its better to check for text.
		if (!isset($message['message']['text'])) {
			return;
		}
		$sender_id = strval($message['sender']['id']);
		$text = strtolower($message['message']['text']);

		if ($text == 'posts') {
			$template = array(
				'attachment' => array(
					'type' => 'template',
					'payload' => array(
						'template_type' => 'generic',
						'elements' => $this->get_posts_elements(),
					),
				),
			);
		} else {
			// Text Template
      $response = $this->request_API_AI($message['message']['text']);
      
      if (!$response){
        //error happense
        $template = array('text' => 'There is something wrong when requesting API.AI');
      }
      else{
        $answer =  $response['result']['speech'];
        error_log('Answer : '.print_r($answer,true));
        $template = array('text' => $answer);
        error_log('template : '.print_r($template,true));
      }
     
		}
    
    $body = array(
				'recipient' => array('id' => $sender_id),
				'message' => $template,
			);
    
		$data = array(
			'body' => $body,
      'headers' => array(
        'Content-Type' => 'application/json'
       ) 
		);
    
    $this->send_message($data);    
	}

	function get_posts_elements() {
		$args = array(
			'posts_per_page' => 10,
			'post_type' => 'post',
		);
		$posts = get_posts($args);
		$elements = [];
		if ($posts) {
			foreach ($posts as $post) {
        
        $img = 'https://chihuong.com/wp-content/uploads/2016/06/noimage.jpg';
        if (has_post_thumbnail( $post->ID ) ): 
          $img = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'single-post-thumbnail' )[0];
        endif;

				$data = array(
					'title' => $this->truncate($post->post_title, 45),
					'image_url' => $img,
					'subtitle' => '',
					'buttons' => array(
						array(
							'type' => 'web_url',
							'url' => get_permalink($post),
							'title' => 'Open',
						),
					),
				);
				$elements[] = $data;
			}
		}
		return $elements;
	}
	function truncate($text, $length) {
		// This is for truncating title and subtitles
		$length = abs((int) $length);
		$text = trim(preg_replace("/&#?[a-z0-9]{2,8};/i", "", $text));
		if (strlen($text) > $length) {
			$text = preg_replace("/^(.{1,$length})(\s.*|$)/s", '\\1...', $text);
		}
		return ($text);
	}
}
new Bot_For_WP;