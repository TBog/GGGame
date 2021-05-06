<?php
ini_set("memory_limit","32M");
require_once('functions.inc.php');

/**************************
	FUNCTIONS
**************************/
function node_distance($node1,$node2) {
	return (int)round(sqrt(pow($node1['x']-$node2['x'],2) + pow($node1['y']-$node2['y'],2)));
}
function node_too_close($node,$graph,$dist) {
	foreach ($graph as $v) {
		if (node_distance($node,$v)<$dist) return true;
	}
	return false;
}
function ok_to_link ($nnr1,$nnr2,$ignore_dist=false) {
	global $graph,$cfg;
	if ( $nnr1==$nnr2 ) return false; // acelashi nod
	if (in_array($nnr1,$graph[$nnr2]['links'])) return false; // sunt deja legate
	if (in_array($nnr2,$graph[$nnr1]['links'])) {echo "$nnr1 legat de $nnr2 dar nu si invers\r\n";return false;} // sunt deja legate
	if ((!$ignore_dist) && (node_distance($graph[$nnr1],$graph[$nnr2])>$cfg['node_max_dist'])) return false;
	if ( (count($graph[$nnr1]['links'])>=$cfg['max_neighbors']) || (count($graph[$nnr2]['links'])>=$cfg['max_neighbors']) ) return false;
	return true;
}
function get_closest_nodes($nnr,$graph,$nr) {
	global $cfg;
	// current nr of links
	$nr-=count($graph[$nnr]['links']);
	if ($nr<1) return array();
	// establish the distance of all stars in relation to this one
	$dists = array();
	foreach($graph as $i=>$n) {
		if(ok_to_link($nnr,$i)) {
			$dists[$i]=node_distance($graph[$nnr],$n);
		}
	}
	if ((count($dists)+count($graph[$nnr]['links']))<$cfg['min_neighbors']) {
		$nr=$cfg['min_neighbors'];
		foreach($graph as $i=>$n) {
			if(ok_to_link($nnr,$i,true)) {
				$dists[$i]=node_distance($graph[$nnr],$n);
			}
		}
	}
	asort($dists,SORT_NUMERIC);
	// array_slice nu pastreaza indicii ... deci:
	$ret=array();
	$cnt=0;
	foreach ($dists as $k=>$v) {
		$ret[]=$k;
		$cnt+=1;
		if ($cnt>=$nr) break;
	}
	return $ret;
}
function link_nodes(&$graph) {
	global $cfg;
	foreach ($graph as $i=>$node) {
		if ($i==0) $linknr=$cfg['max_neighbors'];
		else $linknr=rand($cfg['min_neighbors'],$cfg['max_neighbors']);
		foreach (get_closest_nodes($i,$graph,$linknr) as $j) {
			$graph[$i]['links'][]=$j;
			$graph[$j]['links'][]=$i;
		}
	}
}
/*******************************
	GENERAL PARAMETERS
*******************************/
db_connect();
db("SELECT variable, value FROM config");
$cfg=array();
foreach (dbr() as $var) $cfg[$var[0]]=$var[1];
/*
$cfg['node_min_dist']=40;
$cfg['node_max_dist']=150;
$cfg['map_border']=30;

$cfg['map_layout']=1;
$cfg['node_size']=3;
$cfg['graph_node_nr']=70; // may be overwritten by "special parameters"
$cfg['minimap_stars_randseed']=101;
$cfg['min_neighbors']=2; // can be overwritten by "special parameters"
$cfg['max_neighbors']=6;

// can be overwritten by "special parameters"
$cfg['map_width']=900;
$cfg['map_height']=700;
*/
/*******************************
	SPECIFIC PARAMETERS
*******************************/
if ($cfg['map_layout']==0) { // random grid

	$cfg['graph_node_nr_width']=9;
	$cfg['graph_node_nr_height']=7;
	$cfg['graph_node_nr']=$cfg['graph_node_nr_width']*$cfg['graph_node_nr_height'];

	$nodestart=0;
	$nodefinish=$cfg['graph_node_nr']-1;
	$cfg['min_neighbors']=2;

	$xystart=50;
	$xyrand=25;
	$xyinc=100;
	
	// overwrite default
	$cfg['map_width']=$cfg['graph_node_nr_width']*$xyinc;
	$cfg['map_height']=$cfg['graph_node_nr_height']*$xyinc;
} elseif ($cfg['map_layout']==1) { // total random


} elseif ($cfg['map_layout']==2) { // total random

	
}

