<?php
/**
 * Post Collection integration.
 *
 * @package Send_To_E_Reader
 */

namespace Send_To_E_Reader;

defined( 'ABSPATH' ) || exit;

/**
 * Adds e-reader actions to the Post Collection app.
 *
 * Post Collection is a read-it-later archive with its own frontend, so this is
 * the equivalent of what the Friends integration does on the Friends frontend:
 * an action per configured e-reader on a single article and on a selection of
 * them, plus the download URLs an e-reader's own browser can open to pull the
 * unread articles down without any clicking on the site.
 *
 * There is deliberately no button for the whole backlog. A read-it-later
 * archive runs into the thousands of unread articles, and every image in every
 * chapter is fetched while the file is built, so any one-click version of it
 * either lies about what it does or quietly leaves articles out. Ticking off
 * what you want is the honest way to say how much work to do; a download URL
 * that wants the same thing can carry a number.
 */
class Post_Collection_Integration {
	const AJAX_ACTION = 'post-collection-send-to-e-reader';

	/**
	 * The plugin instance that owns the e-readers.
	 *
	 * @var Send_To_E_Reader
	 */
	private $send_to_e_reader;

	/**
	 * The selections a download URL can ask for.
	 *
	 * @var array
	 */
	private static $selections = array( 'unread', 'new', 'all', 'last', 'list' );

	/**
	 * Constructor.
	 *
	 * @param Send_To_E_Reader $send_to_e_reader The plugin instance.
	 */
	public function __construct( Send_To_E_Reader $send_to_e_reader ) {
		$this->send_to_e_reader = $send_to_e_reader;
		$this->register_hooks();
	}

