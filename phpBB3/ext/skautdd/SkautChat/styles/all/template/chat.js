//var last_post_id = 0;
var type = 'receive';
var post_time = 0;
var read_interval = 5000;
var interval = window.setInterval('handle_send("read", last_post_id);', read_interval);
var unread_rooms = 0;
var URL = "./chat-"
var xmlHttp = http_object();
var current_private_room = 0;


window.onblur = function () {
	change_status(0);
}
window.onfocus = function () {
	change_status(1);
}

document.onblur = window.onblur;
document.focus = window.focus;

function change_status(focusing)
{
	if (xmlHttp.readyState == 4 || xmlHttp.readyState == 0)
	{
		param = 'focused=' + focusing;
		type = 'send';
		xmlHttp.open("POST", URL+'focus', true);
		xmlHttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xmlHttp.onreadystatechange = handle_return;
		xmlHttp.send(param);
	}
}

function change_room(room,username,colour)
{
	document.getElementById('chat').innerHTML = "";
	
	if(!current_private_room)
	{
		document.getElementById('room_' + current_room_id).classList.remove('aktual');
		document.getElementById('room_' + current_room_id).classList.add('normal');
	}else
	{
		document.getElementById("room_list").removeChild(document.getElementById('private_room_' + current_private_room));
	}		

	if(username != 0)
	{
		if(document.getElementById('private_room_' + room) != null)
		{
			document.getElementById('private_room_' + room).classList.remove('unread');
			document.getElementById('private_room_' + room).classList.add('aktual');
		}else
		{
			var new_room = document.createElement("input");
			new_room.setAttribute('type', 'button');
			new_room.setAttribute('id', 'private_room_' + room);
			new_room.classList.add('button_room');
			new_room.classList.add('normal');
			new_room.classList.add('aktual');
			new_room.style.display = 'inline-block';
			new_room.style.color = '#'+colour;
			new_room.value = username;
			document.getElementById("room_list").appendChild(new_room);
		}
		param = 'current_room_id=0';
		param += '&private_user_id=' + room;
		param += '&private_room=1';
		current_private_room = room;
	}else
	{
		document.getElementById('room_' + room).classList.remove('unread');
		document.getElementById('room_' + room).classList.remove('normal');
		document.getElementById('room_' + room).classList.add('aktual');
		
		param = 'current_room_id=' + room;
		param += '&private_user_id=0';
		param += '&private_room=0';
		current_private_room = 0;
		current_room_id = room;
	}

	type = 'send';
	
	indicator_switch('on');
	xmlHttp.open("POST", URL+'changing_room', true);
	xmlHttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	xmlHttp.onreadystatechange = handle_return;
	xmlHttp.send(param);
	
}
function handle_send(mode, f, page)
{
	indicator_switch('on');
	type = 'receive';
	param = 'mode=' + mode;
	param += '&last_post_id=' + last_post_id;
	param += '&current_room_id=' + current_room_id;

	if (mode == 'add' && document.postform.message.value != '')
	{
		type = 'send';
		for(var i = 0; i < f.elements.length; i++)
		{
			elem = f.elements[i];
			param += '&' + elem.name + '=' + encodeURIComponent(elem.value);
		}
		document.postform.message.value = '';
	}else if (mode == 'history')
	{
		document.getElementById('chat').innerHTML = "";

		type = 'send';
		param += '&page=' + page;
	}else if (mode == 'delete')
	{
		type = 'delete';
		param += '&post_id=' + f;
	}
	xmlHttp.open("POST", URL+mode, true);
	xmlHttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	xmlHttp.onreadystatechange = handle_return;
	xmlHttp.send(param);
}
/* =================================================== RETURN =================================================== */
function handle_return()
{
	if (xmlHttp.readyState == 4)
	{
		document.getElementById('eerroorr').innerHTML =  xmlHttp.responseText;
		if (type != 'delete')
		{
			main_parse = xmlHttp.responseText.split('#!#');
			if(main_parse[1] == 'CHAT')
			{
				if (last_post_id == 0) //chat je prazdny
				{
					document.getElementById('chat').innerHTML = main_parse[2];
				}
				else
				{
					//playSound('button_20');
					document.getElementById('chat').innerHTML = main_parse[2] + document.getElementById('chat').innerHTML;
				}
				last_post_id = main_parse[3];
				current_room_id = main_parse[4];
			}			
			if(main_parse[5] == "ONLINE")
			{
				online_parse = main_parse[6].split('$!$');
				document.getElementById('online_users').innerHTML = online_parse[0];
				for (var u = 0; u < users_list.length; u++) 
				{
					var online = false;
					for (var i = 3; i < online_parse[2]+3; i++) 
					{
						if(users_list[u] == online_parse[i])online = true;
					}
					if(online == true)
					{
						document.getElementById('offline_user_'+users_list[u]).style.display = "none";
					}else
					{
						document.getElementById('offline_user_'+users_list[u]).style.display = "block";
					}
				}
				read_interval = online_parse[1]*1000;
				window.clearInterval(interval);
				interval = window.setInterval('handle_send("read", last_post_id);', read_interval);
			}
			if(main_parse[7] == "UNREAD")
			{
				unread_parse = main_parse[8].split('$!$');
				
				for (var i = 2; i < parseInt(unread_parse[0])+2; i++) 
				{
					if(document.getElementById('room_' + unread_parse[i]) != null && current_room_id != unread_parse[i])
					{
						document.getElementById('room_' + unread_parse[i]).classList.remove('aktual');
						document.getElementById('room_' + unread_parse[i]).classList.add('unread');
					}
				}
				for (var i = 2 + parseInt(unread_parse[0]) ; i < parseInt(unread_parse[1]) + parseInt(unread_parse[0]) + 2; i++) 
				{
					parsed_room = unread_parse[i].split('!#!');
					if(document.getElementById('private_room_' + parsed_room[0]) != null)
					{
						if(current_private_room != parsed_room[0])
						{
							document.getElementById('private_room_' + parsed_room[0]).classList.remove('aktual');
							document.getElementById('private_room_' + parsed_room[0]).classList.add('unread');
						}
					}else
					{
						var new_room = document.createElement("input");
						new_room.setAttribute('type', 'button');
						new_room.setAttribute('id', 'private_room_' + parsed_room[0]);
						new_room.classList.add('button_room');
						new_room.classList.add('normal');
						new_room.classList.add('unread');
						new_room.value = parsed_room[1];
						new_room.style.color = '#'+parsed_room[2];
						new_room.setAttribute('onclick','change_room('+parsed_room[0]+',\''+parsed_room[1]+'\',\''+parsed_room[2]+'\')');
						document.getElementById("room_list").appendChild(new_room);
					}
				}
			}
		}
		indicator_switch('off');
	}
}
/* =================================================== DELETE =================================================== */
function delete_post(post_id)
{
	if(confirm("Opravdu smazat příspěvek?"))
	{
		document.getElementById('p' + post_id).style.display = 'none';
		handle_send('delete', post_id);
	}
}

