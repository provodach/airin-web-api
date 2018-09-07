<?php
function mknonce($len = 64)
{
	$SNChars = '0123456789qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM';
	$SNCCount = strlen($SNChars);
	$s = '';
	while (strlen($s) < $len)
	{
		$s .= $SNChars[rand(0, $SNCCount-1)];
	}
	return $s;
}

function getDevice ()
{
	if (stripos($_SERVER['HTTP_USER_AGENT'], 'iPod') ||
		stripos($_SERVER['HTTP_USER_AGENT'], 'iPad') ||
		stripos($_SERVER['HTTP_USER_AGENT'], 'iPhone'))
		return 'ios';
	elseif (stripos($_SERVER['HTTP_USER_AGENT'], 'Android'))
		return 'android';
	else
		return 'pc';
}