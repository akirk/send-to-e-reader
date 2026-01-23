<?php
/**
 * Tests for the E_Reader classes.
 *
 * @package Send_To_E_Reader
 */

use PHPUnit\Framework\TestCase;
use Send_To_E_Reader\E_Reader_Download;
use Send_To_E_Reader\E_Reader_Kindle;
use Send_To_E_Reader\E_Reader_Pocketbook;
use Send_To_E_Reader\E_Reader_Generic_Email;

/**
 * Test class for E_Reader implementations.
 */
class Test_E_Reader extends TestCase {

	/**
	 * Test E_Reader_Download instantiation.
	 */
	public function test_e_reader_download_instantiation() {
		$ereader = new E_Reader_Download( 'Test Download' );
		$this->assertInstanceOf( E_Reader_Download::class, $ereader );
		$this->assertEquals( 'Test Download', $ereader->get_name() );
	}

	/**
	 * Test E_Reader_Download generates an ID.
	 */
	public function test_e_reader_download_generates_id() {
		$ereader = new E_Reader_Download( 'Test Download' );
		$id = $ereader->get_id();
		$this->assertIsString( $id );
		$this->assertNotEmpty( $id );
	}

	/**
	 * Test E_Reader_Generic_Email instantiation.
	 */
	public function test_e_reader_generic_email_instantiation() {
		$ereader = new E_Reader_Generic_Email( 'My E-Reader', 'test@example.com' );
		$this->assertInstanceOf( E_Reader_Generic_Email::class, $ereader );
		$this->assertEquals( 'My E-Reader', $ereader->get_name() );
	}

	/**
	 * Test E_Reader_Generic_Email generates ID based on email.
	 */
	public function test_e_reader_generic_email_generates_id() {
		$ereader = new E_Reader_Generic_Email( 'My E-Reader', 'test@example.com' );
		$id = $ereader->get_id();
		$this->assertIsString( $id );
		$this->assertNotEmpty( $id );
	}

	/**
	 * Test E_Reader_Generic_Email returns null ID when email is empty.
	 */
	public function test_e_reader_generic_email_null_id_without_email() {
		$ereader = new E_Reader_Generic_Email( 'My E-Reader', '' );
		$id = $ereader->get_id();
		$this->assertNull( $id );
	}

	/**
	 * Test E_Reader_Kindle instantiation.
	 */
	public function test_e_reader_kindle_instantiation() {
		$ereader = new E_Reader_Kindle( 'My Kindle', 'user@free.kindle.com' );
		$this->assertInstanceOf( E_Reader_Kindle::class, $ereader );
		$this->assertEquals( 'My Kindle', $ereader->get_name() );
	}

	/**
	 * Test E_Reader_Kindle is subclass of E_Reader_Generic_Email.
	 */
	public function test_e_reader_kindle_extends_generic_email() {
		$ereader = new E_Reader_Kindle( 'My Kindle', 'user@free.kindle.com' );
		$this->assertInstanceOf( E_Reader_Generic_Email::class, $ereader );
	}

	/**
	 * Test E_Reader_Pocketbook instantiation.
	 */
	public function test_e_reader_pocketbook_instantiation() {
		$ereader = new E_Reader_Pocketbook( 'My Pocketbook', 'user@pbsync.com' );
		$this->assertInstanceOf( E_Reader_Pocketbook::class, $ereader );
		$this->assertEquals( 'My Pocketbook', $ereader->get_name() );
	}

	/**
	 * Test E_Reader_Pocketbook is subclass of E_Reader_Generic_Email.
	 */
	public function test_e_reader_pocketbook_extends_generic_email() {
		$ereader = new E_Reader_Pocketbook( 'My Pocketbook', 'user@pbsync.com' );
		$this->assertInstanceOf( E_Reader_Generic_Email::class, $ereader );
	}

	/**
	 * Test E_Reader_Download::instantiate_from_field_data.
	 */
	public function test_e_reader_download_instantiate_from_field_data() {
		$data = array( 'name' => 'Downloaded ePub' );
		$ereader = E_Reader_Download::instantiate_from_field_data( 'test-id', $data );

		$this->assertInstanceOf( E_Reader_Download::class, $ereader );
		$this->assertEquals( 'Downloaded ePub', $ereader->get_name() );
	}

	/**
	 * Test E_Reader_Generic_Email::instantiate_from_field_data.
	 */
	public function test_e_reader_generic_email_instantiate_from_field_data() {
		$data = array(
			'name'  => 'Email Reader',
			'email' => 'reader@example.com',
		);
		$ereader = E_Reader_Generic_Email::instantiate_from_field_data( 'test-id', $data );

		$this->assertInstanceOf( E_Reader_Generic_Email::class, $ereader );
		$this->assertEquals( 'Email Reader', $ereader->get_name() );
	}

	/**
	 * Test E_Reader_Kindle::get_defaults has kindle placeholder.
	 */
	public function test_e_reader_kindle_defaults_has_kindle_placeholder() {
		$defaults = E_Reader_Kindle::get_defaults();
		$this->assertArrayHasKey( 'email_placeholder', $defaults );
		$this->assertStringContainsString( 'kindle', $defaults['email_placeholder'] );
	}

	/**
	 * Test E_Reader_Pocketbook::get_defaults has pocketbook placeholder.
	 */
	public function test_e_reader_pocketbook_defaults_has_pocketbook_placeholder() {
		$defaults = E_Reader_Pocketbook::get_defaults();
		$this->assertArrayHasKey( 'email_placeholder', $defaults );
		$this->assertStringContainsString( 'pbsync', $defaults['email_placeholder'] );
	}

	/**
	 * Test E_Reader active property can be set.
	 */
	public function test_e_reader_active_property() {
		$ereader = new E_Reader_Download( 'Test' );
		$this->assertNull( $ereader->active );

		$ereader->active = true;
		$this->assertTrue( $ereader->active );

		$ereader->active = false;
		$this->assertFalse( $ereader->active );
	}

	/**
	 * Test E_Reader NAME constant.
	 */
	public function test_e_reader_name_constants() {
		$this->assertEquals( 'Download ePub', E_Reader_Download::NAME );
		$this->assertEquals( 'ePub via E-Mail', E_Reader_Generic_Email::NAME );
		$this->assertEquals( 'Kindle', E_Reader_Kindle::NAME );
		$this->assertEquals( 'Pocketbook', E_Reader_Pocketbook::NAME );
	}
}
