<!-- Fetch and display attachments -->
<?php
$attachments = get_posts( array(
	'post_type'      => 'attachment',
	'posts_per_page' => - 1,
	'post_status'    => 'inherit',
	'post_parent'    => get_the_ID(),
) );

if ( $attachments ) :
	?>
    <div class="post-attachments" data-chaos-modal-gallery="gallery_<?php echo get_the_ID(); ?>">
		<?php foreach ( $attachments as $i => $attachment ) : ?>
            <div class="attachment-item item" data-chaxos-moxdal-inxdex="<?php echo $i+1; ?>" <?php if ($i > 0) :?>style="display: none;"<?php endif;?>>
				<?php if ( $attachment->post_mime_type == 'video/mp4' ) : ?>
                    <video controls class="img-responsive">
                        <source src="<?php echo wp_get_attachment_url( $attachment->ID ); ?>" type="video/mp4">
                    </video>
				<?php elseif ( $attachment->post_mime_type == 'image/jpeg' ) : ?>
                    <a href="<?php echo wp_get_attachment_url( $attachment->ID ); ?>"
                       class="chaos-modal-link" data-chaos-modal-caption="<?php echo esc_html(get_the_content()); ?>" >
						<?php echo wp_get_attachment_image( $attachment->ID, "full", "", array( "class"   => "img-responsive",
						                                                                        "loading" => "eager"
						) ); ?>
                    </a>
				<?php endif; ?>
            </div>
		<?php endforeach; ?>
        <?php if (count($attachments) > 1):?><div class="multiple_images"></div><?php endif;?>
    </div>
<?php endif; ?>