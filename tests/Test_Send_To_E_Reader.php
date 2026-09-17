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
		remove_all_filters( 'send_to_e_reader_user_can_send' );
		unset( $GLOBALS['send_to_e_reader_test_options'] );
		unset( $GLOBALS['send_to_e_reader_test_caps'] );
		unset( $GLOBALS['send_to_e_reader_test_posts'] );
		unset( $GLOBALS['send_to_e_reader_test_post_meta'] );
		unset( $GLOBALS['send_to_e_reader_test_scripts'] );
		unset( $GLOBALS['send_to_e_reader_test_nocache_headers_sent'] );
		unset( $_GET['epubsecret'] );
		unset( $_POST['epubsecret'] );
		parent::tearDown();
	}

	/**
	 * Store e-readers in the (stubbed) option the plugin reads them from.
	 *
	 * @param array $ereaders The e-readers to activate.
	 * @return array The e-readers keyed by their id.
	 */
	private function activate_ereaders( array $ereaders ) {
		$stored = array();
		foreach ( $ereaders as $ereader ) {
			$ereader->active = true;
			$stored[ $ereader->get_id() ] = $ereader;
		}
		update_option( Send_To_E_Reader::EREADERS_OPTION, $stored );
		return $stored;
	}

	/**
	 * Register a post the stubbed get_post() can return.
	 *
	 * @param int    $id        The post id.
	 * @param string $post_type The post type.
	 * @return WP_Post The registered post.
	 */
	private function register_post( $id, $post_type = 'post' ) {
		$post = new WP_Post();
		$post->ID = $id;
		$post->post_type = $post_type;
		$GLOBALS['send_to_e_reader_test_posts'][ $id ] = $post;
		return $post;
	}

	/**
	 * Test that an e-reader keeps its id when it is saved again.
	 */
	public function test_ereaders_keep_their_id_when_saved_again() {
		$download = E_Reader_Download::instantiate_from_field_data( 'abcd1234', array( 'name' => 'Download ePub' ) );
		$this->assertSame( 'abcd1234', $download->get_id() );

		$email = E_Reader_Generic_Email::instantiate_from_field_data(
			'ef567890',
			array(
				'name'  => 'Bedside reader',
				'email' => 'bedside@example.com',
			)
		);
		$this->assertSame( 'ef567890', $email->get_id() );
	}

	/**
	 * Test that a newly added e-reader is given a fresh id.
	 */
	public function test_new_ereaders_get_a_generated_id() {
		$download = E_Reader_Download::instantiate_from_field_data( 'new', array( 'name' => 'Download ePub' ) );
		$this->assertNotSame( 'new', $download->get_id() );
		$this->assertNotEmpty( $download->get_id() );

		$email = E_Reader_Generic_Email::instantiate_from_field_data(
			'new' . E_Reader_Generic_Email::class,
			array(
				'name'  => 'Bedside reader',
				'email' => 'bedside@example.com',
			)
		);
		$this->assertStringStartsNotWith( 'new', $email->get_id() );
		$this->assertNotEmpty( $email->get_id() );
	}

	/**
	 * Test that a single e-reader yields a single, unsuffixed bulk action.
	 */
	public function test_bulk_actions_offers_one_entry_for_a_single_ereader() {
		$this->activate_ereaders( array( new E_Reader_Download( 'Download ePub' ) ) );
		$send_to_e_reader = new Send_To_E_Reader( null );

		$actions = $send_to_e_reader->bulk_actions( array() );

		$this->assertSame( array( 'send-to-e-reader' => 'Send to E-Reader' ), $actions );
	}

	/**
	 * Test that every active e-reader gets its own bulk action.
	 */
	public function test_bulk_actions_offers_an_entry_per_ereader() {
		$ereaders = $this->activate_ereaders(
			array(
				new E_Reader_Kindle( 'My Kindle', 'test@free.kindle.com' ),
				new E_Reader_Download( 'Download ePub' ),
			)
		);
		$send_to_e_reader = new Send_To_E_Reader( null );

		$actions = $send_to_e_reader->bulk_actions( array() );

		$labels = array();
		foreach ( $ereaders as $id => $ereader ) {
			$this->assertArrayHasKey( 'send-to-e-reader-' . $id, $actions );
			$labels[] = $actions[ 'send-to-e-reader-' . $id ];
		}
		$this->assertContains( 'Send to E-Reader: My Kindle', $labels );
		$this->assertContains( 'Send to E-Reader: Download ePub', $labels );
		$this->assertArrayNotHasKey( 'send-to-e-reader', $actions );
	}

	/**
	 * Test that posts the user may not read are not sent.
	 */
	public function test_bulk_action_refuses_posts_the_user_cannot_read() {
		$this->activate_ereaders( array( new E_Reader_Download( 'Download ePub' ) ) );
		$this->register_post( 5 );
		$send_to_e_reader = new Send_To_E_Reader( null );

		$redirect_to = $send_to_e_reader->handle_bulk_actions( 'edit.php', 'send-to-e-reader', array( 5 ) );

		$this->assertSame( 'edit.php?send-to-e-reader=forbidden', $redirect_to );
	}

	/**
	 * Test that a readable post passes the capability check.
	 */
	public function test_current_user_can_send_follows_the_read_post_capability() {
		$post = $this->register_post( 5 );
		$send_to_e_reader = new Send_To_E_Reader( null );

		$this->assertFalse( $send_to_e_reader->current_user_can_send( $post ) );

		$GLOBALS['send_to_e_reader_test_caps'] = array( 'read_post' );
		$this->assertTrue( $send_to_e_reader->current_user_can_send( $post ) );
	}

	/**
	 * Test that the capability check can be overridden by a filter.
	 */
	public function test_current_user_can_send_is_filterable() {
		$post = $this->register_post( 5 );
		$send_to_e_reader = new Send_To_E_Reader( null );

		add_filter(
			'send_to_e_reader_user_can_send',
			function () {
				return true;
			}
		);

		$this->assertTrue( $send_to_e_reader->current_user_can_send( $post ) );
	}

	/**
	 * Test that the row action is hidden from users who may not read the post.
	 */
	public function test_row_action_is_hidden_without_the_capability() {
		$this->activate_ereaders( array( new E_Reader_Download( 'Download ePub' ) ) );
		$post = $this->register_post( 5 );
		$send_to_e_reader = new Send_To_E_Reader( null );

		$this->assertSame( array(), $send_to_e_reader->post_row_actions( array(), $post ) );
	}

	/**
	 * Test that the row action carries the post type of the row it sits in.
	 */
	public function test_row_action_carries_the_post_type() {
		$this->activate_ereaders( array( new E_Reader_Download( 'Download ePub' ) ) );
		$post = $this->register_post( 5, 'book' );
		$GLOBALS['send_to_e_reader_test_caps'] = array( 'read_post' );
		$send_to_e_reader = new Send_To_E_Reader( null );

		$actions = $send_to_e_reader->post_row_actions( array(), $post );

		$this->assertArrayHasKey( 'send-to-e-reader', $actions );
		$this->assertStringContainsString( 'post_type=book', $actions['send-to-e-reader'] );
		$this->assertStringContainsString( 'post[]=5', $actions['send-to-e-reader'] );
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
	 * Test that the plain list shows the original article date when available.
	 */
	public function test_plain_list_shows_original_article_date() {
		$post = new \WP_Post();
		$post->ID = 456;
		$post->post_title = 'Collected Link';
		$post->post_content = '<p>A useful article saved for later.</p>';
		update_post_meta( $post->ID, 'published_time', '2026-06-29T16:44:13+00:00' );

		$args = array(
			'title'     => 'Reading List',
			'unsent'    => array( $post->ID => $post ),
			'posts'     => array( $post->ID => $post ),
			'inputname' => 'epubsecret',
		);

		ob_start();
		include FRIENDS_SEND_TO_E_READER_PLUGIN_DIR . 'templates/plain-list.php';
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Collected Link', $output );
		$this->assertStringContainsString( 'June 29, 2026', $output );
		$this->assertStringContainsString( '1 articles (1 selected)', $output );
		$this->assertStringContainsString( '<form method="post">', $output );
		$this->assertStringContainsString( 'name="epubsecret[]" value="456"', $output );
	}

	/**
	 * Test generated ePub filenames can carry a cache-busting suffix.
	 */
	public function test_epub_filename_accepts_generation_suffix() {
		$method = new \ReflectionMethod( \Send_To_E_Reader\Epub_Builder::class, 'build_book_filename' );
		$method->setAccessible( true );

		$this->assertSame(
			'test-author---reading-list-generated-abc',
			$method->invoke( null, 'Reading List', 'Test Author', 'generated-abc' )
		);
	}

	/**
	 * Test download URLs opt out of page and browser caching.
	 */
	public function test_download_url_prevents_response_caching() {
		update_option( Send_To_E_Reader::DOWNLOAD_PASSWORD_OPTION, 'secret' );
		$_GET['epubsecret'] = 'list';

		$send_to_e_reader = new Send_To_E_Reader( null );

		$this->assertTrue( $send_to_e_reader->enable_download_via_url( false ) );
		$this->assertTrue( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE );
	}

	/**
	 * Test compact list download URLs are accepted.
	 */
	public function test_compact_list_download_url_is_accepted() {
		update_option( Send_To_E_Reader::DOWNLOAD_PASSWORD_OPTION, 'secret' );
		$_GET['epubsecret'] = 'compact';

		$send_to_e_reader = new Send_To_E_Reader( null );

		$this->assertTrue( $send_to_e_reader->enable_download_via_url( false ) );
	}

	/**
	 * Test that a POST to the password-backed picker downloads selected posts only.
	 */
	public function test_picker_post_keeps_an_empty_selection_on_the_list() {
		update_option( Send_To_E_Reader::DOWNLOAD_PASSWORD_OPTION, 'secret' );
		$_GET['epubsecret'] = 'list';
		$send_to_e_reader = new Send_To_E_Reader( null );
		$request = new \ReflectionProperty( Send_To_E_Reader::class, 'download_request' );
		$request->setAccessible( true );

		$this->assertTrue( $send_to_e_reader->enable_download_via_url( false ) );
		$this->assertSame( 'list', $request->getValue( $send_to_e_reader ) );

		$_POST['epubsecret'] = array( '4', '9' );
		$this->assertTrue( $send_to_e_reader->enable_download_via_url( false ) );
		$this->assertSame( array( 4, 9 ), $request->getValue( $send_to_e_reader ) );

		$_GET['epubsecret'] = array( '4', '9' );
		$this->assertFalse( $send_to_e_reader->enable_download_via_url( false ) );
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
