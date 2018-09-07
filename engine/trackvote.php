<?php

function trackVote($userLogin, $isClass)
{
	$currentTrack = trim(file_get_contents(CURRENT_TRACK_FILE));

	if (empty($currentTrack))
		return -1;

	$trackId = getTrackId($currentTrack);
	
	if ($trackId === false)
		$trackId = addTrack($currentTrack);
	
	$userVote = getUserVote($trackId, $userLogin);

	if (!empty($userVote))
	{
		
		if ($userVote == $isClass)
			return 3;
		else
		{
			setUserRevote($trackId, $userLogin, $isClass);
			return 4;
		}
	}	
	else
	{
		setUserVoted($trackId, $userLogin, $isClass);
		return 0;
	}
}

function processTrackVote()
{
	global $route;
	header ('Access-Control-Allow-Origin: *');
	

	$isClass = 0;

	switch ($route[1])
	{
		case 'class'    : $isClass = 1; break;
		case 'disclass' : $isClass = -1; break;
		default: die ('{"status":2,"payload":"Bad method, use class or disclass"}'); return;
	}
	
	
	if (preg_match('/^[a-zA-Z0-9]{64}$/', $_REQUEST['auth']) !== 1)
		die ('{"status":1,"payload":"Bad auth, cannot vote"}');

	$userLogin = getUserLogin($_REQUEST['auth']);
	
	if ($userLogin === false)
		die ('{"status":1,"payload":"Bad auth, cannot vote"}');

	$res = trackVote($userLogin, $isClass);

	switch ($res)
	{
		case -1 :
			die ('{"status":-1,"payload":"Internal error"}');
		break;

		case 0 :
			die ('{"status":0,"payload":'.$isClass.'}');
			break;

		case 3 :
			die ('{"status":3,"payload":"You have already voted for this track"}');
			break;

		case 4 :
			die ('{"status":4,"payload":'.$isClass.'}');
			break;
		
		default:
			die ('{"status":-1,"payload":"Internal error"}');
			break;
	}
	
	
}