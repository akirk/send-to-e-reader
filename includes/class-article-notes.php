<?php
/**
 * Article Notes
 *
 * Manages article notes/reviews as a custom post type.
 *
 * @package Send_To_E_Reader
 */

namespace Send_To_E_Reader;

/**
 * Class for managing article notes and reviews.
 *
 * @since 1.1.0
 */
class Article_Notes {
	const POST_TYPE = 'ereader_note';
	const NOTE_ID_META = 'ereader_note_id';
	const RATING_META = 'ereader_rating';
	const STATUS_META = 'ereader_status';
	const INLINE_NOTES_META = 'ereader_inline_notes';

	const STATUS_REVISIT = 'revisit';
	const STATUS_READ = 'read';
	const STATUS_SKIPPED = 'skipped';
	const STATUS_ARCHIVED = 'archived';

	/**
	 * Reference to the main plugin instance.
	 *
	 * @var Send_To_E_Reader
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Send_To_E_Reader $plugin The main plugin instance.
	 */
	public function __construct( Send_To_E_Reader $plugin ) {
		$this->plugin = $plugin;
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks.
	 */
	private function register_hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_review_page_rewrite' ) );
		add_action( 'template_redirect', array( $this, 'render_review_page' ) );
		add_action( 'wp_ajax_ereader_save_note', array( $this, 'ajax_save_note' ) );
		add_action( 'wp_ajax_ereader_get_notes', array( $this, 'ajax_get_notes' ) );
		add_action( 'wp_ajax_ereader_load_more_pending', array( $this, 'ajax_load_more_pending' ) );
		add_action( 'wp_ajax_ereader_create_post_from_notes', array( $this, 'ajax_create_post_from_notes' ) );
		add_action( 'wp_ajax_ereader_create_single_post', array( $this, 'ajax_create_single_post' ) );
		add_action( 'wp_ajax_ereader_dismiss_old_articles', array( $this, 'ajax_dismiss_old_articles' ) );
		add_action( 'wp_ajax_ereader_get_article_content', array( $this, 'ajax_get_article_content' ) );
		add_action( 'before_delete_post', array( $this, 'maybe_delete_note' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_filter( 'query_vars', array( $this, 'add_review_query_vars' ) );
	}

	/**
	 * Register admin menu page.
	 */
	public function register_admin_menu() {
		add_submenu_page(
			'options-general.php',
			__( 'Article Notes', 'send-to-e-reader' ),
			__( 'Article Notes', 'send-to-e-reader' ),
			'edit_posts',
			'ereader-article-notes',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render the admin page.
	 */
	public function render_admin_page() {
		$this->enqueue_admin_page_assets();

		// Get current filter.
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'all';
		$paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$per_page = 20;

		// Get notes based on filter.
		$notes_data = $this->get_all_notes( $status_filter, $per_page, ( $paged - 1 ) * $per_page );

		$this->plugin->get_template_loader()->get_template_part(
			'admin/article-notes-page',
			null,
			array(
				'notes'         => $notes_data['notes'],
				'total'         => $notes_data['total'],
				'paged'         => $paged,
				'per_page'      => $per_page,
				'status_filter' => $status_filter,
				'statuses'      => self::get_statuses(),
				'nonce'         => wp_create_nonce( 'ereader-article-notes' ),
			)
		);
	}

	/**
	 * Enqueue assets for the admin page.
	 */
	private function enqueue_admin_page_assets() {
		$version = SEND_TO_E_READER_VERSION;

		wp_enqueue_style(
			'ereader-article-notes-admin',
			plugins_url( 'assets/css/article-notes-admin.css', dirname( __FILE__ ) ),
			array(),
			$version
		);

		wp_enqueue_script(
			'ereader-article-notes',
			plugins_url( 'assets/js/article-notes.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			$version,
			true
		);

		wp_localize_script(
			'ereader-article-notes',
			'ereaderArticleNotes',
			array(
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ereader-article-notes' ),
				'statuses' => self::get_statuses(),
				'i18n'     => array(
					'saving'  => __( 'Saving...', 'send-to-e-reader' ),
					'saved'   => __( 'Saved', 'send-to-e-reader' ),
					'error'   => __( 'Error saving', 'send-to-e-reader' ),
					'loading' => __( 'Loading...', 'send-to-e-reader' ),
				),
			)
		);
	}

	/**
	 * Get all notes with optional filtering.
	 *
	 * @param string $status Status filter ('all', 'unread', 'read', 'skipped', 'archived').
	 * @param int    $limit  Number of items per page.
	 * @param int    $offset Offset for pagination.
	 * @return array Array with 'notes' and 'total' keys.
	 */
	public function get_all_notes( $status = 'all', $limit = 20, $offset = 0 ) {
		$meta_query = array();

		if ( 'all' !== $status && in_array( $status, self::get_all_status_values(), true ) ) {
			$meta_query[] = array(
				'key'   => self::STATUS_META,
				'value' => $status,
			);
		}

		$args = array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
		}

		$query = new \WP_Query( $args );
		$notes = array();

		foreach ( $query->posts as $note_post ) {
			$parent_id = $note_post->post_parent;
			$parent = get_post( $parent_id );

			if ( ! $parent ) {
				continue;
			}

			$notes[] = array(
				'id'          => $parent_id,
				'note_id'     => $note_post->ID,
				'title'       => get_the_title( $parent ),
				'permalink'   => get_permalink( $parent ),
				'author'      => $this->plugin->get_post_author_name( $parent ),
				'status'      => get_post_meta( $note_post->ID, self::STATUS_META, true ) ?: self::STATUS_UNREAD,
				'rating'      => (int) get_post_meta( $note_post->ID, self::RATING_META, true ),
				'notes'       => $note_post->post_content,
				'updated'     => $note_post->post_modified,
			);
		}

		// Get total count.
		$count_args = array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		if ( ! empty( $meta_query ) ) {
			$count_args['meta_query'] = $meta_query;
		}

		$total = count( get_posts( $count_args ) );

		return array(
			'notes' => $notes,
			'total' => $total,
		);
	}

	/**
	 * Register the article notes custom post type.
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Article Notes', 'send-to-e-reader' ),
					'singular_name' => __( 'Article Note', 'send-to-e-reader' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'supports'            => array( 'editor' ),
				'hierarchical'        => false,
				'can_export'          => true,
			)
		);
	}

	/**
	 * Register the dashboard widget.
	 */
	public function register_dashboard_widget() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'ereader_article_notes',
			__( 'E-Reader Article Notes', 'send-to-e-reader' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render the dashboard widget.
	 */
	public function render_dashboard_widget() {
		$this->enqueue_widget_assets();
		$pending_limit = 5;
		$other_limit = 10;

		$pending_articles = $this->get_pending_articles( $pending_limit + 1 );
		$has_more_pending = count( $pending_articles ) > $pending_limit;
		if ( $has_more_pending ) {
			$pending_articles = array_slice( $pending_articles, 0, $pending_limit );
		}

		$revisit_articles = $this->get_revisit_articles( $other_limit + 1 );
		$has_more_revisit = count( $revisit_articles ) > $other_limit;
		if ( $has_more_revisit ) {
			$revisit_articles = array_slice( $revisit_articles, 0, $other_limit );
		}

		$this->plugin->get_template_loader()->get_template_part(
			'admin/article-notes-widget',
			null,
			array(
				'pending_articles'   => $pending_articles,
				'has_more_pending'   => $has_more_pending,
				'pending_limit'      => $pending_limit,
				'revisit_articles'   => $revisit_articles,
				'has_more_revisit'   => $has_more_revisit,
				'reviewed_articles'  => $this->get_reviewed_articles( $other_limit ),
				'revisit_count'      => count( $this->get_revisit_articles( -1 ) ),
				'nonce'              => wp_create_nonce( 'ereader-article-notes' ),
			)
		);
	}

	/**
	 * Enqueue assets for the widget.
	 */
	private function enqueue_widget_assets() {
		$version = SEND_TO_E_READER_VERSION;

		wp_enqueue_style(
			'ereader-article-notes',
			plugins_url( 'assets/css/article-notes.css', dirname( __FILE__ ) ),
			array(),
			$version
		);

		wp_enqueue_script(
			'ereader-article-notes',
			plugins_url( 'assets/js/article-notes.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			$version,
			true
		);

		wp_localize_script(
			'ereader-article-notes',
			'ereaderArticleNotes',
			array(
				'ajaxurl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'ereader-article-notes' ),
				'statuses'       => self::get_statuses(),
				'triageStatuses' => self::get_triage_statuses(),
				'reviewPageUrl'  => $this->get_review_page_url(),
				'i18n'           => array(
					'saving'        => __( 'Saving...', 'send-to-e-reader' ),
					'saved'         => __( 'Saved', 'send-to-e-reader' ),
					'error'         => __( 'Error saving', 'send-to-e-reader' ),
					'loading'       => __( 'Loading...', 'send-to-e-reader' ),
					'confirmCreate' => __( 'Create a post from the selected reviews?', 'send-to-e-reader' ),
				),
			)
		);
	}

	/**
	 * Get post types to query for articles.
	 *
	 * @return array Array of post type names.
	 */
	private function get_article_post_types() {
		// Get all registered post types to ensure we don't miss any.
		$post_types = get_post_types( array(), 'names' );

		// Exclude our own note post type and some WordPress internals.
		$exclude = array(
			self::POST_TYPE,
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'user_request',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_global_styles',
			'wp_navigation',
			'wp_font_family',
			'wp_font_face',
		);

		return array_values( array_diff( $post_types, $exclude ) );
	}

	/**
	 * Get articles that have been downloaded but not yet reviewed.
	 *
	 * @param int $limit  Maximum number of articles to return.
	 * @param int $offset Number of articles to skip.
	 * @return array Array of post objects with note data.
	 */
	public function get_pending_articles( $limit = 20, $offset = 0 ) {
		$args = array(
			'post_type'      => $this->get_article_post_types(),
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'post_status'    => 'any',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => Send_To_E_Reader::POST_META,
					'compare' => 'EXISTS',
				),
				array(
					'key'     => self::NOTE_ID_META,
					'compare' => 'NOT EXISTS',
				),
			),
			'orderby'        => 'meta_value_num',
			'meta_key'       => Send_To_E_Reader::POST_META,
			'order'          => 'DESC',
		);

		$posts = get_posts( $args );

		return array_map( array( $this, 'prepare_article_data' ), $posts );
	}

	/**
	 * Get articles marked for revisit (has note but status is revisit).
	 *
	 * @param int $limit  Maximum number of articles to return.
	 * @param int $offset Number of articles to skip.
	 * @return array Array of post objects with note data.
	 */
	public function get_revisit_articles( $limit = 20, $offset = 0 ) {
		// Get note IDs with revisit status.
		$note_ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => self::STATUS_META,
						'value' => self::STATUS_REVISIT,
					),
				),
			)
		);

		if ( empty( $note_ids ) ) {
			return array();
		}

		// Get the parent article IDs.
		$article_ids = array();
		foreach ( $note_ids as $note_id ) {
			$parent_id = wp_get_post_parent_id( $note_id );
			if ( $parent_id ) {
				$article_ids[] = $parent_id;
			}
		}

		if ( empty( $article_ids ) ) {
			return array();
		}

		$args = array(
			'post_type'      => $this->get_article_post_types(),
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'post_status'    => 'any',
			'post__in'       => $article_ids,
			'orderby'        => 'post__in',
		);

		$posts = get_posts( $args );

		return array_map( array( $this, 'prepare_article_data' ), $posts );
	}

