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
			<th><?php esc_html_e( 'Reading Summary', 'friends' ); ?></th>
			<td>
				<fieldset>
					<label for="reading_summary">
						<input name="reading_summary" type="checkbox" id="reading_summary" value="1" <?php checked( '1', $args['reading_summary'] ); ?> />
						<?php esc_html_e( 'Automatically create a draft post when sending to E-Reader.', 'friends' ); ?>
					</label>
				</fieldset>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Summary Draft Title', 'friends' ); ?></th>
			<td>
				<fieldset>
					<p>
					<label for="reading_summary_title">
						<input type="text" class="regular-text" name="reading_summary_title" id="reading_summary_title" value="<?php echo esc_attr( $args['reading_summary_title'] ); ?>" />
					</label>
					</p>
					<p class="description">
						<?php echo wp_kses( __( 'This title for the draft post will be used. You can use the following variables: <tt>$date</tt> <tt>$day</tt> <tt>$month</tt> <tt>$year</tt> <tt>$author</tt>', 'friends' ), array( 'tt' => array() ) ); ?>
					</p>
				</fieldset>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Download Password', 'friends' ); ?></th>
			<td>
				<fieldset>
					<p>
					<label for="download_password">
						<input type="text" class="regular-text" name="download_password" id="download_password" value="<?php echo esc_attr( $args['download_password'] ); ?>" pattern="[a-zA-Z0-9_-]+" title="<?php esc_attr_e( 'Only latin characters and digits allowed', 'friends' ); ?>" required />
					</label>
					</p>
					<p class="description">
						<?php esc_html_e( 'This enables you to download an ePub from your E-Reader by appending either of these to any of your Friends URLs:', 'friends' ); ?>
					</p>
					<ul>
						<?php
						foreach (
							array(
								'all'  => __( 'All posts from this friend:', 'friends' ),
								'last' => __( 'The last 10 posts from this friend:', 'friends' ),
								'new'  => __( 'Posts not yet sent from this friend:', 'friends' ),
								'list' => __( 'List last for manual selection from this friend:', 'friends' ),
							) as $key => $description
						) :
							?>
						<li><span class="description"><?php echo esc_html( $description ); ?></span> <span class="download-preview"><tt class="friends-sample-url"></tt><tt>?epub</tt><tt class="download_password_preview"><?php echo esc_html( $args['download_password'] ); ?></tt><tt>=<?php echo esc_html( $key ); ?></tt></span></li>
						<?php endforeach; ?>
					</ul>
					<?php if ( ! empty( $args['all-friends'] ) && is_object( $args['all-friends'] ) ) : ?>
					<p><select id="all-friends-preview">
						<option value=""><?php esc_html_e( 'Preview URL for a friend', 'friends' ); ?></option>
						<?php foreach ( $args['all-friends']->get_results() as $friend ) : ?>
						<option value="<?php echo esc_attr( $friend->get_local_friends_page_url() ); ?>"><?php echo esc_html( $friend->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php endif; ?>
				</fieldset>
			</td>
		</tr>
	</table>
	<input type="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Changes', 'friends' ); ?>" />
</form>
