<?php
require_once("psql.php");
require_once("getview.php");
set_time_limit(0);

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
		// CHECK to see if any rows exist, and we'll start from there
		$result = pg_query($plink, "SELECT count(insert_time) count FROM ".createTableName($view)."_temp");
		$r = pg_fetch_array($result);
		var_dump($r);
		
		$offset = $r['count'];
		$url = buildUrl($view, $offset);
		
		// FETCH
		// fetch URL into object for the first time
		// old method using file_get_contents
		// $json = file_get_contents($url);
		
		// create a new cURL resource
		$ch = curl_init();
		
		// set URL and other appropriate options
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		
		// grab URL and pass it to the browser
		$result = curl_exec($ch); // this is JSON
		
		// close cURL resource, and free up system resources
		curl_close($ch);
		
		// PASS JSON to another function
		$rows = ingestRowsIntoObject($view, $result);
	
		// keep fetching the URL but with the offset this time
		while($rows > 0) { // > 0 or < the max rows you want to fetch
			$offset = $offset+1000;
			$url = buildUrl($view, $offset);
			
			// create a new cURL resource
			$ch = curl_init();
			
			// set URL and other appropriate options
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			
			// grab URL and pass it to the browser
			$result = curl_exec($ch); // this is JSON
			
			// close cURL resource, and free up system resources
			curl_close($ch);
			
			// PASS JSON to another function
			$rows = ingestRowsIntoObject($view, $result); // when this == 0, or reaches your max, the while() loop will stop
			
			// IF DONE, copy from TEMP to MAIN table
			if($rows == 0) {
				copyFromTempTableToMainTable($view, $update);
			}
			
			// BREAK between loops
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
	
	//echo "<li>Copying an object to temp table</li>"; // this doesn't seem to display to the user
	
	$psql = "";
	foreach($object as $r) {
		$columns = array_keys($r);
		$columnsPsql = implode(", ",$columns);
		
		$values = array();
		foreach($r as $v) {
			$values[] = pg_escape_string($v);
		}
		$values = "'".implode("', '",$values)."'";
		
		$psql .= "INSERT INTO ".createTableName($view)."_temp (insert_time, $columnsPsql) VALUES (now(), $values);";
		echo "<li>fetched another row</li>";
	}
	
	$result = pg_query($plink, $psql);
	//echo "<li>".$psql."</li>";
}
?>