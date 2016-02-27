<?php

$out = "<!DOCTYPE html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1, maximum-scale=1'><title>Glop.me &middot; Ecchi Paste Hosting - Using IPFS!</title><link rel='shortcut icon' href='favicon.ico' type='image/x-icon'><link rel='stylesheet' href='pomf.min.css'>
</head><body><div class='container'>

<div class='jumbotron'>

<h1>Glop Paste</h1>

<p class='lead'>Paste utility using IPFS</p>

<textarea rows=30 readonly >".$_POST["pastedata"]."</textarea>

</div>

<nav><ul><li><a href='http://glop.me/index.html'>Glop</a></li><li><a href='http://glop.me/paste.html'>Paste</a></li><li><a href='https://github.com/nokonoko/Pomf'>Github</a></li><li><a href='http://glop.me/tools.html'>Tools</a></li><li><a href='http://glop.me/faq.html'>FAQ</a></li></ul></nav></div>
</div>

<script type='text/javascript'>
var grills = ['img/11.png', 'img/12.png', 'img/13.png', 'img/14.png', 'img/15.png', 'img/16.png'];
document.getElementsByTagName('body')[0].style['background-image'] = 'url(' + grills[Math.floor(Math.random() * grills.length)] + '),url(img/bg.png)';
</script>

</body></html>";

$infile = tmpfile();
fwrite($infile, $out);
$meta_data = stream_get_meta_data($infile);
$filename = $meta_data["uri"];

$inhash = shell_exec("HOME=/home/www-data ipfs add -q ".$filename);
fclose($infile);

$fullhash = shell_exec('HOME=/home/www-data ipfs object patch QmazFHudWq91G7GxuWTpyRWZ1Pc2jg3wnwc2RrgVy5GSa3 add-link index.html '.$inhash);
exec('HOME=/home/www-data ipfs pin add -r '.$fullhash);

header('Location: http://gateway.glop.me/ipfs/'.$fullhash);

?>
