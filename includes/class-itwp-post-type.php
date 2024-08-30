<?php

class ITWP_Post_Type {
	public static function register() {
		add_action('init', array(__CLASS__, 'register_post_type'));
		add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
		add_action('restrict_manage_posts', array(__CLASS__, 'add_fetch_button')); // Hook into restrict_manage_posts
	}

	public static function register_post_type() {
		register_post_type('instagram_post', array(
			'labels' => array(
				'name' => __('Instagram Posts', 'instagram-to-wordpress-posts'),
				'singular_name' => __('Instagram Post', 'instagram-to-wordpress-posts'),
				'add_new_item' => '', // Remove "Add New" link
			),
			'public' => true,
			'has_archive' => true,
			'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
			'show_in_menu' => true,
			'show_in_admin_bar' => false, // Removes "New Post" from the admin toolbar
			'capabilities' => array(
				'create_posts' => 'do_not_allow', // Removes "Add New" button
			),
			'map_meta_cap' => true, // Maps the meta capabilities, necessary when modifying capabilities
			'menu_icon' => 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0OCA0OCIgd2lkdGg9IjI0cHgiIGhlaWdodD0iMjRweCI+PGxpbmVhckdyYWRpZW50IGlkPSJnMSIgeDE9IjI0IiB4Mj0iMjQiIHkxPSI1LjQ0NiIgeTI9IjQwLjg1NCIgZ3JhZGllbnRVbml0cz0idXNlclNwYWNlT25Vc2UiPjxzdG9wIG9mZnNldD0iMCIgc3RvcC1jb2xvcj0iI2ZkNSIgLz48c3RvcCBvZmZzZXQ9Ii41IiBzdG9wLWNvbG9yPSIjZmY1NDNmIiAvPjxzdG9wIG9mZnNldD0iMSIgc3RvcC1jb2xvcj0iI2M4MzdhYiIgLz48L2xpbmVhckdyYWRpZW50PjxwYXRoIGZpbGw9InVybCgjZzEpIiBkPSJNMzQuNSw4aC0yMUM5LjU3LDgsOCw5LjU3LDgsMTEuNXYyMUM4LDM0LjQzLDkuNTcsMzYsMTEuNSwzNmgyMWMxLjkzLDAsMy41LTEuNTcsMy41LTMuNXYtMjFDMzgsOS41NywzNi40Myw4LDM0LjUsOHogTTI0LDMyYy00LjQxLDAtOC0zLjU5LTgtOHMzLjU5LTgsOC04czgsMy41OSw4LDhTMjguNDEsMzIsMjQsMzJ6IE0zNCwxNC41Yy0wLjgzLDAtMS41LTAuNjctMS41LTEuNVMzMy4xNywxMS41LDM0LDExLjVzMS41LDAuNjcsMS41LDEuNVMzNC44MywxNC41LDM0LDE0LjV6Ii8+PGNpcmNsZSBjeD0iMjQiIGN5PSIyNCIgcj0iNSIgZmlsbD0iI2ZmZiIvPjwvc3ZnPg==',
		));
	}

	public static function add_meta_boxes() {
		add_meta_box(
			'itwp_fetch_datetime_meta_box', // Meta box ID
			__('Instagram Post Details', 'instagram-to-wordpress-posts'), // Meta box title
			array(__CLASS__, 'display_fetch_datetime_meta_box'), // Callback function
			'instagram_post', // Post type
			'side', // Context (side, normal, etc.)
			'default' // Priority
		);
	}

	public static function display_fetch_datetime_meta_box($post) {
		// Retrieve the fetch date/time from the post meta
		$fetch_datetime = get_post_meta($post->ID, '_itwp_fetch_datetime', true);
		$instagram_permalink = get_post_meta($post->ID, 'instagram_post_permalink', true);

		echo '<label for="itwp_fetch_datetime">';
		_e('Date/Time when the post was fetched:', 'instagram-to-wordpress-posts');
		echo '</label> ';
		echo '<input type="text" id="itwp_fetch_datetime" name="itwp_fetch_datetime" value="' . esc_attr($fetch_datetime) . '" readonly style="width:100%;" />';

		// Display Instagram post permalink
		if (!empty($instagram_permalink)) {
			echo '<br><br><label for="instagram_post_permalink">';
			_e('Instagram Post Permalink:', 'instagram-to-wordpress-posts');
			echo '</label> ';
			echo '<input type="text" id="instagram_post_permalink" name="instagram_post_permalink" value="' . esc_url($instagram_permalink) . '" readonly style="width:100%;" />';
		}
	}

	public static function add_fetch_button($post_type) {
		if ($post_type == 'instagram_post') {
			echo '<button id="itwp-fetch-btn" class="button button-primary" style="margin: 5px 0;">' . __('Fetch Instagram Posts Now', 'instagram-to-wordpress-posts') . '</button>';
			echo '<progress id="itwp-fetch-progress" value="0" max="100" style="width:200px; display:none; margin-left: 10px;"></progress>';
		}
	}
}
