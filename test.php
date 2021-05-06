<?php
$text = "pana aici merge";

echo $text.'<br>';

//acum bag o functzie care nu exista
$h = hash("sha512", $text);

echo $h;
?>