<div class="instagram-posts-wrapper container-fluid g-0">
    <div class="row row-cols-1 row-cols-lg-3 g-2 g-lg-3">
		<?php if ( $instagram_posts->have_posts() ) : ?>
			<?php while ( $instagram_posts->have_posts() ) : $instagram_posts->the_post(); ?>
                <div class="instagram-post col">
                    <div class="post-content p-4">
						<?php if ( $atts['position'] === 'below' ) : ?>
							<?php include( plugin_dir_path( __FILE__ ) . 'attachments.php' ); ?>
                            <p><?php echo nl2br( get_the_content() ); ?></p>
						<?php else: ?>
                            <p><?php echo nl2br( get_the_content() ); ?></p>
							<?php include( plugin_dir_path( __FILE__ ) . 'attachments.php' ); ?>
						<?php endif; ?>
                    </div>
                </div>
			<?php endwhile; ?>
		<?php else : ?>
            <p><?php _e( 'No Instagram posts found.', 'instagram-to-wordpress-posts' ); ?></p>
		<?php endif; ?>
    </div>
</div>
