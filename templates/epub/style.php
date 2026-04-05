<?php defined( 'ABSPATH' ) || exit; ?>
html, body, div, span, applet, object, iframe, h1, h2, h3, h4, h5, h6, p, blockquote, pre, a, abbr, acronym, address, big, cite, code, del, dfn, em, img, ins, kbd, q, s, samp, small, strike, strong, sub, sup, tt, var, b, u, i, center, fieldset, form, label, legend, table, caption, tbody, tfoot, thead, tr, th, td, article, aside, canvas, details, embed, figure, figcaption, footer, header, hgroup, menu, nav, output, ruby, section, summary, time, mark, audio, video {
	margin: 0;
	padding: 0;
	border: 0;
	font-size: 100%;
	vertical-align: baseline;
}

table {
	border-collapse: collapse;
	border-spacing: 0;
}

body {
	text-align: justify;
	font-size: 100%;
	line-height: 1.5;
	margin: 0;
	padding: 0;
	widows: 2;
	orphans: 2;
}

q {
	quotes: none;
}

body > :last-child,
body > section > :last-child {
	margin-bottom: 0;
}

@page {
	margin: 30px 30px 20px 30px;
	padding: 0;
}

p {
	font-family: serif;
	text-align: justify;
	text-indent: 0;
	widows: 2;
	orphans: 2;
	margin-bottom: .5em;
}

h1, h2 {
	font-style: italic;
	font-size: 1.5em;
}

h1, h2, h3, h4 {
	font-family: sans-serif;
	text-align: left;
	margin-top: 1em;
	margin-bottom: .3em;
}

h6.author {
	font-family: sans-serif;
	font-style: italic;
	text-align: left;
	font-size: 100%;
	margin-bottom: 1.5em;
}

blockquote {
	border-left: .1rem solid #dddddd;
	margin-left: 0;
	margin-top: .5rem;
	margin-bottom: .5rem;
	padding: 0 .7rem;
}

blockquote p {
	margin-bottom: .2rem;
}

figure img, img.size-full {
	max-width: 100% !important;
	height: auto !important;
}

figure {
	display: block;
	text-align: center;
}
figcaption {
	display: block;
	text-align: center;
	font-size: .9rem;
}

code, pre {
	overflow: auto;
	overflow-wrap: break-word;
	white-space: pre;
	font-family: monospace;
	padding: 10px;
	margin: 1em 0;
}

pre p {
	font-family: monospace;
	margin: 0;
}

