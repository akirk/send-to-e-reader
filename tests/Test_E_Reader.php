<?php
/**
 * Tests for the E_Reader classes.
 *
 * @package Send_To_E_Reader
 */

use PHPUnit\Framework\TestCase;
use Send_To_E_Reader\Epub_Builder;
use Send_To_E_Reader\E_Reader_Download;
use Send_To_E_Reader\E_Reader_Kindle;
use Send_To_E_Reader\E_Reader_Pocketbook;
use Send_To_E_Reader\E_Reader_Generic_Email;

/**
 * Test class for E_Reader implementations.
 */
class Test_E_Reader extends TestCase {

	public function tearDown(): void {
		remove_all_filters( 'friends_override_author_name' );
		unset( $GLOBALS['_test_upload_basedir'], $GLOBALS['_test_upload_baseurl'] );
		parent::tearDown();
	}

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
	 * Test override author names replace the user author in ePub chapter bylines.
	 */
	public function test_author_override_replaces_post_author_name() {
		add_filter(
			'friends_override_author_name',
			function () {
				return 'Zetaphor';
			},
			10,
			3
		);

		$post = new \WP_Post();
		$post->ID = 123;
		$post->post_author = 1;

		$ereader = new E_Reader_Download( 'Test' );
		$method = new \ReflectionMethod( $ereader, 'update_author_name' );
		$method->setAccessible( true );

		$this->assertSame( 'Zetaphor', $method->invoke( $ereader, $post ) );
		$this->assertSame( 'Zetaphor', $post->author_name );
	}

	/**
	 * Test same-site upload image URLs are embedded in generated ePubs.
	 */
	public function test_epub_builder_embeds_same_site_upload_images() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ZipArchive is required to inspect the generated ePub.' );
		}

		$upload_dir = sys_get_temp_dir() . '/send-to-e-reader-test-uploads';
		if ( ! file_exists( $upload_dir ) ) {
			mkdir( $upload_dir, 0777, true );
		}

		$image_path = $upload_dir . '/cover.svg';
		file_put_contents(
			$image_path,
			'<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"><rect width="20" height="20" fill="red"/></svg>'
		);

		$GLOBALS['_test_upload_basedir'] = $upload_dir;
		$GLOBALS['_test_upload_baseurl'] = 'https://example.com/wp-content/uploads';

		$content = Epub_Builder::build_content(
			'Image Test',
			'Test Author',
			array(
				array(
					'title'    => 'Chapter',
					'filename' => 'chapter.html',
					'content'  => Epub_Builder::wrap_xhtml(
						'Chapter',
						'Test Author',
						'<figure><img src="https://example.com/wp-content/uploads/cover.svg?ver=1" alt="Cover" /></figure>'
					),
				),
			)
		);

		$path = tempnam( sys_get_temp_dir(), 'send-to-e-reader-image-epub-' );
		file_put_contents( $path, $content );

		$zip = new ZipArchive();
		$this->assertTrue( $zip->open( $path ) );

		$chapter = $zip->getFromName( 'OEBPS/chapter.html' );
		$image_index = false;
		for ( $i = 0; $i < $zip->numFiles; ++$i ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$name = $zip->getNameIndex( $i );
			if ( false !== strpos( $name, 'cover.svg' ) ) {
				$image_index = $i;
				break;
			}
		}

		$zip->close();
		unlink( $path );
		unlink( $image_path );
		rmdir( $upload_dir );

		$this->assertNotFalse( $chapter );
		$this->assertNotFalse( $image_index, 'Expected the local upload image to be packaged in the EPUB.' );
		$this->assertStringNotContainsString( 'https://example.com/wp-content/uploads/cover.svg', $chapter );
		$this->assertStringContainsString( 'cover.svg', $chapter );
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
