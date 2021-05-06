function init()
{
	xajax_init(lsn);
	if (timer_handle)
		clearInterval(timer_handle);
	timer_handle = setInterval ( "timer_counter+=1;xajax_update(lsn, timer_counter)", 60 * 1000 );
}

function xchg_list_display(string)
{
	var e=document.getElementById(string);
	var b=document.getElementById(string+'button');
	e.style.display=(b.className=='expand')?'block':'none';
	b.className=(e.style.display=='block')?'collapse':'expand';
	listdisplaymemory[string]=e.style.display;
}

function show_list_memory()
{
	var e;
	var b;
	for (var key in listdisplaymemory)
	{
		e = document.getElementById(key);
		b = document.getElementById(key+'button');
		if ( (!e) || (!b) ) continue;
		e.style.display=listdisplaymemory[key];
		b.className=(listdisplaymemory[key]=='block')?'collapse':'expand';
		//alert("key: "+key+"\r\nvalue: "+listdisplaymemory[key]);
	}
}

var tooltiplocked = false;
var tooltipvisible = 0; // not visible
function tooltip(string)
{
	if (tooltiplocked)
		return;
	var e = document.getElementById('tooltip');
	if (arguments.length < 1)
	{
		e.style.display = 'none';
		tooltipvisible = 0;
	} else
	{
		tooltipvisible = 2; // on the next position update we will make it visible
		e.innerHTML = string;
	}
}

function locktooltip(lock)
{
	if (arguments.length < 1)
		tooltiplocked = !tooltiplocked;
	else
		tooltiplocked = lock;
}

var ie5 = (document.getElementById && document.all); 
var ns6 = (document.getElementById && !document.all); 
var ua = navigator.userAgent.toLowerCase();
var isapple = (ua.indexOf('applewebkit') != -1);
var iebody = (document.compatMode && document.compatMode != 'BackCompat') ? document.documentElement : document.body;
var offsetx = 10;
var offsety =  5;
document.onmousemove = getmouseposition;
function getmouseposition(e)
{
	if (tooltipvisible && !tooltiplocked && document.getElementById)
	{
		pagex = (isapple?0:(ie5)?iebody.scrollLeft:window.pageXOffset);
		pagey = (isapple?0:(ie5)?iebody.scrollTop:window.pageYOffset);
		mousex = (ie5)?event.x:(ns6)?clientX = e.clientX:false;
		mousey = (ie5)?event.y:(ns6)?clientY = e.clientY:false;

		var ttip = document.getElementById('tooltip');
		ttip.style.left = (mousex+pagex+offsetx) + 'px';
		ttip.style.top = (mousey+pagey+offsety) + 'px';

		var tg = (ie5)?event.srcElement:e.target;
		tg = tg.nodeName;
		if ( (tg != 'EM') && (tg != 'S') ) { // if the mouse is not over these, make tooltip invisible
			ttip.style.display = 'none';
			tooltipvisible = 0;
		} else if ( tooltipvisible == 2 ) { // this is the position update that must make the tooltip visible
			ttip.style.display = 'block';
			tooltipvisible = 1;
		}
	}
}

function calculate(value,price,k,total)
{
	var e = document.getElementById(k);
	var t = document.getElementById(total);
	if (isNaN(parseInt(value))) value = 0;
	if (isNaN(parseInt(price))) price = 0;
	value = parseInt(value);
	value = isNaN(value)?0:value;
	var oldprice = (isNaN(parseInt(e.innerHTML)))?0:e.innerHTML;
	var total = (isNaN(parseInt(t.innerHTML)))?0:t.innerHTML;
	e.innerHTML = value*price;
	t.innerHTML = total-oldprice+(value*price);
}

function popup(text)
{
	var c = document.getElementById('cover');
	var e = document.getElementById('popup');
	if ((arguments.length<1) || (text.length<1))
	{
		c.style.display = 'none';
		e.style.display = 'none';
	} else
	{
		c.style.display = 'block';
		//c.style.zIndex="2";
		e.style.display = 'block';
		//e.style.zIndex="3";
		e.innerHTML = text;
	}
}

function numbersonly(e)
{
	var key;
	if (window.event)
		key = window.event.keyCode;
	else
		if (e)
			key = e.which;
		else
			return true;
	var keychar = String.fromCharCode(key);
	// control keys
	if ( (key==null) || (key==0) || (key==8) || (key==9) || (key==13) || (key==10) || (key==27) )
		return true;
	// numbers
	else
		if (("0123456789").indexOf(keychar) > -1)
			return true;
		else
			return false;
}

function cut2size(field, limit)
{
	if (field.value.length >= limit)
	{
		field.value = field.value.substring(0, limit);
		//return false;
	}
	//return true;
}

function checksizemax(e, field, limit)
{
	var key;
	if (window.event)
		key = window.event.keyCode;
	else if (e)
		key = e.which;
	else
		return true;
	var keychar = String.fromCharCode(key);
	// control keys
	if ( (key==null) || (key==0) || (key==8) || (key==9) /*|| (key==13)*/ || (key==27) )
	{
		//alert(key+' '+keychar);
		return true;
	} else if (field.value.length >= limit)
	{
		//alert(key+' '+keychar);
		return false;
	}
	return true;
}

function nr_format(nr) {
	var str = nr.toString();
	var s = new String();
	if ( str.indexOf(".") != -1 )
	{
		s = str.substring( str.indexOf("."), str.indexOf(".") + 3 );
		str = str.substring(0, str.indexOf(".") );
	}
	while (str.length > 3)
	{
		for (i = 0; i < 3; i += 1)
			s = str.charAt(str.length - 1 - i) + s;
		s = " " + s;
		str = str.substring(0, str.length - 3);
	}
	s = str + s;
	return s;
}

function colonists(total) {
	var idle = document.getElementById('col_idle'); // div
	var fighters = document.getElementById('col_fighters');
	var organics = document.getElementById('col_organics');
	idle.innerHTML = nr_format(total - fighters.value - organics.value);
}
