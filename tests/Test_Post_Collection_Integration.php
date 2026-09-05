<?php
/**
 * Tests for the Post Collection integration.
 *
 * @package Send_To_E_Reader
 */

use PHPUnit\Framework\TestCase;
use Send_To_E_Reader\E_Reader_Download;
use Send_To_E_Reader\E_Reader_Generic_Email;
use Send_To_E_Reader\Post_Collection_Integration;
use Send_To_E_Reader\Send_To_E_Reader;

/**
 * Test class for Post_Collection_Integration.
 */
class Test_Post_Collection_Integration extends TestCase {

	public function tearDown(): void {
		remove_all_filters( 'send_to_e_reader_user_can_send' );
		unset( $GLOBALS['send_to_e_reader_test_options'] );
		unset( $GLOBALS['send_to_e_reader_test_caps'] );
		unset( $GLOBALS['send_to_e_reader_test_posts'] );
		unset( $_GET['epubsecret'] );
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
			$ereader->active              = true;
			$stored[ $ereader->get_id() ] = $ereader;
		}
		update_option( Send_To_E_Reader::EREADERS_OPTION, $stored );

		return $stored;
	}

	/**
	 * Register a post the stubbed get_post() can return.
	 *
	 * @param int $id The post id.
	 * @return WP_Post The registered post.
	 */
	private function register_post( $id ) {
		$post                                          = new WP_Post();
		$post->ID                                      = $id;
		$post->post_type                               = 'collected_post';
		$post->post_title                              = 'A collected article';
		$GLOBALS['send_to_e_reader_test_posts'][ $id ] = $post;

		return $post;
	}

	/**
	 * Call a private method on the integration.
	 *
	 * @param Post_Collection_Integration $integration The integration.
	 * @param string                      $method      The method name.
	 * @param array                       $args        The arguments.
	 * @return mixed The return value.
	 */
	private function call( $integration, $method, array $args = array() ) {
		$reflection = new ReflectionMethod( Post_Collection_Integration::class, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( $integration, $args );
	}

	private function get_integration() {
		return new Post_Collection_Integration( new Send_To_E_Reader( null ) );
	}

	/**
	 * A stand-in for the Post Collection app that records what it was asked for.
	 *
	 * @return object The spy.
	 */
	private function get_app_spy() {
		return new class() {
			public $args = array();

			public function get_unread_posts( $collection = null, array $args = array() ) {
				$this->args = $args;

				return array();
			}

			public function query_app_posts( $collection = null, array $args = array() ) {
				$this->args = $args;

				return array();
			}
		};
	}

	/**
	 * Test that an item gets one action per configured e-reader.
	 */
	public function test_an_item_gets_one_action_per_ereader() {
		$this->activate_ereaders(
			array(
				new E_Reader_Download( 'Download ePub' ),
				new E_Reader_Generic_Email( 'Bedside reader', 'bedside@example.com' ),
			)
		);
		$GLOBALS['send_to_e_reader_test_caps'] = array( 'read_post' );

		$integration = $this->get_integration();
		$post        = $this->register_post( 7 );

		ob_start();
		$integration->item_actions( $post, 'links' );
		$output = ob_get_clean();

		$this->assertSame( 2, substr_count( $output, 'data-e-reader-send' ) );
		$this->assertStringContainsString( 'data-post-id="7"', $output );
		// In a list the labels stay short.
		$this->assertStringContainsString( '>ePub</button>', $output );
		$this->assertStringContainsString( '>To Bedside reader</button>', $output );
	}

	/**
	 * Test that the detail view spells the actions out.
	 */
	public function test_the_detail_view_spells_the_actions_out() {
		$this->activate_ereaders( array( new E_Reader_Generic_Email( 'Bedside reader', 'bedside@example.com' ) ) );
		$GLOBALS['send_to_e_reader_test_caps'] = array( 'read_post' );

		$integration = $this->get_integration();

		ob_start();
		$integration->item_actions( $this->register_post( 7 ), 'detail' );
		$output = ob_get_clean();

		$this->assertStringContainsString( '>Send to Bedside reader</button>', $output );
	}

	/**
	 * Test that a post the user may not read gets no action.
	 */
	public function test_no_action_without_the_capability_to_read_the_post() {
		$this->activate_ereaders( array( new E_Reader_Download( 'Download ePub' ) ) );

		$integration = $this->get_integration();

		ob_start();
		$integration->item_actions( $this->register_post( 7 ), 'links' );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Test that the selection actions send whatever is ticked in the app.
	 */
	public function test_the_selection_actions_act_on_the_selection() {
		$this->activate_ereaders( array( new E_Reader_Download( 'Download ePub' ) ) );

		$integration = $this->get_integration();

		ob_start();
		$integration->selection_actions( 'collection', null );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'data-selection="selected"', $output );
		$this->assertStringContainsString( '>Download ePub</button>', $output );
	}

	/**
	 * Test that a named selection takes everything unless a number is given.
	 */
	public function test_a_named_selection_takes_what_it_is_asked_for() {
		$integration = $this->get_integration();
		$app         = $this->get_app_spy();

		// No number: the selection means what its name says.
		$this->call( $integration, 'get_posts', array( $app, 'unread', null, true ) );
		$this->assertArrayNotHasKey( 'posts_per_page', $app->args );
		$this->assertTrue( $app->args['post_collection_include_private'] );

		$this->call( $integration, 'get_posts', array( $app, 'all' ) );
		$this->assertArrayNotHasKey( 'posts_per_page', $app->args );

		// The last 10 stay the last 10.
		$this->call( $integration, 'get_posts', array( $app, 'last' ) );
		$this->assertSame( 10, $app->args['posts_per_page'] );

		// A number, from the dialog or from the URL, is honoured.
		$this->call( $integration, 'get_posts', array( $app, 'unread', null, false, 5 ) );
		$this->assertSame( 5, $app->args['posts_per_page'] );

		// Articles ticked off by hand are taken as they are.
		$this->call( $integration, 'get_posts', array( $app, array( 4, 9 ) ) );
		$this->assertArrayNotHasKey( 'posts_per_page', $app->args );
		$this->assertSame( array( 4, 9 ), $app->args['post__in'] );
	}

	/**
	 * Test which values the download URL accepts.
	 */
	public function test_the_download_url_only_accepts_known_selections() {
		update_option( Send_To_E_Reader::DOWNLOAD_PASSWORD_OPTION, 'secret' );
		$integration = $this->get_integration();

		$this->assertFalse( $this->call( $integration, 'get_download_request' ) );

		foreach ( array( 'unread', 'new', 'all', 'last', 'list' ) as $selection ) {
			$_GET['epubsecret'] = $selection;
			$this->assertSame( array( $selection, null ), $this->call( $integration, 'get_download_request' ) );
		}

		// A selection can say how many articles to take.
		$_GET['epubsecret'] = 'unread-12';
		$this->assertSame( array( 'unread', 12 ), $this->call( $integration, 'get_download_request' ) );

		$_GET['epubsecret'] = 'everything';
		$this->assertFalse( $this->call( $integration, 'get_download_request' ) );

		$_GET['epubsecret'] = 'everything-3';
		$this->assertFalse( $this->call( $integration, 'get_download_request' ) );

		$_GET['epubsecret'] = array( '4', '0', 'seven', '9' );
		$this->assertSame( array( array( 4, 9 ), null ), $this->call( $integration, 'get_download_request' ) );
	}
}
