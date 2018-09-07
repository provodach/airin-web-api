<?php
require_once ('engine/enconfig.php');
require_once ('engine/database.php');
require_once ('engine/functions.php');

$route = explode('/', $_GET['route']);

switch ($route[0])
{
	case 'auth' :
		require_once 'engine/auth.php';
		processVkAuth();
		break;

	case 'authgram' :
		require_once 'engine/auth.php';
		processTelegramAuth();
		break;
		
	case 'log'  :
		require_once 'engine/logs.php';
		processLog();
		break;
		
	case 'trackvote'  :
		require_once 'engine/trackvote.php';
		processTrackVote();
		break;

	case 'telegram'  :
		require_once 'engine/trackvote.php';
		require_once 'engine/telegram.php';
		processTelegram();
		break;

	case 'donate' : 
		require_once 'engine/donate.php';
		processDonate();
		break;

	case 'catty.svg' :
		require_once 'engine/catty.php';
		processCatty();
		break;
		
	default : displayStub(); break;
}


function displayStub()
{
	?><h1>This is a place where other machines can talk to Airin. WTF are you stalking here?</h1><?php
}