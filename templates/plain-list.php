<?php
/**
 * This template contains the output of a simple list of posts.
 *
 * @package Send_To_E_Reader
 */

?>
<html><head><title><?php echo esc_html( $args['title'] ); ?></title>
<script>
function selectAll() {
	var checkboxes = document.getElementsByTagName('input');
	for (var i = 0; i < checkboxes.length; i++) {
		if (checkboxes[i].type == 'checkbox') {
			checkboxes[i].checked = true;
		}
	}
	return false;
}
function selectNone() {
	var checkboxes = document.getElementsByTagName('input');
	for (var i = 0; i < checkboxes.length; i++) {
		if (checkboxes[i].type == 'checkbox') {
			checkboxes[i].checked = false;
		}
	}
	return false;
}

function reverseList() {
	var list = document.getElementsByTagName('li');
	var parent = list[0].parentNode;
	for (var i = 0; i < list.length; i++) {
		parent.insertBefore(list[i], parent.firstChild);
	}
	return false;
}

function moveUp( element ) {
	var parent = element.parentNode.parentNode;
	var prev = parent.previousElementSibling;
	if ( prev ) {
		parent.parentNode.insertBefore( parent, prev );
	}
	return false;
}

function moveDown( element ) {
	var parent = element.parentNode.parentNode;
	var next = parent.nextElementSibling;
	if ( next ) {
		parent.parentNode.insertBefore( next, parent );
	}
	return false;
}
</script>
</head>
<body>
	<form>
		<span style="float: right"><a href="" onclick="return reverseList()">Reverse</a> | <a href="" onclick="return selectAll()">Select all</a> | <a href="" onclick="return selectNone()">Select none</a></span>
		<button>Download</button>

		<ul>
		<?php foreach ( $args['posts'] as $post ) : ?>
				<li><input type="checkbox" name="<?php echo esc_attr( $args['inputname'] ); ?>[]" value="<?php echo esc_attr( $post->ID ); ?>" <?php checked( isset( $args['unsent'][ $post->ID ] ) ); ?> />
				<?php echo esc_html( get_the_title( $post ) ); ?><br/><small><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $post->post_content ) ) ); ?></small>
				<small><br><a href="<?php echo esc_html( get_the_permalink( $post ) ); ?>"><?php echo esc_html( get_the_permalink( $post ) ); ?></a> | <a href="" onclick="return moveUp( this )">Move up</a> | <a href="" onclick="return moveDown( this )">Move down</a><br><br></small>
				</li>
		<?php endforeach; ?>
		</ul>
		<button>Download</button>
	</form>
	</body>
</html>