// creaza imagine si seteaza culorile
$img=imageCreate($cfg['map_width'], $cfg['map_height']);
$bgcolor=imageColorAllocate($img, 0, 0, 0);
$linecolor=imageColorAllocate($img, 128, 128, 128);
$starcolor=imageColorAllocate($img, 255, 255, 255);
$nodecolor=imageColorAllocate($img, 0, 255, 0);

// genereaza imagine in functzie de map_layout
if ($cfg['map_layout']==0) { // random grid
	$nodename=range($nodestart,$nodefinish);

	// pun pe 0 in mijloc
	list($nodename[0],$nodename[$nodefinish/2])=array($nodename[$nodefinish/2],$nodename[0]);

	// amestec numele nodurilor (exceptand pe 0)
	for ($i=0;$i<$nodefinish;$i+=1) {
		$j=rand($nodestart, $nodefinish);
		if ($nodename[$i]==0 || $nodename[$j]==0) continue;
		list($nodename[$i],$nodename[$j])=array($nodename[$j],$nodename[$i]);
	}

	// genereaza array cu coordonatele nodurilor
	$graph=array();
	for ($nodenr=$nodestart, $i=$xystart, $j=$xystart;$nodenr<=$nodefinish;$i+=$xyinc, $nodenr+=1) {
		if ($i>=$cfg['map_width']) {$i=$xystart; $j+=$xyinc;}
		$x=rand($i-$xyrand,$i+$xyrand);
		$y=rand($j-$xyrand,$j+$xyrand);
		$graph[]=array($x,$y,array());
	}

	// gaseshte toate nodurile apropiate
	for ($i=0;$i<$cfg['graph_node_nr'];$i+=1) {
		for ($j=$i+1;$j<$cfg['graph_node_nr'];$j+=1) {
			if ( sqrt(pow($graph[$i][0]-$graph[$j][0],2)+pow($graph[$i][1]-$graph[$j][1],2))<(2*$xyinc) ) {
				$graph[$i][2][]=$j;
				$graph[$j][2][]=$i;
			}
		}
	}

	// shterge o parte din vecini (graful va deveni orientat)
	for ($i=0;$i<$cfg['graph_node_nr'];$i+=1){
		$vecini=$graph[$i][2];
		shuffle($vecini);
		$nr=$cfg['min_neighbors']; // nr minim de vecini
		if ($nodename[$i]==0) $nr=min(count($vecini)/2, $cfg['max_neighbors']); // centru
		$graph[$i][2]=array_splice($vecini,0,$nr); // tai din $vecini primele $nr linkuri (graful devine orientat), count($graph[$i][2]) = $nr
	}

	// face graful neorientat
	for ($i=0;$i<$cfg['graph_node_nr'];$i+=1) {
		foreach ($graph[$i][2] as $k=>$n) {
			if (!in_array($i,$graph[$n][2])) {
				if ($nodename[$n]===0) { // daca avem muchie orientata catre 0, o steregem
					unset($graph[$i][2][$k]);
				} else {
					#$graph[$n][2][]=$i;
					unset($graph[$i][2][$k]);
					#$deseneaza=true;
				}
			}
		}
	}
	
	// schimba indicii
	$tmp=array();
	for ($i=0;$i<$cfg['graph_node_nr'];$i+=1) {
		$tmp[$nodename[$i]]['x']=$graph[$i][0];
		$tmp[$nodename[$i]]['y']=$graph[$i][1];
		$tmp[$nodename[$i]]['links']=$graph[$i][2];
	}
	$graph=$tmp;
	unset($tmp);
	foreach ($graph as $i=>$a) {
		foreach ($graph[$i]['links'] as $j=>$b)
			$graph[$i]['links'][$j]=$nodename[$b];
		sort($graph[$i]['links'],SORT_NUMERIC);
	}
} elseif ($cfg['map_layout']==1) { // total random
	// pun 0 in centru
	$graph=array(
		0=>array(
			'x'=>$cfg['map_width']/2,
			'y'=>$cfg['map_height']/2,
			'links'=>array(),
		),
	);
	
	// genereaza array cu coordonatele nodurilor
	for ($i=1;$i<$cfg['graph_node_nr'];$i+=1) {
		do {
			$node=array(
				'x'=>rand($cfg['map_border'],$cfg['map_width']-$cfg['map_border']),
				'y'=>rand($cfg['map_border'],$cfg['map_height']-$cfg['map_border']),
				'links'=>array(),
			);
		} while (node_too_close($node,$graph,$cfg['node_min_dist']));
		$graph[$i]=$node;
	}
	
	// legam nodurile cu linkuri
	link_nodes($graph);
} elseif ($cfg['map_layout']==2) { // galactic core
	// pun 0 in centru
	$graph=array(
		0=>array(
			'x'=>$cfg['map_width']/2,
			'y'=>$cfg['map_height']/2,
			'links'=>array(),
		),
	);
	
	// genereaza array cu coordonatele nodurilor
	$size=max($cfg['map_width'],$cfg['map_height']);	
	for ($i=1;$i<$cfg['graph_node_nr'];$i+=1) {
		$base=rand(0,100);
		if ($base<30) { // 30% shanse
			$dist=$size/5;
		} elseif ($base<60) { // 30%
			$dist=$size/3;
		} else { // 20%
			$dist=$size;
		}
		$cnt=0;
		do {
			$node=array(
				'x'=>rand($cfg['map_border'],$cfg['map_width']-$cfg['map_border']),
				'y'=>rand($cfg['map_border'],$cfg['map_height']-$cfg['map_border']),
				'links'=>array(),
			);
			$cnt+=1;
			if ($cnt>500) break;
		} while ( (node_distance($graph[0],$node)>$dist) || node_too_close($node,$graph,$cfg['node_min_dist']) );
		if ($cnt>500) {$i-=1;continue;}
		$graph[$i]=$node;
	}
	
	// legam nodurile cu linkuri
	link_nodes($graph);
}

