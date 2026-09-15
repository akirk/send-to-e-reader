<?php
/**
 * This template contains the output of a simple list of posts.
 *
 * @package Send_To_E_Reader
 */

defined( 'ABSPATH' ) || exit;

$date_format = get_option( 'date_format' );
if ( ! $date_format ) {
	$date_format = 'F j, Y';
}

$get_post_display_date = static function ( $post ) use ( $date_format ) {
	$published_time = get_post_meta( $post->ID, 'published_time', true );
	if ( $published_time ) {
		$timestamp = is_numeric( $published_time ) ? (int) $published_time : strtotime( (string) $published_time );
		if ( $timestamp ) {
			return date_i18n( $date_format, $timestamp );
		}
	}

	return get_the_time( $date_format, $post );
};

$selected_count = count( $args['unsent'] );
$total_count    = count( $args['posts'] );

wp_enqueue_script(
	'send-to-e-reader-plain-list',
	plugins_url( 'plain-list.js', dirname( __DIR__ ) . '/send-to-e-reader.php' ),
	array(),
	filemtime( dirname( __DIR__ ) . '/plain-list.js' ),
	array( 'strategy' => 'defer' )
);

?>
<!doctype html>
<html>
<head>
<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $args['title'] ); ?></title>
<style>
	body {
		background: #f6f4ef;
		color: #1f2328;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
		line-height: 1.5;
		margin: 0;
	}

	main {
		margin: 0 auto;
		max-width: 780px;
		padding: 24px;
	}

	header {
		align-items: center;
		border-bottom: 1px solid #d8d2c6;
		display: flex;
		gap: 16px;
		justify-content: space-between;
		margin-bottom: 18px;
		padding-bottom: 16px;
	}

	h1 {
		font-size: 1.45rem;
		line-height: 1.2;
		margin: 0;
	}

	.header-title {
		min-width: 0;
	}

	button,
	.list-actions a,
	.item-actions a {
		color: #0f4c81;
	}

	button {
		background: #1f2328;
		border: 0;
		border-radius: 6px;
		color: #fff;
		cursor: pointer;
		font: inherit;
		font-weight: 600;
		padding: 8px 14px;
	}

	.header-controls,
	.list-actions {
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
		justify-content: flex-end;
	}

	.header-controls {
		align-items: center;
		flex: 0 1 360px;
	}

	.header-download {
		flex: 0 0 auto;
	}

	.post-list {
		display: grid;
		gap: 12px;
		list-style: none;
		margin: 0 0 18px;
		padding: 0;
	}

	.post-item {
		align-items: flex-start;
		background: #fff;
		border: 1px solid #ddd7cc;
		border-radius: 8px;
		display: grid;
		gap: 10px;
		grid-template-columns: auto minmax(0, 1fr);
		padding: 14px;
	}

	.post-title {
		font-size: 1.05rem;
		font-weight: 700;
		margin: 0 0 4px;
	}

	.post-meta,
	.selection-count,
	.post-summary,
	.item-actions {
		color: #5d646d;
		font-size: .92rem;
		margin: 0;
	}

	.post-summary {
		margin-top: 8px;
	}

	.item-actions {
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
		margin-top: 8px;
	}

	.post-url {
		overflow-wrap: anywhere;
	}

	.form-footer {
		text-align: right;
	}

	@media (max-width: 560px) {
		main {
			padding: 16px;
		}

		header {
			align-items: flex-start;
			flex-wrap: wrap;
		}

		.header-title {
			flex: 1 1 160px;
		}

		.header-controls {
			display: contents;
		}

		.header-download {
			order: 1;
		}

		.list-actions {
			flex: 1 0 100%;
			justify-content: flex-start;
			margin-top: 12px;
			order: 2;
		}
	}
</style>
<?php wp_print_scripts( 'send-to-e-reader-plain-list' ); ?>
</head>
<body>
	<main>
	<form>
		<header>
			<div class="header-title">
				<h1><?php echo esc_html( $args['title'] ); ?></h1>
				<p class="selection-count" data-send-to-e-reader-selection-count
					<?php
					printf(
						'data-selected-template="%s"',
						/* translators: 1: total article count, 2: selected article count. */
						esc_attr__( '%1$d articles (%2$d selected)', 'send-to-e-reader' )
					);
					?>
				>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: total article count, 2: selected article count. */
							__( '%1$d articles (%2$d selected)', 'send-to-e-reader' ),
							$total_count,
							$selected_count
						)
					);
					?>
				</p>
			</div>
			<div class="header-controls">
				<nav class="list-actions" aria-label="<?php esc_attr_e( 'List actions', 'send-to-e-reader' ); ?>">
					<a href="#" data-send-to-e-reader-action="reverse-list"><?php esc_html_e( 'Reverse', 'send-to-e-reader' ); ?></a>
					<a href="#" data-send-to-e-reader-action="select-all"><?php esc_html_e( 'Select all', 'send-to-e-reader' ); ?></a>
					<a href="#" data-send-to-e-reader-action="select-none"><?php esc_html_e( 'Select none', 'send-to-e-reader' ); ?></a>
				</nav>
				<button class="header-download" type="submit"><?php esc_html_e( 'Download', 'send-to-e-reader' ); ?></button>
			</div>
		</header>

		<ul class="post-list">
		<?php foreach ( $args['posts'] as $post ) : ?>
				<li class="post-item">
				<input type="checkbox" name="<?php echo esc_attr( $args['inputname'] ); ?>[]" value="<?php echo esc_attr( $post->ID ); ?>" <?php checked( isset( $args['unsent'][ $post->ID ] ) ); ?> aria-label="<?php echo esc_attr( get_the_title( $post ) ); ?>" />
				<div>
					<p class="post-title"><?php echo esc_html( get_the_title( $post ) ); ?></p>
					<p class="post-meta"><?php echo esc_html( $get_post_display_date( $post ) ); ?></p>
					<p class="post-summary"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $post->post_content ) ) ); ?></p>
					<div class="item-actions">
						<a class="post-url" href="<?php echo esc_url( get_the_permalink( $post ) ); ?>"><?php echo esc_html( get_the_permalink( $post ) ); ?></a>
						<a href="#" data-send-to-e-reader-action="move-up"><?php esc_html_e( 'Move up', 'send-to-e-reader' ); ?></a>
						<a href="#" data-send-to-e-reader-action="move-down"><?php esc_html_e( 'Move down', 'send-to-e-reader' ); ?></a>
					</div>
				</div>
				</li>
		<?php endforeach; ?>
		</ul>
		<div class="form-footer">
			<button type="submit"><?php esc_html_e( 'Download', 'send-to-e-reader' ); ?></button>
		</div>
	</form>
	</main>
</body>
</html>
