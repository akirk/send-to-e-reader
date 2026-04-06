<?php defined( 'ABSPATH' ) || exit; ?>
<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
	"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo esc_html( $args['title'] ); ?></title>
</head>
<body>
	<h1><?php echo esc_html( $args['title'] ); ?></h1>
	<hr />
	<div style="margin-bottom: 2em;"><?php echo esc_html( $args['author'] . ' | ' . $args['date'] ); ?></div>
	<br />
