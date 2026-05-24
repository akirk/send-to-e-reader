<?php
/**
 * Tests for the WordPress Abilities API integration.
 *
 * @package Send_To_E_Reader
 */

use PHPUnit\Framework\TestCase;
use Send_To_E_Reader\Abilities;
use Send_To_E_Reader\E_Reader;
use Send_To_E_Reader\E_Reader_Download;
use Send_To_E_Reader\E_Reader_Kindle;
use Send_To_E_Reader\Send_To_E_Reader;

/**
 * Test e-reader used by ability callbacks.
 */
class Send_To_E_Reader_Test_Ability_E_Reader extends E_Reader {
	private $id;
	private $name;
	public $last_posts = array();
	public $last_title = null;
	public $last_author = null;

	public function __construct( $id, $name ) {
		$this->id = $id;
		$this->name = $name;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_name() {
		return $this->name;
	}

	public function render_input() {
	}

	public static function render_template( $data = array() ) {
	}

	public static function instantiate_from_field_data( $id, $data ) {
		return new self( $id, $data['name'] );
	}

	public function send_posts( array $posts, $title = null, $author = null ) {
		$this->last_posts = $posts;
		$this->last_title = $title;
		$this->last_author = $author;

		return array(
			'send-to-e-reader' => 'success',
			'title'            => $title ? $title : 'Generated title',
			'author'           => $author ? $author : 'Generated author',
			'url'              => 'https://example.com/uploads/book.epub',
			'file'             => '/tmp/private-book.epub',
		);
	}
}

/**
 * Test class for Abilities.
 */
class Test_Abilities extends TestCase {
	private $plugin;
	private $abilities;

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['wp_test_options'] = array();
		$GLOBALS['wp_test_post_meta'] = array();
		$GLOBALS['wp_test_posts'] = array();
		$GLOBALS['wp_test_query_posts'] = array();
		$GLOBALS['wp_test_abilities'] = array();
		$GLOBALS['wp_test_ability_categories'] = array();
		$GLOBALS['wp_test_current_user_caps'] = array();

		$this->plugin = new Send_To_E_Reader( null );
		$this->abilities = new Abilities( $this->plugin );
	}

	protected function tearDown(): void {
		$GLOBALS['wp_test_options'] = array();
		$GLOBALS['wp_test_post_meta'] = array();
		$GLOBALS['wp_test_posts'] = array();
		$GLOBALS['wp_test_query_posts'] = array();
		$GLOBALS['wp_test_abilities'] = array();
		$GLOBALS['wp_test_ability_categories'] = array();
		$GLOBALS['wp_test_current_user_caps'] = array();

		parent::tearDown();
	}

	/**
	 * Test that ability categories and abilities are registered.
	 */
	public function test_registers_abilities() {
		$this->abilities->register_ability_category();
		$this->abilities->register_abilities();

		$this->assertArrayHasKey( 'send-to-e-reader', $GLOBALS['wp_test_ability_categories'] );
		$this->assertArrayHasKey( 'send-to-e-reader/list-ereaders', $GLOBALS['wp_test_abilities'] );
		$this->assertArrayHasKey( 'send-to-e-reader/list-posts', $GLOBALS['wp_test_abilities'] );
		$this->assertArrayHasKey( 'send-to-e-reader/send-posts', $GLOBALS['wp_test_abilities'] );
		$this->assertArrayHasKey( 'send-to-e-reader/mark-posts-sent', $GLOBALS['wp_test_abilities'] );
		$this->assertArrayHasKey( 'send-to-e-reader/mark-posts-new', $GLOBALS['wp_test_abilities'] );
		$this->assertTrue( $GLOBALS['wp_test_abilities']['send-to-e-reader/list-ereaders']['meta']['annotations']['readonly'] );
		$this->assertFalse( $GLOBALS['wp_test_abilities']['send-to-e-reader/send-posts']['meta']['annotations']['destructive'] );
	}

	/**
	 * Test that AI Assistant domain hints and instructions are available.
	 */
	public function test_registers_ai_assistant_domain_and_instructions() {
		$domains = $this->abilities->ai_assistant_ability_domains( array() );

		$this->assertArrayHasKey( 'send-to-e-reader', $domains );
		$this->assertStringContainsString( 'Kindle', $domains['send-to-e-reader'] );

		$instructions = $this->abilities->ai_assistant_ability_instructions( '', 'send-to-e-reader/send-posts', array(), array() );
		$this->assertStringContainsString( 'download_url', $instructions );
	}

