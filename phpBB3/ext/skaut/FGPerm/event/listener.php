<?php
/**
*
* @package for phpBB 3.1
* @copyright (c) 2014
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace skautdd\FGPerm\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\template\template */
	protected $template;

	/**
	* Constructor
	*
	* @param \phpbb\config\config        $config             Config object
	* @param \phpbb\template\template    $template           Template object
	* @return \vse\lightbox\event\listener
	* @access public
	*/
	public function __construct(\phpbb\config\config $config, \phpbb\template\template $template)
	{
		$this->config = $config;
		$this->template = $template;
	}

	/**
	* Assign functions defined in this class to event listeners in the core
	*
	* @return array
	* @static
	* @access public
	*/
	static public function getSubscribedEvents()
	{
		return array(
			'core.user_setup'					=> 'load_language_on_setup',
			'core.viewforum_modify_topics_data'	=> 'group_perm',
			'core.viewtopic_modify_post_data'	=> 'group_perm',
		);
	}

	/**
	* Setup Lightbox settings
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'skautdd/FGPerm',
			'lang_set' => 'common',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}
	
	/**
	* Setup Lightbox settings
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function group_perm($event)
	{
		global $forum_id, $db, $user, $auth, $phpbb_root_path, $phpEx;
		$sql = 'SELECT g.group_id, g.group_name, g.group_colour, g.group_type FROM ' . ACL_GROUPS_TABLE . ' acl, ' . ACL_ROLES_DATA_TABLE . ' roles, ' . GROUPS_TABLE . ' g
				WHERE g.group_id = acl.group_id
				AND acl.auth_role_id = roles.role_id
				AND acl.forum_id = ' . $forum_id . '
				AND roles.auth_option_id = 20
				AND roles.auth_setting = 1
				ORDER BY g.group_name ASC';

		$result = $db->sql_query($sql);
		$rows = $db->sql_fetchrowset($result);
		$groups_read = "";
		foreach ($rows as $row) {

			if($groups_read != "") $groups_read .= ', ';
			$colour_text = ($row['group_colour']) ? ' style="color:#' . $row['group_colour'] . '"' : '';
			$group_name = ($row['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $row['group_name']] : $row['group_name'];

			if ($row['group_name'] == 'BOTS' || ($user->data['user_id'] != ANONYMOUS && !$auth->acl_get('u_viewprofile')))
			{
				$groups_read .= '<span' . $colour_text . '>' . $group_name . '</span>';
			}
			else
			{
				$groups_read .= '<a' . $colour_text . ' href="' . append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=group&amp;g=' . $row['group_id']) . '">' . $group_name . '</a>';
			}
		}

		$this->template->assign_vars(array(
			'GROUPS_READ'				=> $groups_read,
		));
	}
}
