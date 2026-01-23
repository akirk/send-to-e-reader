<?php
/**
 * Friends E-Reader Generic E-Mail
 *
 * This contains the class for a generic E-Reader that can receive ePub via e-mail.
 *
 * @package Send_To_E_Reader
 */

namespace Send_To_E_Reader;

/**
 * This is the class for the sending posts to a generic E-Reader for the Friends Plugin.
 *
 * @since 0.3
 *
 * @package Send_To_E_Reader
 * @author Alex Kirk
 */
class E_Reader_Generic_Email extends E_Reader {
	const NAME = 'ePub via E-Mail';
	protected $id;
	protected $name;
	protected $email;

	public function __construct( $name, $email ) {
		$this->name = $name;
		$this->email = $email;
	}

	public function get_id() {
		if ( ! $this->email ) {
			return null;
		}

		if ( empty( $this->id ) ) {
			$this->id = hash( 'crc32', time() . $this->email, false );
		}

		return $this->id;
	}

	public function get_name() {
		return $this->name;
	}

	public function render_input() {
		self::render_template(
			array(
				'id'    => $this->get_id(),
				'email' => $this->email,
			)
		);
	}

	public static function get_defaults() {
		return array(
			'id'                => 'new' . get_called_class(),
			'email'             => '',
			'email_placeholder' => __( 'E-Reader E-Mail address', 'send-to-e-reader' ),
		);
	}

	public static function render_template( $data = array() ) {
		$data = array_merge( static::get_defaults(), $data );
		?><input type="text" class="email" name="ereaders[<?php echo esc_attr( $data['id'] ); ?>][email]" value="<?php echo esc_attr( $data['email'] ); ?>" size="30" aria-label="<?php esc_attr_e( 'E-Reader E-Mail address', 'send-to-e-reader' ); ?>" placeholder="<?php echo esc_attr( $data['email_placeholder'] ); ?>"/>
		<?php
	}

	public static function instantiate_from_field_data( $id, $data ) {
		$class = get_called_class();
		return new $class( $data['name'], $data['email'] );
	}

	/**
	 * Send a post to the E-Reader reachable at the particular e-mail address.
	 *
	 * @param      array  $posts    The posts.
	 * @param      string $title   The title.
	 * @param      string $author  The author.
	 *
	 * @return     bool     Whether it was sent successfully.
	 */
	public function send_posts( array $posts, $title = null, $author = null ) {
		$file = $this->generate_file( $posts, $title, $author );

		if ( ! file_exists( $file ) ) {
			return false;
		}

		$this->send_email_with_attachment( $file );
		unlink( $file );
		return array( 'send-to-e-reader' => 'success', 'title' => $this->ebook_title, 'author' => $this->ebook_author );
	}

	/**
	 * Send email with attachment, using Friends if available or wp_mail as fallback.
	 *
	 * @param string $file The file path to attach.
	 */
	protected function send_email_with_attachment( $file ) {
		if ( class_exists( '\Friends\Friends' ) ) {
			$friends = \Friends\Friends::get_instance();
			$friends->notifications->send_mail( $this->email, $this->ebook_title, $this->ebook_title, array(), array( $file ) );
		} else {
			wp_mail(
				$this->email,
				$this->ebook_title,
				$this->ebook_title,
				array( 'Content-Type: text/plain; charset=UTF-8' ),
				array( $file )
			);
		}
	}

}
