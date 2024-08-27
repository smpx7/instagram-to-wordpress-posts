<?php
/**
 * Plugin Name: Instagram to WordPress Posts
 * Description: A plugin to fetch Instagram posts using the Instagram Basic Display API, store them as a custom post type, and provide a settings page.
 * Version: 1.4
 * Author: Sven Grün
 * GitHub Plugin URI: https://github.com/smpx7/instagram-to-wordpress-posts
 * GitHub Branch: main
 */

// Prevent direct access to the file
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

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
		// Display error message if access token is missing
		add_action('admin_notices', function() {
			echo '<div class="notice notice-error is-dismissible"><p>Instagram Access Token is missing. Please configure it in the settings page.</p></div>';
		});
		return;
	}

	$api_url = 'https://graph.instagram.com/me/media?fields=id,caption,media_type,media_url,thumbnail_url,permalink&access_token=' . esc_attr( $access_token );
	$response = wp_remote_get( $api_url );

	if ( is_wp_error( $response ) ) {
		add_action('admin_notices', function() {
			echo '<div class="notice notice-error is-dismissible"><p>Failed to retrieve Instagram posts.</p></div>';
		});
		return;
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( isset( $data['error'] ) ) {
		add_action('admin_notices', function() use ($data) {
			echo '<div class="notice notice-error is-dismissible"><p>Instagram API Error: ' . esc_html($data['error']['message']) . '</p></div>';
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

            <!-- Button to manually fetch Instagram posts -->
            <form method="post">
                <input type="hidden" name="itwp_manual_fetch" value="1" />
				<?php submit_button( 'Fetch Instagram Posts Now', 'primary', 'fetch_now' ); ?>
            </form>
        </form>
    </div>
	<?php
}

// Handle manual fetch button
function itwp_handle_manual_fetch() {
	if ( isset( $_POST['itwp_manual_fetch'] ) && $_POST['itwp_manual_fetch'] == '1' ) {
		itwp_fetch_and_store_instagram_posts();
		add_action( 'admin_notices', 'itwp_manual_fetch_notice' );
	}
}
add_action( 'admin_init', 'itwp_handle_manual_fetch' );

// Admin notice after manual fetch
function itwp_manual_fetch_notice() {
	?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e( 'Instagram posts fetched successfully.', 'itwp' ); ?></p>
    </div>
	<?php
}
