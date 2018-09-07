<?php

function processCatty()
{
	$color = (preg_match('/^[0-9a-fA-F]{6}$/', $_GET['c']) == 1)
				? strtolower($_GET['c']) : 'f5f5f5';

	$template = '';

	switch ($_GET['t'])
	{
		case 'jack' :
			$template = 'jack';
			break;

		case 'catty' :
			$template = 'catty';
			break;

		default :
			$template = 'catty';
			break;
	}

	$cat = file_get_contents('engine/catty_template/'.$template.'.svg');
	$cat = str_replace('_color_', $color, $cat);
	header('Content-Type: image/svg+xml');
	header('X-Airin-Message: Nyann :3');
	die($cat);
}