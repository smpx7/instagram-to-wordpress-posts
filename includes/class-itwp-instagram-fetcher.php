<?php

class ITWP_Instagram_Fetcher {

	public static function register() {
		add_action( 'wp', array( __CLASS__, 'schedule_instagram_fetch' ) );
		add_action( 'itwp_daily_instagram_fetch', array( __CLASS__, 'fetch_and_store_instagram_posts' ) );
		add_action( 'wp_ajax_itwp_manual_fetch', array( __CLASS__, 'ajax_start_fetch' ) );
		add_action( 'wp_ajax_itwp_fetch_next_batch', array( __CLASS__, 'ajax_fetch_next_batch' ) );
	}

	public static function schedule_instagram_fetch() {
		if ( ! wp_next_scheduled( 'itwp_daily_instagram_fetch' ) ) {
			wp_schedule_event( time(), 'daily', 'itwp_daily_instagram_fetch' );
		}
	}

	public static function ajax_start_fetch() {
		check_ajax_referer( 'itwp_fetch_nonce', 'security' );

		$access_token = get_option( 'itwp_access_token', '' );
		$fetch_limit = get_option( 'itwp_fetch_limit', 10 ); // User-defined limit
		$batch_size = 10; // Set batch size to 10 posts

		if ( empty( $access_token ) ) {
			wp_send_json_error( array( 'message' => __( 'Instagram Access Token is missing. Please configure it in the settings page.', 'instagram-to-wordpress-posts' ) ) );
		}

		// Initialize fetch and error handling
		try {
			// Initialize or reset session variables at the start of each fetch run
			$_SESSION['itwp_total_posts'] = min($fetch_limit, 200); // Instagram API might limit the number, so cap at 200
			$_SESSION['itwp_fetched_posts'] = 0; // Reset fetched count
			$_SESSION['itwp_batch_size'] = $batch_size; // Set batch size for each fetch

			wp_send_json_success( array( 'total' => $_SESSION['itwp_total_posts'] ) );
		} catch ( Exception $e ) {
			error_log( 'Instagram to WordPress Posts AJAX Start Fetch Error: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => __( 'Error initializing the fetch process: ', 'instagram-to-wordpress-posts' ) . $e->getMessage() ) );
		}
	}

	public static function ajax_fetch_next_batch() {
		check_ajax_referer( 'itwp_fetch_nonce', 'security' );

		$access_token = get_option( 'itwp_access_token', '' );
		$batch_size = $_SESSION['itwp_batch_size']; // Fetch batch size from session

		if ( empty( $access_token ) ) {
			wp_send_json_error( array( 'message' => __( 'Instagram Access Token is missing. Please configure it in the settings page.', 'instagram-to-wordpress-posts' ) ) );
		}

		// Calculate the remaining posts to fetch
		$remaining_posts = $_SESSION['itwp_total_posts'] - $_SESSION['itwp_fetched_posts'];
		$fetch_limit = min($batch_size, $remaining_posts); // Fetch up to 10 posts or remaining posts

		// Fetch next batch and handle potential errors
		try {
			// Use the constant for the API URL and set limit to fetch limit
			$api_url = ITWP_API_URL . '?fields=id,caption,media_type,media_url,thumbnail_url,permalink,timestamp&limit=' . $fetch_limit . '&access_token=' . esc_attr( $access_token );

			// Fetch the latest posts in reverse chronological order
			$response = wp_remote_get( $api_url );

			if ( is_wp_error( $response ) ) {
				throw new Exception( 'Failed to retrieve Instagram posts from the API.' );
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( isset( $data['error'] ) ) {
				throw new Exception( 'Instagram API Error: ' . $data['error']['message'] );
			}

			if ( ! empty( $data['data'] ) ) {
				foreach ( $data['data'] as $post ) {
					ITWP_Media_Handler::save_instagram_post( $post, get_option( 'itwp_date_format', 'Y-m-d H:i:s' ) );
				}

				// Update session variable
				$_SESSION['itwp_fetched_posts'] += count( $data['data'] );

				wp_send_json_success( array( 'fetched' => $_SESSION['itwp_fetched_posts'], 'remaining' => $_SESSION['itwp_total_posts'] - $_SESSION['itwp_fetched_posts'] ) );
			} else {
				throw new Exception( 'No data received from Instagram API.' );
			}
		} catch ( Exception $e ) {
			error_log( 'Instagram to WordPress Posts AJAX Fetch Next Batch Error: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => __( 'Error fetching the next batch of posts: ', 'instagram-to-wordpress-posts' ) . $e->getMessage() ) );
		}
	}
}
