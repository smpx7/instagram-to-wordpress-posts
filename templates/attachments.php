<!-- Fetch and display attachments -->
<?php

/*
 * get attachments for the current post
 */

$attachments = get_posts( array(
	'post_type'      => 'attachment',
	'posts_per_page' => - 1,
	'post_parent'    => get_the_ID(),
	'exclude'        => get_post_thumbnail_id(),
	'orderby'        => 'name',
	'order'          => 'ASC',
) );

if ( $attachments ) :
	?>
    <div class="post-attachments" data-chaos-modal-gallery="gallery_<?php echo get_the_ID(); ?>">
		<?php foreach ( $attachments as $i => $attachment ) : ?>
            <div class="attachment-item item" <?php if ( $i > 0 ) : ?>style="display: none;"<?php endif; ?>>
				<?php
				$mime_type_prefix = explode( '/', $attachment->post_mime_type )[0];
				include( plugin_dir_path( __FILE__ ) . 'attachment-' . $mime_type_prefix . '.php' ); ?>
            </div>
		<?php endforeach; ?>

    </div>
<?php endif; ?>