<?php
/**
 * Send To E-Reader
 *
 * This contains the Send to E-Reader functions.
 *
 * @package Send_To_E_Reader
 */

namespace Send_To_E_Reader;

/**
 * This is the class for the sending posts to an E-Reader.
 *
 * @since 0.3
 *
 * @package Send_To_E_Reader
 * @author Alex Kirk
 */
class Send_To_E_Reader {
	/**
	 * Contains a reference to the Friends class (if available).
	 *
	 * @var \Friends\Friends|null
	 */
	private $friends;

	/**
	 * Article Notes instance.
	 *
	 * @var Article_Notes
	 */
	private $article_notes;

	const POST_META = 'sent-to-ereader';
	const EREADERS_OPTION = 'send-to-e-reader_readers';
	const DOWNLOAD_PASSWORD_OPTION = 'send_to_e_reader_download_password';
	const CRON_OPTION = 'send-to-e-reader_cron';

	const USER_OPTION = 'send_to_e_reader';

	const OLD_POST_META = 'friends-sent-to-ereader';
	const OLD_EREADERS_OPTION = 'friends-send-to-e-reader_readers';
	const OLD_DOWNLOAD_PASSWORD_OPTION = 'friends_send_to_e_reader_download_password';
	const OLD_CRON_OPTION = 'friends-send-to-e-reader_cron';
	const OLD_USER_OPTION = 'friends_send_to_e_reader';

	private $ereaders = null;
	private $ereader_classes = array();

	private $download_request = false;

	/**
	 * Whether the Friends plugin is available.
	 *
	 * @return bool
	 */
	public function friends_is_available() {
		return class_exists( '\Friends\Friends' );
	}

	/**
	 * Get the template loader (Friends or fallback).
	 *
	 * @return object
	 */
	public function get_template_loader() {
		if ( $this->friends_is_available() ) {
			return \Friends\Friends::template_loader();
		}
		return $this->get_fallback_template_loader();
	}

	/**
	 * Get a simple fallback template loader for standalone mode.
	 *
	 * @return object
	 */
	private function get_fallback_template_loader() {
		static $loader = null;
		if ( null === $loader ) {
			$loader = new class {
				private $paths = array();

				public function __construct() {
					$this->paths[] = SEND_TO_E_READER_PLUGIN_DIR . 'templates/';
				}

				public function get_template_part( $slug, $name = null, $args = array(), $echo = true ) {
					$template = $this->locate_template( $slug, $name );
					if ( ! $template ) {
						return '';
					}

					if ( ! $echo ) {
						return $template;
					}

					if ( ! empty( $args ) && is_array( $args ) ) {
						extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
					}
					include $template;
				}

				private function locate_template( $slug, $name = null ) {
					$templates = array();
					if ( $name ) {
						$templates[] = "{$slug}-{$name}.php";
					}
					$templates[] = "{$slug}.php";

					foreach ( $templates as $template ) {
						foreach ( $this->paths as $path ) {
							if ( file_exists( $path . $template ) ) {
								return $path . $template;
							}
						}
					}
					return '';
				}
			};
		}
		return $loader;
	}

	/**
	 * Constructor
	 *
	 * @param \Friends\Friends|null $friends A reference to the Friends object, or null for standalone mode.
	 */
	public function __construct( $friends = null ) {
		$this->friends = $friends;
		$this->maybe_migrate_options();
		$this->register_hooks();
		$this->article_notes = new Article_Notes( $this );
	}

	/**
	 * Get the Article Notes instance.
	 *
	 * @return Article_Notes
	 */
	public function get_article_notes() {
		return $this->article_notes;
	}

