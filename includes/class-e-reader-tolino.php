<?php
/**
 * Friends E-Reader Tolino
 *
 * This contains the class for a Tolino E-Reader
 *
 * @package Friends_Send_To_E_Reader
 */

namespace Send_To_E_Reader;

/**
 * This is the class for the sending posts to a Tolino E-Reader for the Friends Plugin.
 *
 * @since 0.3
 *
 * @package Friends_Send_To_E_Reader
 * @author Alex Kirk
 */
class E_Reader_Tolino extends E_Reader {
	const NAME = 'Tolino';
	protected $id;
	protected $name;
	protected $reseller_id;
	protected $refresh_token;
	protected $hardware_id;
	protected $access_token;
	protected $expires;
	protected $cookies;

	public static $resellers = array(
		4 => 'Thalia.at',
	);

	public function __construct( $id, $name, $reseller_id, $refresh_token = null, $hardware_id = null, $access_token = null, $expires = null, $cookies = null ) {
		$this->id = $id;
		$this->name = $name;
		$this->reseller_id = $reseller_id;
		$this->refresh_token = $refresh_token;
		$this->hardware_id = $hardware_id;
		$this->access_token = $access_token;
		$this->expires = $expires;
		$this->cookies = $cookies;
	}

	public function get_id() {
		if ( empty( $this->id ) ) {
			$this->id = hash( 'crc32', time(), false );
		}

		return $this->id;
	}

	public function get_name() {
		return $this->name;
	}

	public function render_input() {
		$id = $this->get_id();
		if ( ! intval( $this->reseller_id ) ) {
			return self::render_template(
				array(
					'id' => $id,
				)
			);
		}
		if ( ! $this->refresh_token || ! $this->hardware_id ) {
			$url = 'https://www.thalia.de/auth/oauth2/authorize?client_id=webreader&response_type=code&scope=SCOPE_BOSH&redirect_uri=' . self_admin_url() . '&x_buchde.skin_id=17&x_buchde.mandant_id=' . intval( $this->reseller_id );
			?>
			<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Connect account (broken)', 'friends' ); ?></a><br/>
			<?php
		} else {
			// translators: %s is a the name of a Tolino vendor.
			echo esc_html( sprintf( __( 'Connected to %s', 'friends' ), self::$resellers[ $this->reseller_id ] ) );
		}

		if ( $this->refresh_token ) {
			?>
			<input type="hidden" name="ereaders[<?php echo esc_attr( $id ); ?>][refresh_token]" value="<?php echo esc_attr( $this->refresh_token ); ?>" />
			<?php
		} else {
			?>
			<input type="text" name="ereaders[<?php echo esc_attr( $id ); ?>][refresh_token]" placeholder="refresh_token" />
			<?php
		}
		if ( $this->hardware_id ) {
			?>
			<input type="hidden" name="ereaders[<?php echo esc_attr( $id ); ?>][hardware_id]" value="<?php echo esc_attr( $this->hardware_id ); ?>" />
			<?php
		} else {
			?>
			<input type="text" name="ereaders[<?php echo esc_attr( $id ); ?>][hardware_id]" placeholder="hardware_id" />
			<?php
		}
		?>
		<input type="hidden" name="ereaders[<?php echo esc_attr( $id ); ?>][access_token]" value="<?php echo esc_attr( $this->access_token ); ?>" />
		<input type="hidden" name="ereaders[<?php echo esc_attr( $id ); ?>][expires]" value="<?php echo esc_attr( $this->expires ); ?>" />
		<?php if ( is_array( $this->cookies ) ) : ?>
			<?php foreach ( $this->cookies as $cookie ) : ?>
				<input type="hidden" name="ereaders[<?php echo esc_attr( $id ); ?>][cookies][]" value="<?php echo esc_attr( $cookie ); ?>" />
			<?php endforeach; ?>
		<?php endif; ?>
		<input type="hidden" name="ereaders[<?php echo esc_attr( $id ); ?>][reseller_id]" value="<?php echo esc_attr( $this->reseller_id ); ?>" />
		<?php
	}

	public static function get_defaults() {
		return array(
			'id' => 'new',
		);
	}

	public static function render_template( $data = array() ) {
		$data = array_merge(
			array(
				'id' => 'new',
			),
			$data
		);
		?>
		<select name="ereaders[<?php echo esc_attr( $data['id'] ); ?>][reseller_id]">
			<option disabled selected hidden><?php esc_html_e( 'Select your Tolino seller', 'friends' ); ?></option>
			<?php foreach ( self::$resellers as $reseller_id => $name ) : ?>
				<option value="<?php echo esc_html( $reseller_id ); ?>"><?php echo esc_html( $name ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public static function instantiate_from_field_data( $id, $data ) {
		$class = get_called_class();
		$data = array_merge(
			array(
				'reseller_id'   => null,
				'refresh_token' => null,
				'hardware_id'   => null,
				'refresh_token' => null,
				'access_token'  => null,
				'expires'       => null,
				'cookies'       => null,
			),
			$data
		);

		if ( 'new' === $id ) {
			$id = null;
		}

		return new $class( $id, $data['name'], $data['reseller_id'], $data['refresh_token'], $data['hardware_id'], $data['access_token'], $data['expires'], $data['cookies'] );
	}

	public function send_posts( array $posts, $title = null, $author = null ) {
		if ( ! $this->access_token && ! $this->refresh_token ) {
			return false;
		}
		$file = $this->generate_file( $posts[0], $title, $author );

		if ( ! file_exists( $file ) ) {
			return false;
		}

		if ( ! $this->access_token || $this->expires < time() ) {
			$response = wp_remote_post(
				'https://www.thalia.de/auth/oauth2/token',
				array(
					'body'    => array(
						'client_id'           => 'webreader',
						'grant_type'          => 'refresh_token',
						'scope'               => 'SCOPE_BOSH',
						'redirect_uri'        => 'https://webreader.mytolino.com/library/',
						'x_buchde.skin_id'    => '17',
						'x_buchde.mandant_id' => $this->reseller_id,
						'refresh_token'       => $this->refresh_token,
					),
					'cookies' => $this->cookies,
				)
			);

			$data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( $data->access_token ) {
				$this->access_token = $data->access_token;
				$this->refresh_token = $data->refresh_token;
				$this->expires = time() + $data->expires_in;
				foreach ( wp_remote_retrieve_cookies( $response ) as $cookie ) {
					$this->cookies[] = $cookie->getHeaderValue();
				}

				sleep( 1 );
			}
		}

		if ( ! $this->access_token || $this->expires < time() ) {
			return false;
		}

		$boundary = wp_generate_password( 24 );
		$headers  = array(
			'content-type' => 'multipart/form-data; boundary=' . $boundary,
			't_auth_token' => $this->access_token,
			'hardware_id'  => $this->hardware_id,
			'reseller_id'  => $this->reseller_id,
		);

		$body = '';
		$body .= '--' . $boundary;
		$body .= "\r\n";
		$body .= 'Content-Disposition: form-data; name="file"; filename="' . basename( $file ) . '"' . "\r\n";
		$body .= "\r\n";
		$body .= file_get_contents( $file );
		$body .= "\r\n";

		$body .= '--' . $boundary . '--';

		$response = wp_remote_post(
			'https://bosh.pageplace.de/bosh/rest/upload',
			array(
				'headers' => $headers,
				'body'    => $body,
				'cookies' => $this->cookies,
			)
		);

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		// Update access token.
		return $this;
	}
}