// pun puncte pe fundal
srand($cfg['minimap_stars_randseed']); // seteaza seed pt "stele"
for ($i=0;$i<($cfg['map_width']*$cfg['map_height']/2000);$i+=1) imageSetPixel($img, rand(0,$cfg['map_width']-1), rand(0,$cfg['map_height']-1), $starcolor);
srand(time()); // seteaza seed inapoi la ceva aleator

// deseneza liniile
foreach ($graph as $i=>$a) {
	foreach ($a['links'] as $j) {
		imageLine($img, $a['x'], $a['y'], $graph[$j]['x'], $graph[$j]['y'], $linecolor);
	}
}

// deseneaza nodurile
$fontwidth=imageFontWidth($cfg['node_size']);
$fontheight=imageFontHeight($cfg['node_size']);
foreach ($graph as $k=>$v) {
	$cnt=strlen($k);
	imageString($img, $cfg['node_size'], $v['x']-($fontwidth*$cnt/2)+1, $v['y']-$fontheight/2+1, $k, $nodecolor);
}

// export
ob_start();
echo '<?php return ';
var_export($graph);
echo '; ?>';
$data=ob_get_contents();
ob_end_clean();
#file_put_contents('generated_graph.php',$data);
db("TRUNCATE TABLE map");
db("SELECT name FROM names_list ORDER BY RAND() LIMIT ".$cfg['graph_node_nr']);
$planet_name=dbr();
$planet_name[0][0]='Sol';
foreach ($graph as $k=>$n) {
	$link='';
	$vlink='';
	sort($n['links']);
	foreach ($n['links'] as $i=>$v) {
		$link.=',link'.($i+1);
		$vlink.=','.(int)$v;
	}
	db("INSERT INTO map (id,name,x,y$link) VALUES ($k,'{$planet_name[$k][0]}',$n[x],$n[y]$vlink)");
}

// output
header('Content-type: image/png');
imagePNG($img);
imageDestroy($img);
?>