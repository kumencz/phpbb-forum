<?php
/**
*
* @package for phpBB 3.1
* @copyright (c) 2014
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace skautdd\LastPage\event;

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
			'core.viewtopic_input'	=> 'latest_post',
		);
	}

	/**
	* Setup Lightbox settings
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function latest_post($event)
	{
		global $topic_id, $db, $user, $post_id, $phpbb_root_path, $phpEx;
		if(request_var('start',"no") == "no")
		{
		$sql = 'SELECT topic_last_post_id
					FROM ' . TOPICS_TABLE . "
					WHERE topic_id = $topic_id";
				$result = $db->sql_query($sql);
				$topic_last_post_id = (int) $db->sql_fetchfield('topic_last_post_id');
				$db->sql_freeresult($result);
		$event['post_id']	= request_var('p', $topic_last_post_id);
		}
	}
}