	/**
	 * Test listing e-readers.
	 */
	public function test_list_ereaders_filters_inactive_targets() {
		$download = new E_Reader_Download( 'Download ePub' );
		$download->active = true;
		$kindle = new E_Reader_Kindle( 'Kindle', 'reader@free.kindle.com' );
		$kindle->active = false;

		$GLOBALS['wp_test_options'][ Send_To_E_Reader::EREADERS_OPTION ] = array(
			'download' => $download,
			'kindle'   => $kindle,
		);

		$result = $this->abilities->list_ereaders( array( 'include_inactive' => false ) );

		$this->assertSame( 1, $result['count'] );
		$this->assertSame( 1, $result['active_count'] );
		$this->assertSame( 'download', $result['ereaders'][0]['id'] );
		$this->assertSame( 'download', $result['ereaders'][0]['delivery'] );
		$this->assertTrue( $result['ereaders'][0]['returns_download_url'] );
	}

	/**
	 * Test listing posts by sent status.
	 */
	public function test_list_posts_filters_by_sent_status() {
		$sent = $this->create_post( 11, 'Already Sent' );
		$unsent = $this->create_post( 12, 'Fresh Article' );
		$GLOBALS['wp_test_posts'] = array(
			11 => $sent,
			12 => $unsent,
		);
		$GLOBALS['wp_test_query_posts'] = array( $sent, $unsent );
		update_post_meta( 11, Send_To_E_Reader::POST_META, 1234567890 );

		$result = $this->abilities->list_posts( array( 'sent_status' => 'unsent' ) );

		$this->assertSame( 1, $result['count'] );
		$this->assertSame( 12, $result['posts'][0]['id'] );
		$this->assertSame( 'Fresh Article', $result['posts'][0]['title'] );
		$this->assertFalse( $result['posts'][0]['sent'] );
	}

	/**
	 * Test sending posts through an ability.
	 */
	public function test_send_posts_uses_configured_ereader_and_marks_posts_sent() {
		$reader = new Send_To_E_Reader_Test_Ability_E_Reader( 'reader-1', 'Reading Device' );
		$reader->active = true;
		$GLOBALS['wp_test_options'][ Send_To_E_Reader::EREADERS_OPTION ] = array(
			'reader-1' => $reader,
		);

		$GLOBALS['wp_test_posts'] = array(
			21 => $this->create_post( 21, 'First Article' ),
			22 => $this->create_post( 22, 'Second Article' ),
		);

		$result = $this->abilities->send_posts(
			array(
				'post_ids'  => array( 21, 22 ),
				'ereader_id' => 'reader-1',
				'title'      => 'Weekend Reading',
				'author'     => 'Test Author',
			)
		);

		$this->assertSame( 2, $result['sent_count'] );
		$this->assertTrue( $result['marked_sent'] );
		$this->assertSame( 'Reading Device', $result['ereader']['name'] );
		$this->assertSame( 'https://example.com/uploads/book.epub', $result['download_url'] );
		$this->assertArrayNotHasKey( 'file', $result['result'] );
		$this->assertNotEmpty( get_post_meta( 21, Send_To_E_Reader::POST_META, true ) );
		$this->assertSame( 'Weekend Reading', $reader->last_title );
	}

	/**
	 * Test changing sent markers through abilities.
	 */
	public function test_mark_posts_sent_and_new() {
		$GLOBALS['wp_test_posts'] = array(
			31 => $this->create_post( 31, 'Marker Test' ),
		);

		$sent = $this->abilities->mark_posts_sent( array( 'post_ids' => array( 31 ) ) );

		$this->assertSame( 'sent', $sent['status'] );
		$this->assertSame( 1, $sent['marked_count'] );
		$this->assertNotEmpty( get_post_meta( 31, Send_To_E_Reader::POST_META, true ) );

		$new = $this->abilities->mark_posts_new( array( 'post_ids' => array( 31 ) ) );

		$this->assertSame( 'new', $new['status'] );
		$this->assertSame( 1, $new['marked_count'] );
		$this->assertSame( '', get_post_meta( 31, Send_To_E_Reader::POST_META, true ) );
	}

	/**
	 * Create a test post.
	 *
	 * @param int    $id    Post ID.
	 * @param string $title Post title.
	 * @return WP_Post
	 */
	private function create_post( $id, $title ) {
		$post = new WP_Post();
		$post->ID = $id;
		$post->post_title = $title;
		$post->post_author = 1;
		$post->post_type = 'post';
		$post->post_status = 'publish';

		return $post;
	}
}
