/**
 * Article Review Page JavaScript - Deep Review Mode
 *
 * JavaScript for the focused article review page.
 *
 * @package Send_To_E_Reader
 */

(function($) {
	'use strict';

	var ArticleReview = {
		/**
		 * Debounce timer for notes saving.
		 */
		saveTimer: null,

		/**
		 * Current article ID.
		 */
		articleId: null,

		/**
		 * Initialize the review page.
		 */
		init: function() {
			var $container = $('.ereader-review-container');
			if (!$container.length) {
				return;
			}

			var $article = $('.ereader-article-content');
			if ($article.length) {
				this.articleId = $article.data('article-id');
			}

			this.bindEvents();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			var self = this;

			// Notes textarea - debounced auto-save.
			$(document).on('input', '#ereader-article-notes', function() {
				self.debounceSaveNotes();
			});

			// Also save on blur.
			$(document).on('blur', '#ereader-article-notes', function() {
				self.saveNotes();
			});

			// Star rating clicks.
			$(document).on('click', '.ereader-review-sidebar .ereader-star', function(e) {
				e.preventDefault();
				var $star = $(this);
				var $ratingContainer = $star.closest('.ereader-rating');
				var rating = $star.data('rating');

				self.updateStars($ratingContainer, rating);
				self.saveNote({ rating: rating });
			});

			// Mark as Reviewed button.
			$(document).on('click', '.ereader-mark-reviewed', function(e) {
				e.preventDefault();
				self.markReviewed();
			});

			// Skip button.
			$(document).on('click', '.ereader-skip-article', function(e) {
				e.preventDefault();
				self.skipArticle();
			});

			// Create Post button.
			$(document).on('click', '.ereader-create-post', function(e) {
				e.preventDefault();
				self.createPost();
			});

			// Keyboard shortcuts.
			$(document).on('keydown', function(e) {
				// Don't trigger if typing in textarea.
				if ($(e.target).is('textarea, input')) {
					return;
				}

				// Left arrow - previous article.
				if (e.keyCode === 37) {
					var $prev = $('.ereader-nav-prev:not(.disabled)');
					if ($prev.length) {
						window.location.href = $prev.attr('href');
					}
				}

				// Right arrow - next article.
				if (e.keyCode === 39) {
					var $next = $('.ereader-nav-next:not(.disabled)');
					if ($next.length) {
						window.location.href = $next.attr('href');
					}
				}

				// 'r' key - mark as reviewed.
				if (e.keyCode === 82) {
					e.preventDefault();
					$('.ereader-mark-reviewed').click();
				}

				// 's' key - skip.
				if (e.keyCode === 83) {
					e.preventDefault();
					$('.ereader-skip-article').click();
				}
			});
		},

		/**
		 * Debounce notes saving.
		 */
		debounceSaveNotes: function() {
			var self = this;

			if (this.saveTimer) {
				clearTimeout(this.saveTimer);
			}

			this.saveTimer = setTimeout(function() {
				self.saveNotes();
			}, 1000);
		},

		/**
		 * Save notes immediately.
		 */
		saveNotes: function() {
			if (this.saveTimer) {
				clearTimeout(this.saveTimer);
				this.saveTimer = null;
			}

			var notes = $('#ereader-article-notes').val();
			this.saveNote({ notes: notes });
		},

		/**
		 * Update star display.
		 *
		 * @param {jQuery} $container Rating container.
		 * @param {number} rating Current rating.
		 */
		updateStars: function($container, rating) {
			$container.data('rating', rating);
			$container.find('.ereader-star').each(function(index) {
				var $star = $(this);
				var starRating = index + 1;
				if (starRating <= rating) {
					$star.addClass('active').html('★');
				} else {
					$star.removeClass('active').html('☆');
				}
			});
		},

		/**
		 * Save note via AJAX.
		 *
		 * @param {object} data Data to save.
		 */
		saveNote: function(data) {
			var self = this;
			var $status = $('.ereader-sidebar-content .ereader-save-status');

			if (!this.articleId) {
				return;
			}

			$status.text(ereaderArticleReview.i18n.saving).addClass('saving').removeClass('saved error');

			var postData = $.extend({
				action: 'ereader_save_note',
				_ajax_nonce: ereaderArticleReview.nonce,
				article_id: this.articleId
			}, data);

			$.post(ereaderArticleReview.ajaxurl, postData)
				.done(function(response) {
					if (response.success) {
						$status.text(ereaderArticleReview.i18n.saved)
							.removeClass('saving error')
							.addClass('saved');

						setTimeout(function() {
							$status.text('').removeClass('saved');
						}, 2000);
					} else {
						$status.text(ereaderArticleReview.i18n.error)
							.removeClass('saving saved')
							.addClass('error');
					}
				})
				.fail(function() {
					$status.text(ereaderArticleReview.i18n.error)
						.removeClass('saving saved')
						.addClass('error');
				});
		},

		/**
		 * Mark article as reviewed and move to next.
		 */
		markReviewed: function() {
			var self = this;
			var $btn = $('.ereader-mark-reviewed');

			$btn.prop('disabled', true).text(ereaderArticleReview.i18n.saving);

			// Save current notes first.
			var notes = $('#ereader-article-notes').val();
			var rating = $('.ereader-rating').data('rating') || 0;

			$.post(ereaderArticleReview.ajaxurl, {
				action: 'ereader_save_note',
				_ajax_nonce: ereaderArticleReview.nonce,
				article_id: this.articleId,
				status: 'read',
				notes: notes,
				rating: rating
			})
				.done(function(response) {
					if (response.success) {
						// Move to next article or back to dashboard.
						var $next = $('.ereader-nav-next:not(.disabled)');
						if ($next.length) {
							window.location.href = $next.attr('href');
						} else {
							// No more articles, go to dashboard.
							window.location.href = ereaderArticleReview.dashboardUrl;
						}
					} else {
						alert(ereaderArticleReview.i18n.error);
						$btn.prop('disabled', false).text('Mark as Reviewed');
					}
				})
				.fail(function() {
					alert(ereaderArticleReview.i18n.error);
					$btn.prop('disabled', false).text('Mark as Reviewed');
				});
		},

		/**
		 * Skip article.
		 */
		skipArticle: function() {
			var self = this;
			var $btn = $('.ereader-skip-article');

			$btn.prop('disabled', true).text(ereaderArticleReview.i18n.saving);

			$.post(ereaderArticleReview.ajaxurl, {
				action: 'ereader_save_note',
				_ajax_nonce: ereaderArticleReview.nonce,
				article_id: this.articleId,
				status: 'skipped'
			})
				.done(function(response) {
					if (response.success) {
						// Move to next article or back to dashboard.
						var $next = $('.ereader-nav-next:not(.disabled)');
						if ($next.length) {
							window.location.href = $next.attr('href');
						} else {
							window.location.href = ereaderArticleReview.dashboardUrl;
						}
					} else {
						alert(ereaderArticleReview.i18n.error);
						$btn.prop('disabled', false).text('Skip');
					}
				})
				.fail(function() {
					alert(ereaderArticleReview.i18n.error);
					$btn.prop('disabled', false).text('Skip');
				});
		},

		/**
		 * Create a post from this article's notes.
		 */
		createPost: function() {
			var self = this;
			var $btn = $('.ereader-create-post');

			$btn.prop('disabled', true).text('Creating...');

			// Save notes first.
			var notes = $('#ereader-article-notes').val();
			var rating = $('.ereader-rating').data('rating') || 0;

			$.post(ereaderArticleReview.ajaxurl, {
				action: 'ereader_save_note',
				_ajax_nonce: ereaderArticleReview.nonce,
				article_id: this.articleId,
				notes: notes,
				rating: rating
			})
				.done(function() {
					// Now create the post.
					$.post(ereaderArticleReview.ajaxurl, {
						action: 'ereader_create_single_post',
						_ajax_nonce: ereaderArticleReview.nonce,
						article_id: self.articleId
					})
						.done(function(response) {
							if (response.success && response.data.edit_url) {
								window.location.href = response.data.edit_url;
							} else {
								alert(response.data || 'Error creating post');
								$btn.prop('disabled', false).text('Create Post');
							}
						})
						.fail(function() {
							alert('Error creating post');
							$btn.prop('disabled', false).text('Create Post');
						});
				})
				.fail(function() {
					alert('Error saving notes');
					$btn.prop('disabled', false).text('Create Post');
				});
		}
	};

	// Initialize when DOM is ready.
	$(document).ready(function() {
		ArticleReview.init();
	});

})(jQuery);
