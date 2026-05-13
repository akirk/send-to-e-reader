<?php
/**
 * Tests for the AI Assistant integration.
 *
 * @package Send_To_E_Reader
 */

use PHPUnit\Framework\TestCase;
use Send_To_E_Reader\AI_Assistant_Integration;

/**
 * Test class for AI Assistant integration.
 */
class Test_AI_Assistant_Integration extends TestCase {

	/**
	 * Test that the EPUB export format is registered.
	 */
	public function test_registers_epub_export_format() {
		$formats = AI_Assistant_Integration::register_export_formats( array() );

		$this->assertArrayHasKey( 'epub', $formats );
		$this->assertSame( 'EPUB', $formats['epub']['label'] );
		$this->assertSame( 'epub', $formats['epub']['extension'] );
		$this->assertSame( 'application/epub+zip', $formats['epub']['mime'] );
		$this->assertIsCallable( $formats['epub']['callback'] );
	}

	/**
	 * Test that a conversation can be exported as an EPUB binary.
	 */
	public function test_exports_conversation_epub() {
		$format = AI_Assistant_Integration::register_export_formats( array() )['epub'];

		$result = AI_Assistant_Integration::export_conversation_epub(
			array(
				'id'                 => 123,
				'title'              => 'Homepage copy edits',
				'summary'            => 'The user asked for a shorter hero headline.',
				'message_count'      => 2,
				'provider'           => 'openai',
				'model'              => 'gpt-4o',
				'created'            => '2026-05-13 10:00:00',
				'modified'           => '2026-05-13 10:04:00',
				'author_id'          => 1,
				'include_tool_calls' => false,
				'messages'           => array(
					array(
						'role'    => 'user',
						'content' => 'Make the homepage hero headline shorter.',
					),
					array(
						'role'    => 'assistant',
						'content' => array(
							array(
								'type' => 'text',
								'text' => 'Try "Build faster with WordPress" as the headline.',
							),
						),
					),
				),
			),
			$format
		);

		$this->assertSame( 'application/epub+zip', $result['mime'] );
		$this->assertSame( 'ai-conversation-123-Homepage-copy-edits.epub', $result['filename'] );
		$this->assertStringStartsWith( 'PK', $result['content'] );

		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ZipArchive is required to inspect the generated ePub.' );
		}

		$path = tempnam( sys_get_temp_dir(), 'ai-assistant-epub-' );
		file_put_contents( $path, $result['content'] );

		$zip = new ZipArchive();
		$this->assertTrue( $zip->open( $path ) );
		$this->assertNotFalse( $zip->locateName( 'OEBPS/conversation.html' ) );
		$this->assertFalse( $zip->locateName( 'OEBPS/message-001.html' ) );

		$chapter = $zip->getFromName( 'OEBPS/conversation.html' );
		$zip->close();
		unlink( $path );

		$this->assertStringContainsString( 'Make the homepage hero headline shorter.', $chapter );
		$this->assertStringContainsString( 'Try &quot;Build faster with WordPress&quot; as the headline.', $chapter );
		$this->assertStringContainsString( 'Test User | Wednesday, May 13, 2026', $chapter );
		$this->assertStringContainsString( 'This conversation contains 2 messages and was created with openai using gpt-4o.', $chapter );
		$this->assertStringContainsString( '<h3>Test User</h3>', $chapter );
		$this->assertStringContainsString( '<h3>Assistant</h3>', $chapter );
		$this->assertStringNotContainsString( '<table>', $chapter );
		$this->assertStringNotContainsString( '<th>Messages</th>', $chapter );
		$this->assertStringNotContainsString( '<th>Provider</th>', $chapter );
		$this->assertStringNotContainsString( '<th>Model</th>', $chapter );
		$this->assertStringNotContainsString( 'Conversation ID', $chapter );
		$this->assertStringNotContainsString( 'Created', $chapter );
		$this->assertStringNotContainsString( 'Modified', $chapter );
		$this->assertStringNotContainsString( '<h3>User</h3>', $chapter );
		$this->assertStringNotContainsString( 'User 1', $chapter );
		$this->assertStringNotContainsString( 'Assistant 2', $chapter );
	}
}
