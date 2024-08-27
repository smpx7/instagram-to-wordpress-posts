<?php
// Grid Template for displaying Instagram posts

$args = array(
	'post_type'      => 'instagram_post',
	'posts_per_page' => $count,
	'orderby'        => 'date',
	'order'          => 'DESC',
);

$posts = get_posts( $args );

if ( empty( $posts ) ) {
	echo 'No Instagram posts found.';

	return;
}

?>
<div class="instagram-grid">
<?php foreach ( $posts as $post ) : ?>
	<div class="instagram-grid-item">
	<h2><?php echo esc_html( get_the_title( $post->ID ) ); ?></h2>
	<?php echo apply_filters( 'the_content', $post->post_content ); ?>
	</div>
<?php endforeach; ?>
</div>
