<?php

class ITWP_Media_Handler {

	public static function save_instagram_post( $post, $date_format ) {
		$post_id   = $post['id'];
		$permalink = isset( $post['permalink'] ) ? $post['permalink'] : '';

		// Convert Instagram timestamp to WordPress format
		$post_date = date( 'Y-m-d H:i:s', strtotime( $post['timestamp'] ) );

		// Check if post already exists
		$existing_post = get_posts( array(
			'post_type'      => 'instagram_post',
			'meta_key'       => 'instagram_post_id',
			'meta_value'     => $post_id,
			'posts_per_page' => 1,
		) );

		if ( ! empty( $existing_post ) ) {
			return;
		}

		// Initialize variables
		$post_content = '';
		$media_ids    = [];

		try {
			if ( $post['media_type'] == 'IMAGE' ) {
				// Single image post
				$media_url = $post['media_url'];
				$media_id  = self::save_media_to_library( $media_url, $post_id, 'image/jpeg' );

				if ( $media_id === false || is_wp_error( $media_id ) ) {
					throw new Exception( 'Failed to save image to media library.' );

				}

				$media_ids[] = $media_id;
			} elseif ( $post['media_type'] == 'CAROUSEL_ALBUM' /*&& isset( $post['children']['data'] )*/ ) {
				// Carousel post with multiple images
				foreach ( $post['children']['data'] as $child ) {
					if ( ! isset( $child['media_type'] ) || $child['media_type'] == 'IMAGE' ) {
						$media_url = $child['media_url'];
						$media_id  = self::save_media_to_library( $media_url, $post_id, 'image/jpeg' );

						if ( $media_id === false || is_wp_error( $media_id ) ) {
							throw new Exception( 'Failed to save image to media library.' );
						}

						$media_ids[] = $media_id;
					}
				}
			} elseif ( $post['media_type'] == 'VIDEO' ) {
				// Video post
				$media_url = $post['media_url'];
				$media_id  = self::save_media_to_library( $media_url, $post_id, 'video/mp4' );

				if ( $media_id === false || is_wp_error( $media_id ) ) {
					throw new Exception( 'Failed to save video to media library.' );
				}

				$media_ids[] = $media_id;
			}

			// Append the caption text to the post content with nl2br for line breaks
			if ( ! empty( $post['caption'] ) ) {
				$post_content .= nl2br( esc_html( $post['caption'] ) );
			}

			// Sanitize the post content
			$sanitized_content = wp_kses_post( $post_content );

			// Encode emojis in content and title
			$sanitized_content = wp_encode_emoji( $sanitized_content );
			$post_title        = wp_encode_emoji( 'Instagram Post ' . date( $date_format, strtotime( $post['timestamp'] ) ) );

			// Create the post in WordPress
			$new_post_id = wp_insert_post( array(
				'post_title'   => $post_title,
				'post_content' => $sanitized_content,
				'post_status'  => 'publish',
				'post_type'    => 'instagram_post',
				'post_date'    => $post_date, // Use Instagram post date
				'meta_input'   => array(
					'instagram_post_id'        => $post_id,
					'_itwp_fetch_datetime'     => current_time( 'mysql' ),
					'_itwp_content'            => $sanitized_content,
					'_itwp_media_type'         => $post['media_type'],
					'instagram_post_permalink' => $permalink, // Save the permalink as a meta field
				),
			) );

			// Check for wp_insert_post errors
			if ( is_wp_error( $new_post_id ) ) {
				throw new Exception( 'Failed to create post in WordPress: ' . $new_post_id->get_error_message() );
			}

			// Set the post parent for each media attachment and set the first image as featured image
			foreach ( $media_ids as $index => $media_id ) {
				wp_update_post( array(
					'ID'          => $media_id,
					'post_parent' => $new_post_id,
				) );

				if ( $index === 0 ) {
					set_post_thumbnail( $new_post_id, $media_id );
				}
			}

		} catch ( Exception $e ) {
			if ( get_option( 'itwp_debug_mode' ) === 'on' ) {
				echo '<pre>Error saving Instagram post: ' . esc_html( $e->getMessage() ) . '</pre>';
			}
			error_log( 'Error saving Instagram post: ' . $e->getMessage() );
		}
	}

