<?php
/**
 * PHPUnit bootstrap file for Friends Send to E-Reader tests.
 *
 * @package Friends_Send_To_E_Reader
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
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', '/tmp/' );
	}

	// Load Composer autoloader.
	require_once dirname( __DIR__ ) . '/vendor/autoload.php';

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
		return true;
	}

	function apply_filters( $tag, $value, ...$args ) {
		return $value;
	}

	function did_action( $hook_name ) {
		return 0;
	}

	function get_option( $option, $default = false ) {
		return $default;
	}

	function update_option( $option, $value, $autoload = null ) {
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
		return $single ? '' : array();
	}

	function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
		return true;
	}

	function delete_post_meta( $post_id, $meta_key, $meta_value = '' ) {
		return true;
	}

	function wp_salt( $scheme = 'auth' ) {
		return 'test-salt-' . $scheme;
	}

	function sanitize_title( $title, $fallback_title = '', $context = 'save' ) {
		return preg_replace( '/[^a-z0-9-]/', '-', strtolower( $title ) );
	}

	function sanitize_text_field( $str ) {
		return trim( strip_tags( $str ) );
	}

	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}

	function is_user_logged_in() {
		return false;
	}

	function current_user_can( $capability, ...$args ) {
		return false;
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

	function admin_url( $path = '', $scheme = 'admin' ) {
		return 'https://example.com/wp-admin/' . $path;
	}

	function plugins_url( $path = '', $plugin = '' ) {
		return 'https://example.com/wp-content/plugins/' . basename( dirname( $plugin ) ) . '/' . $path;
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
	}

	class WP_Query {
		public $query_vars = array();
		private $posts = array();

		public function __construct( $query = '' ) {
			$this->query_vars = is_array( $query ) ? $query : array();
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

		public function __construct( $post = null ) {
			if ( is_object( $post ) ) {
				foreach ( get_object_vars( $post ) as $key => $value ) {
					$this->$key = $value;
				}
			}
		}
	}

	function get_post( $post = null ) {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		return new WP_Post( $post );
	}

	// Load the plugin files.
	require_once FRIENDS_SEND_TO_E_READER_PLUGIN_DIR . 'includes/class-e-reader.php';
	require_once FRIENDS_SEND_TO_E_READER_PLUGIN_DIR . 'includes/class-send-to-e-reader.php';
	require_once FRIENDS_SEND_TO_E_READER_PLUGIN_DIR . 'includes/class-e-reader-download.php';
	require_once FRIENDS_SEND_TO_E_READER_PLUGIN_DIR . 'includes/class-e-reader-generic-email.php';
	require_once FRIENDS_SEND_TO_E_READER_PLUGIN_DIR . 'includes/class-e-reader-kindle.php';
	require_once FRIENDS_SEND_TO_E_READER_PLUGIN_DIR . 'includes/class-e-reader-pocketbook.php';
}
