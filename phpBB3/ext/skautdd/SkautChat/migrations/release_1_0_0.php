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
            $this->table_prefix . 'chat'        => array(
                'COLUMNS'        		=> array(
                    'message_id'        => array('UINT:11', NULL, 'auto_increment'),
                    'room_id'           => array('UINT:3', 0),
                    'user_id'           => array('UINT:11', 0),
                    'username'        	=> array('VCHAR', ''),
                    'user_colour'       => array('VCHAR:6', ''),
                    'message'           => array('TEXT', ''),
                    'bbcode_bitfield'   => array('VCHAR:255', ''),
                    'bbcode_uid'		=> array('VCHAR:8', ''),
                    'bbcode_options'    => array('UINT:1', 7),
                    'time'              => array('UINT:11', 0),
                ),
                'PRIMARY_KEY'        	=> 'message_id'
                ),
             $this->table_prefix . 'chat_rooms'        => array(
                'COLUMNS'        		=> array(
                    'room_id'           => array('UINT:3', NULL, 'auto_increment' // 0),
                    'room_name'         => array('VCHAR:20',''),
                    'private_room'		=> array('UINT:1', 0),
                    'user1_id'			=> array('UINT:11', 0),
                    'user2_id'        	=> array('UINT:11', 0),
                ),
                'PRIMARY_KEY'			=> 'room_id'
                ),
             $this->table_prefix . 'chat_rooms_read_status'        => array(
                'COLUMNS'        		=> array(
                    'user_id'    	    => array('UINT:11', 0),
                    'room_id'           => array('UINT:3', 0),
                    'post_id'           => array('UINT:11', 0),
                ),
                ), 
             $this->table_prefix . 'chat_sessions'     => array(
                'COLUMNS'        		=> array(
                    'user_id'     		=> array('UINT:11', 0),
                    'username'          => array('VCHAR'),
                    'user_colour'       => array('VCHAR:6', 0),
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
			array('permission.add', array('u_view_chat', true)),
			array('permission.add', array('u_use_chat', true)),
			array('permission.add', array('u_use_users_chat', true)),
			//array('permission.add', array('u_use_chat', true)),
		);
	}
}
