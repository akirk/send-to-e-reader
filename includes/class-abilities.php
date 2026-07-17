<?php
/**
 * WordPress Abilities API integration.
 *
 * @package Send_To_E_Reader
 */

namespace Send_To_E_Reader;

defined( 'ABSPATH' ) || exit;

/**
 * Registers Send to E-Reader abilities for AI Assistant and other clients.
 */
class Abilities {
	const CATEGORY = 'send-to-e-reader';

	/**
	 * Main plugin instance.
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

		if ( function_exists( 'did_action' ) && did_action( 'wp_abilities_api_categories_init' ) ) {
			$this->register_ability_category();
		} else {
			add_action( 'wp_abilities_api_categories_init', array( $this, 'register_ability_category' ) );
		}

		if ( function_exists( 'did_action' ) && did_action( 'wp_abilities_api_init' ) ) {
			$this->register_abilities();
		} else {
			add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		}

		add_filter( 'ai_assistant_ability_domains', array( $this, 'ai_assistant_ability_domains' ) );
		add_filter( 'ai_assistant_ability_instructions', array( $this, 'ai_assistant_ability_instructions' ), 10, 4 );
	}

	/**
	 * Register the Send to E-Reader ability category.
	 */
	public function register_ability_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		if ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( self::CATEGORY ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Send to E-Reader', 'send-to-e-reader' ),
				'description' => __( 'Read e-reader targets and send WordPress posts as EPUB files.', 'send-to-e-reader' ),
			)
		);
	}

	/**
	 * Register Send to E-Reader abilities.
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_ability(
			'send-to-e-reader/list-ereaders',
			array(
				'label'               => __( 'List E-Readers', 'send-to-e-reader' ),
				'description'         => __( 'Lists configured e-reader targets with IDs, names, delivery type, and active state.', 'send-to-e-reader' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'include_inactive' => array(
							'type'        => 'boolean',
							'description' => __( 'Whether to include inactive e-readers.', 'send-to-e-reader' ),
							'default'     => true,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => self::ereaders_output_schema(),
				'execute_callback'    => array( $this, 'list_ereaders' ),
				'permission_callback' => array( $this, 'can_read' ),
				'meta'                => array(
					'annotations'  => array(
						'instructions' => __( 'Use this before sending posts when the user has not named a specific e-reader. Use the returned id as ereader_id for send-to-e-reader/send-posts.', 'send-to-e-reader' ),
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
					),
					'show_in_rest' => true,
				),
			)
		);

		$this->register_ability(
			'send-to-e-reader/list-posts',
			array(
				'label'               => __( 'List E-Reader Posts', 'send-to-e-reader' ),
				'description'         => __( 'Lists WordPress posts with their Send to E-Reader sent status, IDs, titles, authors, and URLs.', 'send-to-e-reader' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'sent_status' => array(
							'type'        => 'string',
							'enum'        => array( 'unsent', 'sent', 'any' ),
							'description' => __( 'Filter by whether posts have already been marked sent to an e-reader.', 'send-to-e-reader' ),
							'default'     => 'unsent',
						),
						'post_type'   => array(
							'type'        => 'string',
							'description' => __( 'Post type to search. Use any to search all post types.', 'send-to-e-reader' ),
							'default'     => 'any',
						),
						'search'      => array(
							'type'        => 'string',
							'description' => __( 'Optional search term for post titles and content.', 'send-to-e-reader' ),
						),
						'author_id'   => array(
							'type'        => 'integer',
							'description' => __( 'Optional author user ID.', 'send-to-e-reader' ),
						),
						'limit'       => array(
							'type'        => 'integer',
							'description' => __( 'Maximum number of posts to return, from 1 to 100.', 'send-to-e-reader' ),
							'default'     => 20,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => self::posts_output_schema(),
				'execute_callback'    => array( $this, 'list_posts' ),
				'permission_callback' => array( $this, 'can_read' ),
				'meta'                => array(
					'annotations'  => array(
						'instructions' => __( 'When presenting posts, include the id, title, author, sent status, and permalink. Use the returned ids as post_ids for send-to-e-reader/send-posts.', 'send-to-e-reader' ),
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
					),
					'show_in_rest' => true,
				),
			)
		);

		$this->register_ability(
			'send-to-e-reader/send-posts',
			array(
				'label'               => __( 'Send Posts to E-Reader', 'send-to-e-reader' ),
				'description'         => __( 'Builds an EPUB from selected WordPress posts and sends it to a configured e-reader or creates a download.', 'send-to-e-reader' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'post_ids' ),
					'properties'           => array(
						'post_ids'  => array(
							'type'        => 'array',
							'description' => __( 'Post IDs to include in the EPUB, in reading order.', 'send-to-e-reader' ),
							'items'       => array(
								'type' => 'integer',
							),
						),
						'ereader_id' => array(
							'type'        => 'string',
							'description' => __( 'E-reader ID from send-to-e-reader/list-ereaders. If omitted, the first active e-reader is used.', 'send-to-e-reader' ),
						),
						'title'      => array(
							'type'        => 'string',
							'description' => __( 'Optional EPUB title. Omit to use the post title or generated multi-post title.', 'send-to-e-reader' ),
						),
						'author'     => array(
							'type'        => 'string',
							'description' => __( 'Optional EPUB author. Omit to use post authors.', 'send-to-e-reader' ),
						),
						'mark_sent'  => array(
							'type'        => 'boolean',
							'description' => __( 'Whether to mark the posts as sent after a successful send.', 'send-to-e-reader' ),
							'default'     => true,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => self::send_output_schema(),
				'execute_callback'    => array( $this, 'send_posts' ),
				'permission_callback' => array( $this, 'can_send' ),
				'meta'                => array(
					'annotations'  => array(
						'instructions' => __( 'Use this only after the user has chosen which posts to send. If download_url is present, include it as the EPUB download link. Report the e-reader name and the post titles that were sent.', 'send-to-e-reader' ),
						'readonly'     => false,
						'destructive'  => false,
						'idempotent'   => false,
					),
					'show_in_rest' => true,
				),
			)
		);

		$this->register_ability(
			'send-to-e-reader/mark-posts-sent',
			array(
				'label'               => __( 'Mark Posts Sent to E-Reader', 'send-to-e-reader' ),
				'description'         => __( 'Marks selected WordPress posts as already sent to an e-reader without sending an EPUB.', 'send-to-e-reader' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::post_ids_input_schema(),
				'output_schema'       => self::mark_output_schema(),
				'execute_callback'    => array( $this, 'mark_posts_sent' ),
				'permission_callback' => array( $this, 'can_send' ),
				'meta'                => array(
					'annotations'  => array(
						'instructions' => __( 'Confirm which post IDs and titles are now marked as sent. This does not send an EPUB.', 'send-to-e-reader' ),
						'readonly'     => false,
						'destructive'  => false,
						'idempotent'   => true,
					),
					'show_in_rest' => true,
				),
			)
		);

		$this->register_ability(
			'send-to-e-reader/mark-posts-new',
			array(
				'label'               => __( 'Mark E-Reader Posts New', 'send-to-e-reader' ),
				'description'         => __( 'Removes the Send to E-Reader sent marker from selected WordPress posts so they are treated as new again.', 'send-to-e-reader' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::post_ids_input_schema(),
				'output_schema'       => self::mark_output_schema(),
				'execute_callback'    => array( $this, 'mark_posts_new' ),
				'permission_callback' => array( $this, 'can_send' ),
				'meta'                => array(
					'annotations'  => array(
						'instructions' => __( 'Confirm which post IDs and titles were marked as new. These posts may appear again in unsent e-reader lists.', 'send-to-e-reader' ),
						'readonly'     => false,
						'destructive'  => false,
						'idempotent'   => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Register domain terms so AI Assistant discovers these abilities.
	 *
	 * @param array $domains Existing ability domains.
	 * @return array Updated ability domains.
	 */
	public function ai_assistant_ability_domains( $domains ) {
		if ( ! is_array( $domains ) ) {
			$domains = array();
		}

		$domains[ self::CATEGORY ] = 'Send to E-Reader, e-reader, ereader, EPUB, ePub, Kindle, Pocketbook, send posts to Kindle, download EPUB, sent articles, mark as new';

		return $domains;
	}

	/**
	 * Provide result-specific instructions after ability execution.
	 *
	 * @param string $instructions Current instructions.
	 * @param string $ability_id   Ability ID.
	 * @param array  $args         Ability arguments.
	 * @param mixed  $result       Ability result.
	 * @return string Instructions for AI Assistant.
	 */
	public function ai_assistant_ability_instructions( $instructions, $ability_id, $args, $result ) {
		unset( $args, $result );

		switch ( $ability_id ) {
			case 'send-to-e-reader/list-ereaders':
				return __( 'Use active e-reader IDs for send-to-e-reader/send-posts. If there is exactly one active e-reader, you can use it when the user asks to send posts without naming a destination.', 'send-to-e-reader' );

			case 'send-to-e-reader/list-posts':
				return __( 'Present post IDs with titles and sent status. Ask which posts to send if the user has not already specified them.', 'send-to-e-reader' );

			case 'send-to-e-reader/send-posts':
				return __( 'Tell the user which posts were sent and to which e-reader. If download_url is present, include it as an EPUB download link.', 'send-to-e-reader' );

			case 'send-to-e-reader/mark-posts-sent':
			case 'send-to-e-reader/mark-posts-new':
				return __( 'Confirm the updated post titles and IDs. Keep the response concise.', 'send-to-e-reader' );
		}

		return $instructions;
	}

	/**
	 * Whether the current user may read e-reader data.
	 *
	 * @return bool
	 */
	public function can_read() {
		return current_user_can( 'edit_private_posts' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Whether the current user may send or update e-reader state.
	 *
	 * @return bool
	 */
	public function can_send() {
		return $this->can_read();
	}

	/**
	 * List configured e-readers.
	 *
	 * @param array|null $input Ability input.
	 * @return array
	 */
	public function list_ereaders( $input = null ) {
		$input            = $this->normalize_input( $input );
		$include_inactive = $this->input_bool( $input, 'include_inactive', true );
		$ereaders         = $this->plugin->get_ereaders_for_abilities();
		$result           = array();
		$active_count     = 0;

		foreach ( $ereaders as $id => $ereader ) {
			$is_active = ! empty( $ereader->active );
			if ( $is_active ) {
				++$active_count;
			}
			if ( ! $include_inactive && ! $is_active ) {
				continue;
			}

			$result[] = $this->prepare_ereader_data( $id, $ereader );
		}

		return array(
			'ereaders'     => $result,
			'count'        => count( $result ),
			'active_count' => $active_count,
		);
	}

	/**
	 * List posts with their e-reader sent status.
	 *
	 * @param array|null $input Ability input.
	 * @return array
	 */
	public function list_posts( $input = null ) {
		$input       = $this->normalize_input( $input );
		$sent_status = isset( $input['sent_status'] ) ? sanitize_key( $input['sent_status'] ) : 'unsent';
		if ( ! in_array( $sent_status, array( 'unsent', 'sent', 'any' ), true ) ) {
			$sent_status = 'unsent';
		}

		$post_type = isset( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'any';
		if ( '' === $post_type ) {
			$post_type = 'any';
		}

		$query_args = array(
			'post_type'           => $post_type,
			'post_status'         => 'any',
			'posts_per_page'      => $this->sanitize_limit( $input['limit'] ?? 20, 1, 100 ),
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
		);

		if ( ! empty( $input['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $input['search'] );
		}

		if ( ! empty( $input['author_id'] ) ) {
			$query_args['author'] = absint( $input['author_id'] );
		}

		if ( 'sent' === $sent_status ) {
			$query_args['meta_query'] = array(
				array(
					'key'     => Send_To_E_Reader::POST_META,
					'compare' => 'EXISTS',
				),
			);
		} elseif ( 'unsent' === $sent_status ) {
			$query_args['meta_query'] = array(
				array(
					'key'     => Send_To_E_Reader::POST_META,
					'compare' => 'NOT EXISTS',
				),
			);
		}

		$query = new \WP_Query( $query_args );
		$posts = array();
		foreach ( $query->get_posts() as $post ) {
			$post_data = $this->prepare_post_data( $post );
			if ( $post_data ) {
				$posts[] = $post_data;
			}
		}

		return array(
			'posts'       => $posts,
			'count'       => count( $posts ),
			'sent_status' => $sent_status,
		);
	}

	/**
	 * Send posts to an e-reader.
	 *
	 * @param array|null $input Ability input.
	 * @return array|\WP_Error
	 */
	public function send_posts( $input = null ) {
		$input = $this->normalize_input( $input );
		$posts = $this->get_posts_from_input( $input );
		if ( is_wp_error( $posts ) ) {
			return $posts;
		}

		$ereader_data = $this->get_ereader_for_send( $input['ereader_id'] ?? '' );
		if ( is_wp_error( $ereader_data ) ) {
			return $ereader_data;
		}

		$title     = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : false;
		$author    = isset( $input['author'] ) ? sanitize_text_field( $input['author'] ) : false;
		$mark_sent = $this->input_bool( $input, 'mark_sent', true );
		$result    = $this->plugin->send_posts_to_ereader(
			$ereader_data['id'],
			$posts,
			'' === $title ? false : $title,
			'' === $author ? false : $author,
			$mark_sent
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = array(
			'ereader'     => $this->prepare_ereader_data( $ereader_data['id'], $ereader_data['ereader'] ),
			'posts'       => $this->prepare_posts_data( $posts ),
			'sent_count'  => count( $posts ),
			'marked_sent' => $mark_sent,
			'result'      => $this->prepare_send_result( $result ),
		);

		if ( is_array( $result ) && ! empty( $result['url'] ) ) {
			$response['download_url'] = esc_url_raw( $result['url'] );
		}

		return $response;
	}

	/**
	 * Mark posts as sent.
	 *
	 * @param array|null $input Ability input.
	 * @return array|\WP_Error
	 */
	public function mark_posts_sent( $input = null ) {
		$input = $this->normalize_input( $input );
		$posts = $this->get_posts_from_input( $input );
		if ( is_wp_error( $posts ) ) {
			return $posts;
		}

		$this->plugin->mark_posts_sent_for_abilities( $posts );

		return array(
			'posts'        => $this->prepare_posts_data( $posts ),
			'marked_count' => count( $posts ),
			'status'       => 'sent',
		);
	}

	/**
	 * Mark posts as new.
	 *
	 * @param array|null $input Ability input.
	 * @return array|\WP_Error
	 */
	public function mark_posts_new( $input = null ) {
		$input = $this->normalize_input( $input );
		$posts = $this->get_posts_from_input( $input );
		if ( is_wp_error( $posts ) ) {
			return $posts;
		}

		$this->plugin->mark_posts_new_for_abilities( $posts );

		return array(
			'posts'        => $this->prepare_posts_data( $posts ),
			'marked_count' => count( $posts ),
			'status'       => 'new',
		);
	}

	/**
	 * Register a single ability if it is not already registered.
	 *
	 * @param string $ability_id Ability ID.
	 * @param array  $args       Ability registration arguments.
	 */
	private function register_ability( $ability_id, $args ) {
		if ( function_exists( 'wp_has_ability' ) && wp_has_ability( $ability_id ) ) {
			return;
		}

		wp_register_ability( $ability_id, $args );
	}

	/**
	 * Get posts from ability input.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	private function get_posts_from_input( array $input ) {
		if ( empty( $input['post_ids'] ) || ! is_array( $input['post_ids'] ) ) {
			return new \WP_Error( 'missing-post-ids', __( 'At least one post ID is required.', 'send-to-e-reader' ) );
		}

		$post_ids = array_values( array_unique( array_map( 'absint', $input['post_ids'] ) ) );
		$posts    = array();
		foreach ( $post_ids as $post_id ) {
			if ( ! $post_id ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post || empty( $post->ID ) ) {
				return new \WP_Error(
					'invalid-post-id',
					sprintf(
						/* translators: %d is a post ID. */
						__( 'Invalid post ID: %d', 'send-to-e-reader' ),
						$post_id
					)
				);
			}

			$posts[] = $post;
		}

		if ( empty( $posts ) ) {
			return new \WP_Error( 'missing-post-ids', __( 'At least one valid post ID is required.', 'send-to-e-reader' ) );
		}

		return $posts;
	}

	/**
	 * Get the requested e-reader or the first active e-reader.
	 *
	 * @param string $ereader_id Requested e-reader ID.
	 * @return array|\WP_Error
	 */
	private function get_ereader_for_send( $ereader_id ) {
		$ereaders   = $this->plugin->get_ereaders_for_abilities();
		$ereader_id = sanitize_text_field( (string) $ereader_id );

		if ( '' === $ereader_id ) {
			foreach ( $ereaders as $id => $ereader ) {
				if ( ! empty( $ereader->active ) ) {
					return array(
						'id'      => $id,
						'ereader' => $ereader,
					);
				}
			}

			return new \WP_Error( 'no-active-ereader', __( 'No active e-reader is configured.', 'send-to-e-reader' ) );
		}

		if ( empty( $ereaders[ $ereader_id ] ) ) {
			return new \WP_Error( 'invalid-ereader', __( 'The requested e-reader is not configured.', 'send-to-e-reader' ) );
		}

		if ( empty( $ereaders[ $ereader_id ]->active ) ) {
			return new \WP_Error( 'inactive-ereader', __( 'The requested e-reader is inactive.', 'send-to-e-reader' ) );
		}

		return array(
			'id'      => $ereader_id,
			'ereader' => $ereaders[ $ereader_id ],
		);
	}

	/**
	 * Prepare multiple post records.
	 *
	 * @param array $posts Posts.
	 * @return array
	 */
	private function prepare_posts_data( array $posts ) {
		$result = array();
		foreach ( $posts as $post ) {
			$post_data = $this->prepare_post_data( $post );
			if ( $post_data ) {
				$result[] = $post_data;
			}
		}

		return $result;
	}

	/**
	 * Prepare a post record for ability output.
	 *
	 * @param \WP_Post|int $post Post object or ID.
	 * @return array|null
	 */
	private function prepare_post_data( $post ) {
		$post = get_post( $post );
		if ( ! $post || empty( $post->ID ) ) {
			return null;
		}

		$sent_at = get_post_meta( $post->ID, Send_To_E_Reader::POST_META, true );
		$title   = get_the_title( $post );
		if ( '' === $title && ! empty( $post->post_title ) ) {
			$title = $post->post_title;
		}

		return array(
			'id'        => (int) $post->ID,
			'title'     => html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES, 'UTF-8' ),
			'post_type' => isset( $post->post_type ) ? $post->post_type : '',
			'status'    => isset( $post->post_status ) ? $post->post_status : '',
			'author'    => $this->plugin->get_post_author_name( $post ),
			'permalink' => get_permalink( $post ),
			'edit_url'  => get_edit_post_link( $post->ID, '' ),
			'sent'      => ! empty( $sent_at ),
			'sent_at'   => ! empty( $sent_at ) ? date_i18n( 'c', (int) $sent_at ) : '',
		);
	}

	/**
	 * Prepare e-reader data for ability output.
	 *
	 * @param string   $id      E-reader ID.
	 * @param E_Reader $ereader E-reader object.
	 * @return array
	 */
	private function prepare_ereader_data( $id, $ereader ) {
		$class = get_class( $ereader );
		$type  = defined( $class . '::NAME' ) ? constant( $class . '::NAME' ) : $class;

		if ( $ereader instanceof E_Reader_Download ) {
			$delivery = 'download';
		} elseif ( $ereader instanceof E_Reader_Generic_Email ) {
			$delivery = 'email';
		} else {
			$delivery = 'other';
		}

		return array(
			'id'                   => (string) $id,
			'name'                 => method_exists( $ereader, 'get_name' ) ? $ereader->get_name() : $type,
			'type'                 => $type,
			'class'                => $class,
			'delivery'             => $delivery,
			'active'               => ! empty( $ereader->active ),
			'returns_download_url' => 'download' === $delivery,
		);
	}

	/**
	 * Prepare send result without exposing local temporary paths.
	 *
	 * @param mixed $result E-reader send result.
	 * @return array
	 */
	private function prepare_send_result( $result ) {
		if ( true === $result ) {
			return array( 'success' => true );
		}

		if ( is_string( $result ) ) {
			return array(
				'success' => true,
				'message' => $result,
			);
		}

		if ( ! is_array( $result ) ) {
			return array( 'success' => ! empty( $result ) );
		}

		$prepared = array( 'success' => true );
		foreach ( array( 'send-to-e-reader', 'title', 'author', 'url', 'type', 'name' ) as $key ) {
			if ( isset( $result[ $key ] ) && is_scalar( $result[ $key ] ) ) {
				$prepared[ $key ] = (string) $result[ $key ];
			}
		}

		return $prepared;
	}

	/**
	 * Normalize ability input.
	 *
	 * @param mixed $input Ability input.
	 * @return array
	 */
	private function normalize_input( $input ) {
		return is_array( $input ) ? $input : array();
	}

	/**
	 * Read a boolean input value.
	 *
	 * @param array  $input   Ability input.
	 * @param string $key     Input key.
	 * @param bool   $default Default value.
	 * @return bool
	 */
	private function input_bool( array $input, $key, $default = false ) {
		if ( ! array_key_exists( $key, $input ) ) {
			return $default;
		}

		return filter_var( $input[ $key ], FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Clamp an integer input.
	 *
	 * @param mixed $value Input value.
	 * @param int   $min   Minimum.
	 * @param int   $max   Maximum.
	 * @return int
	 */
	private function sanitize_limit( $value, $min, $max ) {
		return max( $min, min( $max, absint( $value ) ) );
	}

	/**
	 * Input schema for abilities that accept post IDs.
	 *
	 * @return array
	 */
	private static function post_ids_input_schema() {
		return array(
			'type'                 => 'object',
			'required'             => array( 'post_ids' ),
			'properties'           => array(
				'post_ids' => array(
					'type'        => 'array',
					'description' => __( 'Post IDs to update.', 'send-to-e-reader' ),
					'items'       => array(
						'type' => 'integer',
					),
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * Output schema for e-reader listings.
	 *
	 * @return array
	 */
	private static function ereaders_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'ereaders'     => array(
					'type'  => 'array',
					'items' => self::ereader_schema(),
				),
				'count'        => array( 'type' => 'integer' ),
				'active_count' => array( 'type' => 'integer' ),
			),
		);
	}

	/**
	 * Output schema for post listings.
	 *
	 * @return array
	 */
	private static function posts_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'posts'       => array(
					'type'  => 'array',
					'items' => self::post_schema(),
				),
				'count'       => array( 'type' => 'integer' ),
				'sent_status' => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Output schema for sending posts.
	 *
	 * @return array
	 */
	private static function send_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'ereader'      => self::ereader_schema(),
				'posts'        => array(
					'type'  => 'array',
					'items' => self::post_schema(),
				),
				'sent_count'   => array( 'type' => 'integer' ),
				'marked_sent'  => array( 'type' => 'boolean' ),
				'download_url' => array( 'type' => 'string' ),
				'result'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),
			),
		);
	}

	/**
	 * Output schema for sent marker changes.
	 *
	 * @return array
	 */
	private static function mark_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'posts'        => array(
					'type'  => 'array',
					'items' => self::post_schema(),
				),
				'marked_count' => array( 'type' => 'integer' ),
				'status'       => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Schema for one e-reader.
	 *
	 * @return array
	 */
	private static function ereader_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'                   => array( 'type' => 'string' ),
				'name'                 => array( 'type' => 'string' ),
				'type'                 => array( 'type' => 'string' ),
				'class'                => array( 'type' => 'string' ),
				'delivery'             => array( 'type' => 'string' ),
				'active'               => array( 'type' => 'boolean' ),
				'returns_download_url' => array( 'type' => 'boolean' ),
			),
		);
	}

	/**
	 * Schema for one post.
	 *
	 * @return array
	 */
	private static function post_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'        => array( 'type' => 'integer' ),
				'title'     => array( 'type' => 'string' ),
				'post_type' => array( 'type' => 'string' ),
				'status'    => array( 'type' => 'string' ),
				'author'    => array( 'type' => 'string' ),
				'permalink' => array( 'type' => 'string' ),
				'edit_url'  => array( 'type' => 'string' ),
				'sent'      => array( 'type' => 'boolean' ),
				'sent_at'   => array( 'type' => 'string' ),
			),
		);
	}
}
