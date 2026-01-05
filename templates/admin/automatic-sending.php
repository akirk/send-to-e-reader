<?php
/**
 * E-Reader Automatic Sending Settings (inserted in user pages)
 *
 * @package Send_To_E_Reader
 */

return; // Not ready.
global $wp_locale;
?>
<tr>
	<th><?php esc_html_e( 'Automatic Sending', 'friends' ); ?></th>
	<td>
		<fieldset>
			<label for="automatic_sending">
				<select name="automatic_sending" id="automatic_sending">
					<option value=""<?php selected( $args['automatic_sending_day'], '' ); ?>><?php esc_html_e( 'No automatic sending', 'friends' ); ?></option>
					<?php for ( $day = 0; $day < 7; $day++ ) : ?>
						<option value=""<?php selected( $args['automatic_sending_day'], ); ?>><?php echo esc_html( $wp_locale->get_weekday( $day ) ); ?></option>
					<?php endfor; ?>

				</select>
			</label>
			<p class="description">
				<?php esc_html_e( 'Try to automatically send all new articles.', 'friends' ); ?>
			</p>
		</fieldset>
		<fieldset>
			<label for="automatic_sending_ereader">
				<select name="automatic_sending_ereader" id="automatic_sending_ereader">
					<option value=""<?php selected( $args['automatic_sending_ereader'], '' ); ?>><?php esc_html_e( 'No E-Reader selected', 'friends' ); ?></option>
					<?php foreach ( $args['email_ereaders'] as $ereader ) : ?>
						<option value=""<?php selected( $args['automatic_sending_ereader'], $ereader->ID ); ?>><?php echo esc_html( $ereader->get_name() ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<p class="description">
				<?php esc_html_e( 'Choose the E-Reader that this should be sent to. Only E-Mail based E-Readers are supported here.', 'friends' ); ?>
			</p>
		</fieldset>
	</td>
</tr>
