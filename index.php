<?php

//define("DEBUG_FILE","./debug.log");
//ini_set("display_errors", 1);
//set_time_limit(300);

if(isset($_GET['act']) && $_GET['act'] === "findAndCompare") {
	include_once "FindAndCompare.php";
	$findAndCompare = new FindAndCompare();
	try {
		$findAndCompare->startJob($_POST['firstUrl'] ?? '', $_POST['secondUrl'] ?? '');
	} catch (\Exception $e) {
	    echo "Si è verificato un errore durante l'elaborazione della richiesta:\n".$e->getMessage();
    }
} else if(isset($_GET['jobId'])) {
	include_once "FindAndCompare.php";
	$findAndCompare = new FindAndCompare();
	try {
		$findAndCompare->execute($_GET['jobId']);
	} catch (\Exception $e) {
	    echo "Si è verificato un errore durante l'elaborazione della richiesta:\n".$e->getMessage();
    }
} else {
    include "startPage.php";
}