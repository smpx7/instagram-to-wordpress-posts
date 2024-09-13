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
		<?php foreach ( $attachments as $i => $attachment ) :
			$thumbnail_id = get_post_meta( $attachment->ID, '_thumbnail_id', true );

			?>
            <div class="attachment-item item" data-chaxos-moxdal-inxdex="<?php echo $i + 1; ?>"
			     <?php if ( $i > 0 ) : ?>style="display: none;"<?php endif;
			?>>
				<?php if ( $attachment->post_mime_type == 'video/mp4' ) : ?>

					<?php $thumbnail_src = wp_get_attachment_image_src( $thumbnail_id, 'large' ); ?>
                    <a href="<?php echo wp_get_attachment_url( $attachment->ID ); ?>"
                       class="chaos-modal-link">
                        <img src="<?php echo $thumbnail_src[0]; ?>" class="img-responsive" loading="eager" alt=""/>
	                    <div class="itwp_image_video"></div>
                    </a>

				<?php elseif ( $attachment->post_mime_type == 'image/jpeg' ) : ?>
                    <a href="<?php echo wp_get_attachment_url( $attachment->ID ); ?>"
                       class="chaos-modal-link" data-chaos-modal-caption="<?php echo esc_html( get_the_content() ); ?>">
						<?php echo wp_get_attachment_image( $attachment->ID, "large", "", array(
							"class"   => "img-responsive",
							"loading" => "eager"
						) ); ?>
	                    <?php if ( count( $attachments ) > 1 ): ?><div class="itwp_images"></div><?php endif; ?>
                    </a>
				<?php endif; ?>
            </div>
		<?php endforeach; ?>

    </div>
<?php endif; ?>