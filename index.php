<?php

define("DEBUG_FILE","./debug.log");

ini_set("display_errors", 1);
set_time_limit(300);

if(isset($_GET['act']) && $_GET['act'] === "findAndCompare") {
	if(defined('DEBUG_FILE')) {
		if(file_exists(DEBUG_FILE)) {
			unlink(DEBUG_FILE);
		}
		touch(DEBUG_FILE);
	}

	include_once "FindAndCompare.php";
	$findAndCompare = new FindAndCompare();
	try {
		$findAndCompare->execute($_POST['firstUrl'] ?? '', $_POST['secondUrl'] ?? '');
	} catch (\Exception $e) {
	    echo "Si è verificato un errore durante l'elaborazione della richiesta:\n".$e->getMessage();
    }
} else {
    include "startPage.php";
}