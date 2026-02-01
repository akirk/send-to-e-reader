<dialog id="friends-ereader-multi-prompt" role="dialog" aria-labelledby="prompt-dialog-heading" style="max-width: 50%">
	<a class="btn btn-clear float-right close" aria-label="<?php esc_attr_e( 'Close', 'send-to-e-reader' ); ?>"></a>
	<h5></h5>
	<ul style="max-height: 5em; overflow: auto"></ul>
	<p><?php esc_html_e( 'Here you can choose a title and author for the book. You can also leave them empty, then they will be generated.', 'send-to-e-reader' ); ?></p>
	<form>
		<div class="form-group">
			<label class="form-label" for="ebook-title"><?php esc_html_e( 'E-Book Title', 'send-to-e-reader' ); ?>
				<span class="text-tiny">
					<a href="" class="title" data-content="<?php echo esc_html( date_i18n( __( 'F j, Y' ) ) ); ?>"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Date' ); ?></a>
				</span>
			</label>
			<input class="form-input" id="ebook-title" type="text" placeholder="<?php esc_attr_e( 'Autogenerate', 'send-to-e-reader' ); ?>">
		</div>
		<div class="form-group">
			<label class="form-label" for="ebook-author"><?php esc_html_e( 'E-Book Author', 'send-to-e-reader' ); ?>
			<span class="text-tiny">
				<a href="" class="author" data-content="<?php echo esc_html( $args['friend_name'] ); ?>"><?php esc_html_e( 'Single Author', 'send-to-e-reader' ); ?></a>
			</span>
			<input class="form-input" id="ebook-author" type="text" placeholder="<?php esc_attr_e( 'Autogenerate', 'send-to-e-reader' ); ?>">
		</div>

		<button class="btn btn-primary" name="ok" autofocus><?php esc_html_e( 'Submit', 'send-to-e-reader' ); ?></button>
		<button class="btn close" name="close"><?php esc_html_e( 'Cancel', 'send-to-e-reader' ); ?></button>
	</form>
</dialog>