	/**
	 * Migrate options from old "friends-" prefixed names to new names.
	 */
	private function maybe_migrate_options() {
		$old_ereaders = get_option( self::OLD_EREADERS_OPTION );
		if ( false !== $old_ereaders && false === get_option( self::EREADERS_OPTION ) ) {
			update_option( self::EREADERS_OPTION, $old_ereaders );
			delete_option( self::OLD_EREADERS_OPTION );
		}

		$old_password = get_option( self::OLD_DOWNLOAD_PASSWORD_OPTION );
		if ( false !== $old_password && false === get_option( self::DOWNLOAD_PASSWORD_OPTION ) ) {
			update_option( self::DOWNLOAD_PASSWORD_OPTION, $old_password );
			delete_option( self::OLD_DOWNLOAD_PASSWORD_OPTION );
		}

		$old_cron = get_option( self::OLD_CRON_OPTION );
		if ( false !== $old_cron && false === get_option( self::CRON_OPTION ) ) {
			update_option( self::CRON_OPTION, $old_cron );
			delete_option( self::OLD_CRON_OPTION );
		}

		// Migrate post meta from old key to new key.
		if ( ! get_option( 'send_to_e_reader_post_meta_migrated' ) ) {
			global $wpdb;
			$wpdb->update(
				$wpdb->postmeta,
				array( 'meta_key' => self::POST_META ),
				array( 'meta_key' => self::OLD_POST_META )
			);
			update_option( 'send_to_e_reader_post_meta_migrated', true );
		}
	}

	/**
	 * Get user option with migration from old option name.
	 *
	 * @param int $user_id The user ID.
	 * @return mixed The option value.
	 */
	private function get_user_ereader_option( $user_id ) {
		$value = get_user_option( self::USER_OPTION, $user_id );
		if ( false === $value ) {
			$old_value = get_user_option( self::OLD_USER_OPTION, $user_id );
			if ( false !== $old_value ) {
				update_user_option( $user_id, self::USER_OPTION, $old_value );
				delete_user_option( $user_id, self::OLD_USER_OPTION );
				return $old_value;
			}
		}
		return $value;
	}

	/**
	 * Get the author name for a post, with Friends fallback.
	 *
	 * @param \WP_Post $post The post.
	 * @return string The author display name.
	 */
	public function get_post_author_name( \WP_Post $post ) {
		if ( $this->friends_is_available() && class_exists( '\Friends\User' ) ) {
			$author = \Friends\User::get_post_author( $post );
			return $author->display_name;
		}
		return get_the_author_meta( 'display_name', $post->post_author );
	}

