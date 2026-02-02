<?php
/**
 * Article Notes Dashboard Widget Template - Triage Mode
 *
 * Simplified widget for quick triage: Read, Revisit, or Skip articles.
 *
 * @package Send_To_E_Reader
 */

$pending_articles  = isset( $args['pending_articles'] ) ? $args['pending_articles'] : array();
$has_more_pending  = isset( $args['has_more_pending'] ) ? $args['has_more_pending'] : false;
$revisit_articles  = isset( $args['revisit_articles'] ) ? $args['revisit_articles'] : array();
$has_more_revisit  = isset( $args['has_more_revisit'] ) ? $args['has_more_revisit'] : false;
$reviewed_articles = isset( $args['reviewed_articles'] ) ? $args['reviewed_articles'] : array();
$revisit_count     = isset( $args['revisit_count'] ) ? $args['revisit_count'] : 0;
$nonce             = isset( $args['nonce'] ) ? $args['nonce'] : '';
$triage_statuses   = \Send_To_E_Reader\Article_Notes::get_triage_statuses();
$review_page_url   = home_url( '/article-review/' );
?>

<div class="ereader-article-notes-widget" data-nonce="<?php echo esc_attr( $nonce ); ?>">
	<?php if ( empty( $pending_articles ) && empty( $reviewed_articles ) && 0 === $revisit_count ) : ?>
		<p class="ereader-no-articles">
			<?php esc_html_e( 'No articles have been sent to your e-reader yet.', 'send-to-e-reader' ); ?>
		</p>
	<?php else : ?>

		<?php if ( $revisit_count > 0 ) : ?>
			<div class="ereader-revisit-banner">
				<span class="ereader-revisit-icon">📚</span>
				<span class="ereader-revisit-text">
					<?php
					printf(
						/* translators: %d is the number of articles */
						esc_html( _n( '%d article queued for review', '%d articles queued for review', $revisit_count, 'send-to-e-reader' ) ),
						$revisit_count
					);
					?>
				</span>
				<a href="<?php echo esc_url( $review_page_url ); ?>" class="button button-primary ereader-start-review">
					<?php esc_html_e( 'Start Review', 'send-to-e-reader' ); ?>
				</a>
			</div>
		<?php endif; ?>

		<div class="ereader-widget-tabs">
			<button type="button" class="ereader-tab active" data-tab="pending">
				<?php esc_html_e( 'Triage', 'send-to-e-reader' ); ?>
				<?php if ( ! empty( $pending_articles ) ) : ?>
					<span class="count">(<?php echo count( $pending_articles ); ?><?php echo $has_more_pending ? '+' : ''; ?>)</span>
				<?php endif; ?>
			</button>
			<button type="button" class="ereader-tab" data-tab="reviewed">
				<?php esc_html_e( 'Done', 'send-to-e-reader' ); ?>
				<?php if ( ! empty( $reviewed_articles ) ) : ?>
					<span class="count">(<?php echo count( $reviewed_articles ); ?>)</span>
				<?php endif; ?>
			</button>
		</div>

		<div class="ereader-tab-content active" data-tab="pending">
			<?php if ( empty( $pending_articles ) ) : ?>
				<p class="ereader-no-articles">
					<?php esc_html_e( 'No new articles to triage. Great job!', 'send-to-e-reader' ); ?>
				</p>
			<?php else : ?>
				<p class="ereader-tab-hint">
					<?php esc_html_e( 'Quick triage: Did you read it? Was it good? Mark and move on.', 'send-to-e-reader' ); ?>
				</p>
				<ul class="ereader-article-list ereader-pending-list">
					<?php foreach ( $pending_articles as $article ) : ?>
						<li class="ereader-article-item" data-article-id="<?php echo esc_attr( $article['id'] ); ?>">
							<div class="ereader-article-header">
								<a href="<?php echo esc_url( $article['permalink'] ); ?>" class="ereader-article-title" target="_blank">
									<?php echo esc_html( $article['title'] ); ?>
								</a>
								<span class="ereader-article-meta">
									<?php echo esc_html( $article['author'] ); ?>
									<?php if ( $article['sent_date'] ) : ?>
										&bull; <?php echo esc_html( $article['sent_date'] ); ?>
									<?php endif; ?>
								</span>
							</div>

							<div class="ereader-triage-controls">
								<div class="ereader-triage-buttons">
									<?php foreach ( $triage_statuses as $status_key => $status_label ) : ?>
										<button type="button"
											class="ereader-triage-btn ereader-triage-<?php echo esc_attr( $status_key ); ?>"
											data-status="<?php echo esc_attr( $status_key ); ?>"
											title="<?php echo esc_attr( $status_label ); ?>">
											<?php echo esc_html( $status_label ); ?>
										</button>
									<?php endforeach; ?>
								</div>

								<button type="button" class="ereader-quick-note-toggle" title="<?php esc_attr_e( 'Add a quick note', 'send-to-e-reader' ); ?>">
									<span class="dashicons dashicons-edit"></span>
								</button>
							</div>

							<div class="ereader-quick-notes" style="display: none;">
								<input type="text"
									class="ereader-quick-note-input"
									placeholder="<?php esc_attr_e( 'Quick note (optional)...', 'send-to-e-reader' ); ?>"
									value="<?php echo esc_attr( $article['notes'] ); ?>">
							</div>

							<div class="ereader-save-status"></div>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( $has_more_pending ) : ?>
					<div class="ereader-load-more-section">
						<button type="button" class="button ereader-load-more-btn" data-type="pending" data-offset="<?php echo count( $pending_articles ); ?>">
							<?php esc_html_e( 'Load more', 'send-to-e-reader' ); ?>
						</button>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<div class="ereader-tab-content" data-tab="reviewed">
			<?php if ( empty( $reviewed_articles ) ) : ?>
				<p class="ereader-no-articles">
					<?php esc_html_e( 'No reviewed articles yet.', 'send-to-e-reader' ); ?>
				</p>
			<?php else : ?>
				<p class="ereader-tab-hint">
					<?php esc_html_e( 'Select articles marked as "Read" to create a post, or archive to hide.', 'send-to-e-reader' ); ?>
				</p>
				<ul class="ereader-article-list ereader-reviewed-list">
					<?php foreach ( $reviewed_articles as $article ) : ?>
						<li class="ereader-article-item" data-article-id="<?php echo esc_attr( $article['id'] ); ?>">
							<div class="ereader-article-header">
								<label class="ereader-select-article">
									<?php if ( 'read' === $article['status'] ) : ?>
										<input type="checkbox" name="selected_articles[]" value="<?php echo esc_attr( $article['id'] ); ?>">
									<?php endif; ?>
									<a href="<?php echo esc_url( $article['permalink'] ); ?>" class="ereader-article-title" target="_blank">
										<?php echo esc_html( $article['title'] ); ?>
									</a>
								</label>
								<span class="ereader-article-meta">
									<?php echo esc_html( $article['author'] ); ?>
									<?php if ( $article['rating'] > 0 ) : ?>
										&bull; <?php echo str_repeat( '★', $article['rating'] ); ?>
									<?php endif; ?>
									&bull;
									<span class="ereader-status-badge ereader-status-<?php echo esc_attr( $article['status'] ); ?>">
										<?php echo esc_html( $article['status'] ); ?>
									</span>
								</span>
							</div>

							<?php if ( ! empty( $article['notes'] ) ) : ?>
								<div class="ereader-notes-preview">
									<?php echo esc_html( wp_trim_words( $article['notes'], 20 ) ); ?>
								</div>
							<?php endif; ?>

							<div class="ereader-reviewed-actions">
								<button type="button" class="ereader-archive-btn" title="<?php esc_attr_e( 'Archive - hide from this list', 'send-to-e-reader' ); ?>">
									<?php esc_html_e( 'Archive', 'send-to-e-reader' ); ?>
								</button>
							</div>

							<div class="ereader-save-status"></div>
						</li>
					<?php endforeach; ?>
				</ul>

				<div class="ereader-create-post-section">
					<input type="text"
						id="ereader-post-title"
						class="ereader-post-title-input"
						placeholder="<?php esc_attr_e( 'Post title (optional)', 'send-to-e-reader' ); ?>">
					<button type="button" class="button button-primary ereader-create-post-btn">
						<?php esc_html_e( 'Create Post from Selected', 'send-to-e-reader' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
