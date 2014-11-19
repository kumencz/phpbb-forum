<?php
/**
*
* @package phpBB Extension - Acme Demo
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace skautdd\SkautChat\acp;

class main_info
{
	function module()
	{
		return array(
			'filename'	=> '\skautdd\SkautChat\acp\main_module',
			'title'		=> 'ACP_CHAT_TITLE',
			'version'	=> '1.0.0',
			'modes'		=> array(
				'chat_rooms'	=> array('title' => 'ACP_CHAT_CONFIG_ROOMS', 'auth' => 'acl_a_board', 'cat' => array('ACP_CHAT_TITLE')),
				'chat_setting'	=> array('title' => 'ACP_CHAT_CONFIG', 'auth' => 'acl_a_board', 'cat' => array('ACP_CHAT_TITLE')),
			),
		);
	}
}
