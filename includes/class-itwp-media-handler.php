<?php

class ITWP_Media_Handler {

	const POST_TYPE_IMAGE = 'IMAGE';
	const POST_TYPE_VIDEO = 'VIDEO';
	const POST_TYPE_CAROUSEL_ALBUM = 'CAROUSEL_ALBUM';
	protected static $media_metakey_name = 'itwp_instagram_media_id';
	public static $post_status_name = 'itwp-hidden';
	protected static $prefix_name = 'itwp';
	/**
	 * @var ITWP_Media_Handler
	 */
	protected static $instance;

	/**
	 * @return mixed
	 */
	public static function getInstance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function save_instagram_post( $post, $date_format ) {

		$post_id   = $post['id'];
		$permalink = $post['permalink'] ?? '';  // Null coalescing to simplify isset check

		// Convert Instagram timestamp to WordPress format
		$post_date = date( 'Y-m-d H:i:s', strtotime( $post['timestamp'] ) );

		// Use meta query instead of get_posts() for better performance
		$existing_post = get_posts( array(
			'post_type'      => ITWP_Post_Type::POST_TYPE,
			'meta_key'       => 'instagram_post_id',
			'meta_value'     => $post_id,
			'posts_per_page' => 1,
		) );

		if ( ! empty( $existing_post ) ) {
			return;
		}

		$attachment_ids = [];

		try {
			// Handle media based on type
			switch ( $post['media_type'] ) {
				case self::POST_TYPE_IMAGE:
					$attachment_ids[] = self::handle_single_media( $post['media_url'], $post_id, self::POST_TYPE_IMAGE );
					break;

				case self::POST_TYPE_CAROUSEL_ALBUM:
					$attachment_ids = self::handle_carousel_media( $post['children']['data'], $post_id );
					break;

				case self::POST_TYPE_VIDEO:
					$attachment_ids[] = self::handle_video_media( $post['media_url'], $post['thumbnail_url'], $post_id );
					break;
			}

			// Prepare and sanitize post content
			$post_content      = ! empty( $post['caption'] ) ? nl2br( esc_html( $post['caption'] ) ) : '';
			$sanitized_content = wp_encode_emoji( wp_kses_post( $post_content ) );
			$post_title        = wp_encode_emoji( 'Instagram Post ' . date( $date_format, strtotime( $post['timestamp'] ) ) );

			// Insert the Instagram post in WordPress
			$new_post_id = wp_insert_post( array(
				'post_title'   => $post_title,
				'post_content' => $sanitized_content,
				'post_status'  => 'publish',
				'post_type'    => ITWP_Post_Type::POST_TYPE,
				'post_date'    => $post_date,
				'meta_input'   => array(
					'instagram_post_id'        => $post_id,
					'_itwp_fetch_datetime'     => current_time( 'mysql' ),
					'_itwp_content'            => $sanitized_content,
					'_itwp_media_type'         => $post['media_type'],
					'instagram_post_permalink' => $permalink,
				),
			) );

			if ( is_wp_error( $new_post_id ) ) {
				throw new Exception( 'Failed to create post in WordPress: ' . $new_post_id->get_error_message() );
			}

			// Set the post parent and set featured image if applicable
			self::update_media_post_parent( $new_post_id, $attachment_ids );

		} catch ( Exception $e ) {
			error_log( 'Error saving Instagram post: ' . $e->getMessage() );
		}
	}

	/**
	 * Handle single image or video media saving.
	 */
	private static function handle_single_media( $media_url, $post_id, $media_type ) {
		return self::save_media_to_library( $media_url, 0, $post_id, $media_type );
	}

	/**
	 * Handle carousel media saving.
	 */
	private static function handle_carousel_media( $children, $post_id ) {
		$attachment_ids = [];
		$i              = 0;
		foreach ( $children as $k => $child ) {
			$media_url     = $child['media_url'];
			$media_type    = $child['media_type'];
			$attachment_id = self::save_media_to_library( $media_url, $i, $post_id, $media_type );

			if ( $media_type === self::POST_TYPE_VIDEO ) {
				$thumbnail_url           = $child['thumbnail_url'];
				$attachment_thumbnail_id = self::save_media_to_library( $thumbnail_url, $i, $post_id, self::POST_TYPE_IMAGE );
				error_log( 'setting thumbnail ' . $attachment_thumbnail_id . ' for video ' . $attachment_id );
				set_post_thumbnail( $attachment_id, $attachment_thumbnail_id );
				#wp_update_attachment_metadata( $attachment_id, array( '_thumbnail_id' => $attachment_thumbnail_id ) );
			}

			$attachment_ids[] = $attachment_id;
			$i ++;
		}

		return $attachment_ids;
	}

