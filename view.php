<?php
require_once("psql.php");
require_once("class.windy.php");

showForm();
confirmTableDelete();

// ydr8-5enu is building permits
// 22u3-xenr is building violations

/* 
APPLICATION VARIABLES
*/
$maxrows = 1000;

/*
DON'T EDIT BELOW THIS LINE 
========
*/

function showForm() {
	$f = "<h1>Chicago Socrata Capture</h1><h2>Copy \"views\" from the City of Chicago data portal to PostgreSQL</h2>";
	$f .= "<form name='views' method='get'><input type='text' name='view' id='view' size='30' placeholder='Input an 9-digit view ID'><input type='submit' value='Get this view'></form>";
	echo $f;
	
	if(!empty($_GET['view'])) {
		createInfoTable();
		checkViewExistence($_GET['view']);
	} else {
		message("Input a view ID", false);
	}
}

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

	$result = pg_prepare($plink, "create_info", $psql);
	$result = pg_execute($plink, "create_info",array()) or die("psql: CREATE went wrong");
}

function checkViewExistence($view) {
	$chicago = new windy('city','json','object',null,false); // the final param is true/false for debugging
	
	$check = $chicago->getViewsByID($view);
	$tableName = createTableName($view);
	
	echo "<pre>";
	//var_dump($check);
	echo "</pre>";
	
	if(is_numeric($check->createdAt)) {
		storeView($view, $check, $tableName);
		
		//displayColumns($check->columns); // comment this line if you don't want to display the columns info
		createTable($chicago, $check, $view, $tableName);
	} else {
		message("View ".$view." doesn't seem to exist in the data portal",false);
	}
}

function createTableName($view) {
	$tableName = "view_".str_replace("-", "_", $view);
	return $tableName;
}

function createTable($viewObject, $result, $view, $tableName) {
	global $plink;
	
	$columns = array();
	
	$columnsArray = $result->columns;
	$addOid = 0;
	foreach($columnsArray as $column) {
		if($column->fieldName != "id") {
			$columns[] = strtolower($column->fieldName)." ".strtoupper(choosePsqlColumnType($column->dataTypeName));
			$addOid++;
		} else {
			$columns[] = "id SERIAL";
			$columns[] = "PRIMARY KEY (id)";
			$addOid = -999; // if the id field exists, use that instead of making an oid field
		}
	}
	if($addOid > 0) {
		$columns[] = "oid SERIAL";
		$columns[] = "PRIMARY KEY (oid)";
	}
	$columnsPsql = implode(", ",$columns);

	$psql = "CREATE TABLE
IF NOT EXISTS $tableName (
	insert_time TIMESTAMP,
	$columnsPsql
)";

	echo $psql;
	
	$result = pg_prepare($plink, "", $psql);
	$result = pg_execute($plink, "",array()) or die(message("Couldn't create the table for view: $view"));
	
	copyViewIntoTable($view, $viewObject, $tableName, $columnsArray);
}

function copyViewIntoTable($view, $viewObject, $tableName, $columnsArray) {
	global $plink, $maxrows;
	
	$rows = $viewObject->getRows($view,true,1000,null,null,false,null,null,null);
	
	echo "<pre>";
	//var_dump($rows->data);
	echo "</pre>";
	
	// what columns are we inserting?
	//$columns = array("insert_time"); // the first two columns
	foreach($columnsArray as $column) {
		//if($column->fieldName != "id") {
			$columns[] = strtolower($column->fieldName);
		//}
	}
	$columnsPsql = implode(", ",$columns);
	$colsCount = count($columns);
	$queries = "";
	
	foreach($rows->data as $row) {
		$psql = "INSERT INTO $tableName (insert_time, $columnsPsql) VALUES (";
		$rowData = array();
		$i=8; // this is a workaround; for some reason the first 8 rows of Building Violations (view 22u3-xenr) are gobbledy-gook which are not defined/seen when displaying columns; same with FOIA for Mayor's Office People with Disabilities (view fazv-a8mb)
		foreach($columns as $column) {
			if(empty($row[$i])) {
				//$row[$i] = "null";
				$rowData[] = "null";
			} else {
				$rowData[] = "'".pg_escape_string($row[$i])."'";
			}
			$i++;
		}
		$psql .= "now(), ".implode(", ",$rowData).");";
		//echo "<p>".$psql."</p>";
		$queries .= $psql;
	}
	
	$result = pg_query($plink, $queries);
	if (!$result) {
		message("Couldn't copy the rows from the view to table: $tableName. ".deleteTableLink($view));
		confirmTableDelete($view);
		exit;
	} else {
		message("Copied rows from the view to table: $tableName",false);
	}
}

function confirmTableDelete($view = null) {
	global $plink;
	if(isset($_GET['delete'])) {
		if(!empty($_GET['delete'])) {
			$view = $_GET['delete'];
		}
		$result = pg_query($plink, "DROP TABLE ".createTableName($view));
		if($result) {
			message("Table: ".createTableName($view)." deleted",false);
		}
		$result = pg_query($plink, "DELETE FROM info WHERE view = '$view'");
		if($result) {
			message("View: ".createTableName($view)." removed from info table",false);
		}
	}
}

function deleteTableLink($view = null) {
	echo  "<a href='?delete=$view'>Delete table: ".createTableName($view)."?</a>";
}

function displayColumns($columnsArray) {
	if(is_array($columnsArray)) {
		echo "<ol>";
		foreach($columnsArray as $column) {
			echo "<li>".$column->name.": ".$column->dataTypeName."</li>";
		}
		echo "</ol>";
	}

	echo "<pre>";
	var_dump($columnsArray);
	echo "</pre>";
}

function storeView($view, $result, $tableName) {
	global $plink;
	
	$view = pg_escape_string($view);
	$description = pg_escape_string($result->description);
	$name = pg_escape_string($result->name);
	
	$psql = "INSERT INTO info (view, description, name) VALUES ('{$view}','{$description}','{$name}')";
	
	$result = pg_prepare($plink, "", $psql);
	$result = pg_execute($plink, "",array()) or die(message("Couldn't save the view ID. Have you already saved this view ID? ".deleteTableLink($view)));
	
	message("View: $view added to our info table",false);
}

function message($msg, $error = true) {
	if($error) {
		$print = "<h1>There was a problem</h1><p>".$msg."</p>";
	} else {
		$print = "<h1>Success!</h1><p>".$msg."</p>";
	}
	echo $print;
}

function choosePsqlColumnType($columnType) {
	$types = array(
		"calendar_date"=>"date",
		"text"=>"text",
		"number"=>"decimal",
		"money"=>"decimal"
	);
	return $types[$columnType];
}