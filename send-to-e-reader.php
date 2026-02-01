<?php
/**
 * Plugin name: Send to E-Reader
 * Plugin author: Alex Kirk
 * Plugin URI: https://github.com/akirk/send-to-e-reader
 * Version: 1.1.0
 *
 * Description: Send posts to your e-reader. Works standalone or integrates with the Friends plugin.
 *
 * License: GPL2
 * Text Domain: send-to-e-reader
 *
 * @package Send_To_E_Reader
 */

/**
 * This file contains the main plugin functionality.
 */

defined( 'ABSPATH' ) || exit;
define( 'SEND_TO_E_READER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEND_TO_E_READER_VERSION', '1.1.0' );

require 'libs/autoload.php';
require_once __DIR__ . '/includes/class-send-to-e-reader.php';
require_once __DIR__ . '/includes/class-e-reader.php';
require_once __DIR__ . '/includes/class-article-notes.php';

add_filter( 'send_to_e_reader', '__return_true' );

/**
 * Initialize the plugin with e-reader classes.
 *
 * @param Send_To_E_Reader\Send_To_E_Reader $send_to_e_reader The Send_To_E_Reader instance.
 */
function send_to_e_reader_register_ereaders( $send_to_e_reader ) {
	require_once __DIR__ . '/includes/class-e-reader-generic-email.php';
	$send_to_e_reader->register_ereader( 'Send_To_E_Reader\E_Reader_Generic_Email' );

	require_once __DIR__ . '/includes/class-e-reader-kindle.php';
	$send_to_e_reader->register_ereader( 'Send_To_E_Reader\E_Reader_Kindle' );

	require_once __DIR__ . '/includes/class-e-reader-pocketbook.php';
	$send_to_e_reader->register_ereader( 'Send_To_E_Reader\E_Reader_Pocketbook' );

	/*
	Not ready.
	require_once __DIR__ . '/includes/class-e-reader-tolino.php';
	$send_to_e_reader->register_ereader( 'Send_To_E_Reader\E_Reader_Tolino' );
	*/

	require_once __DIR__ . '/includes/class-e-reader-download.php';
	$send_to_e_reader->register_ereader( 'Send_To_E_Reader\E_Reader_Download' );
}

// Initialize with Friends plugin if available.
add_action(
	'friends_loaded',
	function ( $friends ) {
		$send_to_e_reader = new Send_To_E_Reader\Send_To_E_Reader( $friends );
		send_to_e_reader_register_ereaders( $send_to_e_reader );
	}
);

// Fallback initialization when Friends plugin is not active.
add_action(
	'plugins_loaded',
	function () {
		if ( class_exists( 'Friends\Friends' ) ) {
			// Friends plugin will handle initialization via friends_loaded hook.
			return;
		}
		$send_to_e_reader = new Send_To_E_Reader\Send_To_E_Reader( null );
		send_to_e_reader_register_ereaders( $send_to_e_reader );
	},
	20
);

register_activation_hook( __FILE__, array( 'Send_To_E_Reader\Send_To_E_Reader', 'activate_plugin' ) );
