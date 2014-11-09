<?php

/**
*
* @package NV Newspage Extension
* @copyright (c) 2013 nickvergessen
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace skautdd\SkautChat\controller;

class main
{
    /**
    * Constructor
    * NOTE: The parameters of this method must match in order and type with
    * the dependencies defined in the services.yml file for this service.
    *
    * @param \phpbb\config  $config     Config object
    * @param \phpbb\template    $template   Template object
    * @param \phpbb\user    $user       User object
    * @param \phpbb\controller\helper       $helper             Controller helper object
    * @param string         $root_path  phpBB root path
    * @param string         $php_ext    phpEx
    */
	public function __construct($auth, $cache, $config, $db, $helper, $template, $user, $phpbb_root_path, $php_ext, $chat_table, $chat_rooms_table, $chat_rooms_read_status_table, $chat_sessions_table )
	{
		$this->auth = $auth;
		$this->cache = $cache;
		$this->config = $config;
		$this->helper = $helper;
		$this->template = $template;
		$this->user = $user;
		$this->db = $db;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
		$this->root_path = append_sid($phpbb_root_path . 'ext/skautdd/SkautChat/');
		define('CHAT_TABLE', $chat_table);
		define('CHAT_ROOMS_TABLE', $chat_rooms_table);
		define('CHAT_ROOMS_READ_STATUS_TABLE', $chat_rooms_read_status_table);
		define('CHAT_SESSIONS_TABLE', $chat_sessions_table);
		
		$this->session_time = 130;
		$this->default_delay = 5;
		//set status
		$this->times = array(
			'online'	=> 0,
			'idle'		=> 30,
			'offline'	=> 60,
		);
		//set delay for each status
		$this->delay = array(
			'online'	=> 5,
			'idle'		=> 15,
			'offline'	=> 30,
		);
		$this->unreadrooms = 0;
	}
	
	function post_params()
	{
		$this->last_post_id = request_var('last_post_id', 0);
		$this->current_room_id = request_var('current_room_id', 0);
		$this->private_user_id = request_var('private_user_id', 0);
		$this->private_room = request_var('private_room', 0);
		$this->focused = request_var('focused', 0);
		$this->post_id = request_var('post_id', 0);
		$this->hist_page = request_var('page', 0);
	}
   
	/* ================================ INIT =========================================== */
    public function init()
    {	
		
		$this->post_params();
	
		/*if($auth->acl_gets('u_can_use_room_4')) $curr_room = 4;
		if($auth->acl_gets('u_can_use_room_3')) $curr_room = 3;
		if($auth->acl_gets('u_can_use_room_2')) $curr_room = 2;
		if($auth->acl_gets('u_can_use_room_1')) $curr_room = 1;*/
		
		$this->current_room_id = 1;
		$sql = 'SELECT * FROM ' . CHAT_TABLE . ' WHERE room_id = '.$this->current_room_id.' ORDER BY message_id DESC';
		$result = $this->db->sql_query_limit($sql, 25);
		$rows = $this->db->sql_fetchrowset($result);
		$this->last_post_id = ($rows[0]['message_id']) ? $rows[0]['message_id'] : 0;
		foreach ($rows as $row)
		{	
			$this->template->assign_block_vars('chatrow', array(
				'MESSAGE_ID'	=> $row['message_id'],
				'USERNAME_FULL'	=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour'], $this->user->lang['GUEST']),
				'MESSAGE'		=> generate_text_for_display($row['message'], $row['bbcode_uid'], $row['bbcode_bitfield'], $row['bbcode_options']),
				'TIME'			=> $this->user->format_date($row['time']),
				'CLASS'			=> ($row['message_id'] % 2) ? 1 : 2,
				'USER_AVATAR'	=> $this->avatar($row['user_id']),
				'VIEW_PROFILE'	=> append_sid($this->phpbb_root_path.'memberlist'.$this->php_ext, 'mode=viewprofile&amp;u=' . $row['user_id']),
			));
		}
		$this->db->sql_freeresult($result);
	
		$this->chat_update_rooms_read($this->current_room_id,$this->last_post_id);

		if (($this->user->data['user_type'] == USER_FOUNDER || $this->user->data['user_type'] == USER_NORMAL) && $this->user->data['user_id'] != ANONYMOUS )
		{
			$sql = 'SELECT * FROM ' . CHAT_SESSIONS_TABLE . ' WHERE user_id = ' .$this->user->data['user_id'];
			$result = $this->db->sql_query($sql);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);
	
			if ($row['user_id'] != $this->user->data['user_id'])
			{
				$this->chat_add_session();
			}
			else
			{
				$this->update_chat_session(time(),time());
			}
		}
		
		$this->check_online();
		$this->load_rooms($this->current_room_id);
		$this->get_users_for_chat();
		$this->set_template_data();
		$this->template->assign_vars(array(
			'TIME'	=> time(),
			'DELAY'	=> $this->default_delay,
			'S_CHAT'=> true,
		));
        return $this->helper->render('chat_body.html');
	}
	
	/* ================================ READ =========================================== */
    public function read()
    {	
		$this->post_params();
		
		/*if($this->changing_room)
		{
			$sql_ary = array(
				'username'			=> $this->user->data['username'],
				'lastupdate'		=> time(),
			);
			$sql = 'UPDATE ' . CHAT_SESSIONS_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', $sql_ary) . ' WHERE user_id = '. $this->user->data['user_id'];
			$db->sql_query($sql);
		}*/
	
		$sql = 'SELECT * FROM ' . CHAT_TABLE . ' WHERE message_id > '. $this->last_post_id .' AND room_id = '. $this->current_room_id .' ORDER BY message_id DESC';
		$result = $this->db->sql_query_limit($sql, 25);
		$rows = $this->db->sql_fetchrowset($result);
	
		if(sizeof($rows))
		{
			$this->last_post_id = $rows[0]['message_id'];
			foreach ($rows as $row)
			{
				$this->template->assign_block_vars('chatrow', array(
					'MESSAGE_ID'	=> $row['message_id'],
					'USERNAME_FULL'	=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour'], $this->user->lang['GUEST']),
					'MESSAGE'		=> generate_text_for_display($row['message'], $row['bbcode_uid'], $row['bbcode_bitfield'], $row['bbcode_options']),
					'TIME'			=> $this->user->format_date($row['time']),
					'CLASS'			=> ($row['message_id'] % 2) ? 1 : 2,
					'USER_AVATAR'	=> $this->avatar($row['user_id']),
					'VIEW_PROFILE'	=> append_sid($this->phpbb_root_path.'memberlist'.$this->php_ext, 'mode=viewprofile&amp;u=' . $row['user_id']),
				));
			}
			$this->db->sql_freeresult($result);
			$this->chat_update_rooms_read($this->current_room_id, $this->last_post_id);
			$this->template->assign_var('S_NEW_POSTS', true);
		}
		/*else
		{
			exit;
		}*/
		$this->check_rooms();
		$this->check_online(time());
		$this->set_template_data();
        return $this->helper->render('chat_ajax_add.html');
	}
	
	/* ================================ ADD =========================================== */
	public function add()
    {
		$this->post_params();
		
		if (!$this->user->data['is_registered'] || $this->user->data['user_type'] == USER_INACTIVE || $this->user->data['user_type'] == USER_IGNORE)
		{
			redirect(append_sid($this->phpbb_root_path.'ucp.$phpEx', 'mode=login'));
		}
		$message = utf8_normalize_nfc(request_var('message', '', true));

		if (!$message)
		{
			break;
		}
		$uid = $bitfield = $options = '';
		$allow_bbcode = $allow_urls = $allow_smilies = true;
		generate_text_for_storage($message, $uid, $bitfield, $options, $allow_bbcode, $allow_urls, $allow_smilies);

		$sql_ary = array(
			'room_id'			=> $this->current_room_id,
			'user_id'			=> $this->user->data['user_id'],
			'username'			=> $this->user->data['username'],
			'user_colour'		=> $this->user->data['user_colour'],
			'message'			=> $message,
			'bbcode_bitfield'	=> $bitfield,
			'bbcode_uid'		=> $uid,
			'bbcode_options'	=> $options,
			'time'				=> time(),
		);
		$sql = 'INSERT INTO ' . CHAT_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
		$this->db->sql_query($sql);


		$sql = 'SELECT * FROM ' . CHAT_TABLE . ' WHERE message_id > '.$this->last_post_id.' AND room_id = '.$this->current_room_id.' ORDER BY message_id DESC';
		$result = $this->db->sql_query_limit($sql, 25);
		$rows = $this->db->sql_fetchrowset($result);

		if (!sizeof($rows) && ((time() - 60) < $last_time))
		{
			exit;
		}
		$this->last_post_id = $rows[0]['message_id'];
		foreach ($rows as $row)
		{
			$this->template->assign_block_vars('chatrow', array(
				'MESSAGE_ID'	=> $row['message_id'],
				'USERNAME_FULL'	=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour'], $this->user->lang['GUEST']),
				'MESSAGE'		=> generate_text_for_display($row['message'], $row['bbcode_uid'], $row['bbcode_bitfield'], $row['bbcode_options']),
				'TIME'			=> $this->user->format_date($row['time']),
				'CLASS'			=> ($row['message_id'] % 2) ? 1 : 2,
				'USER_AVATAR'	=> $this->avatar($row['user_id']),
				'VIEW_PROFILE'	=> append_sid($this->phpbb_root_path.'memberlist'.$this->php_ext, 'mode=viewprofile&amp;u=' . $row['user_id']),
			));
		}
		$this->db->sql_freeresult($result);
		
		$sql = 'DELETE FROM ' . CHAT_ROOMS_READ_STATUS_TABLE . ' WHERE user_id = '.$this->user->data['user_id'].' AND room_id = '.$this->current_room_id;
		$this->db->sql_query($sql);

		$this->chat_update_rooms_read($this->current_room_id, $this->last_post_id);
		$this->check_rooms();
		$this->check_online(time(), time());
		$this->set_template_data();
		$this->template->assign_var('S_NEW_POSTS', true);
		return $this->helper->render('chat_ajax_add.html');
		
		//jabber post ------------------------------------------------------------------
		/*$poster = $user->data['username'];
		$sql = 'SELECT * FROM ' . CHAT_ROOMS_TABLE . ' WHERE room_id='.$curr_room;
		$result = $db->sql_query($sql);
		$room_name = $db->sql_fetchrow($result);

		include_once($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);
		$sql = 'SELECT *
				FROM '. USERS_TABLE . '
				WHERE user_notify_chat = \'1\'
				AND user_jabber != ""';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$user->data = array_merge($user->data, $row);
			$auth->acl($user->data);
			if($auth->acl_gets('u_can_use_room_'.$curr_room))
			{
				$messenger = new messenger();
				$messenger->im($row['user_jabber'], $row['username']);
				$messenger->template('notify_chat_im', $row['user_lang']);
				$messenger->assign_vars(array(
							'MESSAGE'		=> $message,
							'POSTER_USERNAME'    => $poster,
							'ROOM_NAME'		=> $room_name['room_name'],
							));
				$messenger->send(NOTIFY_IM);
				$messenger->save_queue();
			}
		}*/
		//jabber post ------------------------------------------------------------------
	}
	
	/* ================================ CHANGING ROOM =========================================== */
	public function changing_room()
	{
		$this->post_params();		
		$room = '';
		if($this->private_room != 0 && $this->private_user_id != $this->user->data['user_id'])
		{
			$sql = 'SELECT * FROM ' . CHAT_ROOMS_TABLE . ' WHERE private_room = 1 AND (user1_id = '. $this->private_user_id .' AND user2_id = '.$this->user->data['user_id'] .') OR (user1_id = '. $this->user->data['user_id'] .' AND user2_id = '. $this->private_user_id .')';
			$result = $this->db->sql_query($sql);
			$room = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);
			if(!$room)
			{
				/* this room is not exist - create it! */
				$sql = 'SELECT username FROM ' . USERS_TABLE . ' WHERE user_id = '. $this->private_user_id;
				$result = $this->db->sql_query($sql);
				$room_username = $this->db->sql_fetchrow($result);
				$this->db->sql_freeresult($result);
				$sql_ary = array(
					'room_name'			=> $room_username['username'],
					'private_room'		=> 1,
					'user1_id'			=> $this->user->data['user_id'],
					'user2_id'			=> $this->private_user_id,
				);
				$sql = 'INSERT INTO ' . CHAT_ROOMS_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
				$this->db->sql_query($sql);
				
				$sql = 'SELECT * FROM ' . CHAT_ROOMS_TABLE . ' WHERE private_room = 1 AND (user1_id = '. $this->private_user_id .' AND user2_id = '.$this->user->data['user_id'] .') OR (user1_id = '. $this->user->data['user_id'] .' AND user2_id = '. $this->private_user_id .')';
				$result = $this->db->sql_query($sql);
				$room = $this->db->sql_fetchrow($result);
				$this->db->sql_freeresult($result);
				
				$sql_ary = array(
					'user_id'			=> $this->user->data['user_id'],
					'room_id'			=> $room['room_id'],
					'post_id'			=> 0,
				);
				$sql = 'INSERT INTO ' . CHAT_ROOMS_READ_STATUS_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
				$this->db->sql_query($sql);
				$sql_ary = array(
					'user_id'			=> $this->private_user_id,
					'room_id'			=> $room['room_id'],
					'post_id'			=> 0,
				);
				$sql = 'INSERT INTO ' . CHAT_ROOMS_READ_STATUS_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
				$this->db->sql_query($sql);
			}
			$this->current_room_id = $room['room_id'];
		}
		/* load posts from this room */
		$sql = 'SELECT * FROM ' . CHAT_TABLE . ' WHERE room_id = '. $this->current_room_id .' ORDER BY message_id DESC';
		$result = $this->db->sql_query_limit($sql, 25);
		$rows = $this->db->sql_fetchrowset($result);
		if(sizeof($rows))
		{
			$this->last_post_id = $rows[0]['message_id'];
			foreach ($rows as $row)
			{
				$this->template->assign_block_vars('chatrow', array(
					'MESSAGE_ID'	=> $row['message_id'],
					'USERNAME_FULL'	=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour'], $this->user->lang['GUEST']),
					'MESSAGE'		=> generate_text_for_display($row['message'], $row['bbcode_uid'], $row['bbcode_bitfield'], $row['bbcode_options']),
					'TIME'			=> $this->user->format_date($row['time']),
					'CLASS'			=> ($row['message_id'] % 2) ? 1 : 2,
					'USER_AVATAR'	=> $this->avatar($row['user_id']),
					'VIEW_PROFILE'	=> append_sid($this->phpbb_root_path.'memberlist'.$this->php_ext, 'mode=viewprofile&amp;u=' . $row['user_id']),
				));
			}
			$this->db->sql_freeresult($result);
			$this->chat_update_rooms_read($this->current_room_id, $this->last_post_id);
			
		}
		$this->template->assign_var('S_NEW_POSTS', true); //true need for update current room_id in JS
		$this->check_rooms();
		$this->check_online(time(), time());
		$this->set_template_data();
		return $this->helper->render('chat_ajax_add.html');
	}
	/* ================================ HISTORY =========================================== */
	public function history()
	{
		$this->post_params();
		
		$sql = 'SELECT * FROM ' . CHAT_TABLE . ' WHERE room_id = '.$this->current_room_id.' ORDER BY message_id DESC LIMIT 20 OFFSET '.(20*$this->hist_page);
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);
		
		if(sizeof($rows))
		{
			foreach ($rows as $row)
			{
				$this->template->assign_block_vars('chatrow', array(
					'MESSAGE_ID'	=> $row['message_id'],
					'USERNAME_FULL'	=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour'], $this->user->lang['GUEST']),
					'MESSAGE'		=> generate_text_for_display($row['message'], $row['bbcode_uid'], $row['bbcode_bitfield'], $row['bbcode_options']),
					'TIME'			=> $this->user->format_date($row['time']),
					'CLASS'			=> ($row['message_id'] % 2) ? 1 : 2,
					'VIEW_PROFILE'	=> append_sid($this->phpbb_root_path.'memberlist'.$this->php_ext, 'mode=viewprofile&amp;u=' . $row['user_id']),
				));
			}
			
		}
		$this->template->assign_vars(array(
				'S_HIST'		=> true,
				'NEXT_PAGE'		=> $this->hist_page + 1,
				'PREV_PAGE'		=> $this->hist_page - 1,
				'CURR_PAGE'		=> $this->hist_page+1,
				'S_NEXT_PAGE'	=> (sizeof($rows) < 20) ? 0 : 1,
				'S_PREV_PAGE'	=> ($this->hist_page > 0) ? 1 : 0,
			));
	
		$this->check_rooms();
		$this->check_online(time(), time());
		$this->set_template_data();
		return $this->helper->render('chat_ajax_add.html');
	}
	/* ================================ FOCUSING =========================================== */
	public function focus()
	{
		$this->post_params();
		$this->check_rooms();
		$this->check_online(time(), ($this->focused) ? time() : (time() - $this->times['idle']));
		$this->set_template_data();
		$this->template->assign_var('S_NEW_POSTS', false);
		return $this->helper->render('chat_ajax_add.html');	
	}
	/* ================================ DELETE =========================================== */
	function delete_post()
	{
		$this->post_params();
		if ($this->post_id )//&& $this->auth->acl_get('u_chat_delete_all_posts'))
		{
			$sql = 'DELETE FROM ' . CHAT_TABLE . ' WHERE message_id = '.$this->post_id;
			$this->db->sql_query($sql);
		}
		return $this->helper->render('chat_ajax_add.html');
	}
	/* ================================ TEMPLATE DATA ================================  */
	function set_template_data()
	{
		global $phpbb_root_path;
		include($phpbb_root_path . 'includes/functions_posting' . $this->php_ext);
		generate_smilies('inline', 0);
		$this->template->assign_vars(array(
			//'FILENAME'			=> append_sid($this->phpbb_root_path.'app.php/chat'),
			'LAST_POST_ID'		=> $this->last_post_id,
			//'SOUND_NOTIFY'	=> $this->user->data["user_sound_notify_chat"],
			'IMAGES_LOCATION'	=> $this->root_path . 'styles/all/theme/images',
			'ROOM_ID'			=> $this->current_room_id,
			'S_DELETE_PERM'		=> true,
		));
	}
	
	
	
	
	
	
	
	
	
	
    
	/* ================================ ROOMS =========================================== */
    function load_rooms($curr_room)
	{
		$this->check_rooms();

		$sql = 'SELECT * FROM ' . CHAT_ROOMS_TABLE . ' WHERE private_room = 0 ORDER BY room_id ASC' ;
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);
		
		foreach ($rows as $row)
		{
			if($row['room_id'] == $curr_room){
				 $status= "aktual";
			}
			else if(in_array($row['room_id'], $this->unreadrooms)) //$this->unreadrooms[$row['room_id']])
			{
				$status= "unread";
			}
			else
			{
				 $status= "normal";
			}
			if($row['private_room'])
			{
				
			}else
			{
			/*if($this->auth->acl_gets('u_can_use_room_'.$row['room_id']))
			{*/
				$this->template->assign_block_vars('rooms_row', array(
					'ROOM_ID'		=> $row['room_id'],
					'ROOM_NAME'		=> $row['room_name'],
					'ROOM_STATUS'   => $status,
				));
			//}
			}
		}
	}

	function check_rooms()
	{	
		//select all room with unread posts
		$sql = 'SELECT DISTINCT rooms.room_id, rooms.private_room, rooms.user1_id, rooms.user2_id FROM `'.CHAT_TABLE.'` AS `posts`, `'.CHAT_ROOMS_READ_STATUS_TABLE.'` AS `read`, `'.CHAT_ROOMS_TABLE.'` AS `rooms`
				WHERE ((rooms.private_room = 1 AND (rooms.user1_id = '.$this->user->data['user_id'].' OR rooms.user2_id = '.$this->user->data['user_id'].')) OR (rooms.private_room = 0 AND read.user_id = '.$this->user->data['user_id'].'))
				AND read.user_id = '.$this->user->data['user_id'].'
				AND read.room_id = rooms.room_id 
				AND posts.room_id = read.room_id 
				AND read.post_id < posts.message_id';
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);
		$row_num = 0;
		/*foreach ($rows as $row)
		{
			if($this->auth->acl_gets('u_can_use_room_'.$row['room_id']) == false && !$row['private_room'])
			{
				 unset($rows[$row_num]);
				// var_dump($rows);
			}
		}*/

		//process unread rooms
		$this->unreadrooms=array();
		$unread_normal = 0;
		$unread_private = 0;
		foreach ($rows as $row)
		{
			$this->unreadrooms[] = $row['room_id'];
			if($row['private_room'] == 1)
			{
				$unread_private++;
				$user_id = ($row['user1_id'] == $this->user->data['user_id']) ? $row['user2_id'] : (($row['user2_id'] == $this->user->data['user_id']) ? $row['user1_id']: 'Error name');
				$sql = 'SELECT username, user_colour
						FROM '. USERS_TABLE . '
						WHERE user_id='.$user_id;
				$result = $this->db->sql_query($sql);
				$room_user = $this->db->sql_fetchrow($result);
				$this->template->assign_block_vars('rooms_private_unread', array(
						'USER_ID'		=> $user_id,
						'USERNAME'		=> $room_user['username'],
						'COLOUR'		=> $room_user['user_colour'],
				));
			}
			else
			{
				$unread_normal++;
				$this->template->assign_block_vars('rooms_normal_unread', array(
							'ROOM_ID'		=> $row['room_id'],
				));
			}
		}

		$this->template->assign_vars(array(
			'S_UNREAD_ROOMS'		=> ($unread_normal || $unread_private) ? true : false,
			'UNREAD_NORMAL_ROOMS'	=> $unread_normal,
			'UNREAD_PRIVATE_ROOMS'	=> $unread_private,
		));

	}
	/* ================================ USERS =========================================== */
	function get_users_for_chat() // all users with permissions for chating in min 1 room
    {
		$sql = 'SELECT user_id, username, user_colour FROM ' . USERS_TABLE. ' WHERE 1' ;
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);

		foreach ($rows as $row)
		{
			$user_data = $this->auth->obtain_user_data($row['user_id']);
			$this->auth->acl($user_data);
			if($this->auth->acl_get('u_use_users_chat') ) //&& $row['user_id'] != $this->user->data['user_id'])
			{
				$this->template->assign_block_vars('offline_users', array(
					'USER_ID'			=> $row['user_id'],
					'USERNAME'			=> get_username_string('no_profile', $row['user_id'], $row['username'], $row['user_colour'], $this->user->lang['GUEST']),
					'USERNAME_CLEAR'	=> $row['username'],
					'COLOUR'			=> ($row['user_colour']) ? $row['user_colour'] : 0,
				));
			}
		}
		$this->db->sql_freeresult($result);
	}
	function check_online($lastupdate = -1,$last_activity = -1)
	{
		if($lastupdate != -1 || $last_activity != -1)
		{
			$this->update_chat_session($lastupdate, $last_activity);
		}
		$sql = 'SELECT * FROM ' . CHAT_SESSIONS_TABLE . ' WHERE user_id = '.$this->user->data['user_id'];
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if ($row['user_id'] != $this->user->data['user_id'])
		{
			$this->chat_add_session();
		}

		/* delete users with no update last (time() - $this->session_time) */
		$check_time = time() - $this->session_time;
		$sql = 'DELETE FROM ' . CHAT_SESSIONS_TABLE . ' WHERE lastupdate < '.$check_time;
		$this->db->sql_query($sql);

		$sql = 'SELECT users.user_id, users.username, users.user_colour, sessions.last_activity	FROM `'.CHAT_SESSIONS_TABLE.'` AS `sessions`, `'.USERS_TABLE.'` AS `users` 
				WHERE sessions.user_id = users.user_id
				ORDER BY sessions.last_activity DESC';
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		
		$online_users = sizeof($rows);
		$status_time = 0;
		$delay = 0;
		foreach ($rows as $row)
		{
			
			$status_time = $row['last_activity'];
			$status = $this->get_status($status_time);
			$this->template->assign_block_vars('online_users', array(
				'USERNAME'					=> $row['username'],
				'USER_ID'					=> $row['user_id'],
				'COLOUR'					=> ($row['user_colour']) ? $row['user_colour'] : 0,
				'CURRENT_USER'				=> ($row['user_id'] == $this->user->data['user_id']) ? 0 : 1 ,
				'USER_STATUS'				=> $status,
				'USER_LAST_ACTIVITY_CHANGE'	=> time()-$row['last_activity']-$this->times[$status].'s',
			));
			if ($row['user_id'] == $this->user->data['user_id'])
			{
				$delay = ($status_time) ? $this->delay[$status] : $this->delay['idle'];
			}
		}
		
		$this->db->sql_freeresult($result);

		$this->template->assign_vars(array(
			'ONLINE_COUNT'	=> $online_users,
			'DELAY'			=> $delay,
			'LAST_TIME'		=> time(),
			'S_WHOISONLINE'	=> true,
		));
		return false;
	}
	function get_status($last)
	{
		$status = 'online';
		if ($last <= (time() - $this->times['offline']))
		{
			$status = 'offline';
		}
		else if ($last <= (time() - $this->times['idle']))
		{
			$status = 'idle';
		}
		return $status;
	}
	function avatar($user_id)
	{
		$sql = 'SELECT user_avatar, user_avatar_type, user_avatar_width, user_avatar_height
					FROM '. USERS_TABLE . '
					WHERE user_id='.$user_id.' AND user_avatar !=\'\'';
		$result = $this->db->sql_query($sql);
		$avatarus = $this->db->sql_fetchrow($result);
		$avatar_code = "";
		if($avatarus)
		{
			$avatar = $avatarus['user_avatar'];
			$avatar_type = $avatarus['user_avatar_type'];
			$avatar_width = $avatarus['user_avatar_width'];
			$avatar_height = $avatarus['user_avatar_height'];
			$avatar_code = get_user_avatar($avatar, $avatar_type, ($avatar_width > $avatar_height) ? 30 : (30 / $avatar_height) * $avatar_width, ($avatar_height > $avatar_width) ? 30 : (30 / $avatar_width) * $avatar_height);
		}
		 return $avatar_code;
	}
	/* ================================ SESSIONS =========================================== */
	function chat_add_session()
	{
		$sql_ary = array(
			'user_id'			=> $this->user->data['user_id'],
			'lastupdate'		=> time(),
			'last_activity'		=> time(),
		);
		$sql = 'INSERT INTO ' . CHAT_SESSIONS_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
		$this->db->sql_query($sql);
	}
	function update_chat_session($lastupdate = -1,$last_activity = -1)
	{
		if($lastupdate != -1) $sql_ary['lastupdate'] = $lastupdate;
		if($last_activity != -1) $sql_ary['last_activity'] = $last_activity;
		$sql = 'UPDATE ' . CHAT_SESSIONS_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', $sql_ary) . ' WHERE user_id = '.$this->user->data['user_id'];
		$this->db->sql_query($sql);
	}
	/* ================================ ROOMS READ STATUS =========================================== */
	function chat_update_rooms_read($room_id, $post_id)
	{
	$sql = 'DELETE FROM ' . CHAT_ROOMS_READ_STATUS_TABLE . ' WHERE user_id = '.$this->user->data['user_id'].' AND room_id = '.$room_id;
		$this->db->sql_query($sql);
	
		$sql_ary = array(
			'user_id'			=> $this->user->data['user_id'],
			'room_id'			=> $room_id,
			'post_id'			=> $post_id,
		);
		$sql = 'INSERT INTO ' . CHAT_ROOMS_READ_STATUS_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
		$this->db->sql_query($sql);
	}
}
