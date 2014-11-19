<?php
/**
*
* groups [English]
*
* @package language
* @version $Id: chat.php 52 2007-11-04 05:56:17Z Handyman $
* @copyright (c) 2006 StarTrekGuide Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/
/**
* DO NOT CHANGE
*/
if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine

$lang = array_merge($lang, array(
	'USER_LOGGED_OUT'		=> 'Nejsi přihlášen - nemůžeš chatovat',
	'UNIT'					=> 'sekund',
	'CHAT_HIST'				=> 'Historie chatu',
	'HIST_PAGE'				=> 'strana',
	'CHAT_PAGE'				=> 'Chat',
	'ROOMS'					=> 'Místnosti',
	'NEW_ROOM'				=> 'Nová veřejná místnost',
	'ROOM_NAME'				=> 'Název veřejné místnosti',
	'ROOM_PERMISSIONS'		=> 'Oprávnění veřejné místnosti',
	'ROOM_PERMISSIONS_EXP'	=> 'Výčet skupin které mají oprávnění pro čtení a zápis a v této místnosti',
	'CREATE_ROOM'			=> 'Vytvořit místnost',
	'ROOM_LIST'				=> 'Seznam všech veřejných místností',
	//ERROR
	'ERROR_PERM_CHAT_VIEW'					=> 'Nemáte oprávnění pro prohlížení chatu',
	'ERROR_PERM_CHAT_POST_ROOM'				=> 'Nemáte dostatečná oprávnění pro přispívání v této místnosti',
	'ERROR_PERM_CANT_DELETE_FOREIGN_POSTS'	=> 'Nemáte oprávnění pro mazání cizích příspěvků',
	'ERROR_PERM_CANT_DELETE_OWN_POSTS'		=> 'Nemáte oprávnění pro mazání vlastních příspěvků',
	'ERROR_PERM_CANT_DELETE_POSTS'			=> 'Nemáte oprávnění pro mazání příspěvků',
	'ERROR_PERM_ROOM_ACCESS'				=> 'Nemáte oprávnění pro přístup do této místonosti',
	'ERROR_PERM_USE_USERS'					=> 'Nemáte oprávnění pro soukromý chat',
	'ERROR_PERM_SECOND_USER_READ_ONLY'		=> 'Adresát má pouze oprávnění ke čtení v této místosnoti!',
	// ACP
	'ACP_CHAT_CONFIG_ROOMS'	=> 'Místnosti',
	'ACP_CHAT_CONFIG'		=> 'Nastavení',
	'ACP_CHAT_TITLE'		=> 'CHAT',
	'ACP_CHAT_SETTINGS'		=> 'Nastavení chatu',
	'ACP_CHAT_TIMEOUTS'		=> 'Čas neaktivity pro změnu stavu',
	'ACP_CHAT_DELAYS'		=> 'Rychlost aktualizace chatu v každém stavu',
	'ACP_CHAT_DELAY_EXPLAIN'=> 'Po tolika vteřínách se bude aktualizovat chat v tomto stavu',
	'ACP_CHAT_TITLE'		=> 'CHAT',
	'ACP_CHAT_ONLINE'					=> 'Online',
	'ACP_CHAT_IDLE'						=> 'Idle',
	'ACP_CHAT_OFFLINE'					=> 'Offline',
	'ACP_CHAT_ONLINE_TO_IDLE'			=> 'Online na Idle',
	'ACP_CHAT_IDLE_TO_OFFLINE'			=> 'Idle na Offline',
	'ACP_CHAT_ONLINE_TIMEOUT_EXPLAIN'	=> 'Čas nekativity nutný pro přechod do stavu Idle',
	'ACP_CHAT_IDLE_TIMEOUT_EXPLAIN'		=> 'Čas nekativity nutný pro přechod do stavu Offline',
	'ACP_CHAT_SETTING_SAVED'			=> 'Nastavení chatu uloženo',
	'ACP_CHAT_ROOM_CREATED'				=> 'Místnost úspěšně vytvořena',
	'ACP_CHAT_DELETE_ROOM'				=> 'Smazat tuto místnost?',
	'ACP_CHAT_DELETE_ROOM_EXP'			=> 'Smaže tuto místnost nehledě na noé nastavení',
	// ACP ERRORS
	'ACP_ERROR_ONLINE_LOWER_IDLE'	=> 'Online čas musí být menší než čas pro Idle!',
	'ACP_ERROR_DELAY_NOT_ASCENDING'	=> 'Časy aktualizací se musí zvyšovat s nižší úrovní stavu!',
	'ACP_ERROR_NO_ROOM_NAME'		=> 'Nebylo zadáno jméno místnosti',
	'ACP_ERROR_NO_GROUPS_SELECTED'	=> 'Nebyla vybrána žádná skupina!',
	
));
?>
