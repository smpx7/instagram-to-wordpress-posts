<?php
/**
 * Plugin Name: Instagram to WordPress Posts
 * Description: A plugin to fetch Instagram posts using the Instagram Basic Display API, store them as a custom post type, and provide a settings page.
 * Version: 1.6
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

// Load text domain for translations
function itwp_load_textdomain() {
	load_plugin_textdomain( 'instagram-to-wordpress-posts', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'itwp_load_textdomain' );

// Register custom post type for Instagram posts
function itwp_register_instagram_post_type() {
	$labels = array(
		'name'               => __( 'Instagram Posts', 'instagram-to-wordpress-posts' ),
		'singular_name'      => __( 'Instagram Post', 'instagram-to-wordpress-posts' ),
		'menu_name'          => __( 'Instagram Posts', 'instagram-to-wordpress-posts' ),
		'name_admin_bar'     => __( 'Instagram Post', 'instagram-to-wordpress-posts' ),
		'add_new'            => __( 'Add New', 'instagram-to-wordpress-posts' ),
		'add_new_item'       => __( 'Add New Instagram Post', 'instagram-to-wordpress-posts' ),
		'new_item'           => __( 'New Instagram Post', 'instagram-to-wordpress-posts' ),
		'edit_item'          => __( 'Edit Instagram Post', 'instagram-to-wordpress-posts' ),
		'view_item'          => __( 'View Instagram Post', 'instagram-to-wordpress-posts' ),
		'all_items'          => __( 'All Instagram Posts', 'instagram-to-wordpress-posts' ),
		'search_items'       => __( 'Search Instagram Posts', 'instagram-to-wordpress-posts' ),
		'not_found'          => __( 'No Instagram posts found.', 'instagram-to-wordpress-posts' ),
		'not_found_in_trash' => __( 'No Instagram posts found in Trash.', 'instagram-to-wordpress-posts' ),
	);

	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'query_var'          => true,
		'rewrite'            => array( 'slug' => 'instagram-post' ),
		'capability_type'    => 'post',
		'has_archive'        => true,
		'hierarchical'       => false, // Flat structure suitable for Instagram posts
		'menu_position'      => null,
		'supports'           => array( 'title', 'editor', 'thumbnail' ),
	);

	register_post_type( 'instagram_post', $args );
}
add_action( 'init', 'itwp_register_instagram_post_type' );

// Ensure theme supports post thumbnails
function itwp_theme_support() {
	add_theme_support('post-thumbnails', array('post', 'instagram_post'));
}
add_action('after_setup_theme', 'itwp_theme_support');

// Schedule a daily event to fetch Instagram posts
function itwp_schedule_instagram_fetch() {
	if ( ! wp_next_scheduled( 'itwp_daily_instagram_fetch' ) ) {
		wp_schedule_event( time(), 'daily', 'itwp_daily_instagram_fetch' );
	}
}
add_action( 'wp', 'itwp_schedule_instagram_fetch' );

// Fetch Instagram posts and save to database
function itwp_fetch_and_store_instagram_posts() {
	$access_token = get_option( 'itwp_access_token', '' );
	$fetch_limit = get_option( 'itwp_fetch_limit', 10 );
	$date_format = get_option( 'itwp_date_format', 'Y-m-d H:i:s' );

	if ( empty( $access_token ) ) {
		add_action('admin_notices', function() {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Instagram Access Token is missing. Please configure it in the settings page.', 'instagram-to-wordpress-posts' ) . '</p></div>';
		});
		return;
	}

	$api_url = 'https://graph.instagram.com/me/media?fields=id,caption,media_type,media_url,thumbnail_url,permalink,timestamp&limit=' . $fetch_limit . '&access_token=' . esc_attr( $access_token );

	// Fetch the latest posts in reverse chronological order
	$response = wp_remote_get( $api_url );

	if ( is_wp_error( $response ) ) {
		add_action('admin_notices', function() {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to retrieve Instagram posts.', 'instagram-to-wordpress-posts' ) . '</p></div>';
		});
		return;
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( isset( $data['error'] ) ) {
		add_action('admin_notices', function() use ($data) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Instagram API Error: ', 'instagram-to-wordpress-posts' ) . esc_html($data['error']['message']) . '</p></div>';
		});
		return;
	}

	if ( ! empty( $data['data'] ) ) {
		foreach ( $data['data'] as $post ) {
			$post_id = $post['id'];

			// Check if post already exists
			$existing_post = get_posts( array(
				'post_type' => 'instagram_post',
				'meta_key' => 'instagram_post_id',
				'meta_value' => $post_id,
				'posts_per_page' => 1,
			) );

			if ( ! empty( $existing_post ) ) {
				continue;
			}

			// Download the image to the WordPress media library
			$media_id = null;
			if ( $post['media_type'] == 'IMAGE' || $post['media_type'] == 'CAROUSEL_ALBUM' ) {
				$media_url = $post['media_url'];
				$media_id = itwp_save_image_to_media_library( $media_url, $post_id, 'image/jpeg' );

				if ( is_wp_error( $media_id ) ) {
					continue; // Skip this post if the image couldn't be saved
				}

				// Use the local image URL in the post content
				$image_url = wp_get_attachment_url( $media_id );
				$post_content = '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $post['caption'] ) . '" />';
			} elseif ( $post['media_type'] == 'VIDEO' ) {
				$media_url = $post['media_url'];
				$media_id = itwp_save_image_to_media_library( $media_url, $post_id, 'video/mp4' );

				if ( is_wp_error( $media_id ) ) {
					continue; // Skip this post if the video couldn't be saved
				}

				// Use the local video URL in the post content
				$video_url = wp_get_attachment_url( $media_id );
				$post_content = '<video controls src="' . esc_url( $video_url ) . '"></video>';
			}

			// Append caption to the post content
			$post_content .= '<p>' . esc_html( $post['caption'] ) . '</p>';

			// Convert Instagram timestamp to WordPress date format
			$post_date_gmt = gmdate( 'Y-m-d H:i:s', strtotime( $post['timestamp'] ) );
			$post_date = get_date_from_gmt( $post_date_gmt );

			// Format the post title using the selected date format
			$formatted_date = date( $date_format, strtotime( $post_date ) );
			$post_title = 'post ' . $formatted_date;

			// Insert post into database with Instagram post date
			$new_post = array(
				'post_title'   => $post_title,
				'post_content' => $post_content,
				'post_status'  => 'publish',
				'post_type'    => 'instagram_post',
				'post_date'    => $post_date,
				'post_date_gmt' => $post_date_gmt,
			);

			$new_post_id = wp_insert_post( $new_post );

			// Set the featured image if the post was successfully created and image was saved
			if ( $new_post_id && $media_id ) {
				set_post_thumbnail( $new_post_id, $media_id );

				// Update the attachment to set the correct post parent
				wp_update_post( array(
					'ID' => $media_id,
					'post_parent' => $new_post_id,
				) );
			}

			// Save Instagram post ID as post meta
			if ( $new_post_id ) {
				add_post_meta( $new_post_id, 'instagram_post_id', $post_id );
			}
		}
	}
}
add_action( 'itwp_daily_instagram_fetch', 'itwp_fetch_and_store_instagram_posts' );

// Function to save Instagram images or videos to the media library with a unique identifier and correct file format
function itwp_save_image_to_media_library( $media_url, $post_id, $desired_mime_type ) {
	// Define upload directory and folder
	$upload_dir = wp_upload_dir();
	$upload_path = $upload_dir['basedir'] . '/instagram-media/';

	// Create the directory if it doesn't exist
	if ( ! file_exists( $upload_path ) ) {
		wp_mkdir_p( $upload_path );
	}

	// Download the media
	$media_data = wp_remote_get( $media_url );

	if ( is_wp_error( $media_data ) ) {
		return $media_data;
	}

	$media_data = wp_remote_retrieve_body( $media_data );

	if ( empty( $media_data ) ) {
		return new WP_Error( 'media_download_error', 'Failed to download media from Instagram.' );
	}

	// Determine the original file extension
	$original_extension = pathinfo( $media_url, PATHINFO_EXTENSION );

	// Set the desired extension based on the desired MIME type
	$extension = ($desired_mime_type == 'image/jpeg') ? 'jpg' : 'mp4';

	// Generate a descriptive filename with the correct extension
	$filename = 'instagram_' . $post_id . '_' . uniqid() . '.' . $extension;
	$file_path = $upload_path . $filename;

	// Save the media to the local file system
	file_put_contents( $file_path, $media_data );

	// Convert the image to JPG or video to MP4 if necessary
	if ($desired_mime_type == 'image/jpeg' && $original_extension != 'jpg') {
		$image = imagecreatefromstring(file_get_contents($file_path));
		if ($image === false) {
			return new WP_Error( 'image_conversion_error', 'Failed to convert image to JPG.' );
		}
		imagejpeg($image, $file_path, 90);
		imagedestroy($image);
	} elseif ($desired_mime_type == 'video/mp4' && $original_extension != 'mp4') {
		// Conversion to MP4 would normally require a server-side tool like FFmpeg,
		// which is outside the scope of this example. Add your server-side conversion code here if needed.
	}

	// Prepare the media for WordPress media library
	$wp_filetype = wp_check_filetype( $filename, null );
	$attachment = array(
		'post_mime_type' => $wp_filetype['type'],
		'post_title'     => sanitize_file_name( $filename ),
		'post_content'   => '',
		'post_status'    => 'inherit'
	);

	// Insert the attachment into the media library
	$attach_id = wp_insert_attachment( $attachment, $file_path );

	require_once( ABSPATH . 'wp-admin/includes/image.php' );
	$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
	wp_update_attachment_metadata( $attach_id, $attach_data );

	return $attach_id;
}

// Shortcode to display Instagram posts from the database with a specified template
function itwp_display_instagram_posts( $atts ) {
	$atts = shortcode_atts( array(
		'count' => 5, // Number of posts to display
		'template' => 'grid', // Default template
	), $atts );

	// Determine template file
	$template_file = plugin_dir_path( __FILE__ ) . 'templates/template-' . sanitize_text_field( $atts['template'] ) . '.php';

	if ( ! file_exists( $template_file ) ) {
		return __( 'Template not found.', 'instagram-to-wordpress-posts' );
	}

	// Set $count variable for template
	$count = intval( $atts['count'] );

	// Include the template file
	ob_start();
	include $template_file;
	return ob_get_clean();
}
add_shortcode( 'itwp', 'itwp_display_instagram_posts' );

// Register settings and settings page
function itwp_register_settings() {
	add_option( 'itwp_access_token', '' );
	add_option( 'itwp_fetch_limit', 10 ); // Default limit
	add_option( 'itwp_date_format', 'Y-m-d H:i:s' ); // Default date format

	register_setting( 'itwp_options_group', 'itwp_access_token', 'itwp_callback' );
	register_setting( 'itwp_options_group', 'itwp_fetch_limit', 'intval' );
	register_setting( 'itwp_options_group', 'itwp_date_format', 'sanitize_text_field' );
}

add_action( 'admin_init', 'itwp_register_settings' );

function itwp_register_options_page() {
	add_options_page( __( 'Instagram API Settings', 'instagram-to-wordpress-posts' ), __( 'Instagram API', 'instagram-to-wordpress-posts' ), 'manage_options', 'itwp', 'itwp_options_page' );
}

add_action( 'admin_menu', 'itwp_register_options_page' );

function itwp_options_page() {
	?>
    <div>
        <h2><?php _e( 'Instagram API Settings', 'instagram-to-wordpress-posts' ); ?></h2>
        <form method="post" action="options.php">
			<?php settings_fields( 'itwp_options_group' ); ?>
            <table>
                <tr valign="top">
                    <th scope="row"><label for="itwp_access_token"><?php _e( 'Access Token', 'instagram-to-wordpress-posts' ); ?></label></th>
                    <td><input type="text" id="itwp_access_token" name="itwp_access_token" value="<?php echo esc_attr( get_option('itwp_access_token') ); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="itwp_fetch_limit"><?php _e( 'Number of Posts to Fetch', 'instagram-to-wordpress-posts' ); ?></label></th>
                    <td><input type="number" id="itwp_fetch_limit" name="itwp_fetch_limit" value="<?php echo esc_attr( get_option('itwp_fetch_limit', 10) ); ?>" min="1" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="itwp_date_format"><?php _e( 'Date Format for Post Title', 'instagram-to-wordpress-posts' ); ?></label></th>
                    <td>
                        <select id="itwp_date_format" name="itwp_date_format">
                            <option value="Y-m-d H:i:s" <?php selected( get_option('itwp_date_format'), 'Y-m-d H:i:s' ); ?>><?php echo date('Y-m-d H:i:s'); ?></option>
                            <option value="Y-m-d H:i" <?php selected( get_option('itwp_date_format'), 'Y-m-d H:i' ); ?>><?php echo date('Y-m-d H:i'); ?></option>
                            <option value="d.m.Y H:i:s" <?php selected( get_option('itwp_date_format'), 'd.m.Y H:i:s' ); ?>><?php echo date('d.m.Y H:i:s'); ?></option>
                            <option value="d.m.Y H:i" <?php selected( get_option('itwp_date_format'), 'd.m.Y H:i' ); ?>><?php echo date('d.m.Y H:i'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
			<?php submit_button(); ?>
        </form>

        <!-- Form to manually fetch Instagram posts -->
        <form method="post">
            <input type="hidden" name="itwp_manual_fetch" value="1" />
			<?php submit_button( __( 'Fetch Instagram Posts Now', 'instagram-to-wordpress-posts' ), 'primary', 'fetch_now' ); ?>
        </form>
    </div>
	<?php
}

// Handle manual fetch button
function itwp_handle_manual_fetch() {
	// Check if the manual fetch form was submitted
	if ( isset( $_POST['itwp_manual_fetch'] ) && $_POST['itwp_manual_fetch'] == '1' ) {
		itwp_fetch_and_store_instagram_posts();
		add_action( 'admin_notices', 'itwp_manual_fetch_notice' );
	}
}
add_action( 'admin_post_itwp_manual_fetch', 'itwp_handle_manual_fetch' );

// Admin notice after manual fetch
function itwp_manual_fetch_notice() {
	?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e( 'Instagram posts fetched successfully.', 'instagram-to-wordpress-posts' ); ?></p>
    </div>
	<?php
}
