<?php
/**
 * E-Reader Settings
 *
 * @package Send_To_E_Reader
 */

?>
<form method="post">
	<?php wp_nonce_field( $args['nonce_value'] ); ?>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Download Password', 'send-to-e-reader' ); ?></th>
			<td>
				<fieldset>
					<p>
					<label for="download_password">
						<input type="text" class="regular-text" name="download_password" id="download_password" value="<?php echo esc_attr( $args['download_password'] ); ?>" pattern="[a-zA-Z0-9_-]+" title="<?php esc_attr_e( 'Only latin characters and digits allowed', 'send-to-e-reader' ); ?>" required />
					</label>
					</p>
					<p class="description">
						<?php esc_html_e( 'This enables you to download an ePub from your E-Reader by appending either of these to any of your Friends URLs:', 'send-to-e-reader' ); ?>
					</p>
					<ul>
						<?php
						foreach (
							array(
								'all'  => __( 'All posts from this friend:', 'send-to-e-reader' ),
								'last' => __( 'The last 10 posts from this friend:', 'send-to-e-reader' ),
								'new'  => __( 'Posts not yet sent from this friend:', 'send-to-e-reader' ),
								'list' => __( 'List last for manual selection from this friend:', 'send-to-e-reader' ),
							) as $key => $description
						) :
							?>
					<li><span class="description"><?php echo esc_html( $description ); ?></span> <span class="download-preview"><tt class="friends-sample-url"></tt><tt>?epub</tt><tt class="download_password_preview"><?php echo esc_html( $args['download_password'] ); ?></tt><tt>=<?php echo esc_html( $key ); ?></tt></span></li>
						<?php endforeach; ?>
					</ul>
					<?php if ( ! empty( $args['all-friends'] ) && is_object( $args['all-friends'] ) ) : ?>
					<p><select id="all-friends-preview">
						<option value=""><?php esc_html_e( 'Preview URL for a friend', 'send-to-e-reader' ); ?></option>
						<?php foreach ( $args['all-friends']->get_results() as $friend ) : ?>
						<option value="<?php echo esc_attr( $friend->get_local_friends_page_url() ); ?>"><?php echo esc_html( $friend->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php endif; ?>
				</fieldset>
			</td>
		</tr>
	</table>
	<p class="description">
		<?php
		printf(
			/* translators: %s is a link to the dashboard */
			esc_html__( 'Article notes can be managed from the %s.', 'send-to-e-reader' ),
			'<a href="' . esc_url( admin_url( 'index.php' ) ) . '">' . esc_html__( 'Dashboard widget', 'send-to-e-reader' ) . '</a>'
		);
		?>
	</p>
	<input type="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Changes', 'send-to-e-reader' ); ?>" />
</form>
