<?php
ini_set("memory_limit","32M");
require "functions.inc.php";

//temporar
$cfg['graphnodenrwidth']=9;
$cfg['graphnodenrheight']=7;
$cfg['graphnodenr']=$cfg['graphnodenrwidth']*$cfg['graphnodenrheight'];
$cfg['minimapdimension']='600x450';
$cfg['minimap_stars_randseed']=101;
$cfg['node_size']=3;

$cfg['map_width']=$cfg['graphnodenrwidth']*100;
$cfg['map_height']=$cfg['graphnodenrheight']*100;

// get config from DB
db_connect();
db("SELECT variable, value FROM config");
$cfg=array();
foreach (dbr() as $var) $cfg[$var[0]]=$var[1];
// get graph from DB
get_map_array($graph);

$img=imageCreate($cfg['map_width'], $cfg['map_height']);
$bgcolor=imageColorAllocate($img, 0, 0, 0);
#$nodebgcolor=imageColorAllocate($img, 0, 100, 0);
$linecolor1=imageColorAllocate($img, 128, 128, 128);
$linecolor2=imageColorAllocate($img, 255, 255, 255);
$starcolor=imageColorAllocate($img, 255, 255, 255);
$nodecolor=imageColorAllocate($img, 255, 215, 0);

//pun stele pe fundal
srand($cfg['minimap_stars_randseed']); // seteaza seed pt stele
for ($i=0;$i<($cfg['map_width']*$cfg['map_height']/2000);$i+=1) imageSetPixel($img, rand(0,$cfg['map_width']-1), rand(0,$cfg['map_height']-1), $starcolor);
srand(time()); // seteaza seed inapoi la ceva aleator

// parameters??
if (isset($_GET['node'])) $node=(int)$_GET['node']; else $node=-1;

//deseneaza muchiile
$validate=array();
foreach ($graph as $i=>$v) {
	foreach ($graph[$i]['links'] as $n) {
		if (!isset($validate[$n])) imageLine($img, $graph[$n]['x'], $graph[$n]['y'], $v['x'], $v['y'], $linecolor1);
	}
	$validate[$i]=true;
}
unset($validate);
if ($node!=-1) {
	foreach ($graph[$node]['links'] as $n) {
		imageLine($img, $graph[$n]['x'], $graph[$n]['y'], $graph[$node]['x'], $graph[$node]['y'], $linecolor2);
	}
}
// deseneaza noduri
$fontwidth=imageFontWidth($cfg['node_size']);
$fontheight=imageFontHeight($cfg['node_size']);
foreach ($graph as $k=>$v) {
	$cnt=strlen($k);
	imagefilledellipse($img, $v['x'], $v['y'], $fontwidth*$cnt+2, $fontheight, $bgcolor);
	imageString($img, $cfg['node_size'], $v['x']-($fontwidth*$cnt/2), $v['y']-$fontheight/2, $k, $nodecolor);
}

// parameters ??
if (isset($_GET['node'])) {
	if (isset($_GET['dim'])) $dim=$_GET['dim'];
	else $dim=$cfg['minimap_width'].'x'.$cfg['minimap_height'];
	list($dw,$dh)=explode('x',$dim);
	
	$sx=min(max($graph[$node]['x']-$dw/2, 0), $cfg['map_width']-$dw);
	$sy=min(max($graph[$node]['y']-$dh/2, 0), $cfg['map_height']-$dh);
	
	$small_img=imageCreate($dw, $dh);
	imageCopyResized($small_img, $img, 0, 0, $sx, $sy, $dw, $dh, $dw, $dh);
	$print_img='small_img';
} else $print_img='img';

// output
header('Content-type: image/png');
imagePNG(${$print_img});
imageDestroy(${$print_img});
?>