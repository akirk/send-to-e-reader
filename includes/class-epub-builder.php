<?php
/**
 * Epub Builder
 *
 * Shared ePub generation for post downloads and integrations.
 *
 * @package Send_To_E_Reader
 */

namespace Send_To_E_Reader;

defined( 'ABSPATH' ) || exit;

/**
 * Builds ePub files from prepared XHTML chapters.
 */
class Epub_Builder {
	const MIME = 'application/epub+zip';

	/**
	 * Build an ePub and return its binary contents.
	 *
	 * @param string $title    The book title.
	 * @param string $author   The book author.
	 * @param array  $chapters Prepared chapters.
	 * @param array  $args     Optional generation arguments.
	 * @return string
	 */
	public static function build_content( $title, $author, array $chapters, array $args = array() ) {
		$book = self::build_book( $title, $author, $chapters, $args );

		return $book->getBook();
	}

	/**
	 * Build an ePub file and return its path.
	 *
	 * @param string $title    The book title.
	 * @param string $author   The book author.
	 * @param array  $chapters Prepared chapters.
	 * @param array  $args     Optional generation arguments.
	 * @return string|false
	 */
	public static function build_file( $title, $author, array $chapters, array $args = array() ) {
		$dir = self::get_temp_dir();
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$args['base_dir'] = $dir;
		$filename         = self::build_book_filename( $title, $author );
		$book             = self::build_book( $title, $author, $chapters, $args );

		if ( false === $book->saveBook( $filename . '.epub', $dir ) ) {
			return false;
		}

		return $dir . '/' . $filename . '.epub';
	}

	/**
	 * Wrap content in the XHTML shell used inside an ePub chapter.
	 *
	 * @param string $title     Chapter title.
	 * @param string $byline    Chapter byline.
	 * @param string $body_html Escaped XHTML body content.
	 * @param string $url       Optional source URL.
	 * @return string
	 */
	public static function wrap_xhtml( $title, $byline, $body_html, $url = '' ) {
		$content  = '<?xml version="1.0" encoding="utf-8"?>' . PHP_EOL;
		$content .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"' . PHP_EOL;
		$content .= "\t" . '"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">' . PHP_EOL;
		$content .= '<html xmlns="http://www.w3.org/1999/xhtml">' . PHP_EOL;
		$content .= '<head>' . PHP_EOL;
		$content .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />' . PHP_EOL;
		$content .= '<link rel="stylesheet" type="text/css" href="style.css" />' . PHP_EOL;
		$content .= '<title>' . self::escape_xml( $title ) . '</title>' . PHP_EOL;
		$content .= '</head>' . PHP_EOL;
		$content .= '<body>' . PHP_EOL;
		$content .= "\t" . '<h1>' . self::escape_xml( $title ) . '</h1>' . PHP_EOL;
		$content .= "\t" . '<hr />' . PHP_EOL;

		if ( '' !== trim( $byline ) ) {
			$content .= "\t" . '<h6 class="author">' . self::escape_xml( $byline ) . '</h6>' . PHP_EOL;
		}

		$content .= $body_html . PHP_EOL;

		if ( '' !== trim( $url ) ) {
			$content .= '<hr />' . PHP_EOL;
			$content .= '<p><em><a href="' . esc_url( $url ) . '">' . self::escape_xml( $url ) . '</a></em></p>' . PHP_EOL;
		}

		$content .= '</body>' . PHP_EOL;
		$content .= '</html>' . PHP_EOL;

		return $content;
	}

	/**
	 * Strip emoji characters from ePub metadata.
	 *
	 * @param string $text The text.
	 * @return string
	 */
	public static function strip_emojis( $text ) {
		$text = (string) $text;

		// Match Emoticons.
		$text = preg_replace( '/[\x{1F600}-\x{1F64F}]/u', '', $text );

		// Match Miscellaneous Symbols and Pictographs.
		$text = preg_replace( '/[\x{1F300}-\x{1F5FF}]/u', '', $text );

		// Match Transport And Map Symbols.
		$text = preg_replace( '/[\x{1F680}-\x{1F6FF}]/u', '', $text );

		// Match Miscellaneous Symbols.
		$text = preg_replace( '/[\x{2600}-\x{26FF}]/u', '', $text );

		// Match Dingbats.
		$text = preg_replace( '/[\x{2700}-\x{27BF}]/u', '', $text );

		return $text;
	}

