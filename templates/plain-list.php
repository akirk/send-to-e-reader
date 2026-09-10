<?php
/**
 * This template contains the output of a simple list of posts.
 *
 * @package Send_To_E_Reader
 */

defined( 'ABSPATH' ) || exit;

?>
<html><head><title><?php echo esc_html( $args['title'] ); ?></title>
<script src="<?php echo esc_url( plugins_url( 'plain-list.js', dirname( __DIR__ ) . '/send-to-e-reader.php' ) ); ?>" defer></script>
</head>
<body>
	<form>
		<span style="float: right"><a href="#" data-send-to-e-reader-action="reverse-list">Reverse</a> | <a href="#" data-send-to-e-reader-action="select-all">Select all</a> | <a href="#" data-send-to-e-reader-action="select-none">Select none</a></span>
		<button>Download</button>

		<ul>
		<?php foreach ( $args['posts'] as $post ) : ?>
				<li><input type="checkbox" name="<?php echo esc_attr( $args['inputname'] ); ?>[]" value="<?php echo esc_attr( $post->ID ); ?>" <?php checked( isset( $args['unsent'][ $post->ID ] ) ); ?> />
				<?php echo esc_html( get_the_title( $post ) ); ?><br/><small><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $post->post_content ) ) ); ?></small>
				<small><br><a href="<?php echo esc_html( get_the_permalink( $post ) ); ?>"><?php echo esc_html( get_the_permalink( $post ) ); ?></a> | <a href="#" data-send-to-e-reader-action="move-up">Move up</a> | <a href="#" data-send-to-e-reader-action="move-down">Move down</a><br><br></small>
				</li>
		<?php endforeach; ?>
		</ul>
		<button>Download</button>
	</form>
	</body>
</html>
