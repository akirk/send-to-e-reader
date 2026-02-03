<?php
/**
 * Article Review Page Template - Deep Review Mode
 *
 * A focused interface for reviewing articles marked for revisit.
 * Supports inline annotations by selecting text.
 *
 * @package Send_To_E_Reader
 */

$article         = isset( $args['article'] ) ? $args['article'] : null;
$article_post    = isset( $args['article_post'] ) ? $args['article_post'] : null;
$article_content = isset( $args['article_content'] ) ? $args['article_content'] : '';
$note            = isset( $args['note'] ) ? $args['note'] : null;
$queue_count     = isset( $args['queue_count'] ) ? $args['queue_count'] : 0;
$current_position = isset( $args['current_position'] ) ? $args['current_position'] : 0;
$prev_article    = isset( $args['prev_article'] ) ? $args['prev_article'] : null;
$next_article    = isset( $args['next_article'] ) ? $args['next_article'] : null;
$nonce           = isset( $args['nonce'] ) ? $args['nonce'] : '';
$statuses        = isset( $args['statuses'] ) ? $args['statuses'] : array();
$review_url      = home_url( '/article-review/' );
$inline_notes    = isset( $args['inline_notes'] ) ? $args['inline_notes'] : '[]';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo $article ? esc_html( $article['title'] ) . ' - ' : ''; ?><?php esc_html_e( 'Article Review', 'send-to-e-reader' ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="ereader-review-page">
	<div class="ereader-review-container" data-nonce="<?php echo esc_attr( $nonce ); ?>">
		<?php if ( ! $article ) : ?>
			<div class="ereader-review-empty">
				<div class="ereader-empty-icon">📖</div>
				<h2><?php esc_html_e( 'No articles to review', 'send-to-e-reader' ); ?></h2>
				<p><?php esc_html_e( 'Your review queue is empty. Mark articles as "Revisit" from the dashboard to add them here.', 'send-to-e-reader' ); ?></p>
				<a href="<?php echo esc_url( admin_url() ); ?>" class="ereader-btn ereader-btn-primary">
					<?php esc_html_e( 'Back to Dashboard', 'send-to-e-reader' ); ?>
				</a>
			</div>
		<?php else : ?>
			<header class="ereader-review-header">
				<div class="ereader-review-nav">
					<a href="<?php echo esc_url( admin_url() ); ?>" class="ereader-back-link">
						← <?php esc_html_e( 'Dashboard', 'send-to-e-reader' ); ?>
					</a>
					<div class="ereader-queue-position">
						<?php
						printf(
							/* translators: %1$d is current position, %2$d is total count */
							esc_html__( '%1$d of %2$d', 'send-to-e-reader' ),
							$current_position,
							$queue_count
						);
						?>
					</div>
					<div class="ereader-nav-buttons">
						<?php if ( $prev_article ) : ?>
							<a href="<?php echo esc_url( $review_url . $prev_article['id'] . '/' ); ?>" class="ereader-nav-btn ereader-nav-prev" title="<?php esc_attr_e( 'Previous article', 'send-to-e-reader' ); ?>">
								← <?php esc_html_e( 'Prev', 'send-to-e-reader' ); ?>
							</a>
						<?php else : ?>
							<span class="ereader-nav-btn ereader-nav-prev disabled">
								← <?php esc_html_e( 'Prev', 'send-to-e-reader' ); ?>
							</span>
						<?php endif; ?>
						<?php if ( $next_article ) : ?>
							<a href="<?php echo esc_url( $review_url . $next_article['id'] . '/' ); ?>" class="ereader-nav-btn ereader-nav-next" title="<?php esc_attr_e( 'Next article', 'send-to-e-reader' ); ?>">
								<?php esc_html_e( 'Next', 'send-to-e-reader' ); ?> →
							</a>
						<?php else : ?>
							<span class="ereader-nav-btn ereader-nav-next disabled">
								<?php esc_html_e( 'Next', 'send-to-e-reader' ); ?> →
							</span>
						<?php endif; ?>
					</div>
				</div>
			</header>

			<main class="ereader-review-main">
				<article class="ereader-article-content" data-article-id="<?php echo esc_attr( $article['id'] ); ?>">
					<header class="ereader-article-header">
						<h1 class="ereader-article-title"><?php echo esc_html( $article['title'] ); ?></h1>
						<div class="ereader-article-meta">
							<span class="ereader-article-author"><?php echo esc_html( $article['author'] ); ?></span>
							<?php if ( $article_post ) : ?>
								<span class="ereader-article-date"><?php echo esc_html( get_the_date( '', $article_post ) ); ?></span>
							<?php endif; ?>
							<a href="<?php echo esc_url( $article['permalink'] ); ?>" class="ereader-article-source" target="_blank" rel="noopener">
								<?php esc_html_e( 'View original', 'send-to-e-reader' ); ?> ↗
							</a>
						</div>
						<p class="ereader-selection-hint">
							<span class="dashicons dashicons-edit"></span>
							<?php esc_html_e( 'Select text to add notes', 'send-to-e-reader' ); ?>
						</p>
					</header>
					<div class="ereader-article-body">
						<?php echo wp_kses_post( $article_content ); ?>
					</div>
				</article>

				<aside class="ereader-review-sidebar">
					<div class="ereader-sidebar-content">
						<div class="ereader-sidebar-header">
							<h2><?php esc_html_e( 'Annotations', 'send-to-e-reader' ); ?></h2>
							<div class="ereader-save-status"></div>
						</div>

						<input type="hidden" id="ereader-inline-notes-data" value="<?php echo esc_attr( $inline_notes ); ?>">

						<div class="ereader-inline-notes-section">
							<div class="ereader-inline-notes-list">
								<p class="ereader-no-inline-notes"><?php esc_html_e( 'Select text in the article to add notes', 'send-to-e-reader' ); ?></p>
							</div>
						</div>

						<div class="ereader-summary-section">
							<label for="ereader-article-notes"><?php esc_html_e( 'Summary (optional)', 'send-to-e-reader' ); ?></label>
							<textarea
								id="ereader-article-notes"
								class="ereader-notes-textarea"
								placeholder="<?php esc_attr_e( 'Overall thoughts...', 'send-to-e-reader' ); ?>"
								rows="3"><?php echo esc_textarea( $note ? $note['notes'] : '' ); ?></textarea>
						</div>

						<div class="ereader-rating-section">
							<label><?php esc_html_e( 'Rating', 'send-to-e-reader' ); ?></label>
							<div class="ereader-rating" data-rating="<?php echo esc_attr( $note ? $note['rating'] : 0 ); ?>">
								<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
									<button type="button"
										class="ereader-star <?php echo ( $note && $i <= $note['rating'] ) ? 'active' : ''; ?>"
										data-rating="<?php echo esc_attr( $i ); ?>"
										title="<?php echo esc_attr( sprintf( __( '%d stars', 'send-to-e-reader' ), $i ) ); ?>">
										<?php echo ( $note && $i <= $note['rating'] ) ? '★' : '☆'; ?>
									</button>
								<?php endfor; ?>
							</div>
						</div>

						<div class="ereader-actions-section">
							<button type="button" class="ereader-btn ereader-btn-primary ereader-mark-reviewed">
								<?php esc_html_e( 'Mark as Reviewed', 'send-to-e-reader' ); ?>
							</button>
							<button type="button" class="ereader-btn ereader-btn-secondary ereader-create-post">
								<?php esc_html_e( 'Create Post', 'send-to-e-reader' ); ?>
							</button>
							<button type="button" class="ereader-btn ereader-btn-skip ereader-skip-article">
								<?php esc_html_e( 'Skip', 'send-to-e-reader' ); ?>
							</button>
						</div>
					</div>
				</aside>
			</main>
		<?php endif; ?>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
