<?php
require_once ('engine/'.DB_TYPE.'sql.php');

function getToken ($uid)
{
	return sqlQuery('SELECT internal_token, active FROM auth WHERE user_id = ?', $uid)->fetch();
}

function addToken($itoken, $uid)
{
	sqlQuery('INSERT INTO auth (internal_token, user_id) VALUES (?, ?)', $itoken, $uid);
}

function updateToken ($uid, $nitoken)
{
	sqlQuery('UPDATE auth SET internal_token = ? WHERE user_id = ?', $nitoken, $uid);
}

function setTokenActive ($itoken, $active = true)
{
	sqlQuery('UPDATE auth SET active = ? WHERE internal_token = ?',
			($active) ? 'true' : 'false', $itoken);
}

function getUserLogin($itoken)
{
	$res = sqlQuery('SELECT COUNT(*) as cnt, user_id FROM auth WHERE internal_token=? group by user_id limit 1', $itoken)->fetch();
	return ($res['cnt'] == 0) ? false : $res['user_id'];
}

function isUserBanned ($login)
{
	$res = sqlQuery('SELECT ban_state from bans WHERE ban_login = ?', $login)->fetch();

	/*
		Ban states:
			0 or empty array - not banned
			1 - shadow banned
			2 - completely banned

		See ban_states table in database
	*/

	return ($res['ban_state'] == 2 ? true : false);
}

function isUserAuthorized ($login)
{
	$res = sqlQuery('SELECT COUNT(*) as cnt, user_id FROM auth WHERE user_id = ? AND active = true group by user_id limit 1', $login)->fetch();

	return ($res['cnt'] == 0) ? false : true;
}

function isUserAdmin ($login)
{
	$res = sqlQuery('SELECT COUNT(*) as cnt FROM admin_users WHERE user_login = ? group by user_login limit 1', $login)->fetch();

	return ($res['cnt'] == 0) ? false : true;
}

function tmpa_getLogin ($code)
{
	$res = sqlQuery('SELECT tmpa_auth_login FROM temp_auth WHERE tmpa_code = ?', $code)->fetch();
	return (empty($res['tmpa_auth_login'])) ? false : $res['tmpa_auth_login'];
}

function tmpa_setCode ($login, $code)
{
	return sqlQuery('INSERT INTO temp_auth (tmpa_auth_login, tmpa_code) VALUES (?, ?)', $login, $code);
}

function tmpa_removeCode ($login)
{
	return sqlQuery('DELETE FROM temp_auth WHERE tmpa_auth_login = ?', $login);
}

function setTgAdminSubscription ($login, $state, $chat = 0)
{
	$state = ($state === true) ? 'true' : 'false'; // PDO workaround
	return sqlQuery('UPDATE admin_users SET telegram_subscribed = ?, telegram_chat_id = ? WHERE user_login = ?', $state, $chat, $login);
}

function getTgAdminSubscribers()
{
	return sqlQuery('SELECT * FROM admin_users WHERE telegram_subscribed = true AND telegram_chat_id <> 0')->fetchAll();
}

/// -------------------------------------------- ///

function getLastId()
{
	$res = sqlQuery('SELECT MAX(message_id) as mval FROM messages')->fetch();
	return (int)$res['mval'];
}
					  
function getMessages($amount, $offset, $getHidden = false)
{
	$amount = (int)$amount;

	if ($getHidden === true)
	{
		$res = sqlQuery('SELECT count(*) as cnt  
						FROM messages 
						where message_visible = false AND message_id >= ?
						GROUP BY message_id
						LIMIT '.$amount,
				$offset)->fetch();

		return (int)$res['cnt'];
	}
		else
	return sqlQuery('SELECT message_id as id,
						message_author_name as name,
						message_name_color as hash, 
						message_text as message,
						UNIX_TIMESTAMP(message_timestamp) as timestamp 
						FROM messages 
						where message_visible = true AND message_id >= ? order by message_id asc LIMIT '.$amount,
				$offset)->fetchAll();
}



function getTrackId($filename)
{
	$res = sqlQuery('SELECT track_id FROM vote_tracks WHERE track_filename = ?', $filename)->fetch();
	
	return (empty($res['track_id']) ? false : (int)$res['track_id']);
}


function getUserVote($trackId, $userLogin)
{
	$res = sqlQuery('SELECT user_vote FROM vote_users WHERE track_id = ? AND user_login = ?', $trackId, $userLogin)->fetch();
	
	return (count($res) == 0) ? 0 : (int)$res['user_vote'];
}

function addTrack($filename)
{
	sqlQuery('INSERT INTO vote_tracks (track_filename) VALUES (?)', $filename);
	return getLastInsertId('seq_vote_tracks_track_id'); // sequence name!
}


function setUserVoted ($trackId, $userLogin, $isClass)
{
	sqlQuery('INSERT INTO vote_users (track_id, user_login, user_vote) values (?, ?, ?)', $trackId, $userLogin, $isClass);
}

function setUserRevote ($trackId, $userLogin, $isClass)
{
	sqlQuery('UPDATE vote_users SET user_vote = ? WHERE track_id = ? AND user_login = ?', $isClass, $trackId, $userLogin);
}


function saveTelegramMessage ($userInfo, $message)
{
	sqlQuery('INSERT INTO telegram_chat_log (from_id, from_first_name, from_last_name, from_username, message_type, message_text, message_attach) VALUES (?, ?, ?, ?, ?, ?, ?)',
			$userInfo['id'],
			$userInfo['first_name'],
			$userInfo['last_name'],
			$userInfo['username'],

			$message['type'],
			$message['text'],
			$message['attach']);
}



function saveDonationEvent ($event)
{
	sqlQuery('INSERT INTO donation_events (notification_type, operation_id, amount_income, amount_withdraw, event_timestamp, event_label, sender_lastname, sender_firstname, sender_patronym, sender_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			$event['notification_type'],
			$event['operation_id'],
			$event['amount_income'],
			$event['amount_withdraw'],
			$event['event_timestamp'],
			$event['event_label'],
			$event['sender_lastname'],
			$event['sender_firstname'],
			$event['sender_patronym'],
			$event['sender_address']
		)->fetch();
}