	/**
	 * Build a PHPePub book.
	 *
	 * @param string $title    The book title.
	 * @param string $author   The book author.
	 * @param array  $chapters Prepared chapters.
	 * @param array  $args     Optional generation arguments.
	 * @return \PHPePub\Core\EPub
	 */
	private static function build_book( $title, $author, array $chapters, array $args = array() ) {
		$title      = self::strip_emojis( $title );
		$author     = self::strip_emojis( $author );
		$identifier = ! empty( $args['identifier'] ) ? (string) $args['identifier'] : home_url( '/' );
		$source_url = ! empty( $args['source_url'] ) ? (string) $args['source_url'] : $identifier;
		$base_dir   = ! empty( $args['base_dir'] ) ? (string) $args['base_dir'] : '';

		$book = new \PHPePub\Core\EPub();
		$book->setGenerator( 'Send to E-Reader (Version ' . SEND_TO_E_READER_VERSION . ')' );
		$book->setTitle( self::escape_xml( $title ) );
		$book->setIdentifier( $identifier, \PHPePub\Core\EPub::IDENTIFIER_URI );
		$book->setAuthor( self::escape_xml( $author ), self::escape_xml( $author ) );
		$book->setSourceURL( $source_url );

		if ( ! empty( $args['description'] ) ) {
			$book->setDescription( self::sanitize_xml_text( $args['description'] ) );
		}

		if ( ! empty( $args['date'] ) ) {
			$timestamp = is_numeric( $args['date'] ) ? (int) $args['date'] : strtotime( (string) $args['date'] );
			if ( $timestamp ) {
				$book->setDate( $timestamp );
			}
		}

		$style_path = self::get_template_file( 'epub/style' );
		if ( $style_path && file_exists( $style_path ) ) {
			$book->addCSSFile( 'style.css', 'css', file_get_contents( $style_path ) );
		}

		$chapter_count = 0;
		foreach ( $chapters as $count => $chapter ) {
			if ( ! is_array( $chapter ) || empty( $chapter['content'] ) ) {
				continue;
			}

			$chapter_title = ! empty( $chapter['title'] ) ? (string) $chapter['title'] : sprintf(
				/* translators: %d is a chapter number. */
				__( 'Chapter %d', 'send-to-e-reader' ),
				$count + 1
			);
			$file_name     = ! empty( $chapter['filename'] ) ? (string) $chapter['filename'] : self::build_chapter_filename( $chapter_title, $count );
			$chapter_base  = array_key_exists( 'base_dir', $chapter ) ? (string) $chapter['base_dir'] : $base_dir;

			$book->addChapter(
				$chapter_title,
				$file_name,
				$chapter['content'],
				! empty( $chapter['auto_split'] ),
				isset( $chapter['external_references'] ) ? $chapter['external_references'] : \PHPePub\Core\EPub::EXTERNAL_REF_ADD,
				$chapter_base
			);
			++$chapter_count;
		}

		if ( $chapter_count > 1 ) {
			$book->buildTOC( null, 'toc', __( 'Table of Contents', 'send-to-e-reader' ), true, true );
		}

		$book->finalize();

		return $book;
	}

	/**
	 * Build a filesystem-safe ePub filename.
	 *
	 * @param string $title  The book title.
	 * @param string $author The book author.
	 * @return string
	 */
	private static function build_book_filename( $title, $author ) {
		$filename = sanitize_title( substr( (string) $author, 0, 40 ) . ' - ' . substr( (string) $title, 0, 100 ) );

		return $filename ? $filename : 'ebook';
	}

	/**
	 * Build a filesystem-safe chapter filename.
	 *
	 * @param string $title The chapter title.
	 * @param int    $index Zero-based chapter index.
	 * @return string
	 */
	private static function build_chapter_filename( $title, $index ) {
		$filename = sanitize_title( substr( self::strip_emojis( $title ), 0, 100 ) );
		if ( ! $filename ) {
			$filename = 'chapter-' . ( $index + 1 );
		}

		return $filename . '.html';
	}

	/**
	 * Get the temporary directory used for generated ePub files.
	 *
	 * @return string
	 */
	private static function get_temp_dir() {
		return rtrim( sys_get_temp_dir(), '/' ) . '/send_to_e_reader';
	}

	/**
	 * Resolve a plugin template file.
	 *
	 * @param string $slug Template slug.
	 * @return string
	 */
	private static function get_template_file( $slug ) {
		if ( class_exists( '\Friends\Friends' ) ) {
			$template = \Friends\Friends::template_loader()->get_template_part( $slug, null, array(), false );
			if ( $template && file_exists( $template ) ) {
				return $template;
			}
		}

		$fallback = SEND_TO_E_READER_PLUGIN_DIR . 'templates/' . $slug . '.php';
		if ( file_exists( $fallback ) ) {
			return $fallback;
		}

		return '';
	}

	/**
	 * Escape text for XHTML.
	 *
	 * @param string $text The text.
	 * @return string
	 */
	private static function escape_xml( $text ) {
		return htmlspecialchars( self::sanitize_xml_text( $text ), ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8' );
	}

	/**
	 * Remove characters that are invalid in XML.
	 *
	 * @param string $text The text.
	 * @return string
	 */
	private static function sanitize_xml_text( $text ) {
		return preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $text );
	}
}
