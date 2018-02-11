<?php
/**
*
* @package for phpBB 3.1
* @copyright (c) 2014
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace skautdd\post2mail\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

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
	
	var $sending = false;

	public function __construct($config, $template, $user, $db, $helper, $auth, $phpbb_root_path, $php_ext)
	{
		$this->config = $config;
		$this->template = $template;
		$this->user = $user;
		$this->db = $db;
		$this->helper = $helper;
		$this->auth = $auth;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
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
			'core.user_setup'						=> 'load_language_on_setup',
			'core.posting_modify_submit_post_after'	=> 'post2mail',
			'core.modify_email_headers'				=> 'email_header',
		);
	}


	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'skautdd/post2mail',
			'lang_set' => 'common',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}
	
	public function email_header($event) 
	{
		if($this->sending == true)
		{
			$header = $event["headers"];
			$header[7] = "Content-type: text/html; charset=UTF-8";
			$event["headers"] = $header;
		}
	}
	
	public function post2mail($event) 
	{
		$this->sending = true;
		$mode = $event['mode'];
		// forum parents
		foreach (get_forum_parents($event['post_data']) as $temp) {
			$forum_parents .= $temp["0"]. " &laquo; ";
		}
		
		// message
		$message = generate_text_for_display($event['data']['message'], $event['data']['bbcode_uid'], $event['data']['bbcode_bitfield'],  $event['post_data']['forum_desc_options']);
		// search for inline attachments to show them in the post text
		if (!empty($event['data']['attachment_data']))
			parse_attachments($event['data']['forum_id'], $message, $event['data']['attachment_data'], $dummy);
		//signature
		if (1) {
			if (0 && $mode != "edit") {
				if(($this->user->data['user_sig']) and ($event['data']['enable_sig']))
				{
					$signature = generate_text_for_display($this->user->data['user_sig'], $this->user->data['user_sig_bbcode_uid'], $this->user->data['user_sig_bbcode_bitfield'], $event['post_data']['forum_desc_options']);
				}
			}else
			{
				if(($event['post_data']['user_sig']) and ($event['post_data']['enable_sig']) and ($n2m_SHOW_SIG))
				{
					$signature = generate_text_for_display($event['post_data']['user_sig'], $event['post_data']['user_sig_bbcode_uid'], $event['post_data']['user_sig_bbcode_bitfield'], $event['post_data']['forum_desc_options']);
				}
			}
		}
		
		//get avatar
		if (!function_exists('get_user_avatar'))
		{
			include($this->phpbb_root_path . 'includes/functions_display' . $this->php_ext);
		}
		$sql = 'SELECT username, user_avatar, user_avatar_type, user_avatar_width, user_avatar_height
				FROM '. USERS_TABLE . ' 
				WHERE username=\''.$this->user->data['username'].'\'';
		$result = $this->db->sql_query($sql);
		$av = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		
		if($av['user_avatar'] != "")
			$avatar_code = get_user_avatar($av['user_avatar'], $av['user_avatar_type'], ($av['user_avatar_width'] > $av['user_avatar_height']) ? 50 : (50 / $av['user_avatar_height']) * $av['user_avatar_width'], ($av['user_avatar_height'] > $av['user_avatar_width']) ? 50 : (50 / $av['user_avatar_width']) * $av['user_avatar_height']);  

		/* board url */
		$board_url = generate_board_url();
		if(substr($board_url, -1) != "/")
			$board_url .= "/";
		
		if (!class_exists('messenger'))
		{
			include($this->phpbb_root_path . 'includes/functions_messenger' . $this->php_ext);
		}
		
		// get all users with emails */		
		$sql = 'SELECT user_id, username, user_colour, user_permissions, user_type, user_email, user_notify_type, user_jabber, user_lang
				FROM '. USERS_TABLE . '
				WHERE user_email != \'\'';
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);
		
		$sended_mails = array();
		$messenger = new \messenger(false);
		
		foreach ($rows as $row)
		{
			$row['user_email'] = strtolower($row['user_email']);// convert address to lowercase
			if(!in_array($row['user_email'], $sended_mails))
			{
				$sended_mails[] = $row['user_email'];
				$this->auth->acl($row);
				
				if($this->auth->acl_gets('f_list', 'f_read', $event['data'][forum_id]) AND (($mode != 'edit' AND $user->data['username'] != $row['username']) OR ($mode == 'edit' AND $user->data['user_notify_post_edit'] == 1 AND $user->data['username'] != $row['username'])))
				{
					/*if($row['user_notify_post_method'] == 2 AND $row['user_jabber'] != '')
					{
						$result = $this->config['email_function_name']($row['user_email'], $subject, $message, $headers);
						$messenger = new messenger();
						$messenger->im($row['user_jabber'], $row['username']);
						$messenger->template('notify_post_im', $row['user_lang']);
						$messenger->assign_vars(array(
							'MESSAGEE'    		=> str_replace("<br />", "\n", $event['data'][message]),
							'POSTER_USERNAME'	=> $user->data['username'],
							'FORUM_NAME'		=> $post_FORUMNAME,
							'TOPIC_NAME'		=> $post_TOPICTITLE,
						));
						 $messenger->send(NOTIFY_IM);
						 $messenger->save_queue();
					}
					elseif($row['user_notify_post_method'] == 1 AND $row['user_jabber'] != ''){
			
						$messenger = new messenger();
						$messenger->im($row['user_jabber'], $row['username']);
						$messenger->template('notify_post_im', $row['user_lang']);
						$messenger->assign_vars(array(
						'MESSAGEE'			=> str_replace("<br />", "\n", $event['data'][message]),
						'POSTER_USERNAME'	=> $user->data['username'],
						'FORUM_NAME'		=> $post_FORUMNAME,
						'TOPIC_NAME'		=> $post_TOPICTITLE,
						));
						 $messenger->send(NOTIFY_IM);
						 $messenger->save_queue();
					}
					else{
						
					}*/
					
					
					$messenger->subject('SkautForum = '.$event['post_data']['post_subject']);
					$messenger->to($row['user_email'], $row['username']);
					$messenger->from($this->config['board_email'], $this->config['sitename']);
					$messenger->im($row['user_jabber'], $row['username']);
					$messenger->template('@skautdd_post2mail/mail_template', $row['user_lang']);
					$messenger->assign_vars(array(
								/* URL */
								'FORUM_BASE_ADDRESS'	=> $board_url,
								'POST_FORUM_URL'		=> $board_url.'viewforum.php?f='.$event['data']['forum_id'],
								'POST_TOPIC_URL'		=> $board_url.'viewtopic.php?f='.$event['data']['forum_id'].'&t='.$event['data']['topic_id'],
								'POST_URL'				=> $board_url.'viewtopic.php?f='.$event['data']['forum_id'].'&t='.$event['data']['topic_id'].'&p='.$event['data']['post_id'].'#p'.$event['data']['post_id'],
								'POSTER_PROFILE_URL'	=> $board_url.'memberlist.php?mode=viewprofile&u='.$event['post_data']['poster_id'],
								'EDITOR_PROFILE_URL'	=> $board_url.'memberlist.php?mode=viewprofile&u='.$event['post_data']['post_edit_user'],
								'REPLY_URL'				=> $board_url.'posting.php?mode=reply&f='.$event['data']['forum_id'].'&t='.$event['data']['topic_id'],
								'EDIT_URL'				=> $board_url.'posting.php?mode=edit&f='.$event['data']['forum_id'].'&p='.$event['data']['post_id'],
								'QUOTE_URL'				=> $board_url.'posting.php?mode=quote&f='.$event['data']['forum_id'].'&p='.$event['data']['post_id'],
								'DELETE_URL'			=> $board_url.'posting.php?mode=delete&f='.$event['data']['forum_id'].'&p='.$event['data']['post_id'],
								'INFO_URL'				=> $board_url.'mcp.php?i=main&mode=post_details&f='.$event['data']['forum_id'].'&p='.$event['data']['post_id'],
								'PM_URL'				=> $board_url.'ucp.php?i=pm&mode=compose&action=quotepost&p='.$event['data']['post_id'],
								'EMAIL_URL'				=> $board_url.'memberlist.php?mode=email&u='.$event['post_data']['poster_id'],
								/* OTHER */
								'POST_FORUM_PARENTS'	=> $forum_parents,
								'POST_FORUM_NAME'		=> $event['data']['forum_name'],
								'POST_TOPIC_NAME'		=> $event['data']['topic_title'],
								'POST_SUBJECT'			=> $event['post_data']['post_subject'],
								'POST_AVATAR'			=> $avatar_code,
								'POST_USERNAME'			=> ($mode == "edit") ? $event['post_data']['username'] : $this->user->data['username'],
								'POST_EDITOR'			=> ($mode == "edit") ? $user->data['username'] : "",
								'POST_EMAIL'			=> $this->user->data['user_email'],
								'CURRENT_TIME'			=> date("H:i:s, j.m. Y "),
								'MESSAGE'				=> $message,
								'POST_SIGNATURE'		=> $signature,
								'EDIT_REASON'			=> $event['post_data']['post_edit_reason'],
								/* S */
								'S_ATTACHMENT'	=> !empty($event['data']['attachment_data']),
								'S_SHOW_EMAIL'	=> $this->user->data['user_allow_viewemail'],
						));
					/*$messenger->msg = trim($messenger->template->assign_display('body'));*/
					/*$headers = $messenger->build_header('','','');
					unset($headers[7]);
					$headers[] = 'Content-type: text/html; charset=UTF-8';
					$result = phpbb_mail($row['user_email'], $messenger->subject, $message, $headers, $messenger->eol,$error_msg);*/
					$messenger->send(NOTIFY_EMAIL);
				}
			}
		}
		// revert acl to current user
		$user_data = $this->auth->obtain_user_data($this->user->data['user_id'],100);
		$this->auth->acl($user_data);
		$this->sending = false;
	}
}
