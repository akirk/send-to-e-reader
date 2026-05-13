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
	 * Test that e-reader can be registered.
	 */
	public function test_can_register_ereader() {
		$send_to_e_reader = new Send_To_E_Reader( null );
		$send_to_e_reader->register_ereader( E_Reader_Download::class );

		// No exception means success - the class stores it internally.
		$this->assertTrue( true );
	}

	/**
	 * Test that ?epub enables the current query download mode.
	 */
	public function test_bare_epub_parameter_enables_current_query_download() {
		$send_to_e_reader = new Send_To_E_Reader( null );
		$_GET['epub'] = '';
		$GLOBALS['is_user_logged_in'] = true;

		try {
			$this->assertTrue( $send_to_e_reader->enable_download_via_url( false ) );
			$this->assertSame( 'current', $this->get_private_property( $send_to_e_reader, 'download_request' ) );
		} finally {
			unset( $_GET['epub'] );
			unset( $GLOBALS['is_user_logged_in'] );
		}
	}

	/**
	 * Test that ?epub does not enable downloads for logged-out visitors.
	 */
	public function test_bare_epub_parameter_requires_logged_in_user() {
		$send_to_e_reader = new Send_To_E_Reader( null );
		$_GET['epub'] = '';

		try {
			$this->assertFalse( $send_to_e_reader->enable_download_via_url( false ) );
			$this->assertFalse( $this->get_private_property( $send_to_e_reader, 'download_request' ) );
		} finally {
			unset( $_GET['epub'] );
		}
	}

	/**
	 * Test that passworded ePub URLs still enable explicit download modes.
	 */
	public function test_passworded_epub_parameter_still_enables_explicit_download_mode() {
		$send_to_e_reader = new Send_To_E_Reader( null );
		$download_password = hash( 'crc32', wp_salt( 'nonce' ), false );
		$_GET[ 'epub' . $download_password ] = 'last';

		try {
			$this->assertTrue( $send_to_e_reader->enable_download_via_url( false ) );
			$this->assertSame( 'last', $this->get_private_property( $send_to_e_reader, 'download_request' ) );
		} finally {
			unset( $_GET[ 'epub' . $download_password ] );
		}
	}

	/**
	 * Test that bare ?epub does not make Friends queries viewable by itself.
	 */
	public function test_bare_epub_parameter_does_not_enable_friends_query_viewability() {
		$send_to_e_reader = new Send_To_E_Reader( null );
		$_GET['epub'] = '';
		$GLOBALS['is_user_logged_in'] = true;

		try {
			$this->assertFalse( $send_to_e_reader->enable_passworded_download_via_url( false ) );
			$this->assertSame( 'current', $this->get_private_property( $send_to_e_reader, 'download_request' ) );
		} finally {
			unset( $_GET['epub'] );
			unset( $GLOBALS['is_user_logged_in'] );
		}
	}

	/**
	 * Get a private property value from an object.
	 *
	 * @param object $object   The object.
	 * @param string $property The property name.
	 * @return mixed
	 */
	private function get_private_property( $object, $property ) {
		$reflection = new ReflectionClass( $object );
		$property = $reflection->getProperty( $property );
		$property->setAccessible( true );

		return $property->getValue( $object );
	}
}
