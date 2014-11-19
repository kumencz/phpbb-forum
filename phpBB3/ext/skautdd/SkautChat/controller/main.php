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
		//set status
		$this->times = array(
			'online'	=> $this->config['chat_online_timeout'],
			'idle'		=> $this->config['chat_idle_timeout'],
		);
		//set delay for each status
		$this->delay = array(
			'online'	=> $this->config['chat_online_delay'],
			'idle'		=> $this->config['chat_idle_delay'],
			'offline'	=> $this->config['chat_offline_delay'],
		);
		$this->session_time = 2*$this->times['idle']+5;
		$this->unreadrooms = 0;
		
		define('CHAT_TABLE', $chat_table);
		define('CHAT_ROOMS_TABLE', $chat_rooms_table);
		define('CHAT_ROOMS_READ_STATUS_TABLE', $chat_rooms_read_status_table);
		define('CHAT_SESSIONS_TABLE', $chat_sessions_table);
	}
	function post_params()
	{
		if(!$this->auth->acl_get('u_chat_view'))
			trigger_error('ERROR_PERM_CHAT_VIEW');
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
		$this->current_room_id = $this->load_rooms();
		$sql = 'SELECT chat.* , user.username, user.user_colour, user.user_avatar, user.user_avatar_type, user.user_avatar_width, user.user_avatar_height 
				FROM ' . CHAT_TABLE . ' as chat
				LEFT JOIN '.USERS_TABLE.' as user 
				ON user.user_id = chat.user_id
				WHERE chat.room_id = '.$this->current_room_id.'
				ORDER BY message_id DESC';
		$result = $this->db->sql_query_limit($sql, 25);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);
		$this->last_post_id = (sizeof($rows)) ? $rows[0]['message_id'] : 0;
		foreach ($rows as $row)
		{	
			$this->template->assign_block_vars('chatrow', array(
				'MESSAGE_ID'	=> $row['message_id'],
				'USERNAME_FULL'	=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour'], $this->user->lang['GUEST']),
				'MESSAGE'		=> generate_text_for_display($row['message'], $row['bbcode_uid'], $row['bbcode_bitfield'], $row['bbcode_options']),
				'TIME'			=> $this->user->format_date($row['time']),
				'CLASS'			=> ($row['message_id'] % 2) ? 1 : 2,
				'USER_AVATAR'	=> $this->chat_avatar($row['user_avatar'], $row['user_avatar_type'], $row['user_avatar_width'], $row['user_avatar_height']),
				'VIEW_PROFILE'	=> append_sid($this->phpbb_root_path.'memberlist'.$this->php_ext, 'mode=viewprofile&amp;u=' . $row['user_id']),
				'S_DELETABLE'	=> ($row['user_id'] == $this->user->data['user_id']) ? (($this->auth->acl_get('u_chat_delete_own_posts') || $this->auth->acl_get('u_chat_delete_all_posts')) ? 1 : 0) : $this->auth->acl_get('u_chat_delete_all_posts'),
			));
		}
		$this->chat_update_rooms_read($this->current_room_id,$this->last_post_id);
		
		if (($this->user->data['user_type'] == USER_FOUNDER || $this->user->data['user_type'] == USER_NORMAL) && $this->user->data['user_id'] != ANONYMOUS )
		{
			$sql = 'SELECT * FROM ' . CHAT_SESSIONS_TABLE . ' WHERE user_id = ' .$this->user->data['user_id'];
			$result = $this->db->sql_query($sql);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);
			
			if ($row['user_id'] != $this->user->data['user_id'])
				$this->chat_add_session();
			else
				$this->update_chat_session(time(),time());
		}
		$this->get_users_for_chat();
		$this->check_online();
		$this->set_template_data();
		$this->template->assign_vars(array(
			'S_PRIVATE_CHAT'	=> $this->auth->acl_get('u_chat_use_users'),
			'TIME'	=> time(),
			'DELAY'	=> $this->config['chat_online_timeout'],
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
		$sql = 'SELECT chat.* , user.username, user.user_colour, user.user_avatar, user.user_avatar_type, user.user_avatar_width, user.user_avatar_height 
				FROM ' . CHAT_TABLE . ' as chat
				LEFT JOIN '.USERS_TABLE.' as user 
				ON user.user_id = chat.user_id
				WHERE chat.room_id = '.$this->current_room_id.'
				AND message_id > '.$this->last_post_id.'
				ORDER BY message_id DESC';
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
					'USER_AVATAR'	=> $this->chat_avatar($row['user_avatar'], $row['user_avatar_type'], $row['user_avatar_width'], $row['user_avatar_height']),
					'VIEW_PROFILE'	=> append_sid($this->phpbb_root_path.'memberlist'.$this->php_ext, 'mode=viewprofile&amp;u=' . $row['user_id']),
					'S_DELETABLE'	=> ($row['user_id'] == $this->user->data['user_id']) ? (($this->auth->acl_get('u_chat_delete_own_posts') || $this->auth->acl_get('u_chat_delete_all_posts')) ? 1 : 0) : $this->auth->acl_get('u_chat_delete_all_posts'),
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
		$message = utf8_normalize_nfc(request_var('message', '', true));
		if($message && $this->check_room_permission($this->current_room_id, $this->user->data['user_id']) && ($this->auth->acl_get('u_chat_use') || ($this->auth->acl_get('u_chat_use_users') && $this->private_room)))
		{
			$uid = $bitfield = $options = '';
			$allow_bbcode = $allow_urls = $allow_smilies = true;
			generate_text_for_storage($message, $uid, $bitfield, $options, $allow_bbcode, $allow_urls, $allow_smilies);

			$sql_ary = array(
				'room_id'			=> $this->current_room_id,
				'user_id'			=> $this->user->data['user_id'],
				'message'			=> $message,
				'bbcode_bitfield'	=> $bitfield,
				'bbcode_uid'		=> $uid,
				'bbcode_options'	=> $options,
				'time'				=> time(),
			);
			$sql = 'INSERT INTO ' . CHAT_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
			$this->db->sql_query($sql);

			$sql = 'SELECT chat.* , user.username, user.user_colour, user.user_avatar, user.user_avatar_type, user.user_avatar_width, user.user_avatar_height 
					FROM ' . CHAT_TABLE . ' as chat
					LEFT JOIN '.USERS_TABLE.' as user 
					ON user.user_id = chat.user_id
					WHERE chat.room_id = '.$this->current_room_id.'
					AND message_id > '.$this->last_post_id.'
					ORDER BY message_id DESC';
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
					'USER_AVATAR'	=> $this->chat_avatar($row['user_avatar'], $row['user_avatar_type'], $row['user_avatar_width'], $row['user_avatar_height']),
					'VIEW_PROFILE'	=> append_sid($this->phpbb_root_path.'memberlist'.$this->php_ext, 'mode=viewprofile&amp;u=' . $row['user_id']),
					'S_DELETABLE'	=> ($row['user_id'] == $this->user->data['user_id']) ? (($this->auth->acl_get('u_chat_delete_own_posts') || $this->auth->acl_get('u_chat_delete_all_posts')) ? 1 : 0) : $this->auth->acl_get('u_chat_delete_all_posts'),
				));
			}
			$this->db->sql_freeresult($result);
			
			$sql = 'DELETE FROM ' . CHAT_ROOMS_READ_STATUS_TABLE . ' WHERE user_id = '.$this->user->data['user_id'].' AND room_id = '.$this->current_room_id;
			$this->db->sql_query($sql);
		}else
		{
			$this->template->assign_block_vars('errors', array(
				'ERROR_MESSAGE'	=> $this->user->lang['ERROR_PERM_CHAT_POST_ROOM']
			));
		}
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
		$room = array();
		if($this->private_room != 0 && $this->private_user_id != $this->user->data['user_id'])
		{
			if(!$this->auth->acl_get('u_chat_use_users'))
			{
				$this->template->assign_block_vars('errors', array('ERROR_MESSAGE'	=> $this->user->lang['ERROR_PERM_USE_USERS']));
				$this->template->assign_var('S_NEW_POSTS', true); //true need for update current room_id in JS
				$this->set_template_data();
				return $this->helper->render('chat_ajax_add.html');
			}
				
			$sql = 'SELECT * FROM ' . CHAT_ROOMS_TABLE . '
					 WHERE private_room = 1 
					 AND (user1_id = '. $this->private_user_id .' AND user2_id = '.$this->user->data['user_id'] .')
					 OR (user1_id = '. $this->user->data['user_id'] .' AND user2_id = '. $this->private_user_id .')';
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
				
				$sql = 'SELECT * FROM ' . CHAT_ROOMS_TABLE . '
						WHERE private_room = 1 
						AND (user1_id = '. $this->private_user_id .' AND user2_id = '.$this->user->data['user_id'] .') 
						OR (user1_id = '. $this->user->data['user_id'] .' AND user2_id = '. $this->private_user_id .')';
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
		if($this->check_room_permission($this->current_room_id, $this->user->data['user_id']))
		{
			/* load posts from this room */
			$sql = 'SELECT chat.* , user.username, user.user_colour, user.user_avatar, user.user_avatar_type, user.user_avatar_width, user.user_avatar_height 
					FROM ' . CHAT_TABLE . ' as chat
					LEFT JOIN '.USERS_TABLE.' as user 
					ON user.user_id = chat.user_id
					WHERE chat.room_id = '.$this->current_room_id.'
					AND user.user_id = chat.user_id
					ORDER BY message_id DESC';
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
						'USER_AVATAR'	=> $this->chat_avatar($row['user_avatar'], $row['user_avatar_type'], $row['user_avatar_width'], $row['user_avatar_height']),
						'VIEW_PROFILE'	=> append_sid($this->phpbb_root_path.'memberlist'.$this->php_ext, 'mode=viewprofile&amp;u=' . $row['user_id']),
						'S_DELETABLE'	=> ($row['user_id'] == $this->user->data['user_id']) ? ($this->auth->acl_get('u_chat_delete_own_posts') || $this->auth->acl_get('u_chat_delete_all_posts')) : $this->auth->acl_get('u_chat_delete_all_posts'),
					));
				}
				$this->db->sql_freeresult($result);
				$this->chat_update_rooms_read($this->current_room_id, $this->last_post_id);
				
			}
		}else
		{
			$this->template->assign_block_vars('errors', array('ERROR_MESSAGE'	=> $this->user->lang['ERROR_PERM_ROOM_ACCESS']));
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
		
		$sql = 'SELECT chat.* , user.username, user.user_colour
				FROM ' . CHAT_TABLE . ' as chat
				LEFT JOIN '.USERS_TABLE.' as user 
				ON user.user_id = chat.user_id
				WHERE chat.room_id = '.$this->current_room_id.'
				ORDER BY message_id DESC LIMIT 20 OFFSET '.(20*$this->hist_page);
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
		$this->check_online(time(), ($this->focused) ? time() : (time() - $this->times['online']));
		$this->set_template_data();
		$this->template->assign_var('S_NEW_POSTS', false);
		return $this->helper->render('chat_ajax_add.html');	
	}
	/* ================================ DELETE =========================================== */
	function delete_post()
	{
		$this->post_params();
		if ($this->post_id && ($this->auth->acl_get('u_chat_delete_all_posts') || $this->auth->acl_get('u_chat_delete_own_posts')))
		{
			$sql = 'DELETE FROM ' . CHAT_TABLE . ' WHERE message_id = '.$this->post_id;
			$this->db->sql_query($sql);
		}else
		{
			if(!$this->auth->acl_get('u_chat_delete_own_posts'))
				$this->template->assign_block_vars('errors', array('ERROR_MESSAGE'	=> $this->user->lang['ERROR_PERM_CANT_DELETE_OWN_POSTS']));
			else if(!$this->auth->acl_get('u_chat_delete_all_posts'))
				$this->template->assign_block_vars('errors', array('ERROR_MESSAGE'	=> $this->user->lang['ERROR_PERM_CANT_DELETE_FOREIGN_POSTS']));
			else
				$this->template->assign_block_vars('errors', array('ERROR_MESSAGE'	=> $this->user->lang['ERROR_PERM_CANT_DELETE_POSTS']));
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
	function check_room_permission($room_id, $user_id)
	{
		$sql = 'SELECT group_id FROM ' . USER_GROUP_TABLE . ' 
				WHERE user_id = '. $user_id;
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);
		$groups = array();
		foreach ($rows as $row)
		{
			$groups[] = $row['group_id'];
		}
		
		$sql = 'SELECT * FROM ' . CHAT_ROOMS_TABLE . '
				WHERE room_id = '.$room_id.'
				ORDER BY room_id ASC';
		$result = $this->db->sql_query($sql);
		$room = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if($room['private_room'])
		{
			return ($room['user1_id'] == $user_id) ? 1 : (($room['user2_id'] == $user_id) ? 1: 0);
		}else
		{	
			$group_room = (!empty($room['groups_granted'])) ? explode(',', $room['groups_granted']) : array();
			print_r($group_room);
			if(sizeof(array_diff($group_room, $groups)) < sizeof($group_room))
			{
				return 1;
			}
		}
		return 0;
	}
    function load_rooms()
	{
		$this->check_rooms();
		
		$sql = 'SELECT group_id FROM ' . USER_GROUP_TABLE . ' 
				WHERE user_id = '. $this->user->data['user_id'];
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);
		$groups = array();
		foreach ($rows as $row)
		{
			$groups[] = $row['group_id'];
		}
		
		$sql = 'SELECT * FROM ' . CHAT_ROOMS_TABLE . ' WHERE private_room = 0 ORDER BY room_id ASC' ;
		$result = $this->db->sql_query($sql);
		$rooms = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);
		$first_room = 0;
		foreach ($rooms as $room)
		{
			$group_room = (!empty($room['groups_granted'])) ? explode(',', $room['groups_granted']) : '';
			if(sizeof(array_diff($group_room, $groups)) < sizeof($group_room))
			{
				if(!$first_room){
					$first_room = $room['room_id'];
					$status= "aktual";
				}else if(in_array($room['room_id'], $this->unreadrooms))
				{
					$status= "unread";
				}else
				{
					$status= "";
				}
				$this->template->assign_block_vars('rooms_row', array(
					'ROOM_ID'		=> $room['room_id'],
					'ROOM_NAME'		=> $room['room_name'],
					'ROOM_STATUS'   => $status,
				));
			}
		}
		return $first_room;
	}
	function check_rooms()
	{
		//select all room with unread posts
		$sql = 'SELECT DISTINCT rooms.room_id, rooms.private_room, rooms.user1_id, rooms.user2_id 
				FROM `'.CHAT_TABLE.'` AS `posts`, `'.CHAT_ROOMS_READ_STATUS_TABLE.'` AS `read`, `'.CHAT_ROOMS_TABLE.'` AS `rooms`
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
    {/*
		$sql = 'SELECT room_id, groups_granted FROM ' . CHAT_ROOMS_TABLE . ' WHERE private_room = 0 ORDER BY room_id ASC' ;
		$result = $this->db->sql_query($sql);
		$rooms = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		foreach ($rooms as $room)
		{
			
		}
		*/	
		$sql = 'SELECT user_id, username, user_colour FROM ' . USERS_TABLE.' WHERE 1';
				//AND groups.group_id IN(rooms.groups_granted)' ;
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		
		
		foreach ($rows as $row)
		{
			;
			$user_data = $this->auth->obtain_user_data($row['user_id'],100);
			$this->auth->acl($user_data);
			if($this->auth->acl_get('u_chat_use_users') ) //&& $row['user_id'] != $this->user->data['user_id'])
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

		if ($row['user_id'] != $this->user->data['user_id'] && $this->user->data['user_id'] != ANONYMOUS )
		{
			$this->chat_add_session();
		}

		/* delete users with no update last (time() - $this->session_time) */
		$check_time = time() - $this->session_time;
		$sql = 'DELETE FROM ' . CHAT_SESSIONS_TABLE . ' WHERE lastupdate < '.$check_time;
		$this->db->sql_query($sql);

		$sql = 'SELECT users.user_id, users.username, users.user_colour, sessions.last_activity	
				FROM `'.CHAT_SESSIONS_TABLE.'` AS `sessions`, `'.USERS_TABLE.'` AS `users` 
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
				'USER_LAST_STATUS_CHANGE'	=> time()-$row['last_activity']-(($status == 'online') ? 0 : (($status == 'idle') ? $this->times['online'] : $this->times['idle'])),
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
		if ($last <= (time() - $this->times['idle']))
		{
			$status = 'offline';
		}
		else if ($last <= (time() - $this->times['online']))
		{
			$status = 'idle';
		}
		return $status;
	}
	function chat_avatar($avatar,$avatar_type,$avatar_width,$avatar_height)
	{
		if($avatar)
		{
			 return get_user_avatar($avatar, $avatar_type, ($avatar_width > $avatar_height) ? 30 : (30 / $avatar_height) * $avatar_width, ($avatar_height > $avatar_width) ? 30 : (30 / $avatar_width) * $avatar_height);
		}
		return '';
	}
	function check_permissions()
	{
		
		
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
