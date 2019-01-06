<?php

$external = (!empty($route[1]) && $route[1] == 'external') ? true : false;

function finish ($status, $payload = '')
{
	global $external;
	if ($status == 0)
	{
		if ($external)
		{
			die ('{"status":0, "payload":"'.$payload.'"}');
		}
		else
		{
			// Oh hardcode oh no...
			header ('Location: https://provoda.ch/chat?auth='.$payload);
			die ('Redirecting...');
		}
	}
		else
	{
		header ('HTTP/1.1 500 Internal Server Error');
		if ($external)
		{
			die ('{"status":'.$status.', "payload":"'.$payload.'"}');
		}
		else
		{
			die ('<h1>Failed to authorize: '.$payload.'</h1>');
		}
	}
}

function request ($url)
{	
	error_log ($url);

	$curl = curl_init();

	if($curl)
	{
		curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, 5);
		curl_setopt($curl, CURLOPT_USERAGENT, 'Airin Backend; +https://lab.nyan.pw/airin');
		$out = curl_exec($curl);
		curl_close($curl);
		return (empty ($out)) ? false : $out;
	} else
	{
		return false;
	}
}

function processIncomingLogin ($login)
{
	$res = getToken($login);
	if (!empty($res['internal_token']))
	{
		$ntoken = '';
		if ($res['active'])
		{
			$ntoken = $res['internal_token'];
		}
		else
		{
			$ntoken = mknonce();
			updateToken ($login, $ntoken);
			setTokenActive ($ntoken, true);
		}
		
		finish(0, $ntoken);
	}
		else
	{
		$ntoken = mknonce();
		addToken($ntoken, $login);
		finish (0, $ntoken);
	}
}

function processVkAuth()
{
	
	if (!empty($_GET['error']))
	{
		finish (2, sprintf('[%s] %s, %s', $_GET['error'], $_GET['error_reason'], $_GET['error_description']));
	}
	
	$code = $_GET['code'];

	if (empty($code))
	{
		header ('HTTP/1.1 500 Server Error');
		finish(1, 'Bad request');
	}
	
	global $external;

	$res = request('https://oauth.vk.com/access_token?client_id='.CLIENT_ID.'&client_secret='.CLIENT_SECRET.'&redirect_uri='.$redirect_uri.($external ? '/external' : '').'&code='.$code);
	
	$json = json_decode($res, true);
	if (empty($json))
	{
		finish(1, 'Bad response from auth server');
	}
		else
	if (!empty($json['error']))
	{
		finish (2, sprintf('Authentication error: %s (%s)', $json['error'], $json['error_description']));	
	}
		else
	{
		processIncomingLogin('vk_'.(int)$json['user_id']);
	}
}

function processTelegramAuth()
{
	$login = tmpa_getLogin($_GET['code']);

	if (empty($login))
		finish(2, 'Bad or expired authentication code');

	tmpa_removeCode($login);
	processIncomingLogin($login);
}