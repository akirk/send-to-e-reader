<?php
/**
 * Plugin overview.
 *
 * @package Send_To_E_Reader
 */

defined( 'ABSPATH' ) || exit;

?>
<h2><?php esc_html_e( 'How it works', 'send-to-e-reader' ); ?></h2>
<p>
	<?php esc_html_e( 'Send to E-Reader turns one or more WordPress posts into an ePub file. You can download the ePub directly or send it to a configured e-reader address.', 'send-to-e-reader' ); ?>
</p>

<h2><?php esc_html_e( 'Where to find it', 'send-to-e-reader' ); ?></h2>
<ul class="ul-disc">
	<li>
		<?php
		echo wp_kses(
			sprintf(
				// translators: %s: URL to the WordPress Posts admin page.
				__( 'On the <a href="%s">Posts</a> screen, use the <strong>Send to E-Reader</strong> row action for a single post.', 'send-to-e-reader' ),
				esc_url( admin_url( 'edit.php' ) )
			),
			array(
				'a'      => array(
					'href' => array(),
				),
				'strong' => array(),
			)
		);
		?>
	</li>
	<li>
		<?php esc_html_e( 'On the Posts screen, select multiple posts and choose Send to E-Reader from the Bulk actions menu.', 'send-to-e-reader' ); ?>
	</li>
	<li>
		<?php
		echo wp_kses(
			sprintf(
				// translators: %s: URL to install or manage the Friends plugin.
				__( 'With the <a href="%s">Friends plugin</a> installed, use the e-reader actions on friend and feed posts, or configure automatic sending for new articles.', 'send-to-e-reader' ),
				esc_url( $args['friends_available'] ? admin_url( 'admin.php?page=friends' ) : admin_url( 'plugin-install.php?s=friends&tab=search&type=term' ) )
			),
			array(
				'a' => array(
					'href' => array(),
				),
			)
		);
		?>
	</li>
</ul>

<h2><?php esc_html_e( 'Delivery', 'send-to-e-reader' ); ?></h2>
<p>
	<?php
	echo wp_kses(
		sprintf(
			// translators: %s: URL to the E-Readers settings tab.
			__( 'The default <strong>Download ePub</strong> reader creates a downloadable file. Add e-mail based readers on the <a href="%s">E-Readers</a> tab for wireless delivery.', 'send-to-e-reader' ),
			esc_url( admin_url( 'admin.php?page=send-to-e-reader-ereaders' ) )
		),
		array(
			'a'      => array(
				'href' => array(),
			),
			'strong' => array(),
		)
	);
	?>
</p>

<p class="description">
<?php
echo wp_kses(
	// translators: %s: URL to the PHPePub library.
	sprintf( __( 'Powered by <a href="%s">PHPePub</a>.', 'send-to-e-reader' ), 'https://github.com/Grandt/PHPePub' ),
	array(
		'a' => array(
			'href' => array(),
		),
	)
);
?>
</p>
