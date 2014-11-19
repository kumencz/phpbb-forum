<?php
/**
*
* @package phpBB Extension - Acme Demo
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace skautdd\SkautChat\acp;

class main_module
{
	var $u_action;
	
	protected $db, $user, $cache, $template, $display_vars, $config, $phpbb_root_path, $phpbb_admin_path, $phpEx, $phpbb_container;
	protected $root_path, $version_check, $request, $php_ext, $portal_helper, $modules_helper, $log;
	
	public function __construct()
	{
		global $db, $user, $cache, $request, $template, $table_prefix;
		global $config, $phpbb_root_path, $phpbb_admin_path, $phpbb_container, $phpEx, $phpbb_log;

		$user->add_lang_ext('skautdd/SkautChat', 'chat');

		$this->root_path = $phpbb_root_path . 'skautdd/SkautChat/';

		//include($this->root_path . 'includes/constants.' . $phpEx);

		$this->db = $db;
		$this->user = $user;
		$this->cache = $cache;
		$this->template = $template;
		$this->config = $config;
		$this->request = $request;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->phpbb_admin_path = $phpbb_admin_path;
		$this->phpEx = $phpEx;
		$this->phpbb_container = $phpbb_container;
		define('CHAT_ROOMS_TABLE', $this->phpbb_container->getParameter('SkautChat.chat_rooms.table')); //service.yml
	}
	
	function main($id, $mode)
	{
		$submit = ($this->request->is_set_post('submit')) ? true : false;

		add_form_key('skautdd\SkautChat');

		/**
		*	Validation types are:
		*		string, int, bool,
		*		script_path (absolute path in url - beginning with / and no trailing slash),
		*		rpath (relative), rwpath (realtive, writeable), path (relative path, but able to escape the root), wpath (writeable)
		*/
		switch ($mode)
		{
			case 'chat_setting':
				if($submit)
				{
					if (!check_form_key('skautdd\SkautChat'))
					{
						trigger_error('FORM_INVALID');
					}
					$chat_online = $this->request->variable('chat_online', array('' => ''));
					print_r( $chat_online);
					if($this->request->variable('chat_online_timeout', 0) <  $this->request->variable('chat_idle_timeout', 0))
					{
						$this->config->set('chat_online_timeout', $this->request->variable('chat_online_timeout', 0));
						$this->config->set('chat_idle_timeout', $this->request->variable('chat_idle_timeout', 0));
					}else
						trigger_error($this->user->lang('ACP_ERROR_ONLINE_LOWER_IDLE') . adm_back_link($this->u_action), E_USER_WARNING);
					
					if($this->request->variable('chat_online_delay', 0) <  $this->request->variable('chat_idle_delay', 0) &&  $this->request->variable('chat_idle_delay', 0) < $this->request->variable('chat_offline_delay', 0))
					{
						$this->config->set('chat_online_delay', $this->request->variable('chat_online_delay', 0));
						$this->config->set('chat_idle_delay', $this->request->variable('chat_idle_delay', 0));
						$this->config->set('chat_offline_delay', $this->request->variable('chat_offline_delay', 0));
					}else
						trigger_error($this->user->lang('ACP_ERROR_DELAY_NOT_ASCENDING') . adm_back_link($this->u_action), E_USER_WARNING);
						
					trigger_error($this->user->lang('ACP_CHAT_SETTING_SAVED') . adm_back_link($this->u_action));
							
				}
				
				$this->template->assign_vars(array(
					'U_ACTION'				=> $this->u_action,
					'CHAT_ONLINE_TIMEOUT'	=> $this->config['chat_online_timeout'],
					'CHAT_IDLE_TIMEOUT'		=> $this->config['chat_idle_timeout'],
					'CHAT_ONLINE_DELAY'		=> $this->config['chat_online_delay'],
					'CHAT_IDLE_DELAY'		=> $this->config['chat_idle_delay'],
					'CHAT_OFFLINE_DELAY'	=> $this->config['chat_offline_delay'],
					'ACME_DEMO_GOODBYE'		=> $this->config['acme_demo_goodbye'],
				));
				$this->tpl_name = 'acp_chat_settings';
			break;
			case 'chat_rooms':
				$sql = 'SELECT * FROM ' . CHAT_ROOMS_TABLE . ' WHERE private_room = 0 ORDER BY room_id ASC' ;
				$result = $this->db->sql_query($sql);
				$chat_rooms = $this->db->sql_fetchrowset($result);
				$this->db->sql_freeresult($result);
				
				$sql = 'SELECT group_id, group_name 
						FROM ' . GROUPS_TABLE . '
						ORDER BY group_id ASC';
				$result = $this->db->sql_query($sql);
				$groups = $this->db->sql_fetchrowset($result);
				$this->db->sql_freeresult($result);
				
				foreach ($groups as $group)
				{
					$this->template->assign_block_vars('groups', array(
									'GROUP_NAME'		=> (isset($this->user->lang['G_' . $group['group_name']])) ? $this->user->lang['G_' . $group['group_name']] : $group['group_name'],
									'GROUP_ID'		=> $group['group_id'],
							));
				}
				foreach ($chat_rooms as $room)
				{
					$this->template->assign_block_vars('public_rooms', array(
								'ROOM_NAME'		=> $room['room_name'],
								'ROOM_ID'		=> $room['room_id'],
						));
					$groups_ary = explode(',', $room['groups_granted']);
					
					// get group info from database and assign the block vars
					
					foreach ($groups as $group)
					{
						$this->template->assign_block_vars('public_rooms.groups', array(
							'SELECTED'		=> (in_array($group['group_id'], $groups_ary)) ? true : false,
							'GROUP_NAME'	=> (isset($this->user->lang['G_' . $group['group_name']])) ? $this->user->lang['G_' . $group['group_name']] : $group['group_name'],
							'GROUP_ID'		=> $group['group_id'],
						));
					}
				}
				if($submit)
				{
					if (!check_form_key('skautdd\SkautChat'))
					{
						trigger_error('FORM_INVALID');
					}
				

					trigger_error($this->user->lang('ACP_DEMO_SETTING_SAVED') . adm_back_link($this->u_action));
							
				}else if($this->request->is_set_post('submit_',0))
				{
					if (!check_form_key('skautdd\SkautChat'))
					{
						trigger_error('FORM_INVALID');
					}
					$room_permission = $this->request->variable('permission-setting', array(0 => ''));
					if($this->request->variable('room_name','') == '')trigger_error($this->user->lang('ACP_ERROR_NO_ROOM_NAME') . adm_back_link($this->u_action), E_USER_WARNING);
					if(!sizeof($room_permission))trigger_error($this->user->lang('ACP_ERROR_NO_GROUPS_SELECTED') . adm_back_link($this->u_action), E_USER_WARNING);
					
					// get groups and check if the selected groups actually exist
					$sql = 'SELECT group_id
							FROM ' . GROUPS_TABLE . '
							ORDER BY group_id ASC';
					$result = $this->db->sql_query($sql);
					while($row = $this->db->sql_fetchrow($result))
					{
						$groups_ary[] = $row['group_id'];
					}
					$this->db->sql_freeresult($result);

					$room_permission = array_intersect($room_permission, $groups_ary);
					$room_permission = implode(',', $room_permission);
					
					
					$sql_ary = array(
						'room_name'			=> $this->request->variable('room_name',''),
						'groups_granted'	=> $room_permission,
						'private_room'		=> 0,
						'user1_id'			=> 0,
						'user2_id'			=> 0,
					);
					$sql = 'INSERT INTO ' . CHAT_ROOMS_TABLE . $this->db->sql_build_array('INSERT', $sql_ary);
					$this->db->sql_query($sql);
					
					trigger_error($this->user->lang('ACP_CHAT_ROOM_CREATED') . adm_back_link($this->u_action));
				}
				$this->template->assign_vars(array(
					'U_ACTION'				=> $this->u_action,
					'ACME_DEMO_GOODBYE'		=> $this->config['acme_demo_goodbye'],
				));
				$this->tpl_name = 'acp_chat_rooms';
			break;
		}
	}
	
	
	/**
	* Reset module settings to default options
	*
	* @param int $id ID of the acp_portal module
	* @param string|int $mode Mode of the acp_portal module
	* @param int $module_id ID of the module that should be reset
	* @param array $module_data Array containing the module's database row
	*/
	protected function reset_module($id, $mode, $module_id, $module_data)
	{
		if (confirm_box(true))
		{
			$sql = 'SELECT module_order, module_column, module_classname
				FROM ' . PORTAL_MODULES_TABLE . '
				WHERE module_id = ' . (int) $module_id;
			$result = $this->db->sql_query_limit($sql, 1);
			$module_data = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if (!($this->c_class = $this->portal_helper->get_module($module_data['module_classname'])))
			{
				trigger_error('CLASS_NOT_FOUND', E_USER_ERROR);
			}

			$sql_ary = array(
				'module_name'		=> $this->c_class->get_name(),
				'module_image_src'	=> $this->c_class->get_image(),
				'module_group_ids'	=> '',
				'module_image_height'	=> 16,
				'module_image_width'	=> 16,
				'module_status'		=> B3_MODULE_ENABLED,
			);
			$sql = 'UPDATE ' . PORTAL_MODULES_TABLE . ' 
					SET ' . $this->db->sql_build_array('UPDATE', $sql_ary) . ' 
					WHERE module_id = ' . (int) $module_id;
			$this->db->sql_query($sql);
			$affected_rows = $this->db->sql_affectedrows();

			if (empty($affected_rows))
			{
				// We need to return to the module config
				meta_refresh(3, $this->get_module_link('config', $module_id));
				trigger_error($this->user->lang['MODULE_NOT_EXISTS'] . adm_back_link($this->u_action . "&amp;module_id=$module_id"), E_USER_WARNING);
			}

			$this->cache->destroy('config');
			$this->cache->destroy('portal_config');
			obtain_portal_config(); // we need to prevent duplicate entry errors
			$this->c_class->install($module_id);
			$this->cache->purge();

			// We need to return to the module config
			meta_refresh(3, $this->get_module_link('config', $module_id));

			trigger_error($this->user->lang['MODULE_RESET_SUCCESS'] . adm_back_link($this->u_action . "&amp;module_id=$module_id"));
		}
		else
		{
			$confirm_text = (isset($this->user->lang[$module_data['module_name']])) ? sprintf($this->user->lang['MODULE_RESET_CONFIRM'], $this->user->lang[$module_data['module_name']]) : sprintf($this->user->lang['DELETE_MODULE_CONFIRM'], utf8_normalize_nfc($module_data['module_name']));
			confirm_box(false, $confirm_text, build_hidden_fields(array(
				'i'				=> $id,
				'mode'			=> $mode,
				'module_reset'	=> true,
				'module_id'		=> $module_id,
			)));
		}
	}
}
