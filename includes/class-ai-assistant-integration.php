<?php
/**
 * AI Assistant integration.
 *
 * @package Send_To_E_Reader
 */

namespace Send_To_E_Reader;

defined( 'ABSPATH' ) || exit;

/**
 * Adds Send to E-Reader export formats to AI Assistant.
 */
class AI_Assistant_Integration {
	/**
	 * Register WordPress hooks.
	 */
	public static function register_hooks() {
		add_filter( 'ai_assistant_conversation_export_formats', array( __CLASS__, 'register_export_formats' ), 100, 2 );
	}

	/**
	 * Add the EPUB conversation export format.
	 *
	 * @param array      $formats      Existing export formats.
	 * @param array|null $conversation Conversation export data, if available.
	 * @return array
	 */
	public static function register_export_formats( $formats, $conversation = null ) {
		$formats = (array) $formats;

		$epub_format = isset( $formats['epub'] ) ? $formats['epub'] : array(
				'label'       => __( 'EPUB', 'send-to-e-reader' ),
				'description' => __( 'E-reader friendly conversation export.', 'send-to-e-reader' ),
				'extension'   => 'epub',
				'mime'        => Epub_Builder::MIME,
				'callback'    => array( __CLASS__, 'export_conversation_epub' ),
		);

		unset( $formats['epub'] );
		$formats['epub'] = $epub_format;

		return $formats;
	}

	/**
	 * Export an AI Assistant conversation as an ePub.
	 *
	 * @param array $conversation Conversation export data.
	 * @param array $format       Export format definition.
	 * @return array
	 */
	public static function export_conversation_epub( array $conversation, array $format ) {
		$messages = isset( $conversation['messages'] ) && is_array( $conversation['messages'] ) ? $conversation['messages'] : array();
		$messages = apply_filters( 'ai_assistant_conversation_export_shrink_tool_calls', $messages, $conversation, $format );
		$title    = self::single_line_text( ! empty( $conversation['title'] ) ? $conversation['title'] : __( 'Conversation', 'send-to-e-reader' ) );
		$author   = self::get_author_name( $conversation );
		$source   = self::get_conversation_url( $conversation );
		$chapters = self::conversation_to_chapters( $conversation, $messages, $author, $source );

		return array(
			'filename' => self::build_export_filename( $conversation, $format ),
			'mime'     => Epub_Builder::MIME,
			'content'  => Epub_Builder::build_content(
				$title,
				$author,
				$chapters,
				array(
					'identifier'  => $source,
					'source_url'  => $source,
					'description' => isset( $conversation['summary'] ) ? $conversation['summary'] : '',
					'date'        => isset( $conversation['created'] ) ? $conversation['created'] : '',
				)
			),
		);
	}

	/**
	 * Convert conversation data to ePub chapters.
	 *
	 * @param array  $conversation Conversation export data.
	 * @param array  $messages     Conversation messages.
	 * @param string $author       Export author.
	 * @param string $source       Conversation source URL.
	 * @return array
	 */
	private static function conversation_to_chapters( array $conversation, array $messages, $author, $source ) {
		$include_tool_calls   = ! empty( $conversation['include_tool_calls'] );
		$conversation_title   = self::single_line_text( ! empty( $conversation['title'] ) ? $conversation['title'] : __( 'Conversation', 'send-to-e-reader' ) );
		$conversation_byline  = self::build_byline( $author, self::format_conversation_date( isset( $conversation['created'] ) ? $conversation['created'] : '' ) );
		$conversation_summary = ! empty( $conversation['summary'] ) ? self::plain_text_to_xhtml( $conversation['summary'] ) : '';
		$meta_sentence        = self::conversation_meta_sentence( $conversation );
		$body                 = '';

		if ( '' !== $conversation_summary ) {
			$body .= '<h2>' . self::escape_xml( __( 'Summary', 'send-to-e-reader' ) ) . '</h2>' . PHP_EOL;
			$body .= $conversation_summary . PHP_EOL;
		}

		if ( '' !== $meta_sentence ) {
			$body .= '<p>' . self::escape_xml( $meta_sentence ) . '</p>' . PHP_EOL;
		}

		if ( '' === trim( $body ) ) {
			$body = '<p>' . self::escape_xml( __( 'No conversation details available.', 'send-to-e-reader' ) ) . '</p>';
		}

		$body .= '<h2>' . self::escape_xml( __( 'Messages', 'send-to-e-reader' ) ) . '</h2>' . PHP_EOL;
		$rendered_messages = 0;

		foreach ( $messages as $index => $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}

			$role    = self::get_message_role( $message );
			$content = self::message_to_plain_text( $message, $include_tool_calls );
			if ( '' === $content && ! $include_tool_calls ) {
				continue;
			}

			$message_title = self::get_message_heading( $role, $author );

			$body .= '<div class="message message-' . self::html_class( $role ) . '">' . PHP_EOL;
			$body .= '<h3>' . self::escape_xml( $message_title ) . '</h3>' . PHP_EOL;
			$body .= self::plain_text_to_xhtml( $content ? $content : __( 'No text content', 'send-to-e-reader' ) );
			$body .= '</div>' . PHP_EOL;
			++$rendered_messages;
		}

