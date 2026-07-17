<?php
/**
 * Tests for the Send_To_E_Reader class.
 *
 * @package Send_To_E_Reader
 */

use PHPUnit\Framework\TestCase;
use Send_To_E_Reader\Send_To_E_Reader;
use Send_To_E_Reader\E_Reader_Download;
use Send_To_E_Reader\E_Reader_Kindle;
use Send_To_E_Reader\E_Reader_Pocketbook;
use Send_To_E_Reader\E_Reader_Generic_Email;

/**
 * Test class for Send_To_E_Reader.
 */
class Test_Send_To_E_Reader extends TestCase {

	public function tearDown(): void {
		remove_all_filters( 'friends_override_author_name' );
		remove_all_filters( 'send_to_e_reader_post_content' );
		remove_all_filters( 'static_archive_post_types' );
		remove_all_filters( 'static_archive_post_html' );
		parent::tearDown();
	}

	/**
	 * Test that Send_To_E_Reader can be instantiated without Friends.
	 */
	public function test_can_instantiate_without_friends() {
		$send_to_e_reader = new Send_To_E_Reader( null );
		$this->assertInstanceOf( Send_To_E_Reader::class, $send_to_e_reader );
	}

	/**
	 * Test that Send_To_E_Reader can be instantiated with Friends.
	 */
	public function test_can_instantiate_with_friends() {
		$friends = \Friends\Friends::get_instance();
		$send_to_e_reader = new Send_To_E_Reader( $friends );
		$this->assertInstanceOf( Send_To_E_Reader::class, $send_to_e_reader );
	}

	/**
	 * Test friends_is_available returns true when Friends class exists.
	 */
	public function test_friends_is_available_returns_true() {
		$send_to_e_reader = new Send_To_E_Reader( null );
		// Friends\Friends class is mocked in bootstrap, so it should be available.
		$this->assertTrue( $send_to_e_reader->friends_is_available() );
	}

	/**
	 * Test get_template_loader returns an object.
	 */
	public function test_get_template_loader_returns_object() {
		$send_to_e_reader = new Send_To_E_Reader( null );
		$loader = $send_to_e_reader->get_template_loader();
		$this->assertIsObject( $loader );
	}

	/**
	 * Test get_post_author_name returns author name for a post.
	 */
	public function test_get_post_author_name_returns_string() {
		$send_to_e_reader = new Send_To_E_Reader( null );

		$post = new \WP_Post();
		$post->ID = 1;
		$post->post_author = 1;
		$post->post_title = 'Test Post';

		$author_name = $send_to_e_reader->get_post_author_name( $post );
		$this->assertIsString( $author_name );
		$this->assertNotEmpty( $author_name );
	}

	/**
	 * Test that the ePub book author prefers post collection override names.
	 */
	public function test_ebook_author_filter_prefers_post_collection_override_name() {
		add_filter(
			'friends_override_author_name',
			function ( $override_name, $author_name, $post_id ) {
				if ( 123 === $post_id ) {
					return 'Weekly Reading';
				}

				return $override_name;
			},
			10,
			3
		);

		$send_to_e_reader = new Send_To_E_Reader( null );
		$post = new \WP_Post();
		$post->ID = 123;
		$post->post_author = 1;

		$this->assertSame(
			'Weekly Reading',
			apply_filters( 'send_to_e_reader_ebook_author', 'Test Author', array( $post ), null, 'Test Author' )
		);
	}

	/**
	 * Test that the ePub book author is unchanged without a post collection override.
	 */
	public function test_ebook_author_filter_keeps_existing_author_without_override_name() {
		$send_to_e_reader = new Send_To_E_Reader( null );
		$post = new \WP_Post();
		$post->ID = 123;
		$post->post_author = 1;

		$this->assertSame(
			'Test Author',
			apply_filters( 'send_to_e_reader_ebook_author', 'Test Author', array( $post ), null, 'Test Author' )
		);
	}

	/**
	 * Test that Static Archive-enabled post types reuse Static Archive HTML.
	 */
	public function test_static_archive_post_content_filter_reuses_static_archive_html() {
		add_filter(
			'static_archive_post_types',
			function ( $post_types ) {
				$post_types[] = 'book-entry';
				return $post_types;
			}
		);
		add_filter(
			'static_archive_post_html',
			function ( $html, $post ) {
				if ( 'book-entry' === $post->post_type ) {
					return '<h2>Archive Body</h2><p>Structured export.</p>';
				}

				return $html;
			},
			10,
			3
		);

		$send_to_e_reader = new Send_To_E_Reader( null );
		$post = new \WP_Post();
		$post->post_type = 'book-entry';
		$post->post_content = '<p>Plain post body.</p>';

		$this->assertSame(
			'<h2>Archive Body</h2><p>Structured export.</p>',
			apply_filters( 'send_to_e_reader_post_content', $post->post_content, $post, 'epub' )
		);
	}

	/**
	 * Test that post types without Static Archive support keep their normal content.
	 */
	public function test_static_archive_post_content_filter_ignores_unsupported_post_types() {
		add_filter(
			'static_archive_post_types',
			function () {
				return array( 'book-entry' );
			}
		);
		add_filter(
			'static_archive_post_html',
			function () {
				return '<h2>Archive Body</h2>';
			},
			10,
			3
		);

		$send_to_e_reader = new Send_To_E_Reader( null );
		$post = new \WP_Post();
		$post->post_type = 'post';
		$post->post_content = '<p>Article body.</p>';

		$this->assertSame(
			'<p>Article body.</p>',
			apply_filters( 'send_to_e_reader_post_content', $post->post_content, $post, 'epub' )
		);
	}

	/**
	 * Test that e-reader can be registered.
	 */
	public function test_can_register_ereader() {
		$send_to_e_reader = new Send_To_E_Reader( null );
		$send_to_e_reader->register_ereader( E_Reader_Download::class );

		// No exception means success - the class stores it internally.
		$this->assertTrue( true );
	}
}