	/**
	 * Register the WordPress hooks
	 */
	private function register_hooks() {
		// Core hooks that work in standalone mode.
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 50 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 40 );
		add_action( 'wp_ajax_send-post-to-e-reader', array( $this, 'ajax_send' ) );
		add_action( 'wp_ajax_unmark-e-reader-send', array( $this, 'ajax_unmark' ) );
		add_filter( 'template_include', array( $this, 'download_via_url' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// Friends-specific hooks - only register when Friends is available.
		if ( $this->friends_is_available() ) {
			add_filter( 'notify_new_friend_post', array( $this, 'post_notification' ), 10 );
			add_action( 'friends_edit_friend_notifications_table_end', array( $this, 'edit_friend_notifications' ), 10 );
			add_action( 'users_edit_post_collection_table_end', array( $this, 'users_edit_post_collection' ), 10 );
			add_action( 'friends_edit_friend_notifications_after_form_submit', array( $this, 'edit_friend_notifications_submit' ), 10 );
			add_action( 'friends_notification_manager_header', array( $this, 'notification_manager_header' ) );
			add_action( 'friends_notification_manager_row', array( $this, 'notification_manager_row' ) );
			add_action( 'friends_notification_manager_after_form_submit', array( $this, 'notification_manager_after_form_submit' ) );
			add_action( 'friends_entry_dropdown_menu', array( $this, 'entry_dropdown_menu' ) );
			add_action( 'friends_template_paths', array( $this, 'friends_template_paths' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
			add_action( 'wp_footer', array( $this, 'print_dialog' ) );
			add_action( 'friends_author_header', array( $this, 'friends_author_header' ), 10, 2 );
			add_filter( 'friends_friend_posts_query_viewable', array( $this, 'enable_download_via_url' ) );
		}
	}
	public function admin_init() {
		foreach ( get_post_types( array( 'show_ui' => true ) ) as $_post_type ) {
			add_filter( 'bulk_actions-edit-' . $_post_type, array( $this, 'bulk_actions' ) );
			add_filter( 'handle_bulk_actions-edit-' . $_post_type, array( $this, 'handle_bulk_actions' ), 10, 3 );
			add_filter( $_post_type . '_row_actions', array( $this, 'post_row_actions' ), 10, 2 );
		}
		add_filter( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	public function register_ereader( $ereader_class ) {
		$this->ereader_classes[ $ereader_class ] = $ereader_class;
	}

	protected function get_active_ereaders() {
		return array_filter(
			$this->get_ereaders(),
			function ( $ereader ) {
				return $ereader->active;
			}
		);
	}

	protected function get_active_email_ereaders() {
		return array_filter(
			$this->get_active_ereaders(),
			function ( $ereader ) {
				return $ereader instanceof E_Reader_Generic_Email;
			}
		);
	}

	protected function get_ereaders() {
		if ( is_null( $this->ereaders ) ) {
			$this->ereaders = array();
			foreach ( get_option( self::EREADERS_OPTION, array() ) as $id => $ereader ) {
				if ( is_object( $ereader ) && get_class( $ereader ) === '__PHP_Incomplete_Class' ) {
					// We need to update these to new class names.
					$this->ereaders = null;
					$alloptions = wp_load_alloptions();
					if ( isset( $alloptions[ self::EREADERS_OPTION ] ) ) {
						$alloptions[ self::EREADERS_OPTION ] = str_replace( 'Friends_', 'Friends\\', $alloptions[ self::EREADERS_OPTION ] );
						$this->update_ereaders( unserialize( $alloptions[ self::EREADERS_OPTION ] ) );
						return $this->get_ereaders();
					}
				}
				if ( is_array( $ereader ) ) {
					if ( false !== strpos( $ereader['email'], '+mobi' ) ) {
						$ereader = new E_Reader_Kindle( $ereader['name'], $ereader['email'] );
					} elseif ( '@pbsync.com' === substr( $ereader['email'], -11 ) ) {
						$ereader = new E_Reader_Pocketbook( $ereader['name'], $ereader['email'] );
					} else { // '@kindle.com' === substr( $ereader['email'], -11 ) || '@free.kindle.com' === substr( $ereader['email'], -16 )
						$ereader = new E_Reader_Generic_Email( $ereader['name'], $ereader['email'] );
					}
					$id = $ereader->get_id();
				}

				if ( $id ) {
					$this->ereaders[ $id ] = $ereader;
				}
			}
		}
		return $this->ereaders;
	}

	protected function update_ereaders( $ereaders ) {
		$this->ereaders = $ereaders;
		return update_option( self::EREADERS_OPTION, $ereaders );
	}

	protected function update_ereader( $id, $ereader ) {
		if ( ! isset( $this->ereaders[ $id ] ) ) {
			return false;
		}
		$this->ereaders[ $id ] = $ereader;
		return $this->update_ereaders( $this->ereaders );
	}

	protected function get_ereader( $id ) {
		$ereaders = $this->get_ereaders();
		return $ereaders[ $id ];
	}

	public function wp_enqueue_scripts() {
		if ( is_user_logged_in() && \Friends\Friends::on_frontend() ) {
			$handle = 'send-to-e-reader';
			$file = 'send-to-e-reader.js';
			$version = SEND_TO_E_READER_VERSION;
			wp_enqueue_script( $handle, plugins_url( $file, __DIR__ ), array( 'friends' ), apply_filters( 'friends_debug_enqueue', $version, $handle, dirname( __DIR__ ) . '/' . $file ) );
			wp_localize_script(
				$handle,
				'send_to_ereader',
				array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'send-post-to-e-reader' ),
					'ereader' => __( 'E-Reader', 'send-to-e-reader' ),
				)
			);
		}
	}

	public function print_dialog() {
		if ( is_user_logged_in() && \Friends\Friends::on_frontend() ) {
			$friend_name = __( 'Friend Post', 'send-to-e-reader' );
			if ( $this->friends && $this->friends->frontend->author ) {
				$friend_name = $this->friends->frontend->author->display_name;
			}
			$this->get_template_loader()->get_template_part(
				'frontend/ereader/dialog',
				null,
				array(
					'friend_name' => $friend_name,
				)
			);
		}
	}

	public function admin_enqueue_scripts() {
		if ( ! $this->friends_is_available() ) {
			return;
		}
		$handle = 'send-to-e-reader';
		$file = 'send-to-e-reader.js';
		$version = SEND_TO_E_READER_VERSION;
		wp_enqueue_script( $handle, plugins_url( $file, __DIR__ ), array( 'friends-admin' ), apply_filters( 'friends_debug_enqueue', $version, $handle, dirname( __DIR__ ) . '/' . $file ) );
	}

	public function admin_menu() {
		// Only show the menu if installed standalone.
		$friends_settings_exist = '' !== menu_page_url( 'friends', false );
		if ( $friends_settings_exist ) {
			add_submenu_page(
				'friends',
				__( 'E-Readers', 'send-to-e-reader' ),
				__( 'E-Readers', 'send-to-e-reader' ),
				'edit_private_posts',
				'send-to-e-reader',
				array( $this, 'configure_ereaders' )
			);
			add_submenu_page(
				'friends',
				__( 'E-Reader Settings', 'send-to-e-reader' ),
				__( 'E-Reader Settings', 'send-to-e-reader' ),
				'edit_private_posts',
				'send-to-e-reader-settings',
				array( $this, 'settings' )
			);
		} else {
			add_submenu_page(
				'tools.php',
				__( 'E-Readers', 'send-to-e-reader' ),
				__( 'E-Readers', 'send-to-e-reader' ),
				'edit_private_posts',
				'send-to-e-reader',
				array( $this, 'configure_ereaders_with_friends_about' )
			);
			add_submenu_page(
				'options-general.php',
				__( 'Send to E-Reader', 'send-to-e-reader' ),
				__( 'Send to E-Reader', 'send-to-e-reader' ),
				'edit_private_posts',
				'send-to-e-reader-settings',
				array( $this, 'settings' )
			);
		}
	}

	public function notification_manager_header() {
		$ereaders = $this->get_ereaders();
		if ( empty( $ereaders ) ) {
			return;
		}
		?>
			<th class="column-send-to-e-reader"><?php esc_html_e( 'Send to E-Reader', 'send-to-e-reader' ); ?></th>
		<?php
	}

	public function notification_manager_row( $friend ) {
		$ereaders = $this->get_ereaders();
		if ( empty( $ereaders ) ) {
			return;
		}
		$selected = $this->get_user_ereader_option( $friend->ID );
		?>
		<td class="column-send-to-e-reader">
			<select name="send-to-e-reader[<?php echo esc_attr( $friend->ID ); ?>]">
				<option value="none">-</option>
				<?php foreach ( $ereaders as $id => $ereader ) : ?>
					<option value="<?php echo esc_attr( $id ); ?>"<?php selected( $selected, $id ); ?>><?php echo esc_html( $ereader->get_name() ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<?php
	}

	public function notification_manager_after_form_submit( $friend_ids ) {
		$ereaders = $this->get_ereaders();
		if ( empty( $ereaders ) ) {
			return;
		}

		foreach ( $friend_ids as $friend_id ) {
			if ( ! isset( $_POST['send-to-e-reader'][ $friend_id ] ) ) {
				continue;
			}

			$ereader_notification = $_POST['send-to-e-reader'][ $friend_id ];
			if ( $this->get_user_ereader_option( $friend_id ) !== $ereader_notification ) {
				update_user_option( $friend_id, self::USER_OPTION, $ereader_notification );
			}
		}
	}

	public function friends_template_paths( $paths ) {
		$c = 50;
		$my_path = SEND_TO_E_READER_PLUGIN_DIR . 'templates/';
		while ( isset( $paths[ $c ] ) && $my_path !== $paths[ $c ] ) {
			$c += 1;
		}
		$paths[ $c ] = $my_path;
		return $paths;
	}

	public function get_query_vars() {
		global $wp_query;
		$query_vars = array_filter( $wp_query->query_vars );
		if ( empty( $query_vars['post_type'] ) ) {
			$query_vars['post_type'] = 'any';
		}
		return $query_vars;
	}

	public function get_unsent_posts( $query_vars = array() ) {
		if ( empty( $query_vars ) ) {
			global $wp_query;
			$query_vars = $wp_query->query_vars;
		}

		// Prevent super cache from caching this page.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		$query = new \WP_Query(
			array_merge(
				$query_vars,
				array(
					'nopaging'     => true,
					'meta_key'     => self::POST_META,
					'meta_compare' => 'NOT EXISTS',
				)
			)
		);

		return $query->get_posts();
	}

	public function entry_dropdown_menu() {
		$divider = '<li class="divider ereader" data-content="' . esc_attr__( 'E-Reader', 'send-to-e-reader' ) . '"></li>';
		$already_sent = get_post_meta( get_the_ID(), self::POST_META, true );
		if ( $already_sent ) {
			$divider = '<li class="divider ereader" data-content="' . esc_attr(
				sprintf(
					// translators: %s is a date.
					__( 'E-Reader: Sent on %s', 'send-to-e-reader' ),
					date_i18n( __( 'M j' ), $already_sent ) // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				)
			) . '"></li>';
		}
		$ereaders = $this->get_active_ereaders();
		foreach ( $ereaders as $id => $ereader ) {
			echo wp_kses(
				$divider,
				array(
					'li' => array(
						'class'        => array(),
						'data-content' => array(),
					),
				)
			);
			$divider = '';
			?>
			<li class="menu-item"><a href="#" data-id="<?php echo esc_attr( get_the_ID() ); ?>" data-ereader="<?php echo esc_attr( $id ); ?>" class="friends-send-post-to-e-reader has-icon-right">
				<?php
				if ( $ereader instanceof E_Reader_Download ) {
					echo esc_html( $ereader->get_name() );
				} else {
					echo esc_html(
						sprintf(
							// translators: %s is an E-Reader name.
							_x( 'Send to %s', 'e-reader', 'send-to-e-reader' ),
							$ereader->get_name()
						)
					);
				}
				?>
				<i class="form-icon"></i></a></li>
			<?php
		}
		?>
		<li class="menu-item">
			<label class="form-switch">
				<input type="checkbox" name="multi-entry"><i class="form-icon off"></i> <?php esc_html_e( 'Include all posts above', 'send-to-e-reader' ); ?>
			</label>
		</li>
		<?php
		if ( $already_sent ) {
			?>
			<li class="menu-item"><a href="#" data-id="<?php echo esc_attr( get_the_ID() ); ?>" class="friends-unmark-e-reader-send has-icon-right"><?php esc_html_e( 'Mark as new', 'send-to-e-reader' ); ?>
				<i class="form-icon"></i></a></li>
			<?php
		}
	}

	function ajax_unmark() {
		check_ajax_referer( 'send-post-to-e-reader' );
		delete_post_meta( $_POST['id'], self::POST_META );
		wp_send_json_success();
	}

	function ajax_send() {
		check_ajax_referer( 'send-post-to-e-reader' );

		$ereaders = $this->get_ereaders();
		if ( ! isset( $ereaders[ $_POST['ereader'] ] ) ) {
			wp_send_json_error( __( 'E-Reader not configured', 'send-to-e-reader' ) );
			exit;
		}
		$posts = array();
		if ( ! empty( $_POST['unsent'] ) && ! empty( $_POST['query_vars'] ) && ! empty( $_POST['qv_sign'] ) ) {
			$query_vars = wp_unslash( $_POST['query_vars'] );
			if ( sha1( wp_salt( 'nonce' ) . $query_vars ) !== $_POST['qv_sign'] ) {
				wp_send_json_error();
				exit;
			}
			$query_vars = unserialize( $query_vars );

			$posts = array_merge( $posts, $this->get_unsent_posts( $query_vars ) );
		}

		if ( ! empty( $_POST['ids'] ) ) {
			$posts = array_merge( $posts, array_map( 'get_post', (array) $_POST['ids'] ) );
		}

		if ( empty( $posts ) ) {
			wp_send_json_error( __( 'No posts could be found.', 'send-to-e-reader' ) );
			exit;
		}

		$ereader = $ereaders[ $_POST['ereader'] ];
		$result = $ereader->send_posts(
			$posts,
			empty( $_POST['title'] ) ? false : sanitize_text_field( wp_unslash( $_POST['title'] ) ),
			empty( $_POST['author'] ) ? false : sanitize_text_field( wp_unslash( $_POST['author'] ) )
		);

		if ( ! $result || is_wp_error( $result ) ) {
			wp_send_json_error( $result );
			exit;
		}

		foreach ( $posts as $post ) {
			update_post_meta( $post->ID, self::POST_META, time() );
		}

		if ( $result instanceof E_Reader ) {
			$this->update_ereader( $_POST['ereader'], $result );
		}
		wp_send_json_success( $result );
	}

	/**
	 * Display the E-Reader Settings header
	 *
	 * @param      string $active  The active page.
	 */
	private function settings_header( $active ) {
		$this->get_template_loader()->get_template_part(
			'admin/settings-header',
			null,
			array(
				'active' => $active,
				'title'  => __( 'Send to E-Reader', 'send-to-e-reader' ),
				'menu'   => array(
					__( 'E-Readers', 'send-to-e-reader' ) => 'send-to-e-reader',
					__( 'Settings' )             => 'send-to-e-reader-settings', // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				),
			)
		);
	}

	/**
	 * Display the configure e-readers page for the plugin.
	 */
	public function settings() {
		$nonce_value = 'send-to-e-reader';

		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], $nonce_value ) ) {
			update_option( self::DOWNLOAD_PASSWORD_OPTION, sanitize_text_field( wp_unslash( $_POST['download_password'] ) ) );
		}

		$this->settings_header( 'send-to-e-reader-settings' );

		$all_friends = array();
		if ( $this->friends_is_available() && class_exists( '\Friends\User_Query' ) ) {
			$all_friends = \Friends\User_Query::all_associated_users();
		}

		$this->get_template_loader()->get_template_part(
			'admin/ereader-settings',
			null,
			array(
				'nonce_value'       => $nonce_value,
				'download_password' => get_option( self::DOWNLOAD_PASSWORD_OPTION, hash( 'crc32', wp_salt( 'nonce' ), false ) ),
				'all-friends'       => $all_friends,
			)
		);

		$this->get_template_loader()->get_template_part( 'admin/settings-footer' );
	}

	/**
	 * Display the configure e-readers page for the plugin.
	 *
	 * @param      bool $display_about_friends  The display about friends section.
	 */
	public function configure_ereaders( $display_about_friends = false ) {
		$ereaders = $this->get_ereaders();

		$friends = $this->friends_is_available() ? \Friends\Friends::get_instance() : null;
		$nonce_value = 'send-to-e-reader';
		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], $nonce_value ) ) {
			$delete_ereaders = $ereaders;
			foreach ( $_POST['ereaders'] as $id => $ereader_data ) {
				if ( ! isset( $ereader_data['class'] ) ) {
					continue;
				}

				$class = wp_unslash( $ereader_data['class'] );
				if ( ! $class || ! class_exists( $class ) || ! is_subclass_of( $class, 'Send_To_E_Reader\E_Reader' ) ) {
					continue;
				}

				if ( 'new' === $id && isset( $_POST['ereaders'][ 'new' . $class ] ) ) {
					$ereader_data = array_merge( $ereader_data, $_POST['ereaders'][ 'new' . $class ] );
				}

				$ereader = $class::instantiate_from_field_data( $id, $ereader_data );
				$id = $ereader->get_id();
				if ( ! $id ) {
					continue;
				}

				if ( isset( $ereaders[ $id ] ) ) {
					unset( $delete_ereaders[ $id ] );
				}
				$ereader->active = isset( $ereader_data['active'] ) && $ereader_data['active'];
				$ereaders[ $id ] = $ereader;
			}
			foreach ( $delete_ereaders as $id => $ereader ) {
				unset( $ereaders[ $id ] );
			}
			uasort(
				$ereaders,
				function ( $a, $b ) {
					return strcmp( $a->get_name(), $b->get_name() );
				}
			);

			$this->update_ereaders( $ereaders );
		}

