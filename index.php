<?php
require_once("psql.php");
require_once("update.php");

function createInfoTable() {
	global $plink;
	
	$psql = "CREATE TABLE
IF NOT EXISTS info (
	id SERIAL,
	view TEXT,
	description TEXT,
	name TEXT,
	PRIMARY KEY (id),
	UNIQUE(view)
)";

	$result = pg_query($plink, $psql);
}

function selectOurViews() {
	global $plink;
	
	createInfoTable();
	
	$result = pg_query($plink, "SELECT * FROM info");
	$rows = pg_num_rows($result);
	
	if($rows > 0) {
		$print = "<ul>";
		while($r = pg_fetch_assoc($result)) {
			$print .= "<li>".$r['name']." - <a href='index.php?update=".$r['view']."'>Update</a></li>";
		}
		$print .= "</ul>";
	} else {
		$print = "<p>No stored views</p>";
	}
	
	return $print;
}

if(isset($_GET['update'])) {
	getAllRows($_GET['update'], true);
}

$form = showForm();
echo "<h1>Chicago Socrata Capture</h1>";
echo "<h2>We have these views stored:</h2>".selectOurViews();


echo "<h2>Get another view</h2>".$form;
?>