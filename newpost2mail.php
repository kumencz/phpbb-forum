<?php
  // newpost2mail beta 20 for phpBB3 by Stefan Hendricks
  // See http://henmedia.de for latest version
  //
  // Do not wonder if this code is formatted strange
  // in your editor as I always use a tabsize of 2 :)
  // only allow access from "posting.php"


  if ((substr($_SERVER["SCRIPT_NAME"],-11) != "posting.php") and (substr($_SERVER["SCRIPT_URI"],-11) != "posting.php") /*or (substr($_SERVER["SCRIPT_NAME"],-11) != "mail2post.php") and (substr($_SERVER["SCRIPT_URI"],-11) != "mail2post.php")*/) die("ACCESS DENIED");
  newpost2mail($data);
  function newpost2mail($data) {
    global $config, $mode, $user, $post_data, $phpEx, $phpbb_root_path, $db;
    if (!function_exists('get_user_avatar'))
	{
		include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
	}
    $version = "beta 20";
    // variables that can be used in newpost2mail.config.php to build an individial subject line
    $post_SITENAME    = $config['sitename'];
    $post_FORUMNAME   = $data['forum_name'];
    $post_MODE        = $mode;
    $post_TOPICTITLE  = $data['topic_title'];
    $post_SUBJECT     = $post_data['post_subject'];
    $post_USERNAME    = $user->data['username'];
    $post_IP          = $data['poster_ip'];
    $post_HOST        = @gethostbyaddr($post_IP);
    // 3rd party edit?
    if ( $mode == "edit" ) {
      $post_EDITOR    = $user->data['username'];
      $post_USERNAME  = $post_data[username];
    }
    // get forum parents
    foreach (get_forum_parents($post_data) as $temp) {
      $post_FORUMPARENTS       .= $temp["0"]. " / ";
      $post_FORUMPARENTS_laquo .= $temp["0"]. " &laquo; ";
    }
    // read configuration
    include($phpbb_root_path . 'newpost2mail.config.php');
    // check if the actual mode is set for sending mails
    if ($n2m_MAIL_ON[$mode]) {
      // if there is a language set in newpost2mail.config.php then use that setting.
      // Otherwise read default language from board config and use that.
      $n2m_LANG ? $lang = $n2m_LANG : $lang = $config['default_lang'];
      // get (translated) phrases and convert them to UTF8
      foreach ($n2m_TEXT[cs] as $key=>$value) {
        if ($n2m_TEXT[$lang][$key]) {
          $phrase[$key] = $n2m_TEXT[$lang][$key];
        } else {
          $phrase[$key] = utf8_encode($n2m_TEXT[en][$key]);
        }
      }
      // set variables for later use
      $board_url      = generate_board_url();
      if (substr($board_url, -1) != "/") $board_url .= "/";
      $forum_url      = $board_url . "viewforum.php?f=$data[forum_id]";
      $thread_url     = $board_url . "viewtopic.php?f=$data[forum_id]&t=$data[topic_id]";
      $post_url       = $board_url . "viewtopic.php?f=$data[forum_id]&t=$data[topic_id]&p=$data[post_id]#p$data[post_id]";
      $u_profile_url  = $board_url . "memberlist.php?mode=viewprofile&u=$post_data[poster_id]";
      $e_profile_url  = $board_url . "memberlist.php?mode=viewprofile&u=$post_data[post_edit_user]";
      $reply_url      = $board_url . "posting.php?mode=reply&f=$data[forum_id]&t=$data[topic_id]";
      $edit_url       = $board_url . "posting.php?mode=edit&f=$data[forum_id]&p=$data[post_id]";
      $quote_url      = $board_url . "posting.php?mode=quote&f=$data[forum_id]&p=$data[post_id]";
      $delete_url     = $board_url . "posting.php?mode=delete&f=$data[forum_id]&p=$data[post_id]";
      $info_url       = $board_url . "mcp.php?i=main&mode=post_details&f=$data[forum_id]&p=$data[post_id]";
      $pm_url         = $board_url . "ucp.php?i=pm&mode=compose&action=quotepost&p=$data[post_id]";
      $email_url      = $board_url . "memberlist.php?mode=email&u=$post_data[poster_id]";
      //get avatar
      $sql = 'SELECT username, user_avatar, user_avatar_type, user_avatar_width, user_avatar_height
				FROM '. USERS_TABLE . '
				WHERE username=\''.$post_USERNAME.'\'';
  		$result = $db->sql_query($sql);
  		$rows = $db->sql_fetchrowset($result);
       foreach ($rows as $avatarus) {
      $avatar = $avatarus['user_avatar'];
      $avatar_type = $avatarus['user_avatar_type'];
      $avatar_width = $avatarus['user_avatar_width'];
      $avatar_height = $avatarus['user_avatar_height'];
      if($avatar != ""){
      $avatar_code = get_user_avatar($avatar, $avatar_type, ($avatar_width > $avatar_height) ? 50 : (50 / $avatar_height) * $avatar_width, ($avatar_height > $avatar_width) ? 50 : (50 / $avatar_width) * $avatar_height);
		}else $avatar_code = "<img src=\"".$phpbb_root_path ."{$web_path}styles/" . rawurlencode($user->theme['theme_path']) . "/theme/images/no_avatar.gif\" width=\"50px;\" height=\"50px;\" alt=\"\" />" ;
      }

      // build the email header
      include_once($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);
      $headers .= "Date: ".date("D, j M Y H:i:s O")."\n";
      $headers .= "From: \"".mail_encode(html_entity_decode($config[sitename]))."\" <$config[board_email]>\n";
      $headers .= "X-Mailer: newpost2mail $version for phpBB3\n";
      $headers .= "MIME-Version: 1.0\n";
      $headers .= "Content-type: text/html; charset=UTF-8\n";
      // build the email body
      $message .= "<HTML>\n";
      $message .= "<HEAD>\n";
      $message .= "<base href='$board_url'>\n";
      $message .= "<META http-equiv='content-type' content='text/html; charset=UTF-8'>\n";
      $message .= "</HEAD>\n";
      $message .= "<BODY>\n";
      // build the informational table
      $message .= " <a href='$forum_url'>$post_FORUMPARENTS_laquo$post_FORUMNAME</a>«<a href='$thread_url'>$post_TOPICTITLE</a>>><a href='$post_url'><b>$post_SUBJECT</b></a>";
      $message .= "<br />\n";
      $message .= "<table class='table_info'>\n";
       if ($post_EDITOR){
      $message .= "<tr><td rowspan=2 align=\"left\">".$avatar_code."</td><td>$phrase[original_by]<font size=\"4\"><a href='$u_profile_url'><b>$post_USERNAME</b></a></font>";
      $message .= "&nbsp;&nbsp;, $phrase[edited_by] <a href='$e_profile_url'><font size=\"4\"><b>$post_EDITOR</b></a></font>";
		}else
		{
	  $message .= "<tr><td rowspan=2 align=\"left\">".$avatar_code."</td><td><a href='$u_profile_url'><font size=\"4\"><b>$post_USERNAME</b></a></font>";
		}
      if (($user->data['user_allow_viewemail']) or ($n2m_ALWAYS_SHOW_EMAIL)) $message .= " (<a href='mailto:". $user->data['user_email'] . "'>". $user->data['user_email'] ."</a>)";
      $message .= "</td>\n</tr>\n";
      $message .= "<tr><td><b>".date("H:i:s, j.m. Y ")."</b></td>\n</tr>\n";
      $message .= "</table>\n";
      $message .= "<br>";
      // build the post text table
      $message .= "<table class='table_post' width=\"100%\" border=1>\n";
      $message .= "<tr><td><div class='content'>\n";
      // search for inline attachments to show them in the post text
      if (!empty($data[attachment_data])) parse_attachments($data[forum_id], $data[message], $data[attachment_data], $dummy, true);
      // generate post text
      $message .= str_replace("<br />", "<br />\n", generate_text_for_display($data[message], $data[bbcode_uid], $data[bbcode_bitfield], $post_data[forum_desc_options]))."\n";
      // show attachments if not already shown in the post text
      if (!empty($data[attachment_data])) {
        $message .= "<br />\n<dl class='attachbox'><dt>$phrase[attachments]:</dt><dd>\n";
        foreach ($data[attachment_data] as $filename) {
          $message .= print_r($filename, 1);
        }
        $message .= "</dl>\n";
      }
      // add signature
      if ($n2m_SHOW_SIG) {
        if ($mode != "edit") {
          if ( ($user->data[user_sig]) and ($data[enable_sig]) ) {
            $message .= "<hr><div style=\"font-size: 8px\" class='signature'>\n";
            $message .= generate_text_for_display($user->data[user_sig], $user->data[user_sig_bbcode_uid], $user->data[user_sig_bbcode_bitfield], $post_data[forum_desc_options])."\n";
            $message .= "</div>\n";
          }
        } else {
          if ( ($post_data[user_sig]) and ($post_data[enable_sig]) and ($n2m_SHOW_SIG)) {
            $message .= "<hr><div style=\"font-size: 8px\" class='signature'>\n";
            $message .= generate_text_for_display($post_data[user_sig], $post_data[user_sig_bbcode_uid], $post_data[user_sig_bbcode_bitfield], $post_data[forum_desc_options])."\n";
            $message .= "</div>\n";
          }
        }
      }
      $message .= "</div></td></tr></table>\n";
      $message .= "<table>\n";
      $message .= "<tr><td width=90><a href='$reply_url'>$phrase[reply]</a></td><td width=70><a href='$quote_url'>$phrase[quote]</a></td><td width=70><a href='$edit_url'>$phrase[edit]</a></td><td width=70><a href='$delete_url'>$phrase[delete]</a></td></tr>\n";
      // 3rd party edit
      if ($post_data[post_edit_reason]) {
        $post_EDITOR ? $edited_by = $post_EDITOR : $edited_by = $post_USERNAME;
        $message .= "<div class='notice'> $phrase[edited_by] $edited_by,</br> $phrase[edit_reason]: <em>$post_data[post_edit_reason]</em></div></br>\n";
      }


      $message .= "</table>\n";
      $message .= "<a href=\"http://www.skautdd.skauting.cz\">http://www.skautdd.skauting.cz</a>";
      $message .= "</BODY></HTML>\n";
			$message = wordwrap($message, 256);
      // encode subject
      $subject = mail_encode(html_entity_decode($n2m_SUBJECT));
      // fix for phpBB 3.05 !
      $subject = str_replace("\r", "", $subject);
      $subject = str_replace("\n", "", $subject);

        $sql = $db->sql_build_query('SELECT', array('SELECT' => 'n.user_id',
                                                    'FROM' => array(NOTIFY_POST_TABLE => 'n'),
													'WHERE' => 'n.topic_id = \''.$data[topic_id].'\' OR n.forum_id = \''.$data[forum_id].'\'',));
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result)) $detach[] = $row['user_id'];
        $db->sql_freeresult($result);
        $detach[] = 0;
        $sql = $db->sql_build_query('SELECT', array('SELECT' => 'u.user_email',
                                                    'FROM' => array(USERS_TABLE => 'u'),
													'WHERE' => 'u.user_email != \'\' AND u.user_notify_post = 1 AND '.$db->sql_in_set('u.user_id', $detach, 1),));
        $result = $db->sql_query($sql);

        while ($row = $db->sql_fetchrow($result)) $mailto[] = $row['user_email'];
        $db->sql_freeresult($result);

      // convert all addresses to lowercase and delete any empty addresses
      foreach ($mailto as $key => $value) {
        if (is_null($value) or ($value == "")) {
          unset($mailto[$key]);
        } else {
          $mailto[$key] = strtolower($mailto[$key]);
        }
      }
      // insure that every address is only used once
      $mailto = array_unique($mailto);

      foreach ($mailto as $key) {
        $mail .= $key;
      }
       //die($mail); // for debugging purposes, mail will be shown in browser and not sent out if we uncomment this line

    // die($message.$mail);



	 include_once($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);
	 global $auth, $db, $user, $config, $template;
	 foreach ($mailto as $mailtoo) {

		$sql = 'SELECT *
				FROM '. USERS_TABLE . '
				WHERE user_email=\''.$mailtoo.'\'';

  		$result = $db->sql_query($sql);




		while ($row = $db->sql_fetchrow($result))
		{

			$user->data = array_merge($user->data, $row);
			$auth->acl($user->data);
			$user->ip = '0.0.0.0';

			$user->setup();
			if($auth->acl_gets('f_list', 'f_read', $data[forum_id]) AND (($mode != 'edit' AND $post_USERNAME != $row['username']) OR ($mode == 'edit' AND $user->data['user_notify_post_edit'] == 1 AND $post_EDITOR != $row['username'])))
			{
				if($row['user_notify_post_method'] == 2 AND $row['user_jabber'] != '')
				{

					$result = $config['email_function_name']($mailtoo, $subject, $message, $headers);
					$messenger = new messenger();
					$messenger->im($row['user_jabber'], $row['username']);
					$messenger->template('notify_post_im', $row['user_lang']);
					$messenger->assign_vars(array(
			        'MESSAGEE'    => str_replace("<br />", "\n", $data[message]),
			        'POSTER_USERNAME'    => $post_USERNAME,
			        'FORUM_NAME'    => $post_FORUMNAME,
			        'TOPIC_NAME'    => $post_TOPICTITLE,
					));
					 $messenger->send(NOTIFY_IM);
					 $messenger->save_queue();
				}
				elseif($row['user_notify_post_method'] == 1 AND $row['user_jabber'] != ''){

					 $messenger = new messenger();
					$messenger->im($row['user_jabber'], $row['username']);
					$messenger->template('notify_post_im', $row['user_lang']);
					$messenger->assign_vars(array(
			        'MESSAGEE'    => str_replace("<br />", "\n", $data[message]),
			        'POSTER_USERNAME'    => $post_USERNAME,
			        'FORUM_NAME'    => $post_FORUMNAME,
			        'TOPIC_NAME'    => $post_TOPICTITLE,
					));
					 $messenger->send(NOTIFY_IM);
					 $messenger->save_queue();
				}
				else{
					$result = $config['email_function_name']($mailtoo, $subject, $message, $headers);
					// echo $result;
					//echo $row['user_email'].'<br>';

				}
				//echo $row['user_email']."<br>";
			}

		}
		$db->sql_freeresult($result);

	  }/*
	  $sql = 'SELECT *
		  FROM '. USERS_TABLE . '
		  WHERE username=\''.$post_USERNAME.'\'';

	  $result = $db->sql_query($sql);
	  echo $sql;
	  $user->data = $row;
	  $auth->acl($user->data);
	  $user->ip = '0.0.0.0';
	  $user->setup();*/
    }
    end:
  }
?>
