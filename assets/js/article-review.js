/**
 * Article Review Page JavaScript - Deep Review Mode
 *
 * JavaScript for the focused article review page with inline annotations.
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
		 * Inline annotations array.
		 */
		inlineNotes: [],

		/**
		 * Currently selected text and range.
		 */
		currentSelection: null,

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

			// Load existing inline notes.
			this.loadInlineNotes();

			this.bindEvents();
			this.createSelectionPopup();
		},

		/**
		 * Load inline notes from data attribute or hidden field.
		 */
		loadInlineNotes: function() {
			var notesData = $('#ereader-inline-notes-data').val();
			if (notesData) {
				try {
					this.inlineNotes = JSON.parse(notesData);
					this.renderInlineNotes();
				} catch (e) {
					this.inlineNotes = [];
				}
			}
		},

		/**
		 * Create the selection popup element.
		 */
		createSelectionPopup: function() {
			// Desktop popup (positioned near selection).
			var popup = '<div class="ereader-selection-popup" style="display:none;">' +
				'<button type="button" class="ereader-add-note-btn" title="' + (ereaderArticleReview.i18n.addNote || 'Add note') + '">' +
				'<span class="dashicons dashicons-edit"></span> ' + (ereaderArticleReview.i18n.addNote || 'Add note') +
				'</button>' +
				'</div>';

			// Mobile floating action button (fixed at bottom).
			var fab = '<div class="ereader-selection-fab" style="display:none;">' +
				'<button type="button" class="ereader-add-note-fab" title="' + (ereaderArticleReview.i18n.addNote || 'Add note') + '">' +
				'<span class="dashicons dashicons-edit"></span> ' + (ereaderArticleReview.i18n.noteSelection || 'Note this selection') +
				'</button>' +
				'</div>';

			var noteInput = '<div class="ereader-note-input-modal" style="display:none;">' +
				'<div class="ereader-note-input-content">' +
				'<div class="ereader-note-input-quote"></div>' +
				'<textarea class="ereader-note-input-text" placeholder="' + (ereaderArticleReview.i18n.yourThoughts || 'Your thoughts on this passage...') + '" rows="3"></textarea>' +
				'<div class="ereader-note-input-actions">' +
				'<button type="button" class="ereader-btn ereader-btn-secondary ereader-note-cancel">' + (ereaderArticleReview.i18n.cancel || 'Cancel') + '</button>' +
				'<button type="button" class="ereader-btn ereader-btn-primary ereader-note-save">' + (ereaderArticleReview.i18n.save || 'Save') + '</button>' +
				'</div>' +
				'</div>' +
				'</div>';

			$('body').append(popup).append(fab).append(noteInput);
		},

		/**
		 * Check if device supports touch.
		 */
		isTouchDevice: function() {
			return ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			var self = this;

			// Different selection handling for touch vs mouse.
			if (this.isTouchDevice()) {
				// On touch devices, use multiple approaches for browser compatibility.
				// 1. selectionchange event (works in Chrome Android).
				document.addEventListener('selectionchange', function() {
					self.handleMobileSelection();
				});

				// 2. Fallback: touchend with delay (for Firefox Android and others).
				$('.ereader-article-body').on('touchend', function() {
					// Check after a delay to let selection complete.
					setTimeout(function() {
						self.handleMobileSelection();
					}, 300);
					// Check again after native selection UI appears.
					setTimeout(function() {
						self.handleMobileSelection();
					}, 600);
				});
			} else {
				// Desktop: show popup near selection on mouseup.
				$('.ereader-article-body').on('mouseup', function(e) {
					setTimeout(function() {
						self.handleTextSelection(e);
					}, 10);
				});
			}

			// Hide popup when clicking elsewhere (desktop).
			$(document).on('mousedown touchstart', function(e) {
				if (!$(e.target).closest('.ereader-selection-popup, .ereader-note-input-modal, .ereader-selection-fab').length) {
					$('.ereader-selection-popup').hide();
				}
			});

			// Add note button click (desktop popup).
			$(document).on('click', '.ereader-add-note-btn', function(e) {
				e.preventDefault();
				e.stopPropagation();
				self.showNoteInput();
			});

			// Add note button click (mobile FAB).
			$(document).on('click', '.ereader-add-note-fab', function(e) {
				e.preventDefault();
				e.stopPropagation();
				self.captureSelectionAndShowInput();
			});

			// Save note from modal.
			$(document).on('click', '.ereader-note-save', function(e) {
				e.preventDefault();
				self.saveInlineNote();
			});

			// Cancel note input.
			$(document).on('click', '.ereader-note-cancel', function(e) {
				e.preventDefault();
				self.hideNoteInput();
			});

			// Enter key in note input (with shift for newline).
			$(document).on('keydown', '.ereader-note-input-text', function(e) {
				if (e.keyCode === 13 && !e.shiftKey) {
					e.preventDefault();
					self.saveInlineNote();
				}
				if (e.keyCode === 27) {
					self.hideNoteInput();
				}
			});

			// Edit inline note.
			$(document).on('click', '.ereader-inline-note-edit', function(e) {
				e.preventDefault();
				var noteId = $(this).closest('.ereader-inline-note').data('note-id');
				self.editInlineNote(noteId);
			});

			// Delete inline note.
			$(document).on('click', '.ereader-inline-note-delete', function(e) {
				e.preventDefault();
				var noteId = $(this).closest('.ereader-inline-note').data('note-id');
				self.deleteInlineNote(noteId);
			});

			// General notes textarea - debounced auto-save.
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
				// Don't trigger if typing in textarea/input or modal is open.
				if ($(e.target).is('textarea, input') || $('.ereader-note-input-modal:visible').length) {
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
		 * Handle text selection in article body (desktop).
		 */
		handleTextSelection: function(e) {
			var selection = window.getSelection();
			var selectedText = selection.toString().trim();

			if (selectedText.length < 3) {
				$('.ereader-selection-popup').hide();
				return;
			}

			// Store selection info.
			this.currentSelection = {
				text: selectedText,
				range: selection.getRangeAt(0).cloneRange()
			};

			// Position popup near selection.
			var range = selection.getRangeAt(0);
			var rect = range.getBoundingClientRect();

			var $popup = $('.ereader-selection-popup');
			var popupWidth = $popup.outerWidth() || 120;

			// Position above the selection, centered.
			var left = rect.left + (rect.width / 2) - (popupWidth / 2) + window.scrollX;
			var top = rect.top - 45 + window.scrollY;

			// Keep within viewport.
			left = Math.max(10, Math.min(left, window.innerWidth - popupWidth - 10));
			if (top < window.scrollY + 10) {
				top = rect.bottom + 10 + window.scrollY;
			}

			$popup.css({
				left: left + 'px',
				top: top + 'px'
			}).show();
		},

		/**
		 * Handle text selection on mobile (shows/hides FAB).
		 */
		handleMobileSelection: function() {
			var selection = window.getSelection();
			var selectedText = selection.toString().trim();
			var $fab = $('.ereader-selection-fab');

			// Check if selection is within the article body.
			var isInArticle = false;
			if (selection.rangeCount > 0) {
				var range = selection.getRangeAt(0);
				var container = range.commonAncestorContainer;
				isInArticle = $(container).closest('.ereader-article-body').length > 0;
			}

			if (selectedText.length >= 3 && isInArticle) {
				$fab.show();
			} else {
				$fab.hide();
			}
		},

		/**
		 * Capture current selection and show note input (mobile).
		 */
		captureSelectionAndShowInput: function() {
			var selection = window.getSelection();
			var selectedText = selection.toString().trim();

			if (selectedText.length < 3) {
				$('.ereader-selection-fab').hide();
				return;
			}

			// Store selection info.
			this.currentSelection = {
				text: selectedText,
				range: selection.rangeCount > 0 ? selection.getRangeAt(0).cloneRange() : null
			};

			// Hide FAB and show note input.
			$('.ereader-selection-fab').hide();
			this.showNoteInput();
		},

		/**
		 * Show the note input modal.
		 */
		showNoteInput: function() {
			if (!this.currentSelection) return;

			$('.ereader-selection-popup').hide();

			var $modal = $('.ereader-note-input-modal');
			var quote = this.currentSelection.text;

			// Truncate long quotes for display.
			var displayQuote = quote.length > 200 ? quote.substring(0, 200) + '...' : quote;

			$modal.find('.ereader-note-input-quote').text('"' + displayQuote + '"');
			$modal.find('.ereader-note-input-text').val('');
			$modal.show();
			$modal.find('.ereader-note-input-text').focus();
		},

		/**
		 * Hide the note input modal.
		 */
		hideNoteInput: function() {
			$('.ereader-note-input-modal').hide();
			$('.ereader-note-input-text').val('');
			this.currentSelection = null;
			this.editingNoteId = null;
		},

		/**
		 * Save an inline note.
		 */
		saveInlineNote: function() {
			var self = this;
			var noteText = $('.ereader-note-input-text').val().trim();

			if (this.editingNoteId) {
				// Editing existing note.
				var note = this.inlineNotes.find(function(n) { return n.id === self.editingNoteId; });
				if (note) {
					note.note = noteText;
				}
			} else if (this.currentSelection) {
				// Creating new note.
				var newNote = {
					id: 'note_' + Date.now(),
					quote: this.currentSelection.text,
					note: noteText,
					timestamp: new Date().toISOString()
				};
				this.inlineNotes.push(newNote);
			}

			this.hideNoteInput();
			this.renderInlineNotes();
			this.persistInlineNotes();
		},

		/**
		 * Edit an existing inline note.
		 */
		editInlineNote: function(noteId) {
			var self = this;
			var note = this.inlineNotes.find(function(n) { return n.id === noteId; });
			if (!note) return;

			this.editingNoteId = noteId;

			var $modal = $('.ereader-note-input-modal');
			var displayQuote = note.quote.length > 200 ? note.quote.substring(0, 200) + '...' : note.quote;

			$modal.find('.ereader-note-input-quote').text('"' + displayQuote + '"');
			$modal.find('.ereader-note-input-text').val(note.note);
			$modal.show();
			$modal.find('.ereader-note-input-text').focus();
		},

		/**
		 * Delete an inline note.
		 */
		deleteInlineNote: function(noteId) {
			var self = this;
			this.inlineNotes = this.inlineNotes.filter(function(n) { return n.id !== noteId; });
			this.renderInlineNotes();
			this.persistInlineNotes();
		},

		/**
		 * Render inline notes in the sidebar.
		 */
		renderInlineNotes: function() {
			var $container = $('.ereader-inline-notes-list');
			$container.empty();

			if (this.inlineNotes.length === 0) {
				$container.append('<p class="ereader-no-inline-notes">' + (ereaderArticleReview.i18n.selectText || 'Select text in the article to add notes') + '</p>');
				return;
			}

			var self = this;
			this.inlineNotes.forEach(function(note) {
				var displayQuote = note.quote.length > 100 ? note.quote.substring(0, 100) + '...' : note.quote;
				var html = '<div class="ereader-inline-note" data-note-id="' + note.id + '">' +
					'<div class="ereader-inline-note-quote">"' + self.escapeHtml(displayQuote) + '"</div>' +
					'<div class="ereader-inline-note-text">' + self.escapeHtml(note.note || '(no comment)') + '</div>' +
					'<div class="ereader-inline-note-actions">' +
					'<button type="button" class="ereader-inline-note-edit" title="Edit"><span class="dashicons dashicons-edit"></span></button>' +
					'<button type="button" class="ereader-inline-note-delete" title="Delete"><span class="dashicons dashicons-trash"></span></button>' +
					'</div>' +
					'</div>';
				$container.append(html);
			});
		},

		/**
		 * Save inline notes to server.
		 */
		persistInlineNotes: function() {
			var self = this;
			var $status = $('.ereader-sidebar-content .ereader-save-status');

			$status.text(ereaderArticleReview.i18n.saving).addClass('saving').removeClass('saved error');

			// Update hidden field.
			$('#ereader-inline-notes-data').val(JSON.stringify(this.inlineNotes));

			$.post(ereaderArticleReview.ajaxurl, {
				action: 'ereader_save_note',
				_ajax_nonce: ereaderArticleReview.nonce,
				article_id: this.articleId,
				inline_notes: JSON.stringify(this.inlineNotes)
			})
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
		 * Escape HTML entities.
		 */
		escapeHtml: function(str) {
			if (!str) return '';
			var div = document.createElement('div');
			div.textContent = str;
			return div.innerHTML;
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
				rating: rating,
				inline_notes: JSON.stringify(this.inlineNotes)
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
				rating: rating,
				inline_notes: JSON.stringify(this.inlineNotes)
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
