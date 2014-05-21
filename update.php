<?php
require_once("psql.php");
require_once("getview.php");

//selectOurViews();

function buildUrl($view, $offset = 0) {
	$base = 'http://data.cityofchicago.org/resource/'.$view.'.json?';
	$url = $base.'$offset='.$offset.'';
	echo $url;
	return $url;
}

function getAllRows($view, $update = false) {
	global $plink;
	
	$continue = false;
	
	if($update) {
		// check to see if this view has an id field (if not, then we can't update)
		$psql = "SELECT column_name FROM information_schema.columns WHERE table_name='".createTableName($view)."_temp' and column_name='id';";
		$result = pg_query($plink, $psql);
		if(pg_num_rows($result) > 0) {
			$continue = true;
		} else {
			message("id column doesn't exist so we can't update this view");
		}
	} else {
		$continue = true;
	}
	
	if($continue) {
		echo message("Fetching more rows for View: ".$view,false);
		echo "<ol>";
		
		// create URL
		$url = buildUrl($view);
		
		// fetch URL into object for the first time
		$json = file_get_contents($url);
		$rows = ingestRowsIntoObject($view, $json);
		$offset = 0;
	
		// keep fetching the URL but with the offset this time
		while($rows > 0) { // > 0 or < the max rows you want to fetch
			$offset = $offset+1000;
			$url = buildUrl($view, $offset);
			$json = file_get_contents($url);
			
			$rows = ingestRowsIntoObject($view, $json); // when this = 0, or reaches your max, the while() loop will stop
			echo "<li>Fetched $rows rows</li>";
			if($rows == 0) {
				copyFromTempTableToMainTable($view, $update);
			}
			usleep(100000); // microseconds, one millionth of a second
		}
		echo "</ol>";
	} // end $continue
}

function ingestRowsIntoObject($view, $results) {
	global $plink;
	
	$obj = json_decode($results, true);
	$rows = count($obj);
	
	if($rows > 0) {
		$psql = "";
		copyFromObjectToTempTable($view, $obj);
		return $rows; // return the number of rows we fetched this time
	} else {
		message("ingestRowsIntoObject: the number of rows reached 0 so we stopped",false);
	}
}

function copyFromTempTableToMainTable($view, $update = false) {
	global $plink;
	
	if($update) {
		$psql = "INSERT INTO ".createTableName($view)." SELECT * FROM ".createTableName($view)."_temp temp LEFT OUTER JOIN ".createTableName($view)." main ON (main.id = temp.id) WHERE main.id IS NULL;";
		echo $psql;
		if($result = pg_query($plink, $psql)) {
			// empty the temp table
			$result = pg_query($plink, "TRUNCATE ".createTableName($view)."_temp;");
		}
	} else {
		// when not updating, copy the entire table
		$psql = "INSERT INTO ".createTableName($view)." SELECT * FROM ".createTableName($view)."_temp;";
		echo $psql;
		if($result = pg_query($plink, $psql)) {
			// empty the temp table
			$result = pg_query($plink, "TRUNCATE ".createTableName($view)."_temp;");
		}
	}
}

function copyFromObjectToTempTable($view, $object) {
	global $plink;
	
	foreach($object as $r) {
		$psql = "";
		$columns = array_keys($r);
		$columnsPsql = implode(", ",$columns);
		
		$values = array();
		foreach($r as $v) {
			$values[] = pg_escape_string($v);
		}
		$values = "'".implode("', '",$values)."'";
		
		$psql .= "INSERT INTO ".createTableName($view)."_temp (insert_time, $columnsPsql) VALUES (now(), $values);";
		/*
echo "<pre>";
		var_dump($psql);
		echo "</pre>";
*/
		$result = pg_query($plink, $psql);
		//echo "<li>".$psql."</li>";
		echo "<li>fetched another row</li>";
	}
}
?>