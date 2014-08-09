<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.my-bb.ir
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("reputation_vote", "erc_vote");
$plugins->add_hook("xmlhttp", "erc_xmlhttp");

function editreputationcomment_info()
{
	global $lang;
	$lang->load('editreputationcomment');

	return array(
		"name"			=> $lang->editreputationcomment_info,
		"description"	=> $lang->editreputationcomment_info_desc,
		"website"		=> "http://www.my-bb.ir",
		"author"		=> "AliReza_Tofighi",
		"authorsite"	=> "http://www.my-bb.ir",
		"version"		=> "1.0",
		"guid" 			=> "",
		"compatibility" => "17*, 18*"
	);
}

function editreputationcomment_activate()
{
	global $db, $mybb, $lang;
	$lang->load('editreputationcomment');

	// DELETE ALL SETTINGS TO AVOID DUPLICATES
	$db->write_query("DELETE FROM ".TABLE_PREFIX."settings WHERE name IN(
		'editreputationcomment_active',
		'editreputationcomment_moderators'
	)");
	$db->delete_query("settinggroups", "name = 'editreputationcomment'");

	$query = $db->simple_select("settinggroups", "COUNT(*) as rows");
	$rows = $db->fetch_field($query, "rows");
	
	$insertarray = array(
		'name' => 'editreputationcomment',
		'title' => $lang->editreputationcomment_opt,
		'description' => $lang->editreputationcomment_opt_desc,
		'disporder' => $rows+1,
		'isdefault' => 0
	);
	$group['gid'] = $db->insert_query("settinggroups", $insertarray);
	
	$insertarray = array(
		'name' => 'editreputationcomment_active',
		'title' => $lang->editreputationcomment_active,
		'description' => $lang->editreputationcomment_active_desc,
		'optionscode' => 'onoff',
		'value' => 1,
		'disporder' => 1,
		'gid' => $group['gid']
	);
	$db->insert_query("settings", $insertarray);
	
	$insertarray = array(
		'name' => 'editreputationcomment_moderators',
		'title' => $lang->editreputationcomment_moderators,
		'description' => $lang->editreputationcomment_moderators_desc,
		'optionscode' => 'groupselect',
		'value' => 4,
		'disporder' => 1,
		'gid' => $group['gid']
	);
	$db->insert_query("settings", $insertarray);

	// insert reputation_vote_edit template:
	$db->write_query("DELETE FROM ".TABLE_PREFIX."templates WHERE title = 'reputation_vote_edit'");
	$insertarray = array(
		"title" => "reputation_vote_edit",
		"template" => $db->escape_string('		<div class="float_right postbit_buttons">
			<a href="javascript:editReputation({$reputation_vote[\'rated_uid\']}, {$reputation_vote[\'rid\']});" class="postbit_edit"><span>{$lang->edit_vote}</span></a>
		</div>'),
		"sid" => "-1",
		"version" => "1800",
		"dateline" => TIME_NOW
	);
	$db->insert_query("templates", $insertarray);

	if(!function_exists('find_replace_templatesets'))
	{
		require_once MYBB_ROOT."inc/adminfunctions_templates.php";
	}

	find_replace_templatesets("reputation_vote", "#".preg_quote('{$edit_link}')."#i", '', 0);
	find_replace_templatesets("reputation", "#".preg_quote('<script src="{$mybb->asset_url}/jscripts/jeditable/jeditable.min.js"></script><script type="text/javascript" src="{$mybb->asset_url}/jscripts/editcomment.js"></script>')."#i", '', 0);

	find_replace_templatesets("reputation_vote", "#".preg_quote('{$delete_link}')."#i", '{$delete_link}{$edit_link}');
	find_replace_templatesets("reputation", "#".preg_quote('</head>')."#i", '<script src="{$mybb->asset_url}/jscripts/jeditable/jeditable.min.js"></script><script type="text/javascript" src="{$mybb->asset_url}/jscripts/editcomment.js"></script></head>');

	rebuild_settings();
}

function editreputationcomment_deactivate()
{
	global $db, $mybb;

	$db->write_query("DELETE FROM ".TABLE_PREFIX."settings WHERE name IN(
		'editreputationcomment_active',
		'editreputationcomment_moderators'
	)");

	$db->delete_query("settinggroups", "name = 'editreputationcomment'");
	$db->delete_query("templates", "title = 'reputation_vote_edit'");

	if(!function_exists('find_replace_templatesets'))
	{
		require_once MYBB_ROOT."inc/adminfunctions_templates.php";
	}

	find_replace_templatesets("reputation_vote", "#".preg_quote('{$edit_link}')."#i", '', 0);
	find_replace_templatesets("reputation", "#".preg_quote('<script src="{$mybb->asset_url}/jscripts/jeditable/jeditable.min.js"></script><script type="text/javascript" src="{$mybb->asset_url}/jscripts/editcomment.js"></script>')."#i", '', 0);

	rebuild_settings();
}


function erc_vote()
{
	global $mybb, $templates, $lang, $edit_link, $reputation_vote;
	$lang->load('editreputationcomment');
	$moderators = explode("\n", $mybb->settings['editreputationcomment_moderators']);

	$edit_link = '';
	if($mybb->settings['editreputationcomment_moderators'] == -1 || in_array($mybb->user['usergroup'], $moderators))
	{
		eval("\$edit_link = \"".$templates->get("reputation_vote_edit")."\";");
	}
	
	$reputation_vote['comments'] = '<span id="vote_comment_'.$reputation_vote['rated_uid'].'_'.$reputation_vote['rid'].'" style="display:inline-block">'.$reputation_vote['comments'].'</span>';
}

function erc_xmlhttp()
{
	global $lang, $mybb, $db, $plugins;

	if($mybb->get_input('action') == 'edit_vote')
	{
		$moderators = explode("\n", $mybb->settings['editreputationcomment_moderators']);
		$query = $db->simple_select("reputation", "*", "rid = '".$mybb->get_input('rid', 1)."' And uid = '".$mybb->get_input('uid', 1)."'");
		$reputation = $db->fetch_array($query);

		if($mybb->settings['editreputationcomment_moderators'] == -1 || in_array($mybb->user['usergroup'], $moderators))
		{
			$lang->load("reputation");
			$lang->load("editreputationcomment");
			if($mybb->get_input('do') == 'get_vote')
			{
				// Send our headers.
				header("Content-type: text/html; charset={$charset}");

				// Send the contents of the post.
				echo $reputation['comments'];
				exit;
			}
			elseif($mybb->get_input('do') == "update_vote")
			{
				// Verify POST request
				if(!verify_post_check($mybb->get_input('my_post_key'), true))
				{
					xmlhttp_error($lang->invalid_post_code);
				}
				if(!is_array($reputation))
				{
					xmlhttp_error($lang->reputation_not_exists);
				}

				$message = trim($mybb->get_input('value'));

				if(my_strlen($message) < $mybb->settings['minreplength'] && $reputation['pid'] == 0)
				{
					xmlhttp_error($lang->add_no_comment);
				}

				if(my_strtolower($charset) != "utf-8")
				{
					if(function_exists("iconv"))
					{
						$message = iconv($charset, "UTF-8//IGNORE", $message);
					}
					else if(function_exists("mb_convert_encoding"))
					{
						$message = @mb_convert_encoding($message, $charset, "UTF-8");
					}
					else if(my_strtolower($charset) == "iso-8859-1")
					{
						$message = utf8_decode($message);
					}
				}

				$db->update_query("reputation", array("comments" => $db->escape_string($message)), "rid = '".$mybb->get_input('rid', 1)."' And uid = '".$mybb->get_input('uid', 1)."'", 1);

				require_once MYBB_ROOT."inc/class_parser.php";
				$parser = new postParser;

				$parser_options = array(
					"allow_html" => 0,
					"allow_mycode" => 0,
					"allow_smilies" => 1,
					"allow_imgcode" => 0,
					"filter_badwords" => 1
				);

				$post_message = $parser->parse_message($message, $parser_options);
				if($post_message == '')
				{
					$post_message = $lang->no_comment;
				}

				// Send our headers.
				header("Content-type: application/json; charset={$charset}");

				$plugins->run_hooks("xmlhttp_update_vote");

				echo json_encode(array("message" => $post_message."\n"));
				exit;
			}
		}
	}
}