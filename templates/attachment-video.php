<?php $thumbnail_src = wp_get_attachment_image_src( get_post_meta( $attachment->ID, '_thumbnail_id', true ), 'medium' ); ?>
<a href="<?php echo wp_get_attachment_url( $attachment->ID ); ?>"
   class="chaos-modal-link">
    <img src="<?php echo $thumbnail_src[0]; ?>" class="img-responsive" loading="eager" alt=""/>
    <div class="itwp_image_video"></div>
	<?php if ( count( $attachments ) > 1 ): ?><div class="itwp_images"></div><?php endif; ?>
</a>