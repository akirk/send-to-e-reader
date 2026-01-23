<?php
/**
 * This template contains the message part for the author header on /friends/.
 *
 * @package Send_To_E_Reader
 */

if ( count( $args['unsent_posts'] ) ) {
	foreach ( $args['ereaders'] as $id => $ereader ) {
		?><a class="chip friends-send-new-posts-to-ereader" data-ereader="<?php echo esc_attr( $id ); ?>" data-unsent="<?php echo esc_attr( count( $args['unsent_posts'] ) ); ?>" href="#">
		<?php
		echo esc_html(
			sprintf(
			// translators: %s is an E-Reader name.
				_n( 'Send %1$d new post to %2$s', 'Send %1$d new posts to %2$s', count( $args['unsent_posts'] ), 'send-to-e-reader' ),
				count( $args['unsent_posts'] ),
				$ereader->get_name()
			)
		);
		?>
			<i class="form-icon"></i>
			</a>
			<?php
	}
}

