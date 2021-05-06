<?php
error_reporting(E_ALL & ~E_STRICT);

######################################################################################
## change_link
######################################################################################
function change_link(&$text, $tag, $add, $tag_delim = array('<','>'), $quote_delim = array('"', "'"), $quote_style = true, $ignore_chars = array(' '), $equal_sign = '=') {
	$nSize = strlen($text);
	$nTag = strlen($tag);
	$tag = strtolower($tag);
	$newtext = '';
	$cachetxt = '';
	$inquote[0] = false;
	$inquote[1] = false;
	// $inquote["'"] = &$inquote[0];
	// $inquote['"'] = &$inquote[1];
	$inquote[$quote_delim[0]] = &$inquote[0];
	$inquote[$quote_delim[1]] = &$inquote[1];
	$intag = false;
	$found = false;
	$cache = false;
	$j = 0;
	for ($i = 0; $i < $nSize; $i += 1) {
		$default = false;
		switch ($text[$i]) {
			case $quote_delim[0]: {
				if (!$intag)
					break;
				if ($quote_style) {
					if ($inquote[$quote_delim[1]])
						break;
					$inquote[$quote_delim[0]] = !$inquote[$quote_delim[0]];
				} else { // tag style
					$inquote[$quote_delim[0]] = true;
				}
				break;
			}
			case $quote_delim[1]: {
				if (!$intag)
					break;
				if ($quote_style) {
					if ($inquote[$quote_delim[0]])
						break;
					$inquote[$quote_delim[1]] = !$inquote[$quote_delim[1]];
				} else { // tag style
					$inquote[$quote_delim[0]] = false;
				}
				break;
			}
			case $tag_delim[0]: {
				if ($inquote[0] || $inquote[1])
					break;
				$intag = true;
				break;
			}
			case $tag_delim[1]: {
				if ($inquote[0] || $inquote[1])
					break;
				// if (!$intag)
					// die("'>' not in tag, it was found at $i in the text:<br>\r\n".$text);
				$intag = false;
				break;
			}
			default: {
				if ( !$intag || $inquote[0] || $inquote[1] )
					break;
				$default = true;
				// echo "j = $j<br>\r\n";
				if ( $tag[$j] == strtolower($text[$i]) )
					$j += 1;
				else
					$j = 0;
				break;
			}
		}
		if ( $cache )
			if ( $inquote[0] || $inquote[1] )
				$cachetxt.= $text[$i];
			else {
				$newtext.= urlencode(base64_encode($cachetxt));
				$cache = false;
				$cachetxt = '';
				$newtext.= $text[$i];
			}
		else
			$newtext.= $text[$i];
		if ( $found && ($inquote[0] || $inquote[1]) ) {
			$newtext.=$add;
			$found = false;
			$cache = true;
		}
		if (!$default) {
			$j = 0;
		}
		if ($j == $nTag) {
			//while ($text[++$i] == ' ');
			while ( is_int(array_search($text[++$i], $ignore_chars)) );
			if (!empty($equal_sign))
				if ($text[$i] == $equal_sign) {
					$newtext.= $equal_sign;
					$i += 1;
				} else
					continue;
			//while ($text[$i] == ' ')
			while ( is_int(array_search($text[$i], $ignore_chars)) )
				$i += 1;
			$found = true;
			$i -= 1;
			$j = 0;
		}
	}
	$text = $newtext;
	unset($newtext);
}

######################################################################################
## redirect_html_links
######################################################################################
function redirect_html_links(&$html_page, $to) {
	foreach( array('href', 'src') as $tag)
		change_link($html_page, $tag, $to);
}

######################################################################################
## redirect_css_links
######################################################################################
function redirect_css_links(&$html_page, $to) {
	foreach( array('url') as $tag)
		change_link($html_page, $tag, $to, array('{','}'), array('(', ')'), false, array(' '), '');
}

######################################################################################
## s_var_dump
######################################################################################
function s_var_dump($info, $pre = false) {
	ob_start();
	if ($pre)
		echo '<pre>';
	var_dump($info);
	if ($pre)
		echo "</pre>";
	$txt = ob_get_contents();
	ob_end_clean();
	return $txt;
}

?>