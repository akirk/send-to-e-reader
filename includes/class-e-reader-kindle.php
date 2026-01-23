<?php
/**
 * Friends E-Reader Kindle
 *
 * This contains the class for a Kindle E-Reader
 *
 * @package Send_To_E_Reader
 */

namespace Send_To_E_Reader;

/**
 * This is the class for the sending posts to a Kindle E-Reader for the Friends Plugin.
 *
 * @since 0.3
 *
 * @package Send_To_E_Reader
 * @author Alex Kirk
 */
class E_Reader_Kindle extends E_Reader_Generic_Email {
	const NAME = 'Kindle';

	public static function get_defaults() {
		return array_merge(
			parent::get_defaults(),
			array(
				'email_placeholder' => '@free.kindle.com',
			)
		);
	}

	protected function old_generate_file( \WP_Post $post ) {
		$this->update_author_name( $post );
		$content = $this->get_content( 'mobi', $post );

		$dir = rtrim( sys_get_temp_dir(), '/' ) . '/send_to_e_reader';
		if ( ! file_exists( $dir ) ) {
			mkdir( $dir );
		}

		$filename = sanitize_title( $this->strip_emojis( $post->author_name . ' - ' . $post->post_title ) );

		require_once __DIR__ . '/../libs/MOBIClass/MOBI.php';
		$mobi = new \MOBI();

		$mobi_content = new \OnlineArticle( $url, $content );

		$mobi_content->setMetadata( 'title', $this->strip_emojis( $post->post_title ) );
		$mobi_content->setMetadata( 'author', $post->author_name );
		$mobi_content->setMetadata( 'publishingdate', gmdate( 'd-m-Y' ) );

		$mobi_content->setMetadata( 'source', get_permalink( $post ) );
		$mobi_content->setMetadata( 'publisher', get_bloginfo( 'name' ), get_bloginfo( 'url' ) );

		$mobi->setContentProvider( $mobi_content );

		$file = $dir . '/' . $filename . '.mobi';
		$mobi->save( $file );

		return $file;
	}


}
