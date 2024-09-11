<?php
function itwp_widget_styles() {
	wp_enqueue_style( 'itwp-widget-style', plugins_url( '/../assets/css/style.css', __FILE__ ) );
	wp_enqueue_script( 'chaos-modal-script', plugins_url( '/../assets/vendor/chaos-modal/jquery.modal.js', __FILE__ ), array( 'jquery' ), '1.0.0', true );
}

add_action( 'wp_enqueue_scripts', 'itwp_widget_styles' );

class Itwp_Widget extends WP_Widget {

	const LAYOUT_GRID = 'grid';
	const LAYOUT_LIST = 'list';
	const LAYOUT_MASONRY = 'masonry';
	const POSITION_ABOVE = 'above';
	const POSITION_BELOW = 'below';

	// Constructor
	public function __construct() {
		parent::__construct(
			'itwp_widget', // Base ID
			'Instagram to WordPress Widget',   // Name
			array( 'description' => __( 'A widget that displays Instagram posts.', 'instagram-to-wordpress-posts' ) )
		);
	}

	// Check if Elementor is installed and active
	public static function is_elementor_installed() {
		return defined( 'ELEMENTOR_VERSION' ) && is_plugin_active( 'elementor/elementor.php' );
	}

	// Check if Beaver Builder is installed and active
	public static function is_beaver_builder_installed() {
		return class_exists( 'FLBuilderLoader' ) && defined( 'FL_BUILDER_VERSION' ) && is_plugin_active( 'bb-plugin/fl-builder.php' );
	}

	// The widget() function is responsible for rendering the widget on the front end
	public function widget( $args, $instance ) {
		echo $args['before_widget'];

		// Use the selected option
		$limit              = ! empty( $instance['limit'] ) ? $instance['limit'] : 3;
		$position           = ! empty( $instance['position'] ) ? $instance['position'] : self::POSITION_ABOVE;
		$layout             = ! empty( $instance['layout'] ) ? $instance['layout'] : self::LAYOUT_GRID;
		$use_elementor      = ! empty( $instance['use_elementor'] ) ? $instance['use_elementor'] : self::is_elementor_installed();
		$use_beaver_builder = ! empty( $instance['use_beaver_builder'] ) ? $instance['use_beaver_builder'] : self::is_beaver_builder_installed();

		echo do_shortcode( sprintf( '[itwp_instagram_posts limit="%d" layout="%s" position="%s" use_elementor="%b" use_beaver_builder="%b"]', $limit, $layout, $position, $use_elementor, $use_beaver_builder ) );

		echo $args['after_widget'];
	}

	// The form() function outputs the widget settings form in the admin
	public function form( $instance ) {
		//$title    = ! empty( $instance['title'] ) ? $instance['title'] : __( 'New title', 'instagram-to-wordpress-posts' );

		$limit    = ! empty( $instance['limit'] ) ? $instance['limit'] : 3;
		$position = ! empty( $instance['position'] ) ? $instance['position'] : self::POSITION_ABOVE;
		$layout   = ! empty( $instance['layout'] ) ? $instance['layout'] : self::LAYOUT_GRID;
		?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"><?php _e( 'Number of posts to display:', 'instagram-to-wordpress-posts' ); ?></label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>" type="number"
                   value="<?php echo esc_attr( $limit ); ?>">
        </p>
        <p>
			<?php // layout ?>
            <label for="<?php echo esc_attr( $this->get_field_id( 'layout' ) ); ?>"><?php _e( 'Layout:', 'instagram-to-wordpress-posts' ); ?></label>
            <select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'layout' ) ); ?>"
                    name="<?php echo esc_attr( $this->get_field_name( 'layout' ) ); ?>">
                <option value="<?php echo self::LAYOUT_GRID; ?>" <?php echo ( $layout == self::LAYOUT_GRID ) ? 'selected' : ''; ?>><?php _e( 'Grid', 'instagram-to-wordpress-posts' ); ?></option>
                <option value="<?php echo self::LAYOUT_LIST; ?>" <?php echo ( $layout == self::LAYOUT_LIST ) ? 'selected' : ''; ?>><?php _e( 'List', 'instagram-to-wordpress-posts' ); ?></option>
                <option value="<?php echo self::LAYOUT_MASONRY; ?>" <?php echo ( $layout == self::LAYOUT_MASONRY ) ? 'selected' : ''; ?>><?php _e( 'Masonry', 'instagram-to-wordpress-posts' ); ?></option>
            </select>
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'position' ) ); ?>"><?php _e( 'Text-Position:', 'instagram-to-wordpress-posts' ); ?></label>
            <select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'position' ) ); ?>"
                    name="<?php echo esc_attr( $this->get_field_name( 'position' ) ); ?>">
                <option value="<?php echo self::POSITION_ABOVE; ?>" <?php echo ( $position == self::POSITION_ABOVE ) ? 'selected' : ''; ?>><?php _e( 'Above', 'instagram-to-wordpress-posts' ); ?></option>
                <option value="<?php echo self::POSITION_BELOW; ?>" <?php echo ( $position == self::POSITION_BELOW ) ? 'selected' : ''; ?>><?php _e( 'Below', 'instagram-to-wordpress-posts' ); ?></option>
            </select>
        </p>
		<?php
	}

	// The update() function processes widget options to be saved
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		//$instance['title']    = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['limit']    = ( ! empty( $new_instance['limit'] ) ) ? strip_tags( $new_instance['limit'] ) : 3;
		$instance['position'] = ( ! empty( $new_instance['position'] ) ) ? strip_tags( $new_instance['position'] ) : self::POSITION_ABOVE;
		$instance['layout']   = ( ! empty( $new_instance['layout'] ) ) ? strip_tags( $new_instance['layout'] ) : self::LAYOUT_GRID;

		return $instance;
	}

}

// Register the widget
function register_itwp_widget() {
	register_widget( 'Itwp_Widget' );
}

add_action( 'widgets_init', 'register_itwp_widget' );