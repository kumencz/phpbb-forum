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
	'P2M_MODE'				=> 'Režim',
	'P2M_FORUM'				=> 'Fórum',
	'P2M_THREAD'			=> 'Vlákno',
	'P2M_SUBJECT'			=> 'Předmět',
	'P2M_USER'				=> 'Uřivatel',
	'P2M_IP_HOST'			=> 'IP/Host',
	'P2M_NA'				=> '(n/a)',
	'P2M_ACTIONS'			=> 'Úkony',
	'P2M_REPLY'				=> 'odpovědět',
	'P2M_QUOTE'				=> 'citovat',
	'P2M_EDIT'				=> 'upravit',
	'P2M_DELETE'			=> 'smazat',
	'P2M_INFO'				=> 'informace',
	'P2M_EMAIL'				=> 'email',
	'P2M_ATTACHMENTS'		=> 'Přílohy',
	'P2M_EDITED_BY'			=> 'Upravil',
	'P2M_ORIGINAL_BY'		=> 'Napsal',
	'P2M_EDIT_REASON'		=> 'Důvod úpravy',
));
