<?php
/**
 * Tests for the Send_To_E_Reader class.
 *
 * @package Friends_Send_To_E_Reader
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
}
