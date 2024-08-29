<?php

class ITWP_Media_Handler {

	public static function save_instagram_post( $post, $date_format ) {
		$post_id = wp_insert_post(array(
			'post_title' => 'Instagram Post ' . $post['id'],
			'post_content' => isset($post['caption']) ? $post['caption'] : '',
			'post_status' => 'publish',
			'post_type' => 'instagram_post',
			'post_date' => date($date_format, strtotime($post['timestamp'])),
		));

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			if ($post['media_type'] == 'IMAGE' || $post['media_type'] == 'CAROUSEL_ALBUM') {
				self::save_image_to_media_library($post['media_url'], $post_id);
			} elseif ($post['media_type'] == 'VIDEO') {
				self::save_video_to_media_library($post['media_url'], $post_id);
			}
		}
	}

	public static function save_image_to_media_library( $image_url, $post_id ) {
		$upload_dir = wp_upload_dir();
		$image_data = file_get_contents( $image_url );

		if ( $image_data ) {
			$filename = basename( $image_url );
			$file = $upload_dir['path'] . '/' . $filename;

			if ( file_put_contents( $file, $image_data ) ) {
				$wp_filetype = wp_check_filetype( $filename, null );
				$attachment = array(
					'post_mime_type' => $wp_filetype['type'],
					'post_title' => sanitize_file_name( $filename ),
					'post_content' => '',
					'post_status' => 'inherit'
				);

				$attach_id = wp_insert_attachment( $attachment, $file, $post_id );
				require_once( ABSPATH . 'wp-admin/includes/image.php' );
				$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
				wp_update_attachment_metadata( $attach_id, $attach_data );
				set_post_thumbnail( $post_id, $attach_id );
			}
		}
	}

	public static function save_video_to_media_library( $video_url, $post_id ) {
		// Implement video saving logic here
	}
}
