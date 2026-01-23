<?php
/**
 * Configure E-Readers
 *
 * @package Send_To_E_Reader
 */

$save_changes = __( 'Save Changes', 'send-to-e-reader' );

?>
<form method="post">
	<?php wp_nonce_field( $args['nonce_value'] ); ?>
	<table class="reader-table form-table">
		<thead>
			<tr>
				<th class="check-column"><?php esc_html_e( 'Active', 'send-to-e-reader' ); ?></th>
				<th><?php esc_html_e( 'E-Reader Type', 'send-to-e-reader' ); ?></th>
				<th><?php esc_html_e( 'Name', 'send-to-e-reader' ); ?></th>
				<th><?php esc_html_e( 'E-Mail address', 'send-to-e-reader' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php
		foreach ( $args['ereaders'] as $id => $ereader ) :
			$delete_text = sprintf(
				// translators: %1$s is the button named "delete", %2$s is the user given name of an e-reader.
				__( 'Click %1$s to really delete the reader %2$s.', 'send-to-e-reader' ),
				'<em>' . esc_html( $save_changes ) . '</em>',
				'<em>' . esc_html( $ereader->get_name() ) . '</em>'
			);
			?>
			<tr>
				<td class="check-column"><input type="checkbox" name="ereaders[<?php echo esc_attr( $id ); ?>][active]" value="1" <?php checked( $ereader->active ); ?> /></td>
				<td><input type="hidden" name="ereaders[<?php echo esc_attr( $id ); ?>][class]" value="<?php echo esc_attr( get_class( $ereader ) ); ?>" /><?php echo esc_html( $ereader::NAME ); ?> </td>
				<td><input type="text" class="name" name="ereaders[<?php echo esc_attr( $id ); ?>][name]" value="<?php echo esc_attr( $ereader->get_name() ); ?>" size="30" aria-label="<?php esc_attr_e( 'E-Reader Name', 'send-to-e-reader' ); ?>" /></td>
				<td><?php $ereader->render_input(); ?></td>
				<td><a href="" class="delete-reader" data-delete-text="<?php echo wp_kses( $delete_text, array( 'em' => array() ) ); ?>"><?php esc_html_e( 'delete' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></a></td>
			</tr>
		<?php endforeach; ?>
		<tr class="template<?php echo empty( $args['ereaders'] ) ? '' : ' hidden'; ?>">
			<td><input type="checkbox" name="ereaders[new][active]" value="1" <?php checked( true ); ?> /></td>
			<td>
				<select name="ereaders[new][class]" id="ereader-class">
					<option  disabled selected hidden><?php esc_html_e( 'Select your E-Reader', 'send-to-e-reader' ); ?></option>
					<?php foreach ( $args['ereader_classes'] as $ereader_class ) : ?>
						<option value="<?php echo esc_attr( $ereader_class ); ?>"><?php echo esc_html( $ereader_class::NAME ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td><input type="text" class="name" name="ereaders[new][name]" placeholder="<?php echo esc_attr__( 'Name', 'send-to-e-reader' ); ?>" size="30" aria-label="<?php esc_attr_e( 'E-Reader Name', 'send-to-e-reader' ); ?>" /></td>
			<td>
				<?php foreach ( $args['ereader_classes'] as $ereader_class ) : ?>
					<div id="<?php echo esc_html( $ereader_class ); ?>" class="hidden">
						<?php $ereader_class::render_template(); ?>
					</div>
				<?php endforeach; ?>
			</td>
		</tr>
		</tbody>
	</table>
	<?php if ( ! empty( $args['ereaders'] ) ) : ?>
		<a href="" id="add-reader"><?php esc_html_e( 'Add another E-Reader', 'send-to-e-reader' ); ?></a>
	<?php endif; ?>
	<p class="description">
		<?php
		echo __( 'Some E-Readers offer wireless delivery via an e-mail address which you\'ll first need to create.', 'send-to-e-reader' );
		?>
	</p>
	<p class="description">
		<?php
		echo wp_kses(
			sprintf(
				// translators: %1$s and %2$s are URLs.
				__( 'Examples include Kindle (@free.kindle.com, <a href="%1$s">Instructions</a>) or Pocketbook (@pbsync.com, <a href="%2$s">Instructions</a>).', 'send-to-e-reader' ),
				'https://help.fivefilters.org/push-to-kindle/email-address.html" target="_blank" rel="noopener noreferrer',
				'https://sync.pocketbook-int.com/files/s2pb_info_en.pdf" target="_blank" rel="noopener noreferrer'
			),
			array(
				'a' => array(
					'href'   => array(),
					'rel'    => array(),
					'target' => array(),
				),
			)
		);
		if ( $args['friends'] && isset( $args['friends']->notifications ) ) {
			echo '<br/>';
			echo esc_html(
				sprintf(
					// translators: %s is an e-mail address.
					__( 'Make sure that you whitelist the e-mail address which the friend plugin sends its e-mails from: %s', 'send-to-e-reader' ),
					$args['friends']->notifications->get_friends_plugin_from_email_address()
				)
			);
		}

		?>
	</p>
	<p class="description">
		<?php
		esc_html_e( 'Theoretically you can enter any e-mail address.', 'send-to-e-reader' );
		echo ' ';
		esc_html_e( 'By default the plugin will send an e-mail with an ePub file attached.', 'send-to-e-reader' );
		?>
		</p>
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php echo esc_html( $save_changes ); ?>">
	</p>
</form>

<?php if ( $args['display_about_friends'] ) : ?>
	<p>
		<?php
		echo wp_kses(
			// translators: %s: URL to the Friends Plugin page on WordPress.org.
			sprintf( __( 'The Friends plugin is all about connecting with friends and news. Learn more on its <a href=%s>plugin page on WordPress.org</a>.', 'send-to-e-reader' ), '"https://wordpress.org/plugins/friends" target="_blank" rel="noopener noreferrer"' ),
			array(
				'a' => array(
					'href'   => array(),
					'rel'    => array(),
					'target' => array(),
				),
			)
		);
		?>
	</p>
<?php endif; ?>
<p>
<?php
echo wp_kses(
	// translators: %s: URL to the Embed library.
	sprintf( __( 'This plugin is largely powered by the open source project <a href=%s>PHPePub</a>.', 'send-to-e-reader' ), '"https://github.com/Grandt/PHPePub" target="_blank" rel="noopener noreferrer"' ),
	array(
		'a' => array(
			'href'   => array(),
			'rel'    => array(),
			'target' => array(),
		),
	)
);
?>
</p>
<?php
