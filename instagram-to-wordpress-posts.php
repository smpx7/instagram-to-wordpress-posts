<?php
/**
 * Plugin Name: Instagram to WordPress Posts
 * Description: A plugin to fetch Instagram posts using the Instagram Basic Display API, store them as a custom post type, and provide a settings page.
 * Version: 1.8.1
 * Author: Sven Grün
 * Text Domain: instagram-to-wordpress-posts
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/smpx7/instagram-to-wordpress-posts
 * GitHub Branch: main
 */

// Prevent direct access to the file
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define a constant for the Instagram Basic Display API URL
define( 'ITWP_API_URL', 'https://graph.instagram.com/me/media' );

// Start session if not already started
function itwp_start_session() {
	if ( ! session_id() ) {
		session_start();
	}
}

add_action( 'init', 'itwp_start_session', 1 );

// Load text domain for translations
function itwp_load_textdomain() {
	load_plugin_textdomain( 'instagram-to-wordpress-posts', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

add_action( 'plugins_loaded', 'itwp_load_textdomain' );

// Enqueue the JavaScript for handling the AJAX fetch
function itwp_enqueue_scripts( $hook ) {
	// Check if we're on the settings page or the post type listing page
	if ( $hook == 'settings_page_itwp' || $hook == 'edit.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] == 'instagram_post' ) {
		wp_enqueue_script( 'itwp-fetch-script', plugin_dir_url( __FILE__ ) . 'assets/js/itwp-fetch.js', array( 'jquery' ), '1.0', true );
		wp_localize_script( 'itwp-fetch-script', 'itwp_ajax_obj', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'itwp_fetch_nonce' )
		) );
	}
}

add_action( 'admin_enqueue_scripts', 'itwp_enqueue_scripts' );


// Include necessary files
require_once plugin_dir_path( __FILE__ ) . 'includes/class-itwp-post-type.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-itwp-instagram-fetcher.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-itwp-media-handler.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-itwp-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-itwp-widget.php';
// Initialize the plugin components
ITWP_Post_Type::register();
ITWP_Settings::register();
ITWP_Instagram_Fetcher::register();

// Handle Instagram Authorization Callback
function itwp_handle_instagram_auth_callback() {
	if ( isset( $_GET['code'] ) ) {
		$code          = sanitize_text_field( $_GET['code'] );
		$client_id     = get_option( 'itwp_client_id' );  // Fetch stored Client ID
		$client_secret = get_option( 'itwp_client_secret' ); // Fetch stored Client Secret
		$redirect_uri  = admin_url( 'admin.php?page=itwp' ); // Must match the URI in your Instagram App settings

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			error_log( 'Instagram to WordPress Posts Error: Client ID or Client Secret is missing.' );

			return;
		}

		// Exchange the code for an access token
		$response = wp_remote_post( 'https://api.instagram.com/oauth/access_token', array(
			'body' => array(
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'grant_type'    => 'authorization_code',
				'redirect_uri'  => $redirect_uri,
				'code'          => $code
			)
		) );

		if ( is_wp_error( $response ) ) {
			error_log( 'Instagram to WordPress Posts Error: Failed to get access token. ' . $response->get_error_message() );

			return;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['access_token'] ) ) {
			update_option( 'itwp_access_token', sanitize_text_field( $data['access_token'] ) );
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Successfully authorized with Instagram.', 'instagram-to-wordpress-posts' ) . '</p></div>';
			} );
		} else {
			error_log( 'Instagram to WordPress Posts Error: Failed to retrieve access token from Instagram.' );
		}
	}
}

add_action( 'admin_init', 'itwp_handle_instagram_auth_callback' );

// Custom error and exception handler for debugging
function itwp_custom_error_handler( $errno, $errstr, $errfile, $errline ) {
	if ( get_option( 'itwp_debug_mode' ) === 'on' ) {
		echo "<b>Error:</b> [$errno] $errstr - $errfile:$errline";
		echo "<br />";
		echo "Terminating PHP Script";
		die();
	}

	return false; // Let the default PHP error handler handle it as well
}

function itwp_custom_exception_handler( $exception ) {
	if ( get_option( 'itwp_debug_mode' ) === 'on' ) {
		echo "<b>Exception:</b> " . $exception->getMessage();
		die();
	}
}

// Set custom error and exception handlers
set_error_handler( "itwp_custom_error_handler" );
set_exception_handler( "itwp_custom_exception_handler" );

