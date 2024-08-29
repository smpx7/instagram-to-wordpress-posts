<?php
class ITWP_Post_Type {
	public static function register() {
		add_action('init', array(__CLASS__, 'register_post_type'));
		add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
	}

	public static function register_post_type() {
		register_post_type('instagram_post', array(
			'labels' => array(
				'name' => __('Instagram Posts', 'instagram-to-wordpress-posts'),
				'singular_name' => __('Instagram Post', 'instagram-to-wordpress-posts')
			),
			'public' => true,
			'has_archive' => true,
			'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
		));
	}

	public static function add_meta_boxes() {
		add_meta_box(
			'itwp_fetch_datetime_meta_box', // Meta box ID
			__('Fetch Date/Time', 'instagram-to-wordpress-posts'), // Meta box title
			array(__CLASS__, 'display_fetch_datetime_meta_box'), // Callback function
			'instagram_post', // Post type
			'side', // Context (side, normal, etc.)
			'default' // Priority
		);
	}

	public static function display_fetch_datetime_meta_box($post) {
		// Retrieve the fetch date/time from the post meta
		$fetch_datetime = get_post_meta($post->ID, '_itwp_fetch_datetime', true);

		echo '<label for="itwp_fetch_datetime">';
		_e('Date/Time when the post was fetched:', 'instagram-to-wordpress-posts');
		echo '</label> ';
		echo '<input type="text" id="itwp_fetch_datetime" name="itwp_fetch_datetime" value="' . esc_attr($fetch_datetime) . '" readonly style="width:100%;" />';
	}
}