		$this->settings_header( 'send-to-e-reader' );

		$this->get_template_loader()->get_template_part(
			'admin/configure-ereaders',
			null,
			array(
				'ereaders'              => $ereaders,
				'nonce_value'           => $nonce_value,
				'friends'               => $friends,
				'display_about_friends' => $display_about_friends,
				'ereader_classes'       => $this->ereader_classes,
			)
		);

		$this->get_template_loader()->get_template_part( 'admin/settings-footer' );
	}

	/**
	 * Display an about page for the plugin with the friends section.
	 */
	public function configure_ereaders_with_friends_about() {
		return $this->configure_ereaders( true );
	}

	/**
	 * Display an input field to enter the e-reader e-mail address.
	 *
	 * @param      \Friends\User $friend  The friend.
	 */
	function users_edit_post_collection( \Friends\User $friend ) {
		$this->get_template_loader()->get_template_part(
			'admin/automatic-sending',
			null,
			array(
				'ereaders' => $this->get_active_email_ereaders(),
			)
		);
	}

	/**
	 * Display an input field to enter the e-reader e-mail address.
	 *
	 * @param      \Friends\User $friend  The friend.
	 */
	function edit_friend_notifications( \Friends\User $friend ) {
		$this->get_template_loader()->get_template_part(
			'admin/edit-notifications-ereader',
			null,
			array(
				'ereaders' => $this->get_active_email_ereaders(),
				'selected' => $this->get_user_ereader_option( $friend->ID ),
			)
		);
		$this->get_template_loader()->get_template_part(
			'admin/automatic-sending',
			null,
			array(
				'ereaders' => $this->get_active_email_ereaders(),
			)
		);
	}

	/**
	 * Save the e-reader e-mail address to a friend.
	 *
	 * @param      \Friends\User $friend  The friend.
	 */
	function edit_friend_notifications_submit( \Friends\User $friend ) {
		$ereaders = get_option( self::EREADERS_OPTION, array() );
		if ( isset( $_POST['send-to-e-reader'] ) && isset( $ereaders[ $_POST['send-to-e-reader'] ] ) ) {
			update_user_option( $friend->ID, self::USER_OPTION, $_POST['send-to-e-reader'] );
		} else {
			delete_user_option( $friend->ID, self::USER_OPTION );
		}
	}

	/**
	 * Send a post to the E-Reader if enabled for the friend.
	 *
	 * @param      \WP_Post $post   The post.
	 */
	function post_notification( \WP_Post $post ) {
		if ( 'trash' === $post->post_status ) {
			return;
		}

		$ereaders = get_option( self::EREADERS_OPTION, array() );
		$id = $this->get_user_ereader_option( $post->post_author );
		if ( false !== $id && isset( $ereaders[ $id ] ) ) {
			$ereaders[ $id ]->send_posts( array( $post ), $ereaders[ $id ]['email'] );
		}
	}

	public function friends_author_header( \Friends\User $friend_user, $args ) {
		$this->get_template_loader()->get_template_part(
			'frontend/ereader/author-header',
			null,
			array_merge(
				array(
					'ereaders'     => $this->get_active_ereaders(),
					'unsent_posts' => $this->get_unsent_posts(),
					'friend'       => $friend_user,
				),
				$args
			)
		);
	}


	public function enable_download_via_url( $viewable ) {
		$ereader_url_var = 'epub' . get_option( self::DOWNLOAD_PASSWORD_OPTION, hash( 'crc32', wp_salt( 'nonce' ), false ) );
		if ( ! isset( $_GET[ $ereader_url_var ] ) ) {
			return $viewable;
		}
		if (
			! is_array( $_GET[ $ereader_url_var ] )
			&& ! in_array(
				$_GET[ $ereader_url_var ],
				array(
					'new',
					'all',
					'last',
					'list',
				)
			)
		) {
			return $viewable;
		}

		$this->download_request = $_GET[ $ereader_url_var ];
		return true;
	}

	public function download_via_url( $template ) {
		if ( ! $this->enable_download_via_url( false ) ) {
			return $template;
		}

		if ( 'list' === $this->download_request ) {
			$unsent = array();
			foreach ( $this->get_unsent_posts() as $post ) {
				if ( in_array( get_post_format( $post ), array( 'video' ), true ) ) {
					continue;
				}
				$unsent[ $post->ID ] = $post;
			}

			$query = new \WP_Query(
				array_merge(
					$this->get_query_vars(),
					array(
						'posts_per_page' => 50,
					)
				)
			);
			$posts = array();
			foreach ( $query->get_posts() as $post ) {
				if ( in_array( get_post_format( $post ), array( 'video' ), true ) ) {
					continue;
				}
				$posts[ $post->ID ] = $post;
			}

			if ( empty( $posts ) ) {
				status_header( 404 );
				echo 'No posts found.';
				exit;
			}

			$this->get_template_loader()->get_template_part(
				'plain-list',
				null,
				array(
					'title'     => 'Friends ePub',
					'unsent'    => $unsent,
					'posts'     => $posts,
					'inputname' => 'epub' . get_option( self::DOWNLOAD_PASSWORD_OPTION, hash( 'crc32', wp_salt( 'nonce' ), false ) ),
				)
			);
			exit;
		}

		$ereader = new E_Reader_Download( $this->download_request );
		if ( is_array( $this->download_request ) ) {
			$query = new \WP_Query(
				array_merge(
					$this->get_query_vars(),
					array(
						'post__in' => $this->download_request,
					)
				)
			);
			$list = $this->download_request;
			$posts = $query->get_posts();
			usort(
				$posts,
				function ( $a, $b ) use ( $list ) {
					return array_search( $a->ID, $list ) - array_search( $b->ID, $list );
				}
			);
		} elseif ( 'new' === $this->download_request ) {
			$posts = $this->get_unsent_posts();
		} elseif ( 'all' === $this->download_request ) {
			$query = new \WP_Query(
				array_merge(
					$this->get_query_vars(),
					array(
						'nopaging' => true,
					)
				)
			);
			$posts = $query->get_posts();
		} elseif ( 'last' === $this->download_request ) {
			$query = new \WP_Query(
				array_merge(
					$this->get_query_vars(),
					array(
						'posts_per_page' => 10,
					)
				)
			);
			$posts = $query->get_posts();
		}

		if ( empty( $posts ) ) {
			status_header( 404 );
			echo 'no posts found';
			exit;
		}

		$title = date_i18n( __( 'F j, Y' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain

		$author = __( 'Friend Post', 'send-to-e-reader' );
		if ( $this->friends && $this->friends->frontend->author ) {
			$author = $this->friends->frontend->author->display_name;
		}

		if ( 1 === count( $posts ) ) {
			$title = $posts[0]->post_title;
			$author = $this->get_post_author_name( $posts[0] );
		}

		$result = $ereader->send_posts(
			$posts,
			$title,
			$author
		);

		if ( ! $result ) {
			status_header( 404 );
			echo 'error';
			exit;
		}

		foreach ( $posts as $post ) {
			update_post_meta( $post->ID, self::POST_META, time() );
		}

		wp_redirect( $result['url'] );
		exit;
	}

	public function bulk_actions( $actions ) {
		$actions['send-to-e-reader'] = __( 'Send to E-Reader', 'send-to-e-reader' );
		return $actions;
	}

	public function handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
		if ( 'send-to-e-reader' !== $doaction ) {
			return $redirect_to;
		}

		$ereaders = $this->get_active_ereaders();
		$ereader = array_shift( $ereaders );

		if ( ! $ereader ) {
			return add_query_arg( 'send-to-e-reader', 'no-ereader', $redirect_to );
		}

		$posts = array_map( 'get_post', $post_ids );
		$result = $ereader->send_posts( $posts, false, false );

		if ( ! $result ) {
			return $redirect_to;
		}

		foreach ( $posts as $post ) {
			update_post_meta( $post->ID, self::POST_META, time() );
		}

		if ( isset( $result['url'] ) ) {
			wp_safe_redirect( $result['url'] );
			exit;
		}

		return add_query_arg( $result, $redirect_to );
	}

	public function post_row_actions( $actions, $post ) {
		$actions['send-to-e-reader'] = sprintf(
			'<a href="%s">%s</a>',
			add_query_arg(
				array(
					'action'   => 'send-to-e-reader',
					'post[]'   => $post->ID,
					'_wpnonce' => wp_create_nonce( 'bulk-posts' ),
				),
				'edit.php'
			),
			__( 'Send to E-Reader', 'send-to-e-reader' )
		);
		return $actions;
	}

	public function admin_notices() {
		if ( ! empty( $_GET['send-to-e-reader'] ) ) {
			if ( 'success' === $_GET['send-to-e-reader'] ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Posts sent to E-Reader.', 'send-to-e-reader' ); ?></p>
				</div>
				<?php
			} elseif ( 'no-ereader' === $_GET['send-to-e-reader'] ) {
				?>
				<div class="notice notice-error is-dismissible">
					<p><?php
						printf(
							/* translators: %s is a link to the settings page */
							esc_html__( 'No active E-Reader configured. Please configure one in the %s.', 'send-to-e-reader' ),
							'<a href="' . esc_url( admin_url( 'options-general.php?page=send-to-e-reader-settings' ) ) . '">' . esc_html__( 'settings', 'send-to-e-reader' ) . '</a>'
						);
					?></p>
				</div>
				<?php
			}
		}
	}

	public static function activate_plugin() {
		$ereaders = get_option( self::EREADERS_OPTION, array() );
		if ( empty( $ereaders ) ) {
			$ereaders = array();
			require_once SEND_TO_E_READER_PLUGIN_DIR . 'includes/class-e-reader-download.php';
			$ereader = new E_Reader_Download( __( 'Download ePub', 'send-to-e-reader' ) );
			$ereader->active = true;
			$id = $ereader->get_id();
			if ( $id ) {
				$ereaders[ $id ] = $ereader;
			}
				update_option( self::EREADERS_OPTION, $ereaders );
		}

		// Flush rewrite rules for the article review page.
		flush_rewrite_rules();
	}
}
