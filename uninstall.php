<?php
/**
 * Uninstall Send to E-Reader.
 *
 * @package Send_To_E_Reader
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'send-to-e-reader_readers' );
delete_option( 'send_to_e_reader_download_password' );
delete_option( 'send-to-e-reader_cron' );

// Clean up old option names from when this was "Friends Send to E-Reader".
delete_option( 'friends-send-to-e-reader_readers' );
delete_option( 'friends_send_to_e_reader_download_password' );
delete_option( 'friends-send-to-e-reader_cron' );
