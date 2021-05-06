<?php
// $debug = @file_get_contents('debug.txt');
if (!isset($_GET['l']) || !isset($_GET['h'])) {
	// file_put_contents('debug.txt', 'error on request '.$_SERVER['REQUEST_URI']."\r\n");
	die('ups ... wrong usage of this script');
}
	
require_once 'http.inc.php';
require_once 'func.inc.php';

$link = base64_decode($_GET['l']);
$host = base64_decode($_GET['h']);

$link = trim($link, " \r\n\t\0\"'");
if (strpos($link, '://') === false) {
	$link = '/'.ltrim($link, '/');
	$path = explode('/', $host.$link);
	$clean_path = array();
	foreach ($path as $dir)
		if ($dir == '..') {
			if (count($clean_path)>1)
				array_pop($clean_path);
		} else if ($dir != '.') {
			$clean_path[] = $dir;
		}
	// $debug.='path from '.$host.$link.' = '.s_var_dump( $clean_path );
	$host = array_shift($clean_path);
	$link = '/'.implode('/', $clean_path);
	// $debug.="now host = $host | link = $link\r\n";
} else {
	// this coud be done much better
	$pos = strpos($link, '/', 7);
	// $debug.= "B4: host = $host | link = $link | pos = $pos\r\n";
	if ($pos === false) {
		$host = rtrim(substr($link, 7), '/ ');
		$link = '/';
	} else {
		$host = substr($link, 7, $pos - 7);
		$link = substr($link, $pos);
	}
	// $debug.= "AT: host = $host | link = $link\r\n";
}

$http_client = new http(HTTP_V11);
$http_client->host = $host;
if ( HTTP_STATUS_OK == $http_client->get($link) ) {
	$page = $http_client->get_response_body();
	$response = $http_client->get_response();
	$http_client->disconnect();
	unset( $http_client );
} else {
	// $debug.= "An error occured while requesting your file !\r\n";
	// $debug.= s_var_dump( $http_client );
	// file_put_contents('debug.txt', $debug);
	die();
}

// file_put_contents('debug.txt', $debug."\r\n");

if ( (strpos($response->get_content_type(), 'html') !== false) || ($response->get_content_type() == '') )
	redirect_html_links($page, 'p.php?h='.urlencode(base64_encode($host)).'&l=');
else if (strpos($response->get_content_type(), 'css') !== false)
	redirect_css_links($page, 'p.php?h='.urlencode(base64_encode($host)).'&l=');
echo $page;//."\r\ncontent: ".nl2br(htmlspecialchars( s_var_dump($response->get_content_type()) ));
// $path = explode('/', $link);
// $file = array_pop($path);
// @file_put_contents('./data/'.$file, $page);
?>