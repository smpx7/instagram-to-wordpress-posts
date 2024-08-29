<?php

class ITWP_Settings {

	public static function register() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_options_page' ) );
	}

	public static function register_settings() {
		add_option( 'itwp_access_token', '' );
		add_option( 'itwp_client_id', '' ); // Option for Client ID
		add_option( 'itwp_client_secret', '' ); // Option for Client Secret
		add_option( 'itwp_fetch_limit', 10 ); // Default limit
		add_option( 'itwp_date_format', 'Y-m-d H:i:s' ); // Default date format

		register_setting( 'itwp_options_group', 'itwp_access_token', 'sanitize_text_field' );
		register_setting( 'itwp_options_group', 'itwp_client_id', 'sanitize_text_field' ); // Register new setting for Client ID
		register_setting( 'itwp_options_group', 'itwp_client_secret', 'sanitize_text_field' ); // Register new setting for Client Secret
		register_setting( 'itwp_options_group', 'itwp_fetch_limit', 'intval' );
		register_setting( 'itwp_options_group', 'itwp_date_format', 'sanitize_text_field' );
	}

	public static function register_options_page() {
		add_options_page( __( 'Instagram API Settings', 'instagram-to-wordpress-posts' ), __( 'Instagram API', 'instagram-to-wordpress-posts' ), 'manage_options', 'itwp', array( __CLASS__, 'options_page' ) );
	}

	public static function options_page() {
		// Get stored Client ID and Secret
		$client_id = get_option('itwp_client_id');
		$client_secret = get_option('itwp_client_secret');
		$redirect_uri = urlencode(admin_url('admin.php?page=itwp')); // This URL must be registered in your Instagram App settings

		// Construct the Instagram authorization URL
		$auth_url = "https://api.instagram.com/oauth/authorize?client_id={$client_id}&redirect_uri={$redirect_uri}&scope=user_profile,user_media&response_type=code";
		?>
        <div>
            <h2><?php _e( 'Instagram API Settings', 'instagram-to-wordpress-posts' ); ?></h2>
            <form method="post" action="options.php">
				<?php settings_fields( 'itwp_options_group' ); ?>
                <table>
                    <tr valign="top">
                        <th scope="row"><label for="itwp_client_id"><?php _e( 'Client ID', 'instagram-to-wordpress-posts' ); ?></label></th>
                        <td><input type="text" id="itwp_client_id" name="itwp_client_id" value="<?php echo esc_attr( $client_id ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="itwp_client_secret"><?php _e( 'Client Secret', 'instagram-to-wordpress-posts' ); ?></label></th>
                        <td><input type="text" id="itwp_client_secret" name="itwp_client_secret" value="<?php echo esc_attr( $client_secret ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="itwp_access_token"><?php _e( 'Access Token', 'instagram-to-wordpress-posts' ); ?></label></th>
                        <td><input type="text" id="itwp_access_token" name="itwp_access_token" value="<?php echo esc_attr( get_option('itwp_access_token') ); ?>" readonly /></td>
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

            <!-- Authorization Button -->
            <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary"><?php _e('Authorize with Instagram', 'instagram-to-wordpress-posts'); ?></a>

            <!-- Form to manually fetch Instagram posts -->
            <button id="itwp-fetch-btn" class="button button-primary"><?php _e( 'Fetch Instagram Posts Now', 'instagram-to-wordpress-posts' ); ?></button>
            <progress id="itwp-fetch-progress" value="0" max="100" style="width:100%; display:none;"></progress>
        </div>
		<?php
	}
}