function indicator_switch(mode)
{
	if(document.getElementById("act_indicator"))
	{
		var update_interval = document.getElementById("update_interval");
		var img = document.getElementById("act_indicator");
		if(img.style.display == "none" && mode == 'on')
		{
			update_interval.innerHTML = '';
			update_interval.parentNode.setAttribute('onclick','');
			img.style.display = "block";
		}
		else if (mode == 'off')
		{
			update_interval.parentNode.setAttribute('onclick',"handle_send('read', last_post_id);");
			update_interval.innerHTML = read_interval/1000 + 's';
			img.style.display = "none";
			// reset intervalu po kliknuti
			window.clearInterval(interval);
			interval = window.setInterval('handle_send("read", last_post_id);', read_interval);
		}
	}
}

function playSound(filename)
{
	<!-- IF SOUND_NOTIFY -->
	document.getElementById("sound").innerHTML='<audio autoplay="autoplay"><source src="sounds/' + filename + '.mp3" type="audio/mpeg" /><source src="sounds/' + filename + '.ogg" type="audio/ogg" /><embed hidden="true" autostart="true" loop="false" src="sounds/' + filename +'.mp3" /></audio>';
	<!-- ENDIF -->
}
function toogle_smile()
{
	var smile = document.getElementById("smile_bar");
	if(smile.style.display == "none")
	{
		smile.style.display = "block";
	}
	else
	{
		smile.style.display = "none"
	}	
}
function hide_offline()
{
	document.getElementById("hide_offline").style.display = 'none';
	document.getElementById("show_offline").style.display = 'block';
	
	document.getElementById("offline_users").style.display = 'none';
}
function show_offline()
{
	document.getElementById("hide_offline").style.display = 'block';
	document.getElementById("show_offline").style.display = 'none';
	
	document.getElementById("offline_users").style.display = 'block';
}
function http_object()
{
	if (window.XMLHttpRequest)
	{
		return new XMLHttpRequest();
	}
	else if(window.ActiveXObject)
	{
		return new ActiveXObject("Microsoft.XMLHTTP");
	}
	else
	{
		document.getElementById('p_status').innerHTML = 'Status: Cound not create XmlHttpRequest Object.  Consider upgrading your browser.';
	}
}
