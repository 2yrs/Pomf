<?php
$images = array(
	/*'img/2.png',
	'img/3.png',
	'img/4.png',
	'img/5.png',
	'img/6.png',
	'img/7.png',
	'img/8.png',
	'img/9.png',
	'img/10.png'*/
	'img/11.png',
	'img/12.png',
	'img/13.png',
	'img/14.png',
	'img/15.png',
	'img/16.png'
);
if (headers_sent() === false) {
	header('Location: ' . $images[array_rand($images)], true, 303);
}
?>
