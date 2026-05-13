<?php
/**
 * Friends E-Reader Kindle
 *
 * This contains the class for a Kindle E-Reader
 *
 * @package Send_To_E_Reader
 */

namespace Send_To_E_Reader;

defined( 'ABSPATH' ) || exit;

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


}