		if ( 0 === $rendered_messages ) {
			$body .= '<p>' . self::escape_xml( __( 'No messages available.', 'send-to-e-reader' ) ) . '</p>' . PHP_EOL;
		}

		return array(
			array(
				'title'      => $conversation_title,
				'filename'   => 'conversation.html',
				'content'    => Epub_Builder::wrap_xhtml( $conversation_title, $conversation_byline, $body, $source ),
				'auto_split' => true,
			),
		);
	}

	/**
	 * Build a short conversation metadata sentence.
	 *
	 * @param array $conversation Conversation export data.
	 * @return string
	 */
	private static function conversation_meta_sentence( array $conversation ) {
		$sentence = '';

		if ( isset( $conversation['message_count'] ) ) {
			$message_count = intval( $conversation['message_count'] );
			$sentence      = sprintf(
				/* translators: 1: Number of conversation messages, 2: message/messages. */
				__( 'This conversation contains %d %s', 'send-to-e-reader' ),
				$message_count,
				1 === $message_count ? __( 'message', 'send-to-e-reader' ) : __( 'messages', 'send-to-e-reader' )
			);
		}

		$provider = ! empty( $conversation['provider'] ) ? self::single_line_text( $conversation['provider'] ) : '';
		$model    = ! empty( $conversation['model'] ) ? self::single_line_text( $conversation['model'] ) : '';

		if ( '' !== $provider && '' !== $model ) {
			$created = sprintf(
				/* translators: 1: AI provider, 2: AI model. */
				__( 'was created with %1$s using %2$s', 'send-to-e-reader' ),
				$provider,
				$model
			);
		} elseif ( '' !== $provider ) {
			$created = sprintf(
				/* translators: %s is an AI provider. */
				__( 'was created with %s', 'send-to-e-reader' ),
				$provider
			);
		} elseif ( '' !== $model ) {
			$created = sprintf(
				/* translators: %s is an AI model. */
				__( 'was created using %s', 'send-to-e-reader' ),
				$model
			);
		}

		if ( ! empty( $created ) ) {
			if ( '' === $sentence ) {
				$sentence = __( 'This conversation', 'send-to-e-reader' ) . ' ' . $created;
			} else {
				$sentence .= ' ' . __( 'and', 'send-to-e-reader' ) . ' ' . $created;
			}
		}

		return $sentence ? $sentence . '.' : '';
	}

	/**
	 * Convert a message to readable plain text.
	 *
	 * @param array $message            Message data.
	 * @param bool  $include_tool_calls Whether tool calls should be included.
	 * @return string
	 */
	private static function message_to_plain_text( array $message, $include_tool_calls = false ) {
		$parts   = array();
		$content = self::message_content_to_plain_text( isset( $message['content'] ) ? $message['content'] : '', $include_tool_calls );

		if ( '' !== $content ) {
			$parts[] = $content;
		}

		if ( $include_tool_calls && ! empty( $message['tool_calls'] ) && is_array( $message['tool_calls'] ) ) {
			foreach ( $message['tool_calls'] as $tool_call ) {
				if ( ! is_array( $tool_call ) ) {
					continue;
				}

				$tool_name = isset( $tool_call['function']['name'] ) ? $tool_call['function']['name'] : 'unknown';
				$text      = '[Tool: ' . $tool_name . ']';
				if ( isset( $tool_call['function']['arguments'] ) && '' !== $tool_call['function']['arguments'] ) {
					$text .= "\n" . self::format_jsonish_text( $tool_call['function']['arguments'] );
				}
				$parts[] = $text;
			}
		}

		return trim( implode( "\n\n", $parts ) );
	}

	/**
	 * Convert message content to readable plain text.
	 *
	 * @param mixed $content            Message content.
	 * @param bool  $include_tool_calls Whether tool calls should be included.
	 * @return string
	 */
	private static function message_content_to_plain_text( $content, $include_tool_calls = false ) {
		if ( is_string( $content ) ) {
			return trim( self::strip_file_context_for_display( $content ) );
		}

		if ( ! is_array( $content ) ) {
			return '';
		}

		$parts = array();
		foreach ( $content as $block ) {
			if ( is_string( $block ) ) {
				$parts[] = self::strip_file_context_for_display( $block );
				continue;
			}

			if ( ! is_array( $block ) ) {
				continue;
			}

			$type = isset( $block['type'] ) ? $block['type'] : '';
			if ( 'text' === $type && isset( $block['text'] ) ) {
				$parts[] = self::strip_file_context_for_display( (string) $block['text'] );
			} elseif ( 'tool_use' === $type ) {
				if ( ! $include_tool_calls ) {
					continue;
				}
				$tool = isset( $block['name'] ) ? $block['name'] : 'unknown';
				$text = '[Tool: ' . $tool . ']';
				if ( isset( $block['input'] ) ) {
					$text .= "\n" . ( is_string( $block['input'] ) ? self::format_jsonish_text( $block['input'] ) : self::json_encode( $block['input'] ) );
				}
				$parts[] = $text;
			} elseif ( 'tool_result' === $type ) {
				if ( ! $include_tool_calls ) {
					continue;
				}
				$text = '[Tool Result]';
				if ( isset( $block['content'] ) ) {
					$result_text = self::message_content_to_plain_text( $block['content'], $include_tool_calls );
					if ( '' !== $result_text ) {
						$text .= "\n" . $result_text;
					}
				}
				$parts[] = $text;
			} elseif ( $include_tool_calls ) {
				$parts[] = self::json_encode( $block );
			}
		}

		$parts = array_filter(
			$parts,
			function ( $part ) {
				return '' !== trim( (string) $part );
			}
		);

		return trim( implode( "\n\n", $parts ) );
	}

	/**
	 * Convert plain text to XHTML paragraphs.
	 *
	 * @param string $text Plain text.
	 * @return string
	 */
	private static function plain_text_to_xhtml( $text ) {
		$text       = trim( (string) $text );
		$paragraphs = preg_split( "/\n{2,}/", $text );
		$html       = '';

		foreach ( $paragraphs as $paragraph ) {
			$paragraph = trim( $paragraph );
			if ( '' === $paragraph ) {
				continue;
			}

			$html .= '<p>' . nl2br( self::escape_xml( $paragraph ), true ) . '</p>' . PHP_EOL;
		}

		return $html;
	}

	/**
	 * Strip AI Assistant hidden file context and keep a readable attachment summary.
	 *
	 * @param string $content Message content.
	 * @return string
	 */
	private static function strip_file_context_for_display( $content ) {
		if ( ! is_string( $content ) ) {
			return $content;
		}

		if ( ! preg_match( '/\n*<ai_assistant_file_context>\n(.*?)\n<\/ai_assistant_file_context>/s', $content, $matches ) ) {
			return $content;
		}

		$visible = trim( str_replace( $matches[0], '', $content ) );
		$payload = json_decode( $matches[1], true );
		$files   = is_array( $payload ) && isset( $payload['files'] ) && is_array( $payload['files'] ) ? $payload['files'] : array();

		if ( empty( $files ) ) {
			return $visible;
		}

		$summary = "\n\n[Attached files]\n";
		foreach ( $files as $file ) {
			if ( ! is_array( $file ) ) {
				continue;
			}

			$name     = isset( $file['original_name'] ) ? $file['original_name'] : ( isset( $file['filename'] ) ? $file['filename'] : __( 'Attachment', 'send-to-e-reader' ) );
			$path     = isset( $file['wp_content_path'] ) ? $file['wp_content_path'] : '';
			$summary .= '- ' . $name . ( $path ? ' (' . $path . ')' : '' ) . "\n";
		}

		return ( $visible ? $visible : __( 'Attached files', 'send-to-e-reader' ) ) . rtrim( $summary );
	}

	/**
	 * Get a display role for an exported message.
	 *
	 * @param array $message Message data.
	 * @return string
	 */
	private static function get_message_role( array $message ) {
		$role = self::single_line_text( isset( $message['role'] ) ? $message['role'] : __( 'message', 'send-to-e-reader' ) );
		if ( 'tool' === $role && ! empty( $message['name'] ) ) {
			$role .= ': ' . self::single_line_text( $message['name'] );
		}

		return $role;
	}

	/**
	 * Get the visible heading for a message.
	 *
	 * @param string $role   Message role.
	 * @param string $author Conversation author display name.
	 * @return string
	 */
	private static function get_message_heading( $role, $author ) {
		if ( 'user' === strtolower( (string) $role ) && '' !== self::single_line_text( $author ) ) {
			return $author;
		}

		return ucfirst( $role );
	}

	/**
	 * Build a short chapter byline.
	 *
	 * @param string $author Author name.
	 * @param string $date   Date string.
	 * @return string
	 */
	private static function build_byline( $author, $date ) {
		$parts = array_filter( array( self::single_line_text( $author ), self::single_line_text( $date ) ) );

		return implode( ' | ', $parts );
	}

	/**
	 * Format a conversation date for the ePub chapter header.
	 *
	 * @param string $date Date string.
	 * @return string
	 */
	private static function format_conversation_date( $date ) {
		if ( empty( $date ) ) {
			return '';
		}

		$timestamp = strtotime( (string) $date );
		if ( ! $timestamp ) {
			return self::single_line_text( $date );
		}

		return date_i18n( _x( 'l, F j, Y', 'date format', 'send-to-e-reader' ), $timestamp );
	}

	/**
	 * Build a conversation source URL.
	 *
	 * @param array $conversation Conversation export data.
	 * @return string
	 */
	private static function get_conversation_url( array $conversation ) {
		if ( ! empty( $conversation['id'] ) ) {
			return admin_url( 'post.php?post=' . intval( $conversation['id'] ) . '&action=edit' );
		}

		return home_url( '/' );
	}

	/**
	 * Get the export author name.
	 *
	 * @param array $conversation Conversation export data.
	 * @return string
	 */
	private static function get_author_name( array $conversation ) {
		if ( ! empty( $conversation['author_id'] ) ) {
			$user = get_userdata( intval( $conversation['author_id'] ) );
			if ( $user && ! empty( $user->display_name ) ) {
				return $user->display_name;
			}
		}

		return get_bloginfo( 'name' );
	}

	/**
	 * Build the download filename.
	 *
	 * @param array $conversation Conversation export data.
	 * @param array $format       Format definition.
	 * @return string
	 */
	private static function build_export_filename( array $conversation, array $format ) {
		$title = sanitize_file_name( ! empty( $conversation['title'] ) ? $conversation['title'] : 'conversation' );
		if ( '' === $title ) {
			$title = 'conversation';
		}

		$extension = ! empty( $format['extension'] ) ? $format['extension'] : 'epub';

		return sprintf( 'ai-conversation-%d-%s.%s', ! empty( $conversation['id'] ) ? intval( $conversation['id'] ) : 0, $title, $extension );
	}

	/**
	 * Pretty-print JSON strings when possible.
	 *
	 * @param mixed $text Input text.
	 * @return string
	 */
	private static function format_jsonish_text( $text ) {
		$decoded = json_decode( (string) $text, true );
		if ( JSON_ERROR_NONE === json_last_error() ) {
			return self::json_encode( $decoded );
		}

		return (string) $text;
	}

	/**
	 * Encode JSON consistently.
	 *
	 * @param mixed $value Value to encode.
	 * @return string
	 */
	private static function json_encode( $value ) {
		if ( function_exists( 'wp_json_encode' ) ) {
			return wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}

		return json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Normalize text to a single line.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function single_line_text( $text ) {
		return trim( preg_replace( '/\s+/', ' ', (string) $text ) );
	}

	/**
	 * Convert text to a safe HTML class fragment.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function html_class( $text ) {
		return preg_replace( '/[^a-z0-9_-]/', '-', strtolower( (string) $text ) );
	}

	/**
	 * Escape text for XHTML.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function escape_xml( $text ) {
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $text );

		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8' );
	}
}
