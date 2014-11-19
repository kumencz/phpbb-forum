<?php
/**
*
* @package phpBB Extension - Acme Demo
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace skautdd\SkautChat\migrations;

class release_1_0_0 extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return array('\phpbb\db\migration\data\v310\alpha2');
	}
	public function update_schema()
	{
		return array(
			'add_tables'    => array(
            $this->table_prefix . 'chat'						=> array(
                'COLUMNS'        		=> array(
                    'message_id'        => array('UINT:11', NULL, 'auto_increment'),
                    'room_id'           => array('UINT:3', 0),
                    'user_id'           => array('UINT:11', 0),
                    'message'           => array('TEXT', ''),
                    'bbcode_bitfield'   => array('VCHAR:255', ''),
                    'bbcode_uid'		=> array('VCHAR:8', ''),
                    'bbcode_options'    => array('UINT:1', 7),
                    'time'              => array('UINT:11', 0),
                ),
                'PRIMARY_KEY'        	=> 'message_id'
                ),
             $this->table_prefix . 'chat_rooms'					=> array(
                'COLUMNS'        		=> array(
                    'room_id'           => array('UINT:3', NULL, 'auto_increment'),
                    'room_name'         => array('VCHAR:20',''),
                    'groups_granted'    => array('VCHAR',''),
                    'private_room'		=> array('UINT:1', 0),
                    'user1_id'			=> array('UINT:11', 0),
                    'user2_id'        	=> array('UINT:11', 0),
                ),
                'PRIMARY_KEY'			=> 'room_id'
                ),
             $this->table_prefix . 'chat_rooms_read_status'		=> array(
                'COLUMNS'        		=> array(
                    'user_id'    	    => array('UINT:11', 0),
                    'room_id'           => array('UINT:3', 0),
                    'post_id'           => array('UINT:11', 0),
                ),
                ), 
             $this->table_prefix . 'chat_sessions'				=> array(
                'COLUMNS'        		=> array(
                    'user_id'     		=> array('UINT:11', 0),
                    'last_activity'		=> array('UINT:11', 0),
                    'lastupdate'		=> array('UINT:11', 0),
                ),
                'PRIMARY_KEY'        	=> 'user_id'
                ),
            ),
		);
	}
	public function update_data()
	{
		return array(
			array('permission.add', array('u_chat_view', true)),
			array('permission.add', array('u_chat_use', true)),
			array('permission.add', array('u_chat_use_users', true)),
			array('permission.add', array('u_chat_delete_own_posts', true)),
			array('permission.add', array('u_chat_delete_all_posts', true)),
			
			array('config.add', array('chat_online_timeout', 30)),
			array('config.add', array('chat_idle_timeout', 60)),
			
			array('config.add', array('chat_online_delay', 5)),
			array('config.add', array('chat_idle_delay', 15)),
			array('config.add', array('chat_offline_delay', 30)),

			array('module.add', array(
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_CHAT_TITLE'
			)),
			array('module.add', array(
				'acp',
				'ACP_CHAT_TITLE',
				array(
					'module_basename'	=> '\board3\portal\acp\portal_module',
					'module_mode'		=> 'chat_settings',
					'module_auth'		=> 'acl_a_board',
					'module_langname'	=> 'ACP_CHAT_CONFIG',
				),
			)),
			array('module.add', array(
				'acp',
				'ACP_CHAT_TITLE',
				array(
					'module_basename'	=> '\board3\portal\acp\portal_module',
					'module_mode'		=> 'chat_rooms',
					'module_auth'		=> 'acl_a_board',
					'module_langname'	=> 'ACP_CHAT_CONFIG_ROOMS',
				),
			)),
			array('custom', array(array($this, 'add_initial_room'))),
		);
	}
	public function revert_schema()
	{
		return array(
			'drop_tables'		=> array(
				$this->table_prefix . 'chat',
				$this->table_prefix . 'chat_rooms',
				$this->table_prefix . 'chat_rooms_read_status',
				$this->table_prefix . 'chat_sessions'
			),
		);
	}
	public function revert_data()
	{
		return array(
			array('permission.remove', array('u_chat_view')),
			array('permission.remove', array('u_chat_use')),
			array('permission.remove', array('u_chat_use_users')),
			array('permission.remove', array('u_chat_delete_own_posts')),
			array('permission.remove', array('u_chat_delete_all_posts')),
			
			array('config.remove', array('chat_online_timeout')),
			array('config.remove', array('chat_idle_timeout')),
			
			array('config.remove', array('chat_online_delay')),
			array('config.remove', array('chat_idle_delay')),
			array('config.remove', array('chat_offline_delay')),
			
			array('module.remove', array(
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_CHAT_TITLE'
			)),
			array('module.remove', array(
				'acp',
				'ACP_CHAT_TITLE',
				array(
					'module_basename'	=> '\skautdd\SkautChat\acp\main_module',
					'modes'				=> array('chat_settings','chat_rooms'),
				),
			)),
		);
	}
	
	public function add_initial_room()
	{
		$sql_ary = array(
			'room_id'			=> 1,
			'room_name'			=> 'Room',
			'groups_granted'	=> '1,2,3,4,5,6,7',
			'private_room'		=> 0,
			'user1_id'			=> 0,
			'user2_id'			=> 0,
		);
		$sql = 'INSERT INTO ' . $this->table_prefix . 'chat_rooms ' . $this->db->sql_build_array('INSERT', $sql_ary);
		$this->db->sql_query($sql);
		
	}
	
}
