<?php
/**
 * Article Review Page Template
 *
 * Allows reviewing an article with text selection and comments.
 *
 * @package Send_To_E_Reader
 */

$article = isset( $args['article'] ) ? $args['article'] : null;
$note = isset( $args['note'] ) ? $args['note'] : null;
$author = isset( $args['author'] ) ? $args['author'] : '';
$nonce = isset( $args['nonce'] ) ? $args['nonce'] : '';
$back_url = isset( $args['back_url'] ) ? $args['back_url'] : '';

if ( ! $article ) {
	return;
}

// Parse existing selections from note content if any.
$existing_selections = array();
if ( $note && ! empty( $note['notes'] ) ) {
	// Parse blocks to extract selections.
	preg_match_all( '/<blockquote[^>]*><p>(.*?)<\/p><\/blockquote>/s', $note['notes'], $quotes );
	preg_match_all( '/<!-- \/wp:quote -->.*?<!-- wp:paragraph -->\s*<p>(.*?)<\/p>/s', $note['notes'], $comments );

	foreach ( $quotes[1] as $i => $quote_text ) {
		$existing_selections[] = array(
			'text'    => html_entity_decode( $quote_text ),
			'comment' => isset( $comments[1][ $i ] ) ? html_entity_decode( $comments[1][ $i ] ) : '',
		);
	}
}
?>

<div class="wrap ereader-article-review" data-article-id="<?php echo esc_attr( $article->ID ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
	<div class="ereader-review-header">
		<a href="<?php echo esc_url( $back_url ); ?>" class="ereader-back-link">&larr; <?php esc_html_e( 'Back to Notes', 'send-to-e-reader' ); ?></a>
		<h1><?php echo esc_html( get_the_title( $article ) ); ?></h1>
		<p class="ereader-review-meta">
			<?php echo esc_html( $author ); ?>
			&bull;
			<a href="<?php echo esc_url( get_permalink( $article ) ); ?>" target="_blank"><?php esc_html_e( 'View original', 'send-to-e-reader' ); ?></a>
		</p>
	</div>

	<div class="ereader-review-instructions">
		<p><?php esc_html_e( 'Tap once to start selection, tap again to end. Then add your comment.', 'send-to-e-reader' ); ?></p>
		<span class="ereader-selection-mode" id="selection-mode-indicator"><?php esc_html_e( 'Tap to start selection', 'send-to-e-reader' ); ?></span>
	</div>

	<div class="ereader-review-layout">
		<div class="ereader-article-content" id="article-content">
			<?php echo wp_kses_post( apply_filters( 'the_content', $article->post_content ) ); ?>
		</div>

		<div class="ereader-selections-panel" id="selections-panel">
			<h2><?php esc_html_e( 'Your Selections', 'send-to-e-reader' ); ?></h2>

			<div class="ereader-selections-list" id="selections-list">
				<?php if ( empty( $existing_selections ) ) : ?>
					<p class="ereader-no-selections"><?php esc_html_e( 'No selections yet. Select text from the article to add notes.', 'send-to-e-reader' ); ?></p>
				<?php else : ?>
					<?php foreach ( $existing_selections as $i => $sel ) : ?>
						<div class="ereader-selection-item" data-index="<?php echo esc_attr( $i ); ?>">
							<blockquote class="ereader-selection-text"><?php echo esc_html( $sel['text'] ); ?></blockquote>
							<div class="ereader-selection-comment-wrapper">
								<textarea class="ereader-selection-comment" placeholder="<?php esc_attr_e( 'Add your comment...', 'send-to-e-reader' ); ?>"><?php echo esc_textarea( $sel['comment'] ); ?></textarea>
							</div>
							<button type="button" class="ereader-delete-selection" title="<?php esc_attr_e( 'Delete selection', 'send-to-e-reader' ); ?>">&times;</button>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<div class="ereader-selections-actions">
				<button type="button" class="button button-primary" id="save-selections">
					<?php esc_html_e( 'Save All', 'send-to-e-reader' ); ?>
				</button>
				<span class="ereader-save-status" id="save-status"></span>
			</div>
		</div>
	</div>
</div>
