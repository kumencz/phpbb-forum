<?php
/**
*
* @package for phpBB 3.1
* @copyright (c) 2014
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace skautdd\SkautChat\event;

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
	public function __construct(\phpbb\config\config $config, \phpbb\template\template $template, \phpbb\controller\helper $helper)
	{
		$this->config = $config;
		$this->template = $template;
		$this->helper = $helper;
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
			'core.user_setup'		=> 'load_language_on_setup',
			'core.page_header'		=> 'add_page_header_link',
			'core.permissions'		=> 'add_permission',
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
			'ext_name' => 'skautdd/SkautChat',
			'lang_set' => 'chat',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}
	public function add_page_header_link($event)
	{
		$this->template->assign_vars(array(
			'U_CHAT_PAGE'	=> $this->helper->route('SkautChat_controller'),
		));
	}
	/**
	* Add administrative permissions to manage Pages
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function add_permission($event)
	{
		// Adding new permissions
		$permissions = $event['permissions'];
		$permissions['u_view_chat'] = array('lang' => 'ACL_U_VIEW_CHAT', 'cat' => 'chat');
		$permissions['u_use_chat'] = array('lang' => 'ACL_U_USE_CHAT', 'cat' => 'chat');
		$permissions['u_use_users_chat'] = array('lang' => 'ACL_U_USE_USERS_CHAT', 'cat' => 'chat');
		$event['permissions'] = $permissions;
		
		// Adding new category
		$categories = $event['categories'];
		$categories['chat'] = 'ACL_CAT_CHAT';
		$event['categories'] = $categories;		
	}
}
