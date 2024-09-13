<a href="<?php echo wp_get_attachment_url( $attachment->ID ); ?>"
   class="chaos-modal-link" data-chaos-modal-caption="<?php echo esc_html( get_the_content() ); ?>">
	<?php echo wp_get_attachment_image( $attachment->ID, "medium", "", array(
		"class"   => "img-responsive",
		"loading" => "eager"
	) ); ?>
	<?php if ( count( $attachments ) > 1 ): ?><div class="itwp_images"></div><?php endif; ?>
</a>