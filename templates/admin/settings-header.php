<?php
/**
 * Settings Header Template (Standalone)
 *
 * @package Send_To_E_Reader
 */

?>
<div class="wrap">
	<h1><?php echo esc_html( $args['title'] ); ?></h1>

	<?php if ( ! empty( $args['menu'] ) ) : ?>
	<h2 class="nav-tab-wrapper">
		<?php foreach ( $args['menu'] as $label => $page ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $page ) ); ?>" class="nav-tab<?php echo $args['active'] === $page ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</h2>
	<?php endif; ?>
