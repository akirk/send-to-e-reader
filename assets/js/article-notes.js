/**
 * Article Notes Widget JavaScript
 *
 * @package Send_To_E_Reader
 */

(function($) {
	'use strict';

	var ArticleNotes = {
		/**
		 * Debounce timer for notes saving.
		 */
		saveTimers: {},

		/**
		 * Initialize the widget.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			var self = this;

			// Tab switching.
			$(document).on('click', '.ereader-tab', function(e) {
				e.preventDefault();
				self.switchTab($(this).data('tab'));
			});

			// Status button clicks.
			$(document).on('click', '.ereader-status-btn', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var $item = $btn.closest('.ereader-article-item');
				var articleId = $item.data('article-id');
				var status = $btn.data('status');

				self.saveNote(articleId, { status: status }, $item);

				// Update UI.
				$item.find('.ereader-status-btn').removeClass('active');
				$btn.addClass('active');
			});

			// Star rating clicks.
			$(document).on('click', '.ereader-star', function(e) {
				e.preventDefault();
				var $star = $(this);
				var $item = $star.closest('.ereader-article-item');
				var $ratingContainer = $star.closest('.ereader-rating');
				var articleId = $item.data('article-id');
				var rating = $star.data('rating');

				self.saveNote(articleId, { rating: rating }, $item);

				// Update UI.
				self.updateStars($ratingContainer, rating);
			});

			// Notes textarea - save on blur with debounce.
			$(document).on('input', '.ereader-notes', function() {
				var $textarea = $(this);
				var $item = $textarea.closest('.ereader-article-item');
				var articleId = $item.data('article-id');

				// Clear existing timer for this article.
				if (self.saveTimers[articleId]) {
					clearTimeout(self.saveTimers[articleId]);
				}

				// Set new debounced save.
				self.saveTimers[articleId] = setTimeout(function() {
					self.saveNote(articleId, { notes: $textarea.val() }, $item);
				}, 1000);
			});

			// Also save on blur.
			$(document).on('blur', '.ereader-notes', function() {
				var $textarea = $(this);
				var $item = $textarea.closest('.ereader-article-item');
				var articleId = $item.data('article-id');

				// Clear pending timer and save immediately.
				if (self.saveTimers[articleId]) {
					clearTimeout(self.saveTimers[articleId]);
					delete self.saveTimers[articleId];
				}

				self.saveNote(articleId, { notes: $textarea.val() }, $item);
			});

			// Toggle notes in reviewed list.
			$(document).on('click', '.ereader-notes-preview', function() {
				var $item = $(this).closest('.ereader-article-item');
				$(this).hide();
				$item.find('.ereader-notes-wrapper').show();
				$item.find('.ereader-notes').focus();
			});

			// Create post from selected.
			$(document).on('click', '.ereader-create-post-btn', function(e) {
				e.preventDefault();
				self.createPostFromSelected();
			});

			// Select all toggle for reviewed list.
			$(document).on('change', '.ereader-select-all', function() {
				var checked = $(this).prop('checked');
				$('.ereader-reviewed-list input[type="checkbox"]').prop('checked', checked);
			});
		},

		/**
		 * Switch between tabs.
		 *
		 * @param {string} tab Tab name.
		 */
		switchTab: function(tab) {
			$('.ereader-tab').removeClass('active');
			$('.ereader-tab[data-tab="' + tab + '"]').addClass('active');

			$('.ereader-tab-content').removeClass('active');
			$('.ereader-tab-content[data-tab="' + tab + '"]').addClass('active');
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
					$star.addClass('active').html('&#9733;');
				} else {
					$star.removeClass('active').html('&#9734;');
				}
			});
		},

		/**
		 * Save note via AJAX.
		 *
		 * @param {number} articleId Article post ID.
		 * @param {object} data Data to save.
		 * @param {jQuery} $item Article item element.
		 */
		saveNote: function(articleId, data, $item) {
			var self = this;
			var $status = $item.find('.ereader-save-status');

			$status.text(ereaderArticleNotes.i18n.saving).addClass('saving');

			var postData = $.extend({
				action: 'ereader_save_note',
				_ajax_nonce: ereaderArticleNotes.nonce,
				article_id: articleId
			}, data);

			$.post(ereaderArticleNotes.ajaxurl, postData)
				.done(function(response) {
					if (response.success) {
						$status.text(ereaderArticleNotes.i18n.saved)
							.removeClass('saving error')
							.addClass('saved');

						// If this was in pending and now has a status, it might need to move.
						if (data.status && data.status !== 'unread') {
							self.maybeMoveToPending($item, false);
						}

						setTimeout(function() {
							$status.text('').removeClass('saved');
						}, 2000);
					} else {
						$status.text(ereaderArticleNotes.i18n.error)
							.removeClass('saving saved')
							.addClass('error');
					}
				})
				.fail(function() {
					$status.text(ereaderArticleNotes.i18n.error)
						.removeClass('saving saved')
						.addClass('error');
				});
		},

		/**
		 * Maybe move item between pending/reviewed.
		 *
		 * @param {jQuery} $item Article item.
		 * @param {boolean} toPending Move to pending?
		 */
		maybeMoveToPending: function($item, toPending) {
			// For now, just update the count. Full move would require page refresh.
			var $pendingTab = $('.ereader-tab[data-tab="pending"]');
			var countMatch = $pendingTab.find('.count').text().match(/\d+/);
			if (countMatch) {
				var count = parseInt(countMatch[0], 10);
				if (toPending) {
					count++;
				} else {
					count = Math.max(0, count - 1);
				}
				$pendingTab.find('.count').text('(' + count + ')');
			}
		},

		/**
		 * Create a post from selected reviewed articles.
		 */
		createPostFromSelected: function() {
			var selectedIds = [];
			$('.ereader-reviewed-list input[type="checkbox"]:checked').each(function() {
				selectedIds.push($(this).val());
			});

			if (selectedIds.length === 0) {
				alert('Please select at least one article.');
				return;
			}

			var postTitle = $('#ereader-post-title').val();
			var $btn = $('.ereader-create-post-btn');

			$btn.prop('disabled', true).text('Creating...');

			$.post(ereaderArticleNotes.ajaxurl, {
				action: 'ereader_create_post_from_notes',
				_ajax_nonce: ereaderArticleNotes.nonce,
				article_ids: selectedIds,
				post_title: postTitle
			})
				.done(function(response) {
					if (response.success && response.data.edit_url) {
						window.location.href = response.data.edit_url;
					} else {
						alert(response.data || 'Error creating post');
						$btn.prop('disabled', false).text('Create Post from Selected');
					}
				})
				.fail(function() {
					alert('Error creating post');
					$btn.prop('disabled', false).text('Create Post from Selected');
				});
		}
	};

	// Initialize when DOM is ready.
	$(document).ready(function() {
		ArticleNotes.init();
	});

})(jQuery);