	/**
	 * Get articles that have been reviewed (read or skipped).
	 *
	 * @param int $limit Maximum number of articles to return.
	 * @return array Array of post objects with note data.
	 */
	public function get_reviewed_articles( $limit = 20 ) {
		// Get note IDs with read or skipped status.
		$note_ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => self::STATUS_META,
						'value'   => array( self::STATUS_READ, self::STATUS_SKIPPED ),
						'compare' => 'IN',
					),
				),
			)
		);

		if ( empty( $note_ids ) ) {
			return array();
		}

		// Get the parent article IDs.
		$article_ids = array();
		foreach ( $note_ids as $note_id ) {
			$parent_id = wp_get_post_parent_id( $note_id );
			if ( $parent_id ) {
				$article_ids[] = $parent_id;
			}
		}

		if ( empty( $article_ids ) ) {
			return array();
		}

		$args = array(
			'post_type'      => $this->get_article_post_types(),
			'posts_per_page' => $limit,
			'post_status'    => 'any',
			'post__in'       => $article_ids,
			'orderby'        => 'post__in',
		);

		$posts = get_posts( $args );

		return array_map( array( $this, 'prepare_article_data' ), $posts );
	}

	/**
	 * Prepare article data for display.
	 *
	 * @param \WP_Post $post The post object.
	 * @return array Prepared article data.
	 */
	private function prepare_article_data( $post ) {
		$note = $this->get_note( $post->ID );
		$sent_date = get_post_meta( $post->ID, Send_To_E_Reader::POST_META, true );

		return array(
			'id'          => $post->ID,
			'title'       => get_the_title( $post ),
			'permalink'   => get_permalink( $post ),
			'author'      => $this->plugin->get_post_author_name( $post ),
			'sent_date'   => $sent_date ? date_i18n( get_option( 'date_format' ), $sent_date ) : '',
			'excerpt'     => get_the_excerpt( $post ),
			'note_id'     => $note ? $note['id'] : 0,
			'status'      => $note ? $note['status'] : '',
			'rating'      => $note ? $note['rating'] : 0,
			'notes'       => $note ? $note['notes'] : '',
		);
	}

	/**
	 * Get note for an article.
	 *
	 * @param int $article_id The article post ID.
	 * @return array|null Note data or null if not found.
	 */
	public function get_note( $article_id ) {
		$note_id = get_post_meta( $article_id, self::NOTE_ID_META, true );

		if ( ! $note_id ) {
			return null;
		}

		$note_post = get_post( $note_id );

		if ( ! $note_post || self::POST_TYPE !== $note_post->post_type ) {
			// Clean up orphaned reference.
			delete_post_meta( $article_id, self::NOTE_ID_META );
			return null;
		}

		return array(
			'id'           => $note_post->ID,
			'status'       => get_post_meta( $note_post->ID, self::STATUS_META, true ) ?: self::STATUS_REVISIT,
			'rating'       => (int) get_post_meta( $note_post->ID, self::RATING_META, true ),
			'notes'        => $note_post->post_content,
			'inline_notes' => get_post_meta( $note_post->ID, self::INLINE_NOTES_META, true ) ?: '[]',
			'updated'      => $note_post->post_modified,
		);
	}

	/**
	 * Save or update a note for an article.
	 *
	 * @param int    $article_id The article post ID.
	 * @param string $status     Reading status (unread, read, skipped).
	 * @param int    $rating     Star rating (0-5).
	 * @param string $notes      Notes text.
	 * @return int|false Note post ID on success, false on failure.
	 */
	public function save_note( $article_id, $status = null, $rating = null, $notes = null, $inline_notes = null ) {
		$existing_note_id = get_post_meta( $article_id, self::NOTE_ID_META, true );

		$note_data = array(
			'post_type'   => self::POST_TYPE,
			'post_parent' => $article_id,
			'post_status' => 'publish',
		);

		if ( null !== $notes ) {
			$note_data['post_content'] = wp_kses_post( $notes );
		}

		if ( $existing_note_id ) {
			$note_data['ID'] = $existing_note_id;
			$note_id = wp_update_post( $note_data );
		} else {
			$note_id = wp_insert_post( $note_data );

			if ( $note_id && ! is_wp_error( $note_id ) ) {
				update_post_meta( $article_id, self::NOTE_ID_META, $note_id );
			}
		}

		if ( ! $note_id || is_wp_error( $note_id ) ) {
			return false;
		}

		if ( null !== $status && in_array( $status, self::get_all_status_values(), true ) ) {
			update_post_meta( $note_id, self::STATUS_META, $status );
		}

		if ( null !== $rating ) {
			$rating = max( 0, min( 5, (int) $rating ) );
			update_post_meta( $note_id, self::RATING_META, $rating );
		}

		if ( null !== $inline_notes ) {
			update_post_meta( $note_id, self::INLINE_NOTES_META, $inline_notes );
		}

		return $note_id;
	}

	/**
	 * Delete a note.
	 *
	 * @param int $article_id The article post ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_note( $article_id ) {
		$note_id = get_post_meta( $article_id, self::NOTE_ID_META, true );

		if ( ! $note_id ) {
			return false;
		}

		delete_post_meta( $article_id, self::NOTE_ID_META );
		wp_delete_post( $note_id, true );

		return true;
	}

	/**
	 * Maybe delete note when article is deleted.
	 *
	 * @param int $post_id The post ID being deleted.
	 */
	public function maybe_delete_note( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		// If an article is being deleted, delete its note too.
		if ( self::POST_TYPE !== $post->post_type ) {
			$this->delete_note( $post_id );
		}
	}

	/**
	 * AJAX handler for saving a note.
	 */
	public function ajax_save_note() {
		check_ajax_referer( 'ereader-article-notes' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'send-to-e-reader' ) );
		}

		$article_id = isset( $_POST['article_id'] ) ? (int) $_POST['article_id'] : 0;

		if ( ! $article_id ) {
			wp_send_json_error( __( 'Invalid article ID.', 'send-to-e-reader' ) );
		}

		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : null;
		$rating = isset( $_POST['rating'] ) ? (int) $_POST['rating'] : null;
		$notes = isset( $_POST['notes'] ) ? wp_kses_post( wp_unslash( $_POST['notes'] ) ) : null;
		$inline_notes = isset( $_POST['inline_notes'] ) ? sanitize_text_field( wp_unslash( $_POST['inline_notes'] ) ) : null;

		$note_id = $this->save_note( $article_id, $status, $rating, $notes, $inline_notes );

		if ( ! $note_id ) {
			wp_send_json_error( __( 'Failed to save note.', 'send-to-e-reader' ) );
		}

		wp_send_json_success(
			array(
				'note_id' => $note_id,
				'message' => __( 'Note saved.', 'send-to-e-reader' ),
			)
		);
	}

	/**
	 * AJAX handler for getting notes data.
	 */
	public function ajax_get_notes() {
		check_ajax_referer( 'ereader-article-notes' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'send-to-e-reader' ) );
		}

		$type = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : 'pending';
		$limit = isset( $_GET['limit'] ) ? (int) $_GET['limit'] : 20;

		if ( 'reviewed' === $type ) {
			$articles = $this->get_reviewed_articles( $limit );
		} else {
			$articles = $this->get_pending_articles( $limit );
		}

		wp_send_json_success( $articles );
	}

	/**
	 * AJAX handler for loading more pending articles.
	 */
	public function ajax_load_more_pending() {
		check_ajax_referer( 'ereader-article-notes' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'send-to-e-reader' ) );
		}

		$offset = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'pending';
		$limit = 10;

		if ( 'revisit' === $type ) {
			$articles = $this->get_revisit_articles( $limit + 1, $offset );
		} else {
			$articles = $this->get_pending_articles( $limit + 1, $offset );
		}

		$has_more = count( $articles ) > $limit;
		if ( $has_more ) {
			$articles = array_slice( $articles, 0, $limit );
		}

		wp_send_json_success(
			array(
				'articles' => $articles,
				'has_more' => $has_more,
				'offset'   => $offset + count( $articles ),
			)
		);
	}

	/**
	 * AJAX handler for creating a post from selected notes.
	 */
	public function ajax_create_post_from_notes() {
		check_ajax_referer( 'ereader-article-notes' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'send-to-e-reader' ) );
		}

		$article_ids = isset( $_POST['article_ids'] ) ? array_map( 'intval', (array) $_POST['article_ids'] ) : array();
		$post_title = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';

		if ( empty( $article_ids ) ) {
			wp_send_json_error( __( 'No articles selected.', 'send-to-e-reader' ) );
		}

		if ( empty( $post_title ) ) {
			$post_title = sprintf(
				/* translators: %s is a date */
				__( 'Reading Notes - %s', 'send-to-e-reader' ),
				date_i18n( get_option( 'date_format' ) )
			);
		}

		$post_content = $this->generate_post_content( $article_ids );

		$post_id = wp_insert_post(
			array(
				'post_title'   => $post_title,
				'post_content' => $post_content,
				'post_status'  => 'draft',
				'post_type'    => 'post',
			)
		);

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( $post_id->get_error_message() );
		}

		wp_send_json_success(
			array(
				'post_id'  => $post_id,
				'edit_url' => get_edit_post_link( $post_id, 'raw' ),
				'message'  => __( 'Post created successfully.', 'send-to-e-reader' ),
			)
		);
	}

	/**
	 * Generate post content from article notes.
	 *
	 * @param array $article_ids Array of article IDs.
	 * @return string Generated post content in block editor format.
	 */
	private function generate_post_content( $article_ids ) {
		$blocks = array();

		foreach ( $article_ids as $article_id ) {
			$post = get_post( $article_id );
			if ( ! $post ) {
				continue;
			}

			$note = $this->get_note( $article_id );
			if ( ! $note ) {
				continue;
			}

			$permalink = esc_url( get_permalink( $post ) );
			$title = wp_kses_post( get_the_title( $post ) );
			$author = esc_html( $this->plugin->get_post_author_name( $post ) );

			// Build star rating display.
			$stars = '';
			if ( $note['rating'] > 0 ) {
				$stars = str_repeat( '★', $note['rating'] ) . str_repeat( '☆', 5 - $note['rating'] );
			}

			$group_meta = array(
				'metadata' => array(
					'name' => $title,
				),
				'layout'   => array(
					'type' => 'constrained',
				),
			);

			$content = '<!-- wp:group ' . wp_json_encode( $group_meta ) . ' -->' . PHP_EOL;
			$content .= '<div class="wp-block-group">';

			// Heading with link.
			$content .= '<!-- wp:heading {"level":3} -->' . PHP_EOL;
			$content .= '<h3><a href="' . $permalink . '">' . $title . '</a></h3>' . PHP_EOL;
			$content .= '<!-- /wp:heading -->';

			// Author and rating.
			$meta_line = $author;
			if ( $stars ) {
				$meta_line .= ' — ' . $stars;
			}
			$content .= '<!-- wp:paragraph {"className":"article-meta"} -->' . PHP_EOL;
			$content .= '<p class="article-meta">' . $meta_line . '</p>' . PHP_EOL;
			$content .= '<!-- /wp:paragraph -->';

			// Inline notes (quote + comment pairs).
			$inline_notes = array();
			if ( ! empty( $note['inline_notes'] ) ) {
				$inline_notes = json_decode( $note['inline_notes'], true );
				if ( ! is_array( $inline_notes ) ) {
					$inline_notes = array();
				}
			}

			if ( ! empty( $inline_notes ) ) {
				foreach ( $inline_notes as $inline_note ) {
					if ( empty( $inline_note['quote'] ) ) {
						continue;
					}

					// Quote block with the selected text.
					$content .= '<!-- wp:quote -->' . PHP_EOL;
					$content .= '<blockquote class="wp-block-quote"><p>' . esc_html( $inline_note['quote'] ) . '</p></blockquote>' . PHP_EOL;
					$content .= '<!-- /wp:quote -->';

					// Comment paragraph following the quote.
					if ( ! empty( $inline_note['note'] ) ) {
						$content .= '<!-- wp:paragraph -->' . PHP_EOL;
						$content .= '<p>' . wp_kses_post( $inline_note['note'] ) . '</p>' . PHP_EOL;
						$content .= '<!-- /wp:paragraph -->';
					}
				}
			}

			// Summary notes (general notes textarea).
			if ( ! empty( $note['notes'] ) ) {
				$content .= '<!-- wp:separator -->' . PHP_EOL;
				$content .= '<hr class="wp-block-separator"/>' . PHP_EOL;
				$content .= '<!-- /wp:separator -->';

				$content .= '<!-- wp:paragraph {"className":"summary-notes"} -->' . PHP_EOL;
				$content .= '<p class="summary-notes"><strong>' . esc_html__( 'Summary:', 'send-to-e-reader' ) . '</strong> ' . wp_kses_post( $note['notes'] ) . '</p>' . PHP_EOL;
				$content .= '<!-- /wp:paragraph -->';
			}

			$content .= '</div>' . PHP_EOL;
			$content .= '<!-- /wp:group -->';

			$blocks[] = $content;
		}

		return implode( PHP_EOL . PHP_EOL, $blocks );
	}

	/**
	 * AJAX handler for dismissing old articles (marking all pending as skipped).
	 */
	public function ajax_dismiss_old_articles() {
		check_ajax_referer( 'ereader-article-notes' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'send-to-e-reader' ) );
		}

		// Get all pending articles.
		$args = array(
			'post_type'      => $this->get_article_post_types(),
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => Send_To_E_Reader::POST_META,
					'compare' => 'EXISTS',
				),
				array(
					'key'     => self::NOTE_ID_META,
					'compare' => 'NOT EXISTS',
				),
			),
		);

		$article_ids = get_posts( $args );
		$count = 0;

		foreach ( $article_ids as $article_id ) {
			$note_id = $this->save_note( $article_id, self::STATUS_SKIPPED, 0, '' );
			if ( $note_id ) {
				$count++;
			}
		}

		wp_send_json_success(
			array(
				'count'   => $count,
				'message' => sprintf(
					/* translators: %d is the number of articles dismissed */
					__( '%d articles marked as skipped.', 'send-to-e-reader' ),
					$count
				),
			)
		);
	}

	/**
	 * Get triage statuses (for the dashboard widget).
	 *
	 * @return array Associative array of status => label.
	 */
	public static function get_triage_statuses() {
		return array(
			self::STATUS_READ    => __( 'Read', 'send-to-e-reader' ),
			self::STATUS_REVISIT => __( 'Revisit', 'send-to-e-reader' ),
			self::STATUS_SKIPPED => __( 'Skip', 'send-to-e-reader' ),
		);
	}

	/**
	 * Get all valid reading statuses.
	 *
	 * @return array Associative array of status => label.
	 */
	public static function get_statuses() {
		return array(
			self::STATUS_REVISIT => __( 'Revisit', 'send-to-e-reader' ),
			self::STATUS_READ    => __( 'Read', 'send-to-e-reader' ),
			self::STATUS_SKIPPED => __( 'Skipped', 'send-to-e-reader' ),
		);
	}

	/**
	 * Get all valid status values including archived.
	 *
	 * @return array Array of status values.
	 */
	public static function get_all_status_values() {
		return array(
			self::STATUS_REVISIT,
			self::STATUS_READ,
			self::STATUS_SKIPPED,
			self::STATUS_ARCHIVED,
		);
	}

	/**
	 * Register rewrite rules for the review page.
	 */
	public function register_review_page_rewrite() {
		add_rewrite_rule(
			'^article-review/?$',
			'index.php?ereader_review_page=1',
			'top'
		);
		add_rewrite_rule(
			'^article-review/([0-9]+)/?$',
			'index.php?ereader_review_page=1&ereader_article_id=$matches[1]',
			'top'
		);

		// Flush rewrite rules once after adding the review page.
		if ( get_option( 'ereader_review_page_rules_version' ) !== '1.0' ) {
			flush_rewrite_rules();
			update_option( 'ereader_review_page_rules_version', '1.0' );
		}
	}

	/**
	 * Add query vars for the review page.
	 *
	 * @param array $vars Existing query vars.
	 * @return array Modified query vars.
	 */
	public function add_review_query_vars( $vars ) {
		$vars[] = 'ereader_review_page';
		$vars[] = 'ereader_article_id';
		return $vars;
	}

	/**
	 * Render the review page on the frontend.
	 */
	public function render_review_page() {
		if ( ! get_query_var( 'ereader_review_page' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'send-to-e-reader' ) );
		}

		$this->enqueue_review_page_assets();

		// Get the revisit queue.
		$revisit_articles = $this->get_revisit_articles( -1 );
		$article_id = get_query_var( 'ereader_article_id' );
		$current_index = 0;
		$current_article = null;

		if ( $article_id ) {
			// Find the article in the queue.
			foreach ( $revisit_articles as $index => $article ) {
				if ( (int) $article['id'] === (int) $article_id ) {
					$current_index = $index;
					$current_article = $article;
					break;
				}
			}
		}

		// If no specific article requested or not found, use first in queue.
		if ( ! $current_article && ! empty( $revisit_articles ) ) {
			$current_article = $revisit_articles[0];
			$article_id = $current_article['id'];
		}

		// Get article content if we have an article.
		$article_content = '';
		$article_post = null;
		if ( $current_article ) {
			$article_post = get_post( $article_id );
			if ( $article_post ) {
				$article_content = apply_filters( 'the_content', $article_post->post_content );
			}
		}

		// Calculate prev/next.
		$prev_article = $current_index > 0 ? $revisit_articles[ $current_index - 1 ] : null;
		$next_article = $current_index < count( $revisit_articles ) - 1 ? $revisit_articles[ $current_index + 1 ] : null;

		// Get the note data.
		$note = $current_article ? $this->get_note( $article_id ) : null;

		$this->plugin->get_template_loader()->get_template_part(
			'frontend/article-review',
			null,
			array(
				'article'          => $current_article,
				'article_post'     => $article_post,
				'article_content'  => $article_content,
				'note'             => $note,
				'inline_notes'     => $note ? $note['inline_notes'] : '[]',
				'queue_count'      => count( $revisit_articles ),
				'current_position' => $current_index + 1,
				'prev_article'     => $prev_article,
				'next_article'     => $next_article,
				'nonce'            => wp_create_nonce( 'ereader-article-notes' ),
				'statuses'         => self::get_statuses(),
			)
		);
		exit;
	}

	/**
	 * Enqueue assets for the review page.
	 */
	private function enqueue_review_page_assets() {
		$version = SEND_TO_E_READER_VERSION;

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'ereader-article-review',
			plugins_url( 'assets/css/article-review.css', dirname( __FILE__ ) ),
			array( 'dashicons' ),
			$version
		);

		wp_enqueue_script(
			'ereader-article-review',
			plugins_url( 'assets/js/article-review.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			$version,
			true
		);

		wp_localize_script(
			'ereader-article-review',
			'ereaderArticleReview',
			array(
				'ajaxurl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'ereader-article-notes' ),
				'reviewUrl'    => home_url( '/article-review/' ),
				'dashboardUrl' => admin_url(),
				'i18n'         => array(
					'saving'       => __( 'Saving...', 'send-to-e-reader' ),
					'saved'        => __( 'Saved', 'send-to-e-reader' ),
					'error'        => __( 'Error saving', 'send-to-e-reader' ),
					'loading'      => __( 'Loading...', 'send-to-e-reader' ),
					'addNote'      => __( 'Add note', 'send-to-e-reader' ),
					'yourThoughts' => __( 'Your thoughts on this passage...', 'send-to-e-reader' ),
					'cancel'       => __( 'Cancel', 'send-to-e-reader' ),
					'save'         => __( 'Save', 'send-to-e-reader' ),
					'selectText'   => __( 'Select text in the article to add notes', 'send-to-e-reader' ),
				),
			)
		);
	}

	/**
	 * AJAX handler for getting article content.
	 */
	public function ajax_get_article_content() {
		check_ajax_referer( 'ereader-article-notes' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'send-to-e-reader' ) );
		}

		$article_id = isset( $_GET['article_id'] ) ? (int) $_GET['article_id'] : 0;

		if ( ! $article_id ) {
			wp_send_json_error( __( 'Invalid article ID.', 'send-to-e-reader' ) );
		}

		$post = get_post( $article_id );

		if ( ! $post ) {
			wp_send_json_error( __( 'Article not found.', 'send-to-e-reader' ) );
		}

		$note = $this->get_note( $article_id );

		wp_send_json_success(
			array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'content'   => apply_filters( 'the_content', $post->post_content ),
				'author'    => $this->plugin->get_post_author_name( $post ),
				'permalink' => get_permalink( $post ),
				'date'      => get_the_date( '', $post ),
				'note'      => $note,
			)
		);
	}

	/**
	 * AJAX handler for creating a post from a single article's notes.
	 */
	public function ajax_create_single_post() {
		check_ajax_referer( 'ereader-article-notes' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'send-to-e-reader' ) );
		}

		$article_id = isset( $_POST['article_id'] ) ? (int) $_POST['article_id'] : 0;

		if ( ! $article_id ) {
			wp_send_json_error( __( 'Invalid article ID.', 'send-to-e-reader' ) );
		}

		$post = get_post( $article_id );
		if ( ! $post ) {
			wp_send_json_error( __( 'Article not found.', 'send-to-e-reader' ) );
		}

		$note = $this->get_note( $article_id );
		if ( ! $note || empty( $note['notes'] ) ) {
			wp_send_json_error( __( 'No notes found for this article.', 'send-to-e-reader' ) );
		}

		$post_title = sprintf(
			/* translators: %s is the article title */
			__( 'Notes on: %s', 'send-to-e-reader' ),
			get_the_title( $post )
		);

		$post_content = $this->generate_post_content( array( $article_id ) );

		$new_post_id = wp_insert_post(
			array(
				'post_title'   => $post_title,
				'post_content' => $post_content,
				'post_status'  => 'draft',
				'post_type'    => 'post',
			)
		);

		if ( is_wp_error( $new_post_id ) ) {
			wp_send_json_error( $new_post_id->get_error_message() );
		}

		wp_send_json_success(
			array(
				'post_id'  => $new_post_id,
				'edit_url' => get_edit_post_link( $new_post_id, 'raw' ),
				'message'  => __( 'Post created successfully.', 'send-to-e-reader' ),
			)
		);
	}

	/**
	 * Get the URL to the article review page.
	 *
	 * @param int $article_id Optional article ID.
	 * @return string URL to the review page.
	 */
	public function get_review_page_url( $article_id = 0 ) {
		$url = home_url( '/article-review/' );
		if ( $article_id ) {
			$url .= intval( $article_id ) . '/';
		}
		return $url;
	}
}
