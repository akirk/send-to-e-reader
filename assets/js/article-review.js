/**
 * Article Review Page JavaScript
 *
 * Handles click-to-select text and comment functionality.
 *
 * @package Send_To_E_Reader
 */

(function($) {
	'use strict';

	var ArticleReview = {
		/**
		 * Selection state: 'idle', 'selecting'
		 */
		state: 'idle',

		/**
		 * Selection start position.
		 */
		selectionStart: null,

		/**
		 * All selections.
		 */
		selections: [],

		/**
		 * Initialize the review page.
		 */
		init: function() {
			this.loadExistingSelections();
			this.bindEvents();
		},

		/**
		 * Load existing selections from the DOM.
		 */
		loadExistingSelections: function() {
			var self = this;
			$('.ereader-selection-item').each(function() {
				var $item = $(this);
				self.selections.push({
					text: $item.find('.ereader-selection-text').text(),
					comment: $item.find('.ereader-selection-comment').val()
				});
			});
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			var self = this;

			// Click on article content to select text.
			$('#article-content').on('click', function(e) {
				// Don't interfere with links.
				if ($(e.target).is('a') || $(e.target).closest('a').length) {
					return;
				}

				e.preventDefault();
				self.handleContentClick(e);
			});

			// Delete selection.
			$(document).on('click', '.ereader-delete-selection', function(e) {
				e.preventDefault();
				var $item = $(this).closest('.ereader-selection-item');
				var index = $item.data('index');
				self.deleteSelection(index);
				$item.remove();
				self.reindexSelections();
			});

			// Update comment in selections array.
			$(document).on('input', '.ereader-selection-comment', function() {
				var $item = $(this).closest('.ereader-selection-item');
				var index = $item.data('index');
				if (self.selections[index]) {
					self.selections[index].comment = $(this).val();
				}
			});

			// Save all selections.
			$('#save-selections').on('click', function(e) {
				e.preventDefault();
				self.saveSelections();
			});

			// Clear any browser text selection when clicking.
			$('#article-content').on('mousedown touchstart', function() {
				if (window.getSelection) {
					window.getSelection().removeAllRanges();
				}
			});
		},

		/**
		 * Handle click on article content.
		 *
		 * @param {Event} e Click event.
		 */
		handleContentClick: function(e) {
			var self = this;

			if (this.state === 'idle') {
				// First click - start selection.
				this.state = 'selecting';
				this.selectionStart = this.getClickPosition(e);
				this.updateModeIndicator(ereaderArticleReview.i18n.selectEnd);
				$('#article-content').addClass('ereader-selecting');

				// Mark the start position visually.
				this.markPosition(this.selectionStart, 'start');

			} else if (this.state === 'selecting') {
				// Second click - end selection.
				var selectionEnd = this.getClickPosition(e);

				// Get text between start and end.
				var selectedText = this.getTextBetween(this.selectionStart, selectionEnd);

				if (selectedText && selectedText.trim().length > 0) {
					this.addSelection(selectedText.trim());
				}

				// Reset state.
				this.state = 'idle';
				this.selectionStart = null;
				this.updateModeIndicator(ereaderArticleReview.i18n.selectStart);
				$('#article-content').removeClass('ereader-selecting');
				this.clearMarkers();
			}
		},

		/**
		 * Get click position info.
		 *
		 * @param {Event} e Click event.
		 * @return {Object} Position info.
		 */
		getClickPosition: function(e) {
			var range;
			var textNode;
			var offset;

			// Try to get caret position from click.
			if (document.caretPositionFromPoint) {
				range = document.caretPositionFromPoint(e.clientX, e.clientY);
				if (range) {
					textNode = range.offsetNode;
					offset = range.offset;
				}
			} else if (document.caretRangeFromPoint) {
				range = document.caretRangeFromPoint(e.clientX, e.clientY);
				if (range) {
					textNode = range.startContainer;
					offset = range.startOffset;
				}
			}

			return {
				node: textNode,
				offset: offset,
				x: e.clientX,
				y: e.clientY
			};
		},

		/**
		 * Get text between two positions.
		 *
		 * @param {Object} start Start position.
		 * @param {Object} end End position.
		 * @return {string} Selected text.
		 */
		getTextBetween: function(start, end) {
			if (!start.node || !end.node) {
				return '';
			}

			try {
				var range = document.createRange();

				// Determine order based on document position.
				var comparison = start.node.compareDocumentPosition ?
					start.node.compareDocumentPosition(end.node) :
					0;

				var startNode, startOffset, endNode, endOffset;

				if (comparison & Node.DOCUMENT_POSITION_FOLLOWING || comparison === 0 && start.offset <= end.offset) {
					// Start is before end.
					startNode = start.node;
					startOffset = start.offset;
					endNode = end.node;
					endOffset = end.offset;
				} else {
					// End is before start - swap them.
					startNode = end.node;
					startOffset = end.offset;
					endNode = start.node;
					endOffset = start.offset;
				}

				range.setStart(startNode, Math.min(startOffset, startNode.length || 0));
				range.setEnd(endNode, Math.min(endOffset, endNode.length || 0));

				return range.toString();
			} catch (e) {
				console.error('Error getting text between positions:', e);
				return '';
			}
		},

		/**
		 * Mark a position visually.
		 *
		 * @param {Object} position Position to mark.
		 * @param {string} type 'start' or 'end'.
		 */
		markPosition: function(position, type) {
			var $marker = $('<span class="ereader-position-marker ereader-marker-' + type + '">|</span>');

			if (position.node && position.node.nodeType === Node.TEXT_NODE) {
				var range = document.createRange();
				range.setStart(position.node, Math.min(position.offset, position.node.length));
				range.collapse(true);

				var rect = range.getBoundingClientRect();
				$marker.css({
					position: 'fixed',
					left: rect.left + 'px',
					top: rect.top + 'px'
				});
				$('body').append($marker);
			}
		},

		/**
		 * Clear position markers.
		 */
		clearMarkers: function() {
			$('.ereader-position-marker').remove();
		},

		/**
		 * Update the mode indicator text.
		 *
		 * @param {string} text Indicator text.
		 */
		updateModeIndicator: function(text) {
			$('#selection-mode-indicator').text(text);
		},

		/**
		 * Add a selection to the list.
		 *
		 * @param {string} text Selected text.
		 */
		addSelection: function(text) {
			var index = this.selections.length;
			this.selections.push({
				text: text,
				comment: ''
			});

			var html = '<div class="ereader-selection-item" data-index="' + index + '">' +
				'<blockquote class="ereader-selection-text">' + this.escapeHtml(text) + '</blockquote>' +
				'<div class="ereader-selection-comment-wrapper">' +
				'<textarea class="ereader-selection-comment" placeholder="' + ereaderArticleReview.i18n.addComment + '"></textarea>' +
				'</div>' +
				'<button type="button" class="ereader-delete-selection" title="' + ereaderArticleReview.i18n.deleteSelection + '">&times;</button>' +
				'</div>';

			// Remove "no selections" message if present.
			$('.ereader-no-selections').remove();

			$('#selections-list').append(html);

			// Scroll to the new selection.
			var $newItem = $('.ereader-selection-item[data-index="' + index + '"]');
			$newItem[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			$newItem.find('.ereader-selection-comment').focus();
		},

		/**
		 * Delete a selection.
		 *
		 * @param {number} index Selection index.
		 */
		deleteSelection: function(index) {
			this.selections.splice(index, 1);
		},

		/**
		 * Reindex selections after deletion.
		 */
		reindexSelections: function() {
			var self = this;
			self.selections = [];

			$('.ereader-selection-item').each(function(i) {
				$(this).data('index', i).attr('data-index', i);
				self.selections.push({
					text: $(this).find('.ereader-selection-text').text(),
					comment: $(this).find('.ereader-selection-comment').val()
				});
			});

			// Show "no selections" message if empty.
			if (self.selections.length === 0) {
				$('#selections-list').html('<p class="ereader-no-selections">' +
					'No selections yet. Select text from the article to add notes.</p>');
			}
		},

		/**
		 * Save all selections via AJAX.
		 */
		saveSelections: function() {
			var self = this;
			var $btn = $('#save-selections');
			var $status = $('#save-status');
			var articleId = $('.ereader-article-review').data('article-id');

			// Update comments from textareas.
			$('.ereader-selection-item').each(function(i) {
				if (self.selections[i]) {
					self.selections[i].comment = $(this).find('.ereader-selection-comment').val();
				}
			});

			$btn.prop('disabled', true);
			$status.text(ereaderArticleReview.i18n.saving).removeClass('saved error');

			$.post(ereaderArticleReview.ajaxurl, {
				action: 'ereader_save_selection',
				_ajax_nonce: ereaderArticleReview.nonce,
				article_id: articleId,
				selections: self.selections
			})
				.done(function(response) {
					if (response.success) {
						$status.text(ereaderArticleReview.i18n.saved).addClass('saved');
						setTimeout(function() {
							$status.text('').removeClass('saved');
						}, 3000);
					} else {
						$status.text(ereaderArticleReview.i18n.error).addClass('error');
					}
				})
				.fail(function() {
					$status.text(ereaderArticleReview.i18n.error).addClass('error');
				})
				.always(function() {
					$btn.prop('disabled', false);
				});
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
		}
	};

	// Initialize when DOM is ready.
	$(document).ready(function() {
		if ($('.ereader-article-review').length) {
			ArticleReview.init();
		}
	});

})(jQuery);
