<?php
/**
 * PHPUnit bootstrap file for Friends Send to E-Reader tests.
 *
 * @package Send_To_E_Reader
 */

namespace Friends {
	class Friends {
		private static $instance = null;
		public $notifications;
		public $frontend;

		public function __construct() {
			$this->notifications = new class {
				public function send_mail( $to, $subject, $message, $headers = array(), $attachments = array() ) {
					return true;
				}
				public function get_friends_plugin_from_email_address() {
					return 'friends@example.com';
				}
			};
			$this->frontend = new class {
				public $author = null;
			};
		}

		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public static function template_loader() {
			static $loader = null;
			if ( null === $loader ) {
				$loader = new class {
					public function get_template_part( $slug, $name = null, $args = array(), $echo = true ) {
						return '';
					}
				};
			}
			return $loader;
		}

		public static function on_frontend() {
			return false;
		}
	}

	class User {
		public $ID;
		public $display_name = 'Test User';

		public function __construct( $id = null ) {
			$this->ID = $id;
		}

		public static function get_post_author( $post ) {
			$user = new self( $post->post_author ?? 1 );
			$user->display_name = 'Test Author';
			return $user;
		}

		public function get_local_friends_page_url( $post_id = null ) {
			return '/friends/';
		}
	}

	class User_Query {
		public function __construct( $args = array() ) {}

		public static function all_associated_users() {
			return new self();
		}

		public function get_results() {
			return array();
		}
	}
}

namespace {
	// Define plugin constants.
	if ( ! defined( 'FRIENDS_SEND_TO_E_READER_PLUGIN_DIR' ) ) {
		define( 'FRIENDS_SEND_TO_E_READER_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
	}
	if ( ! defined( 'FRIENDS_SEND_TO_E_READER_VERSION' ) ) {
		define( 'FRIENDS_SEND_TO_E_READER_VERSION', '0.8.4' );
	}
	if ( ! defined( 'SEND_TO_E_READER_PLUGIN_DIR' ) ) {
		define( 'SEND_TO_E_READER_PLUGIN_DIR', FRIENDS_SEND_TO_E_READER_PLUGIN_DIR );
	}
	if ( ! defined( 'SEND_TO_E_READER_VERSION' ) ) {
		define( 'SEND_TO_E_READER_VERSION', FRIENDS_SEND_TO_E_READER_VERSION );
	}
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', '/tmp/' );
	}

	$GLOBALS['wpdb'] = new class {
		public $postmeta = 'wp_postmeta';

		public function update( $table, $data, $where ) {
			return true;
		}
	};

	// Load Composer autoloader.
	require_once dirname( __DIR__ ) . '/vendor/autoload.php';

	if ( ! class_exists( 'RelativePath', false ) ) {
		require_once dirname( __DIR__ ) . '/libs/grandt/relativepath/RelativePath.php';
	}
	if ( ! class_exists( 'UUID', false ) ) {
		require_once dirname( __DIR__ ) . '/libs/grandt/phpepub/src/lib.uuid.php';
	}

	if ( ! class_exists( 'UUID', false ) ) {
		require_once dirname( __DIR__ ) . '/libs/grandt/phpepub/src/lib.uuid.php';
	}

