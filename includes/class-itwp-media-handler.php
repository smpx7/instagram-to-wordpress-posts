<?php

class ITWP_Media_Handler {

	public static function save_instagram_post( $post, $date_format ) {
		$post_id = $post['id'];
		$permalink = isset($post['permalink']) ? $post['permalink'] : '';

		// Check if post already exists
		$existing_post = get_posts( array(
			'post_type' => 'instagram_post',
			'meta_key' => 'instagram_post_id',
			'meta_value' => $post_id,
			'posts_per_page' => 1,
		) );

		if ( ! empty( $existing_post ) ) {
			return;
		}

		// Initialize variables
		$post_content = '';
		$media_id = null;

		try {
			if ( $post['media_type'] == 'IMAGE' || $post['media_type'] == 'CAROUSEL_ALBUM' ) {
				$media_url = $post['media_url'];
				$media_id = self::save_media_to_library( $media_url, $post_id, 'image/jpeg' );

				if ( is_wp_error( $media_id ) ) {
					throw new Exception( 'Failed to save image to media library.' );
				}

				// Use the local image URL in the post content without the alt attribute
				$image_url = wp_get_attachment_url( $media_id );
				$post_content .= '<img src="' . esc_url( $image_url ) . '" />';
			} elseif ( $post['media_type'] == 'VIDEO' ) {
				$media_url = $post['media_url'];
				$media_id = self::save_media_to_library( $media_url, $post_id, 'video/mp4' );

				if ( is_wp_error( $media_id ) ) {
					throw new Exception( 'Failed to save video to media library.' );
				}

				// Embed the video in the post content
				$video_url = wp_get_attachment_url( $media_id );
				$post_content .= '<video controls><source src="' . esc_url( $video_url ) . '" type="video/mp4"></video>';
			}

			// Append the caption text to the post content with nl2br for line breaks
			if ( !empty( $post['caption'] ) ) {
				$post_content .= '<p>' . nl2br( esc_html( $post['caption'] ) ) . '</p>';
			}

			// Create the post in WordPress
			$new_post_id = wp_insert_post( array(
				'post_title'    => 'Instagram Post ' . date( $date_format, strtotime( $post['timestamp'] ) ),
				'post_content'  => $post_content,
				'post_status'   => 'publish',
				'post_type'     => 'instagram_post',
				'meta_input'    => array(
					'instagram_post_id' => $post_id,
					'_itwp_fetch_datetime' => current_time( 'mysql' ),
					'instagram_post_permalink' => $permalink, // Save the permalink as a meta field
				),
			) );

			// Check for wp_insert_post errors
			if ( is_wp_error( $new_post_id ) ) {
				throw new Exception( 'Failed to create post in WordPress: ' . $new_post_id->get_error_message() );
			}

			if ( $media_id ) {
				// Set the post parent of the attachment to the new post ID
				wp_update_post( array(
					'ID' => $media_id,
					'post_parent' => $new_post_id,
				) );

				// Set the featured image of the post
				set_post_thumbnail( $new_post_id, $media_id );
			}

		} catch ( Exception $e ) {
			if (get_option('itwp_debug_mode') === 'on') {
				echo '<pre>Error saving Instagram post: ' . $e->getMessage() . '</pre>';
			}
			error_log( 'Error saving Instagram post: ' . $e->getMessage() );
		}
	}

	public static function save_media_to_library( $media_url, $post_id, $mime_type ) {
		$upload_dir = wp_upload_dir();
		$subdir = 'instagram_posts';
		$upload_path = $upload_dir['path'] . '/' . $subdir;

		// Create subdirectory if it doesn't exist
		if ( ! file_exists( $upload_path ) ) {
			wp_mkdir_p( $upload_path );
		}

		$unique_id = uniqid();
		$filename = $post_id . '_' . $unique_id . '.' . pathinfo( parse_url( $media_url, PHP_URL_PATH ), PATHINFO_EXTENSION );

		// Convert to jpg or mp4 if needed
		$file_extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( $file_extension !== 'jpg' && $mime_type === 'image/jpeg' ) {
			$filename = $post_id . '_' . $unique_id . '.jpg';
		} elseif ( $file_extension !== 'mp4' && $mime_type === 'video/mp4' ) {
			$filename = $post_id . '_' . $unique_id . '.mp4';
		}

		$file_path = $upload_path . '/' . $filename;

		// Download file to local path
		$file_content = file_get_contents( $media_url );
		if ( $file_content === false ) {
			return new WP_Error( 'download_error', __( 'Failed to download media from Instagram.', 'instagram-to-wordpress-posts' ) );
		}

		file_put_contents( $file_path, $file_content );

		// Prepare the attachment
		$attachment = array(
			'guid'           => $upload_dir['url'] . '/' . $subdir . '/' . basename( $file_path ),
			'post_mime_type' => $mime_type,
			'post_title'     => sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
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
}
