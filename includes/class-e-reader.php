<?php
/**
 * Friends E-Reader
 *
 * This contains an abstract class of an E-Reader
 *
 * @package Send_To_E_Reader
 */

namespace Send_To_E_Reader;

defined( 'ABSPATH' ) || exit;

/**
 * This is the abstract class for the sending posts to an E-Reader for the Friends Plugin.
 *
 * @since 0.3
 *
 * @package Send_To_E_Reader
 * @author Alex Kirk
 */
abstract class E_Reader {
	protected $ebook_title;
	protected $ebook_author;
	public $active;
	abstract public function get_id();
	abstract public function render_input();
	abstract public static function render_template( $data = array() );
	abstract public static function instantiate_from_field_data( $id, $data );
	abstract public function send_posts( array $posts, $title = null, $author = null );

	/**
	 * Get the template loader (Friends or fallback).
	 *
	 * @return object
	 */
	protected static function get_template_loader() {
		if ( class_exists( '\Friends\Friends' ) ) {
			return \Friends\Friends::template_loader();
		}

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
	 * Get the author for a post, with Friends fallback.
	 *
	 * @param \WP_Post $post The post.
	 * @return object An object with display_name property.
	 */
	protected static function get_post_author( \WP_Post $post ) {
		if ( class_exists( '\Friends\User' ) ) {
			return \Friends\User::get_post_author( $post );
		}
		return get_userdata( $post->post_author ) ?: (object) array( 'display_name' => __( 'Unknown', 'send-to-e-reader' ) );
	}

	/**
	 * Strip Emojis from text
	 *
	 * @param      string $text   The text.
	 *
	 * @return     string  The text stripped off emojis.
	 */
	protected function strip_emojis( $text ) {
		return Epub_Builder::strip_emojis( $text );
	}

	protected function get_content( $format, \WP_Post $post ) {
		ob_start();
		$post_title = $post->post_title;
		if ( empty( $post_title ) ) {
			$post_title = get_the_time( 'F j, Y H:i:s', $post );
		}

		self::get_template_loader()->get_template_part(
			$format . '/header',
			null,
			array(
				'title'  => $post_title,
				'author' => $post->author_name,
				'date'   => get_the_time( 'l, F j, Y', $post ),
			)
		);

		echo wp_kses_post( $post->post_content );

		self::get_template_loader()->get_template_part(
			$format . '/footer',
			null,
			array(
				'url' => get_permalink( $post ),
			)
		);
		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}

	protected function update_author_name( \WP_Post $post ) {
		if ( ! isset( $post->author_name ) ) {
			$author = self::get_post_author( $post );
			$author_name = $author->display_name;
			$override_author_name = apply_filters( 'friends_override_author_name', '', $author->display_name, $post->ID );
			if ( $override_author_name ) {
				$author_name = $override_author_name;
			}
			$post->author_name = $author_name;
		}
		return $post->author_name;
	}


	protected function generate_file( array $posts, $title = null, $author = null ) {
		$authors = array();
		$chapters = array();
		$this->ebook_title = $title;
		$this->ebook_author = $author;

		foreach ( $posts as $post ) {
			if ( ! $this->ebook_title ) {
				$post_title = $post->post_title;
				if ( empty( $post_title ) ) {
					$post_title = get_the_time( 'F j, Y H:i:s', $post );
				}
				$this->ebook_title = $this->strip_emojis( $post_title );
			}

			$author_name = $this->update_author_name( $post );
			if ( ! in_array( $author_name, $authors ) ) {
				$authors[] = $author_name;
			}
		}

		if ( count( $posts ) > 1 && ! $title ) {
			// translators: %s is a post title. This is a title to be used when multiple posts are compiled to an ePub.
			$this->ebook_title = sprintf( __( '%s & more', 'send-to-e-reader' ), $this->ebook_title );
		}

		if ( ! $this->ebook_author ) {
			$this->ebook_author = implode( ', ', $authors );
		}
		$this->ebook_author = apply_filters( 'send_to_e_reader_ebook_author', $this->ebook_author, $posts, $title, $author );
		$this->ebook_title = $this->strip_emojis( $this->ebook_title );
		$this->ebook_author = $this->strip_emojis( $this->ebook_author );

		$url = home_url( '?' . implode( '-', array_map( 'intval', array_column( $posts, 'ID' ) ) ) );

		foreach ( $posts as $post ) {
			$post_title = $post->post_title;
			if ( empty( $post_title ) ) {
				$post_title = get_the_excerpt( $post );
			}

			$content = $this->get_content( 'epub', $post );

			$chapters[] = array(
				'title'    => $post_title,
				'filename' => sanitize_title( substr( $this->strip_emojis( $post->post_author ), 0, 40 ) . ' - ' . substr( $post_title, 0, 100 ) ) . '.html',
				'content'  => $content,
			);
		}

		return Epub_Builder::build_file(
			$this->ebook_title,
			$this->ebook_author,
			$chapters,
			array(
				'identifier' => $url,
				'source_url' => $url,
			)
		);
	}
}