	spl_autoload_register(
		function ( $class ) {
			$classmap = array(
				'com\\grandt\\BinString'       => dirname( __DIR__ ) . '/libs/grandt/binstring/BinString.php',
				'com\\grandt\\BinStringStatic' => dirname( __DIR__ ) . '/libs/grandt/binstring/BinStringStatic.php',
			);

			if ( isset( $classmap[ $class ] ) && file_exists( $classmap[ $class ] ) ) {
				require_once $classmap[ $class ];
				return;
			}

			$prefix = 'PHPePub\\';
			if ( 0 !== strpos( $class, $prefix ) ) {
				return;
			}

			$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
			$file = dirname( __DIR__ ) . '/libs/grandt/phpepub/src/PHPePub/' . $relative . '.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	);

	// The checked-in vendor tree used by these tests can omit the runtime phpzip package.
	spl_autoload_register(
		function ( $class ) {
			$zipmerge_prefix = 'ZipMerge\\Zip\\';
			if ( 0 === strpos( $class, $zipmerge_prefix ) ) {
				$relative = str_replace( '\\', '/', substr( $class, strlen( $zipmerge_prefix ) ) );
				$file = dirname( __DIR__ ) . '/libs/grandt/phpzipmerge/src/ZipMerge/Zip/' . $relative . '.php';
				if ( file_exists( $file ) ) {
					require_once $file;
				}
				return;
			}

			$prefix = 'PHPZip\\Zip\\';
			if ( 0 !== strpos( $class, $prefix ) ) {
				return;
			}

			$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
			$file = dirname( __DIR__ ) . '/libs/phpzip/phpzip/src/Zip/' . $relative . '.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	);
	spl_autoload_register(
		function ( $class ) {
			$prefix = 'PHPePub\\';
			if ( 0 !== strpos( $class, $prefix ) ) {
				return;
			}

			$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
			$file = dirname( __DIR__ ) . '/libs/grandt/phpepub/src/PHPePub/' . $relative . '.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	);
	spl_autoload_register(
		function ( $class ) {
			$prefix = 'ZipMerge\\';
			if ( 0 !== strpos( $class, $prefix ) ) {
				return;
			}

			$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
			$file = dirname( __DIR__ ) . '/libs/grandt/phpzipmerge/src/ZipMerge/' . $relative . '.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	);
	spl_autoload_register(
		function ( $class ) {
			$prefix = 'com\\grandt\\';
			if ( 0 !== strpos( $class, $prefix ) ) {
				return;
			}

			$class_name = substr( $class, strlen( $prefix ) );
			$file       = dirname( __DIR__ ) . '/libs/grandt/binstring/' . $class_name . '.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	);

	// Mock WordPress functions.
	function __( $text, $domain = 'default' ) {
		return $text;
	}

	function _x( $text, $context, $domain = 'default' ) {
		return $text;
	}

	function _e( $text, $domain = 'default' ) {
		echo $text;
	}

	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html__( $text, $domain );
	}

	function esc_attr__( $text, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_attr_e( $text, $domain = 'default' ) {
		echo esc_attr__( $text, $domain );
	}

	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_attr( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_url( $url ) {
		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}

	function wp_kses( $string, $allowed_html, $allowed_protocols = array() ) {
		return $string;
	}

	function wp_kses_post( $data ) {
		return $data;
	}

	function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		return true;
	}

	function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		global $wp_filter;

		if ( ! isset( $wp_filter ) || ! is_array( $wp_filter ) ) {
			$wp_filter = array();
		}

		$wp_filter[ $tag ][ $priority ][] = array(
			'function'      => $function_to_add,
			'accepted_args' => $accepted_args,
		);

		return true;
	}

	function apply_filters( $tag, $value, ...$args ) {
		global $wp_filter;

		if ( empty( $wp_filter[ $tag ] ) ) {
			return $value;
		}

		ksort( $wp_filter[ $tag ] );

		foreach ( $wp_filter[ $tag ] as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$callback_args = array_slice( array_merge( array( $value ), $args ), 0, $callback['accepted_args'] );
				$value = call_user_func_array( $callback['function'], $callback_args );
			}
		}

		return $value;
	}

	function remove_all_filters( $tag, $priority = false ) {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $tag ] ) ) {
			return true;
		}

		if ( false === $priority ) {
			unset( $wp_filter[ $tag ] );
			return true;
		}

		unset( $wp_filter[ $tag ][ $priority ] );
		return true;
	}

	function did_action( $hook_name ) {
		return 0;
	}

	function get_option( $option, $default = false ) {
		if ( isset( $GLOBALS['send_to_e_reader_test_options'][ $option ] ) ) {
			return $GLOBALS['send_to_e_reader_test_options'][ $option ];
		}
		return $default;
	}

	function update_option( $option, $value, $autoload = null ) {
		$GLOBALS['send_to_e_reader_test_options'][ $option ] = $value;
		return true;
	}

	function get_user_option( $option, $user_id = 0 ) {
		return false;
	}

	function update_user_option( $user_id, $option, $value, $global = false ) {
		return true;
	}

	function delete_user_option( $user_id, $option, $global = false ) {
		return true;
	}

	function get_post_meta( $post_id, $key = '', $single = false ) {
		if ( isset( $GLOBALS['send_to_e_reader_test_post_meta'][ $post_id ][ $key ] ) ) {
			$values = (array) $GLOBALS['send_to_e_reader_test_post_meta'][ $post_id ][ $key ];
			return $single ? end( $values ) : $values;
		}
		return $single ? '' : array();
	}

	function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
		$GLOBALS['send_to_e_reader_test_post_meta'][ $post_id ][ $meta_key ] = array( $meta_value );
		return true;
	}

	function delete_post_meta( $post_id, $meta_key, $meta_value = '' ) {
		unset( $GLOBALS['send_to_e_reader_test_post_meta'][ $post_id ][ $meta_key ] );
		return true;
	}

	function wp_salt( $scheme = 'auth' ) {
		return 'test-salt-' . $scheme;
	}

	function sanitize_title( $title, $fallback_title = '', $context = 'save' ) {
		return preg_replace( '/[^a-z0-9-]/', '-', strtolower( $title ) );
	}

	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
	}

	function sanitize_text_field( $str ) {
		return trim( strip_tags( $str ) );
	}

	function sanitize_file_name( $filename ) {
		return preg_replace( '/[^A-Za-z0-9._-]/', '-', $filename );
	}

	function get_bloginfo( $show = '', $filter = 'raw' ) {
		return 'Test Site';
	}

	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}

	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}

	function esc_url_raw( $url, $protocols = null ) {
		return (string) $url;
	}

	function absint( $maybeint ) {
		return abs( intval( $maybeint ) );
	}

	function wp_register_ability_category( $slug, $args = array() ) {
		$GLOBALS['send_to_e_reader_test_ability_categories'][ $slug ] = $args;
		return true;
	}

	function wp_get_ability_category( $slug ) {
		return isset( $GLOBALS['send_to_e_reader_test_ability_categories'][ $slug ] ) ? $GLOBALS['send_to_e_reader_test_ability_categories'][ $slug ] : null;
	}

	function wp_register_ability( $name, $args = array() ) {
		$GLOBALS['send_to_e_reader_test_abilities'][ $name ] = $args;
		return true;
	}

	function wp_get_ability( $name ) {
		return isset( $GLOBALS['send_to_e_reader_test_abilities'][ $name ] ) ? $GLOBALS['send_to_e_reader_test_abilities'][ $name ] : null;
	}

	function is_user_logged_in() {
		return false;
	}

	function current_user_can( $capability, ...$args ) {
		if ( ! isset( $GLOBALS['send_to_e_reader_test_caps'] ) ) {
			return false;
		}
		if ( is_callable( $GLOBALS['send_to_e_reader_test_caps'] ) ) {
			return (bool) call_user_func_array( $GLOBALS['send_to_e_reader_test_caps'], array_merge( array( $capability ), $args ) );
		}
		return in_array( $capability, (array) $GLOBALS['send_to_e_reader_test_caps'], true );
	}

	function add_query_arg( ...$args ) {
		if ( is_array( $args[0] ) ) {
			$params = $args[0];
			$url = isset( $args[1] ) ? $args[1] : '';
		} else {
			$params = array( $args[0] => $args[1] );
			$url = isset( $args[2] ) ? $args[2] : '';
		}

		$query = array();
		foreach ( $params as $key => $value ) {
			$query[] = $key . '=' . rawurlencode( $value );
		}

		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . implode( '&', $query );
	}

	function get_the_author_meta( $field = '', $user_id = false ) {
		if ( 'display_name' === $field ) {
			return 'Test Author';
		}
		return '';
	}

	function get_userdata( $user_id ) {
		return (object) array(
			'ID'           => $user_id,
			'display_name' => 'Test User',
		);
	}

	function home_url( $path = '', $scheme = null ) {
		return 'https://example.com' . $path;
	}

	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}

	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
		$text = strip_tags( $text );
		if ( $remove_breaks ) {
			$text = preg_replace( '/[\\r\\n\\t ]+/', ' ', $text );
		}
		return trim( $text );
	}

	function admin_url( $path = '', $scheme = 'admin' ) {
		return 'https://example.com/wp-admin/' . $path;
	}

	function plugins_url( $path = '', $plugin = '' ) {
		return 'https://example.com/wp-content/plugins/' . basename( dirname( $plugin ) ) . '/' . $path;
	}

	function wp_get_upload_dir() {
		$basedir = isset( $GLOBALS['_test_upload_basedir'] ) ? $GLOBALS['_test_upload_basedir'] : sys_get_temp_dir() . '/uploads';
		$baseurl = isset( $GLOBALS['_test_upload_baseurl'] ) ? $GLOBALS['_test_upload_baseurl'] : 'https://example.com/wp-content/uploads';

		return array(
			'basedir' => $basedir,
			'baseurl' => $baseurl,
		);
	}

	function get_the_time( $format = '', $post = null ) {
		return date( $format ?: 'U' );
	}

	function get_the_title( $post = 0 ) {
		if ( is_object( $post ) ) {
			return $post->post_title ?? '';
		}
		return '';
	}

	function get_the_excerpt( $post = null ) {
		if ( is_object( $post ) ) {
			return $post->post_excerpt ?? '';
		}
		return '';
	}

	function get_the_permalink( $post = 0 ) {
		$id = is_object( $post ) ? $post->ID : $post;
		return 'https://example.com/?p=' . $id;
	}

	function get_permalink( $post = 0 ) {
		return get_the_permalink( $post );
	}

	function get_edit_post_link( $id = 0, $context = 'display' ) {
		return admin_url( 'post.php?post=' . intval( $id ) . '&action=edit' );
	}

	function get_post_format( $post = null ) {
		return false;
	}

	function date_i18n( $format, $timestamp = false, $gmt = false ) {
		return date( $format, $timestamp ?: time() );
	}

	function wp_create_nonce( $action = -1 ) {
		return 'test-nonce-' . $action;
	}

	function wp_verify_nonce( $nonce, $action = -1 ) {
		return 1;
	}

	function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) {
		return true;
	}

	function wp_send_json_success( $data = null, $status_code = null ) {
		echo json_encode( array( 'success' => true, 'data' => $data ) );
	}

	function wp_send_json_error( $data = null, $status_code = null ) {
		echo json_encode( array( 'success' => false, 'data' => $data ) );
	}

	function is_wp_error( $thing ) {
		return $thing instanceof \WP_Error;
	}

	class WP_Error {
		public $errors = array();
		public $error_data = array();

		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( $code ) {
				$this->errors[ $code ][] = $message;
				if ( $data ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		public function get_error_message( $code = '' ) {
			if ( empty( $code ) ) {
				$code = array_key_first( $this->errors );
			}
			return $this->errors[ $code ][0] ?? '';
		}

		public function get_error_code() {
			return array_key_first( $this->errors ) ?? '';
		}
	}

	class WP_Query {
		public $query_vars = array();
		private $posts = array();

		public function __construct( $query = '' ) {
			$this->query_vars = is_array( $query ) ? $query : array();
			$this->posts = isset( $GLOBALS['send_to_e_reader_test_query_posts'] ) ? $GLOBALS['send_to_e_reader_test_query_posts'] : array_values( $GLOBALS['send_to_e_reader_test_posts'] ?? array() );

			if ( ! empty( $this->query_vars['post__in'] ) ) {
				$post_ids = array_map( 'intval', (array) $this->query_vars['post__in'] );
				$this->posts = array_values(
					array_filter(
						$this->posts,
						function ( $post ) use ( $post_ids ) {
							return in_array( (int) $post->ID, $post_ids, true );
						}
					)
				);
			}

			if ( ! empty( $this->query_vars['meta_query'][0]['key'] ) ) {
				$key = $this->query_vars['meta_query'][0]['key'];
				$compare = $this->query_vars['meta_query'][0]['compare'] ?? '';
				$this->posts = array_values(
					array_filter(
						$this->posts,
						function ( $post ) use ( $key, $compare ) {
							$has_meta = '' !== get_post_meta( $post->ID, $key, true );
							return 'EXISTS' === $compare ? $has_meta : ! $has_meta;
						}
					)
				);
			}
		}

		public function get_posts() {
			return $this->posts;
		}
	}

	class WP_Post {
		public $ID = 0;
		public $post_author = 1;
		public $post_title = '';
		public $post_content = '';
		public $post_excerpt = '';
		public $post_status = 'publish';
		public $post_type = 'post';

		public function __construct( $post = null ) {
			if ( is_object( $post ) ) {
				foreach ( get_object_vars( $post ) as $key => $value ) {
					$this->$key = $value;
				}
			} elseif ( is_numeric( $post ) ) {
				$this->ID = intval( $post );
			}
		}
	}

	function get_post( $post = null ) {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		if ( is_numeric( $post ) && isset( $GLOBALS['send_to_e_reader_test_posts'][ intval( $post ) ] ) ) {
			return $GLOBALS['send_to_e_reader_test_posts'][ intval( $post ) ];
		}
		return new WP_Post( $post );
	}

	/**
	 * Stub of the AI Assistant conversation store.
	 */
	class Send_To_E_Reader_Test_Conversations {
		const POST_TYPE = 'ai_conversation';

		public function get_conversation_export_data( $conversation_id ) {
			$conversation_id = intval( $conversation_id );
			if ( empty( $GLOBALS['send_to_e_reader_test_conversations'][ $conversation_id ] ) ) {
				return new WP_Error( 'conversation_not_found', 'Conversation not found' );
			}

			return $GLOBALS['send_to_e_reader_test_conversations'][ $conversation_id ];
		}
	}

	/**
	 * Stub of the AI Assistant plugin singleton.
	 */
	class AI_Assistant {
		private static $instance = null;

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function conversations() {
			return new Send_To_E_Reader_Test_Conversations();
		}
	}

	function ai_assistant() {
		return AI_Assistant::instance();
	}

	// Load the plugin files.
	require_once FRIENDS_SEND_TO_E_READER_PLUGIN_DIR . 'includes/class-epub-builder.php';
	require_once FRIENDS_SEND_TO_E_READER_PLUGIN_DIR . 'includes/class-ai-assistant-integration.php';
	require_once FRIENDS_SEND_TO_E_READER_PLUGIN_DIR . 'includes/class-e-reader.php';
	require_once FRIENDS_SEND_TO_E_READER_PLUGIN_DIR . 'includes/class-send-to-e-reader.php';
	require_once FRIENDS_SEND_TO_E_READER_PLUGIN_DIR . 'includes/class-abilities.php';
	require_once FRIENDS_SEND_TO_E_READER_PLUGIN_DIR . 'includes/class-e-reader-download.php';
	require_once FRIENDS_SEND_TO_E_READER_PLUGIN_DIR . 'includes/class-e-reader-generic-email.php';
	require_once FRIENDS_SEND_TO_E_READER_PLUGIN_DIR . 'includes/class-e-reader-kindle.php';
	require_once FRIENDS_SEND_TO_E_READER_PLUGIN_DIR . 'includes/class-e-reader-pocketbook.php';
	require_once FRIENDS_SEND_TO_E_READER_PLUGIN_DIR . 'includes/class-post-collection-integration.php';
}
