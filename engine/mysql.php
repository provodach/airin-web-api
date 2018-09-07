<?php
$DB = null;

function getLastInsertId()
{
	global $DB;
	return $DB->lastInsertId();
}

function sqlQuery()
{
	global $DB;
	global $lang;

	if(is_null($DB)) {
		try {
			$DB = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=UTF8',
						   DB_USER, DB_PASSWORD);

			$DB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch(PDOException $error) {
			if (function_exists('displayError'))
				displayError ('Database feels bad a little');
			else
				die('Database feels bad a little');
		}
	}

	$args = func_get_args();
	if(empty($args))
		return;

	try {
		$request = $DB->prepare($args[0]);
		$request->setFetchMode(PDO::FETCH_ASSOC);

		if(sizeof($args) > 1) {
			$args = array_splice($args, 1);
			$request->execute($args);
		} else
			$request->execute();
	} catch(PDOException $error) {
		if (function_exists('displayError'))
			displayError ('Database could not process the query, cyka lol');
		else
			die('Database could not process the query, cyka lol'.$error->getMessage());
	}
	
	return $request;
}?>