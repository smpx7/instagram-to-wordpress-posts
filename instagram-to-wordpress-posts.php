<?php
/**
 * Plugin Name: Instagram to WordPress Posts
 * Description: A plugin to fetch Instagram posts using the Instagram Basic Display API, store them as a custom post type, and provide a settings page.
 * Version: 1.2
 * Author: Sven Grün
 * GitHub Plugin URI: https://github.com/yourusername/instagram-to-wordpress-posts
 * GitHub Branch: main
 */

// Prevent direct access to the file
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Include the Composer autoload file
require_once __DIR__ . '/vendor/autoload.php';

// Git Updater requires the plugin headers to be correctly formatted.
// Initialize Git Updater if necessary (optional; Git Updater generally works automatically with the correct headers).

// Custom error handler
function itwp_custom_error_handler($errno, $errstr, $errfile, $errline) {
	// Only handle the error if it is not suppressed by @
	if (!(error_reporting() & $errno)) {
		return;
	}

	// Display the error message
	echo "<div class='notice notice-error'><p><strong>Error:</strong> [$errno] $errstr in $errfile on line $errline</p></div>";

	// Prevent WordPress from sending an email notification
	if (defined('DOING_AJAX') && DOING_AJAX) {
		wp_die(); // Stop execution for AJAX requests
	} else {
		die(); // Stop execution for non-AJAX requests
	}
}

// Register the custom error handler
set_error_handler('itwp_custom_error_handler');

// Register custom post type for Instagram posts
function itwp_register_instagram_post_type() {
	$labels = array(
		'name'               => 'Instagram Posts',
		'singular_name'      => 'Instagram Post',
		'menu_name'          => 'Instagram Posts',
		'name_admin_bar'     => 'Instagram Post',
		'add_new'            => 'Add New',
		'add_new_item'       => 'Add New Instagram Post',
		'new_item'           => 'New Instagram Post',
		'edit_item'          => 'Edit Instagram Post',
		'view_item'          => 'View Instagram Post',
		'all_items'          => 'All Instagram Posts',
		'search_items'       => 'Search Instagram Posts',
		'not_found'          => 'No Instagram posts found.',
		'not_found_in_trash' => 'No Instagram posts found in Trash.',
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
		'hierarchical'       => false,
		'menu_position'      => null,
		'supports'           => array( 'title', 'editor', 'thumbnail' ),
	);

	register_post_type( 'instagram_post', $args );
}
add_action( 'init', 'itwp_register_instagram_post_type' );

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

	if ( empty( $access_token ) ) {
		trigger_error('Access token is missing.', E_USER_WARNING);
		return;
	}

	$api_url = 'https://graph.instagram.com/me/media?fields=id,caption,media_type,media_url,thumbnail_url,permalink&access_token=' . esc_attr( $access_token );
	$response = wp_remote_get( $api_url );

	if ( is_wp_error( $response ) ) {
		trigger_error('Failed to retrieve Instagram posts.', E_USER_WARNING);
		return;
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( isset( $data['error'] ) ) {
		trigger_error('Instagram API Error: ' . $data['error']['message'], E_USER_WARNING);
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

			// Prepare post content
			$post_content = '';
			if ( $post['media_type'] == 'IMAGE' || $post['media_type'] == 'CAROUSEL_ALBUM' ) {
				$post_content = '<img src="' . esc_url( $post['media_url'] ) . '" alt="' . esc_attr( $post['caption'] ) . '" />';
			} elseif ( $post['media_type'] == 'VIDEO' ) {
				$post_content = '<video controls src="' . esc_url( $post['media_url'] ) . '"></video>';
			}

			$post_content .= '<p>' . esc_html( $post['caption'] ) . '</p>';
			$post_content .= '<a href="' . esc_url( $post['permalink'] ) . '" target="_blank">View on Instagram</a>';

			// Insert post into database
			$new_post = array(
				'post_title'   => 'Instagram Post ' . $post_id,
				'post_content' => $post_content,
				'post_status'  => 'publish',
				'post_type'    => 'instagram_post',
			);

			$new_post_id = wp_insert_post( $new_post );

			// Save Instagram post ID as post meta
			if ( $new_post_id ) {
				add_post_meta( $new_post_id, 'instagram_post_id', $post_id );
			}
		}
	}
}
add_action( 'itwp_daily_instagram_fetch', 'itwp_fetch_and_store_instagram_posts' );

// Shortcode to display Instagram posts from the database with a specified template
function itwp_display_instagram_posts( $atts ) {
	$atts = shortcode_atts( array(
		'count' => 5, // Number of posts to display
		'template' => 'grid', // Default template
	), $atts );

	// Determine template file
	$template_file = plugin_dir_path( __FILE__ ) . 'templates/template-' . sanitize_text_field( $atts['template'] ) . '.php';

	if ( ! file_exists( $template_file ) ) {
		return 'Template not found.';
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
	register_setting( 'itwp_options_group', 'itwp_access_token', 'itwp_callback' );
}

add_action( 'admin_init', 'itwp_register_settings' );

function itwp_register_options_page() {
	add_options_page( 'Instagram API Settings', 'Instagram API', 'manage_options', 'itwp', 'itwp_options_page' );
}

add_action( 'admin_menu', 'itwp_register_options_page' );

function itwp_options_page() {
	?>
    <div>
        <h2>Instagram API Settings</h2>
        <form method="post" action="options.php">
			<?php settings_fields( 'itwp_options_group' ); ?>
            <table>
                <tr valign="top">
                    <th scope="row"><label for="itwp_access_token">Access Token</label></th>
                    <td><input type="text" id="itwp_access_token" name="itwp_access_token" value="<?php echo get_option('itwp_access_token'); ?>" /></td>
                </tr>
            </table>
			<?php submit_button(); ?>
        </form>
    </div>
	<?php
}
