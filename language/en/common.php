<?php
/**
*
* @package phpBB Extension
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'GROUPS_READ_STR'		=> 'Skupiny které mají oprávnění pro čtení:',
	'P2M_MODE'				=> 'Mode',
	'P2M_FORUM'				=> 'Forum',
	'P2M_THREAD'			=> 'Thread',
	'P2M_SUBJECT'			=> 'Subject',
	'P2M_USER'				=> 'User',
	'P2M_IP_HOST'			=> 'IP/Host',
	'P2M_NA'				=> '(n/a)',
	'P2M_ACTIONS'			=> 'Actions',
	'P2M_REPLY'				=> 'reply',
	'P2M_QUOTE'				=> 'quote',
	'P2M_EDIT'				=> 'edit',
	'P2M_DELETE'			=> 'delete',
	'P2M_INFO'				=> 'info',
	'P2M_EMAIL'				=> 'email',
	'P2M_ATTACHMENTS'		=> 'Attachments',
	'P2M_EDITED_BY'			=> 'Edited by',
	'P2M_ORIGINAL_BY'		=> 'Original by',
	'P2M_EDIT_REASON'		=> 'Edit reason',
));
