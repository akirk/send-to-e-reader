/**
 * Article Notes Widget JavaScript - Triage Mode
 *
 * Simplified JavaScript for quick triage: Read, Revisit, or Skip.
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

			// Triage button clicks (Read, Revisit, Skip).
			$(document).on('click', '.ereader-triage-btn', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var $item = $btn.closest('.ereader-article-item');
				var articleId = $item.data('article-id');
				var status = $btn.data('status');

				// Get quick note if any.
				var notes = $item.find('.ereader-quick-note-input').val() || '';

				// Save with status and notes.
				self.saveNote(articleId, { status: status, notes: notes }, $item);

				// Animate removal from triage list.
				self.animateItemRemoval($item);
			});

			// Quick note toggle.
			$(document).on('click', '.ereader-quick-note-toggle', function(e) {
				e.preventDefault();
				var $item = $(this).closest('.ereader-article-item');
				var $notes = $item.find('.ereader-quick-notes');
				$notes.slideToggle(200);
				if ($notes.is(':visible')) {
					$notes.find('.ereader-quick-note-input').focus();
				}
			});

			// Quick note input - save on blur.
			$(document).on('blur', '.ereader-quick-note-input', function() {
				var $input = $(this);
				var $item = $input.closest('.ereader-article-item');
				var articleId = $item.data('article-id');
				var notes = $input.val();

				if (notes) {
					self.saveNote(articleId, { notes: notes }, $item);
				}
			});

			// Archive button clicks.
			$(document).on('click', '.ereader-archive-btn', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var $item = $btn.closest('.ereader-article-item');
				var articleId = $item.data('article-id');

				self.saveNote(articleId, { status: 'archived' }, $item);
				self.animateItemRemoval($item);
			});

			// Load more pending articles.
			$(document).on('click', '.ereader-load-more-btn', function(e) {
				e.preventDefault();
				self.loadMorePending($(this));
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
		 * Animate item removal from list.
		 *
		 * @param {jQuery} $item The article item to remove.
		 */
		animateItemRemoval: function($item) {
			$item.addClass('ereader-moving');
			setTimeout(function() {
				$item.addClass('ereader-removed');
				setTimeout(function() {
					$item.remove();

					// Check if list is now empty.
					var $list = $('.ereader-pending-list');
					if ($list.length && $list.children().length === 0) {
						$list.closest('.ereader-tab-content').find('.ereader-tab-hint').remove();
						$list.replaceWith('<p class="ereader-no-articles">No new articles to triage. Great job!</p>');
					}
				}, 300);
			}, 200);
		},

		/**
		 * Save note via AJAX.
		 *
		 * @param {number} articleId Article post ID.
		 * @param {object} data Data to save.
		 * @param {jQuery} $item Article item element.
		 */
		saveNote: function(articleId, data, $item) {
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
		 * Load more articles.
		 *
		 * @param {jQuery} $btn The load more button.
		 */
		loadMorePending: function($btn) {
			var self = this;
			var offset = $btn.data('offset');
			var type = $btn.data('type') || 'pending';
			var originalText = $btn.text();

			$btn.prop('disabled', true).text(ereaderArticleNotes.i18n.loading || 'Loading...');

			$.post(ereaderArticleNotes.ajaxurl, {
				action: 'ereader_load_more_pending',
				_ajax_nonce: ereaderArticleNotes.nonce,
				offset: offset,
				type: type
			})
				.done(function(response) {
					if (response.success && response.data.articles) {
						var $list = $('.ereader-' + type + '-list');

						// Append new articles.
						response.data.articles.forEach(function(article) {
							$list.append(self.renderTriageItem(article));
						});

						// Update button offset or hide if no more.
						if (response.data.has_more) {
							$btn.data('offset', response.data.offset);
							$btn.prop('disabled', false).text(originalText);
						} else {
							$btn.closest('.ereader-load-more-section').remove();
						}
					} else {
						$btn.prop('disabled', false).text(originalText);
					}
				})
				.fail(function() {
					$btn.prop('disabled', false).text(originalText);
				});
		},

		/**
		 * Render a triage article item HTML.
		 *
		 * @param {object} article Article data.
		 * @return {string} HTML string.
		 */
		renderTriageItem: function(article) {
			var statuses = ereaderArticleNotes.triageStatuses || {
				'read': 'Read',
				'revisit': 'Revisit',
				'skipped': 'Skip'
			};

			var html = '<li class="ereader-article-item" data-article-id="' + article.id + '">';
			html += '<div class="ereader-article-header">';
			html += '<a href="' + article.permalink + '" class="ereader-article-title" target="_blank">' + this.escapeHtml(article.title) + '</a>';
			html += '<span class="ereader-article-meta">' + this.escapeHtml(article.author);
			if (article.sent_date) {
				html += ' &bull; ' + this.escapeHtml(article.sent_date);
			}
			html += '</span></div>';

			html += '<div class="ereader-triage-controls">';
			html += '<div class="ereader-triage-buttons">';
			for (var key in statuses) {
				html += '<button type="button" class="ereader-triage-btn ereader-triage-' + key + '" data-status="' + key + '" title="' + statuses[key] + '">' + statuses[key] + '</button>';
			}
			html += '</div>';
			html += '<button type="button" class="ereader-quick-note-toggle" title="Add a quick note"><span class="dashicons dashicons-edit"></span></button>';
			html += '</div>';

			html += '<div class="ereader-quick-notes" style="display: none;">';
			html += '<input type="text" class="ereader-quick-note-input" placeholder="Quick note (optional)..." value="' + this.escapeHtml(article.notes || '') + '">';
			html += '</div>';

			html += '<div class="ereader-save-status"></div>';
			html += '</li>';

			return html;
		},

		/**
		 * Escape HTML entities.
		 *
		 * @param {string} str String to escape.
		 * @return {string} Escaped string.
		 */
		escapeHtml: function(str) {
			if (!str) return '';
			var div = document.createElement('div');
			div.textContent = str;
			return div.innerHTML;
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