	/**
	 * Register the WordPress hooks.
	 */
	private function register_hooks() {
		add_action( 'post_collection_app_loaded', array( $this, 'app_loaded' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_send' ) );
		add_filter( 'send_to_e_reader_skip_download_via_url', array( $this, 'skip_download_via_url' ) );
		add_action( 'send_to_e_reader_settings_download_urls', array( $this, 'settings_download_urls' ) );
	}

	/**
	 * Whether the Post Collection app is available.
	 *
	 * @return bool
	 */
	private function app_is_available() {
		return class_exists( '\PostCollection\Post_Collection_App' ) && null !== \PostCollection\Post_Collection_App::instance();
	}

	/**
	 * Get the Post Collection app.
	 *
	 * @return \PostCollection\Post_Collection_App|null
	 */
	private function get_app() {
		return $this->app_is_available() ? \PostCollection\Post_Collection_App::instance() : null;
	}

	/**
	 * Hook into the app once it has registered its routes.
	 *
	 * @param \PostCollection\Post_Collection_App $app The app instance.
	 */
	public function app_loaded( $app ) {
		// An e-reader opens the download URL without being logged in, so this
		// one is registered whoever is asking.
		add_action( 'post_collection_app_request', array( $this, 'maybe_download' ), 10, 2 );

		if ( ! $app->can_manage_collections() ) {
			return;
		}

		add_action( 'post_collection_app_item_actions', array( $this, 'item_actions' ), 10, 2 );
		add_action( 'post_collection_app_selection_actions', array( $this, 'selection_actions' ), 10, 2 );

		if ( function_exists( 'wp_app_enqueue_script' ) ) {
			$file = 'post-collection-e-reader.js';
			$path = SEND_TO_E_READER_PLUGIN_DIR . $file;
			wp_app_enqueue_script(
				'send-to-e-reader-post-collection',
				plugins_url( $file, SEND_TO_E_READER_PLUGIN_DIR . 'send-to-e-reader.php' ),
				array(),
				file_exists( $path ) ? filemtime( $path ) : SEND_TO_E_READER_VERSION,
				true,
				\PostCollection\Post_Collection_App::PATH
			);
		}
	}

	/**
	 * Keep the generic download handler away from the app's URLs.
	 *
	 * It would build its ePub from the main query, which on an app URL holds
	 * whatever the rewrite rule matched rather than the collected posts.
	 *
	 * @param bool $skip Whether to leave the request alone.
	 * @return bool
	 */
	public function skip_download_via_url( $skip ) {
		$app = $this->get_app();

		return $app && $app->is_app_request() ? true : $skip;
	}

	/**
	 * Render the e-reader actions for a single collected post.
	 *
	 * @param \WP_Post $post    The collected post.
	 * @param string   $context Where the item is being rendered.
	 */
	public function item_actions( $post, $context = '' ) {
		$ereaders = $this->send_to_e_reader->get_active_ereaders();
		if ( empty( $ereaders ) || ! $this->send_to_e_reader->current_user_can_send( $post ) ) {
			return;
		}

		$sent = get_post_meta( $post->ID, Send_To_E_Reader::POST_META, true );
		$title = '';
		if ( $sent ) {
			$title = sprintf(
				// translators: %s is a date.
				__( 'Already sent to an e-reader on %s', 'send-to-e-reader' ),
				date_i18n( get_option( 'date_format' ), $sent )
			);
		}

		foreach ( $ereaders as $id => $ereader ) {
			$this->render_action_button(
				$this->get_action_label( $ereader, $context ),
				array(
					'ereader' => $id,
					'post-id' => $post->ID,
				),
				$sent ? 'pc-item-action is-done' : 'pc-item-action',
				$title
			);
		}
	}

	/**
	 * Render the e-reader actions for the current selection of items.
	 *
	 * @param string        $context    Which list the selection is made in.
	 * @param \WP_Term|null $collection The collection in context, if any.
	 */
	public function selection_actions( $context = '', $collection = null ) {
		$ereaders = $this->send_to_e_reader->get_active_ereaders();

		foreach ( $ereaders as $id => $ereader ) {
			$this->render_action_button(
				$this->get_action_label( $ereader, 'selection' ),
				array(
					'ereader'    => $id,
					'selection'  => 'selected',
					'collection' => $collection instanceof \WP_Term ? $collection->term_id : 0,
				)
			);
		}
	}

	/**
	 * Render one action button.
	 *
	 * @param string $label   The button label.
	 * @param array  $data    Data attributes to set, without the data- prefix.
	 * @param string $classes The classes for the button.
	 * @param string $title   An optional title attribute.
	 */
	private function render_action_button( $label, array $data, $classes = 'pc-item-action', $title = '' ) {
		$data['nonce']     = wp_create_nonce( self::AJAX_ACTION );
		$data['ajax-url']  = admin_url( 'admin-ajax.php' );
		$data['busy-label'] = __( 'Sending…', 'send-to-e-reader' );
		$data['done-label'] = __( 'Sent', 'send-to-e-reader' );

		$attributes = '';
		foreach ( $data as $key => $value ) {
			$attributes .= ' data-' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}

		printf(
			'<button type="button" class="%1$s" data-e-reader-send%2$s%3$s>%4$s</button>',
			esc_attr( $classes ),
			$attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each attribute is escaped above.
			$title ? ' title="' . esc_attr( $title ) . '"' : '',
			esc_html( $label )
		);
	}

	/**
	 * Get the label for an e-reader action.
	 *
	 * In a list the label sits between the other per-item links, so it is kept
	 * to a word or two there.
	 *
	 * @param E_Reader $ereader The e-reader.
	 * @param string   $context Where the action is being rendered.
	 * @return string
	 */
	private function get_action_label( $ereader, $context ) {
		$compact = in_array( $context, array( 'board', 'links', 'reader', 'review' ), true );

		if ( $ereader instanceof E_Reader_Download ) {
			return $compact ? __( 'ePub', 'send-to-e-reader' ) : __( 'Download ePub', 'send-to-e-reader' );
		}

		if ( $compact ) {
			return sprintf(
				// translators: %s is an E-Reader name.
				_x( 'To %s', 'e-reader', 'send-to-e-reader' ),
				$ereader->get_name()
			);
		}

		return sprintf(
			// translators: %s is an E-Reader name.
			_x( 'Send to %s', 'e-reader', 'send-to-e-reader' ),
			$ereader->get_name()
		);
	}

	/**
	 * Answer an app request that asks for an ePub.
	 *
	 * @param \PostCollection\Post_Collection_App $app        The app instance.
	 * @param \WP_Term|null                       $collection The collection the URL points at.
	 */
	public function maybe_download( $app, $collection = null ) {
		$request = $this->get_download_request();
		if ( false === $request ) {
			return;
		}

		list( $selection, $limit ) = $request;

		// The download password stands in for being logged in here, the same way
		// it does on the Friends frontend: whoever knows it gets the private
		// posts too, because that is the point of pulling the unread articles
		// onto a device that cannot log in.
		if ( 'list' === $selection ) {
			$this->render_list( $app, $collection );
			exit;
		}

		$posts = $this->get_posts( $app, $selection, $collection, true, $limit );
		if ( empty( $posts ) ) {
			status_header( 404 );
			echo 'no posts found';
			exit;
		}

		$ereader = new E_Reader_Download( __( 'Download ePub', 'send-to-e-reader' ) );
		$result  = $ereader->send_posts( $posts, $this->get_ebook_title( $posts, $collection ) );
		if ( ! $result || is_wp_error( $result ) ) {
			status_header( 500 );
			echo 'error';
			exit;
		}

		$this->mark_sent( $posts );

		wp_safe_redirect( $result['url'] );
		exit;
	}

	/**
	 * Render the list an e-reader can pick posts from.
	 *
	 * @param \PostCollection\Post_Collection_App $app        The app instance.
	 * @param \WP_Term|null                       $collection The collection in context.
	 */
	private function render_list( $app, $collection ) {
		$posts  = array();
		$unsent = array();
		foreach ( $this->get_posts( $app, 'last', $collection, true, 50 ) as $post ) {
			$posts[ $post->ID ] = $post;
			// The list is picked from by hand, so it is not capped, and the
			// ones that have not been sent yet start out ticked.
			if ( ! get_post_meta( $post->ID, Send_To_E_Reader::POST_META, true ) ) {
				$unsent[ $post->ID ] = $post;
			}
		}

		if ( empty( $posts ) ) {
			status_header( 404 );
			echo 'No posts found.';
			exit;
		}

		$this->send_to_e_reader->get_template_loader()->get_template_part(
			'plain-list',
			null,
			array(
				'title'     => $collection instanceof \WP_Term ? $collection->name : __( 'Post Collection', 'send-to-e-reader' ),
				'unsent'    => $unsent,
				'posts'     => $posts,
				'inputname' => $this->send_to_e_reader->get_download_url_var(),
			)
		);
	}

	/**
	 * Read the requested selection out of the download URL.
	 *
	 * A selection can carry how many articles to take: unread-10 is the ten most
	 * recent unread ones. Without a number it means all of them, which is what
	 * the name says, so nothing is quietly left out of the file.
	 *
	 * @return array|false An array of selection and limit, where the selection is
	 *                     a name or a list of post IDs, or false when this is not
	 *                     a download request.
	 */
	private function get_download_request() {
		$url_var = $this->send_to_e_reader->get_download_url_var();
		if ( ! isset( $_GET[ $url_var ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public download URL with the password in the parameter name.
			return false;
		}

		$value = wp_unslash( $_GET[ $url_var ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below.
		if ( is_array( $value ) ) {
			$ids = array_values( array_filter( array_map( 'intval', $value ) ) );

			return empty( $ids ) ? false : array( $ids, null );
		}

		$value = sanitize_key( $value );
		$limit = null;

		if ( preg_match( '/^([a-z]+)-([0-9]+)$/', $value, $matches ) ) {
			$value = $matches[1];
			$limit = max( 1, (int) $matches[2] );
		}

		if ( ! in_array( $value, self::$selections, true ) ) {
			return false;
		}

		return array( $value, $limit );
	}

	/**
	 * Collect the posts a selection stands for.
	 *
	 * @param \PostCollection\Post_Collection_App $app             The app instance.
	 * @param string|array                        $selection       A selection name or a list of post IDs.
	 * @param \WP_Term|null                       $collection      The collection to restrict to.
	 * @param bool                                $include_private Whether private posts are included.
	 * @param int|null                            $limit           How many posts to take, 0 for all of
	 *                                                             them, null for what the selection means
	 *                                                             on its own.
	 * @return \WP_Post[]
	 */
	private function get_posts( $app, $selection, $collection = null, $include_private = false, $limit = null ) {
		$args = array();
		if ( $include_private ) {
			$args['post_collection_include_private'] = true;
		}

		// A list of ids is taken as it is: the reader picked those one by one.
		if ( is_array( $selection ) ) {
			$args['post__in'] = $selection;
			$args['orderby']  = 'post__in';

			return $app->query_app_posts( $collection, $args );
		}

		if ( null === $limit ) {
			$limit = 'last' === $selection ? 10 : 0;
		}

		if ( $limit > 0 ) {
			$args['posts_per_page'] = $limit;
		}

		if ( 'unread' === $selection ) {
			return $app->get_unread_posts( $collection, $args );
		}

		if ( 'new' === $selection ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- the set of unsent posts cannot be expressed otherwise.
				array(
					'key'     => Send_To_E_Reader::POST_META,
					'compare' => 'NOT EXISTS',
				),
			);
		}

		return $app->query_app_posts( $collection, $args );
	}

	/**
	 * Get a title for the generated ePub.
	 *
	 * @param \WP_Post[]    $posts      The posts going into the ePub.
	 * @param \WP_Term|null $collection The collection in context, if any.
	 * @return string|null The title, or null to let the single post name it.
	 */
	private function get_ebook_title( array $posts, $collection = null ) {
		if ( 1 === count( $posts ) ) {
			return null;
		}

		if ( ! $collection instanceof \WP_Term ) {
			$collection = $this->get_common_collection( $posts );
		}

		$name = $collection instanceof \WP_Term ? $collection->name : __( 'Post Collection', 'send-to-e-reader' );

		return sprintf(
			/* translators: 1: a collection name, 2: a date. */
			__( '%1$s, %2$s', 'send-to-e-reader' ),
			$name,
			date_i18n( get_option( 'date_format' ) )
		);
	}

	/**
	 * Get the collection all of the posts are in, if it is the same one.
	 *
	 * @param \WP_Post[] $posts The posts going into the ePub.
	 * @return \WP_Term|null The shared collection, or null when they are spread over several.
	 */
	private function get_common_collection( array $posts ) {
		$common = null;

		foreach ( $posts as $post ) {
			$terms = get_the_terms( $post, \PostCollection\Post_Collection::COLLECTION_TAXONOMY );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				return null;
			}

			foreach ( $terms as $term ) {
				if ( null === $common ) {
					$common = $term;
				} elseif ( $common->term_id !== $term->term_id ) {
					return null;
				}
			}
		}

		return $common;
	}

	/**
	 * Remember that posts went to an e-reader.
	 *
	 * @param \WP_Post[] $posts The posts that were sent.
	 */
	private function mark_sent( array $posts ) {
		foreach ( $posts as $post ) {
			update_post_meta( $post->ID, Send_To_E_Reader::POST_META, time() );
		}
	}

	/**
	 * Send collected posts to an e-reader.
	 */
	public function ajax_send() {
		check_ajax_referer( self::AJAX_ACTION );

		$app = $this->get_app();
		if ( ! $app || ! $app->can_manage_collections() ) {
			wp_send_json_error( __( 'Sorry, you are not allowed to do that.', 'send-to-e-reader' ) );
		}

		$ereader_id = isset( $_POST['ereader'] ) ? sanitize_text_field( wp_unslash( $_POST['ereader'] ) ) : '';
		$ereaders   = $this->send_to_e_reader->get_ereaders();
		if ( ! isset( $ereaders[ $ereader_id ] ) ) {
			wp_send_json_error( __( 'E-Reader not configured', 'send-to-e-reader' ) );
		}

		$collection = null;
		if ( ! empty( $_POST['collection'] ) ) {
			$term = get_term( absint( wp_unslash( $_POST['collection'] ) ), \PostCollection\Post_Collection::COLLECTION_TAXONOMY );
			if ( $term instanceof \WP_Term ) {
				$collection = $term;
			}
		}

		// How many articles to take is asked for in the dialog, so a batch is
		// never larger than the reader agreed to.
		$limit = isset( $_POST['limit'] ) ? max( 0, (int) wp_unslash( $_POST['limit'] ) ) : 0;

		$posts = array();
		if ( ! empty( $_POST['ids'] ) ) {
			$ids = array_values( array_filter( array_map( 'intval', (array) wp_unslash( $_POST['ids'] ) ) ) );
			if ( ! empty( $ids ) ) {
				$posts = $this->get_posts( $app, $ids, $collection );
			}
		} elseif ( ! empty( $_POST['selection'] ) ) {
			$selection = sanitize_key( wp_unslash( $_POST['selection'] ) );
			if ( in_array( $selection, self::$selections, true ) ) {
				$posts = $this->get_posts( $app, $selection, $collection, false, $limit );
			}
		}

		$posts = array_values(
			array_filter(
				$posts,
				function ( $post ) {
					return $this->send_to_e_reader->current_user_can_send( $post );
				}
			)
		);

		if ( empty( $posts ) ) {
			wp_send_json_error( __( 'No posts could be found.', 'send-to-e-reader' ) );
		}

		$result = $ereaders[ $ereader_id ]->send_posts( $posts, $this->get_ebook_title( $posts, $collection ) );
		if ( ! $result || is_wp_error( $result ) ) {
			wp_send_json_error( $result );
		}

		$this->mark_sent( $posts );

		wp_send_json_success( is_array( $result ) ? $result : array() );
	}

	/**
	 * Document the app's download URLs on the settings screen.
	 *
	 * @param string $download_password The configured download password.
	 */
	public function settings_download_urls( $download_password ) {
		$app = $this->get_app();
		if ( ! $app ) {
			return;
		}

		$descriptions = array(
			'unread'    => __( 'Articles you have not read yet:', 'send-to-e-reader' ),
			'unread-10' => __( 'The 10 most recent of them, for a long backlog:', 'send-to-e-reader' ),
			'new'       => __( 'Articles not yet sent to an e-reader:', 'send-to-e-reader' ),
			'all'       => __( 'All collected articles:', 'send-to-e-reader' ),
			'last'      => __( 'The last 10 collected articles:', 'send-to-e-reader' ),
			'list'      => __( 'A list to pick from:', 'send-to-e-reader' ),
		);

		$base = wp_parse_url( $app->get_home_url(), PHP_URL_PATH );
		?>
		<p class="description">
			<?php esc_html_e( 'The same works on the Post Collection app, for one collection or across all of them. Any of these takes a number to cut a long backlog down, as in unread-10:', 'send-to-e-reader' ); ?>
		</p>
		<ul>
			<?php foreach ( $descriptions as $key => $description ) : ?>
				<li>
					<span class="description"><?php echo esc_html( $description ); ?></span>
					<span class="download-preview"><tt><?php echo esc_html( $base ); ?></tt><tt>?epub</tt><tt class="download_password_preview"><?php echo esc_html( $download_password ); ?></tt><tt>=<?php echo esc_html( $key ); ?></tt></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}
}
