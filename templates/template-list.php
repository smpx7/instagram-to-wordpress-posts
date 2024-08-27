<?php
// List Template for displaying Instagram posts

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
<ul class="instagram-list">
<?php foreach ( $posts as $post ) : ?>
	<li class="instagram-list-item">
	<h2><?php echo esc_html( get_the_title( $post->ID ) ); ?></h2>
	<?php echo apply_filters( 'the_content', $post->post_content ); ?>
	</li>
<?php endforeach; ?>
</ul>