	/**
	 * Save media file to the WordPress media library
	 *
	 * @param string $media_url
	 * @param int $post_id
	 * @param string $mime_type
	 *
	 * @return int|WP_Error|false
	 */
	public static function save_media_to_library( $media_url, $post_id, $mime_type ) {
		$upload_dir  = wp_upload_dir();
		$subdir      = 'instagram_posts';
		$upload_path = $upload_dir['path'] . '/' . $subdir;

		// Create subdirectory if it doesn't exist
		if ( ! file_exists( $upload_path ) ) {
			wp_mkdir_p( $upload_path );
		}

		$unique_id = md5( $media_url );
		$filename  = $post_id . '_' . $unique_id . '.' . pathinfo( parse_url( $media_url, PHP_URL_PATH ), PATHINFO_EXTENSION );

		// Convert to jpg or mp4 if needed
		$file_extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( $file_extension !== 'jpg' && $mime_type === 'image/jpeg' ) {
			$filename = $post_id . '_' . $unique_id . '.jpg';
		} elseif ( $file_extension !== 'mp4' && $mime_type === 'video/mp4' ) {
			$filename = $post_id . '_' . $unique_id . '.mp4';
		}

		$file_path = $upload_path . '/' . $filename;

		if ( file_exists( $file_path ) ) {
			// get attachment_id by file_path
			$attachment_id = attachment_url_to_postid( $upload_dir['url'] . '/' . $subdir . '/' . basename( $file_path ) );
			if ( $attachment_id === 0 ) {
				return new WP_Error( 'file_exists', __( 'Media file already exists.', 'instagram-to-wordpress-posts' ) );
			}

			return $attachment_id;
		}

		// Download file to local path
		$file_content = self::get_file_content_with_curl( $media_url );
		if ( $file_content === false ) {
			return new WP_Error( 'download_error', __( 'Failed to download media from Instagram.', 'instagram-to-wordpress-posts' ) );
		}

		// Prepare the attachment

		if ( file_put_contents( $file_path, $file_content ) ) {
			$wp_filetype = wp_check_filetype( $filename, null );
			$attachment  = array(
				'guid'           => $upload_dir['url'] . '/' . $subdir . '/' . basename( $file_path ),
				'post_mime_type' => $wp_filetype['type'],
				'post_title'     => sanitize_file_name( $filename ),
				'post_content'   => '',
				'post_status'    => 'inherit'
			);


			// Insert the attachment into the media library
			$attachment_id = wp_insert_attachment( $attachment, $file_path, 0 );

			if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}

			$attachment_metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
			wp_update_attachment_metadata( $attachment_id, $attachment_metadata );

			return $attachment_id;
		}

		return false;
	}

	/**
	 * Save media file to the WordPress media library using cURL
	 *
	 * @param string $url
	 *
	 * @return bool|string
	 */
	public static function get_file_content_with_curl( $url ) {
		// Initialize cURL session
		$ch = curl_init();

		// Set the URL to fetch
		curl_setopt( $ch, CURLOPT_URL, $url );

		// Set option to return the result as a string
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

		// Optional: Set a user agent string
		curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; PHP cURL)' );

		// Optional: Set a timeout
		curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );

		// Execute the cURL request
		$file_content = curl_exec( $ch );

		// Check for any cURL errors
		if ( curl_errno( $ch ) ) {
			echo 'cURL error: ' . curl_error( $ch );

			return false;
		}

		// Get HTTP status code
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

		// Check if the request was successful (status code 200)
		if ( $http_code != 200 ) {
			echo 'HTTP error: ' . $http_code;

			return false;
		}

		// Close the cURL session
		curl_close( $ch );

		return $file_content;
	}
}