	/**
	 * Handle video media saving.
	 */
	private static function handle_video_media( $media_url, $thumbnail_url, $post_id ) {
		$attachment_id           = self::save_media_to_library( $media_url, 0, $post_id, self::POST_TYPE_VIDEO );
		$attachment_thumbnail_id = self::save_media_to_library( $thumbnail_url, 0, $post_id, self::POST_TYPE_IMAGE );
		$r                       = set_post_thumbnail( $attachment_id, $attachment_thumbnail_id );
		error_log( 'setting thumbnail ' . $attachment_thumbnail_id . ' for video ' . $attachment_id . ': ' . var_export( $r, true ) );

		#if ( ! is_wp_error( $attachment_id ) && ! is_wp_error( $attachment_thumbnail_id ) ) {
		#wp_update_attachment_metadata( $attachment_id, array( '_thumbnail_id' => $attachment_thumbnail_id ) );
		#}

		return $attachment_id;
	}

	/**
	 * Update media post parent.
	 */
	private static function update_media_post_parent( $post_id, $attachment_ids ) {
		foreach ( $attachment_ids as $attachment_id ) {
			wp_update_post( array(
				'ID'          => $attachment_id,
				'post_parent' => $post_id,
			) );
		}
	}

	/**
	 * @param $post_name
	 *
	 * @return string|WP_Error
	 */
	public static function wp_get_attachment_by_post_name( $post_name ) {

		$args = array(
			'posts_per_page' => 1,
			'post_type'      => 'attachment',
			'name'           => trim( $post_name ),
		);

		$get_attachment = new WP_Query( $args );

		if ( ! $get_attachment || ! isset( $get_attachment->posts, $get_attachment->posts[0] ) ) {
			return false;
		}

		return $get_attachment->posts[0];
	}

	/**
	 * Save media file to the WordPress media library
	 *
	 * @param string $media_url
	 * @param int $key
	 * @param string $post_id instagram post id
	 * @param string $media_type (IMAGE|VIDEO)
	 *
	 * @return int|WP_Error|false
	 */
	public static function save_media_to_library( $media_url, $key, $post_id, $media_type ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$is_video = $media_type === 'VIDEO';

		$upload_dir = wp_upload_dir();
		$subdir     = 'itwp-images';

		// Create subdirectory if it doesn't exist
		$custom_dir = $upload_dir['basedir'] . '/' . $subdir;

		if ( ! file_exists( $custom_dir ) ) {
			wp_mkdir_p( $custom_dir ); // Create the directory if it doesn't exist
		}

		// Download url to a temp file
		$tmp = download_url( $media_url ); // string with tmp file path or WP_Error

		if ( ! is_wp_error( $tmp ) ) {
			$filename = sanitize_file_name( $post_id . '_' . $key . ( $is_video ? '.mp4' : '.jpg' ) );

			//Check if it doesn't exist
			$get_local_file = self::wp_get_attachment_by_post_name( $filename );

			if ( $get_local_file && isset( $get_local_file->ID ) ) {
				update_post_meta( $get_local_file->ID, self::$media_metakey_name, $post_id );

				return $get_local_file->ID;
			}

			// Move the file to the custom directory
			$file_path = $custom_dir . '/' . $filename;
			error_log( 'Saving media (key=' . $key . ', type: ' . $media_type . ') to library: ' . $filename );
			$file_content = file_get_contents( $tmp );
			if ( file_put_contents( $file_path, $file_content ) !== false ) {
				error_log( 'Media saved to library: ' . $file_path );
				// Remove temp file
				//unlink( $tmp );

				$wp_filetype = wp_check_filetype( $file_path, null );
				if ( $wp_filetype['type'] == 'image/jpeg' && extension_loaded( 'imagick' ) ) {
					// strip exif data to avoid error in wp_read_image_metadata
					try {
						$img = new Imagick( $file_path );
						$img->stripImage();
						$img->writeImage( $file_path );
					} catch ( Exception $e ) {
						error_log( 'Error stripping exif data: ' . $e->getMessage() );
					}
				}

				$attachment = array(
					'guid'           => $upload_dir['url'] . '/' . $subdir . '/' . basename( $file_path ),
					'post_mime_type' => $wp_filetype['type'],
					'post_title'     => sanitize_file_name( $filename ),
					'post_content'   => '',
					'post_status'    => 'inherit'
				);

				// Insert the attachment into the media library
				$attachment_id = wp_insert_attachment( $attachment, $file_path, 0 );

				if ( ! is_wp_error( $attachment_id ) ) {
					//	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $file_path ) );
					require_once( ABSPATH . 'wp-admin/includes/image.php' );

					// Step 3: Generate the image sizes (thumbnails, medium, large, etc.)
					$attachment_metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
					wp_update_attachment_metadata( $attachment_id, $attachment_metadata );
				}

				return $attachment_id;
			} else {
				error_log( 'Error saving media to library: ' . $file_path );
			}

			// Remove temp file
			//unlink( $tmp );
		} else {
			error_log( 'Error downloading media: ' . $media_url );

			return $tmp;
		}
		// Remove temp file
		unlink( $tmp );

		return false;
	}
}

ITWP_Media_Handler::getInstance();