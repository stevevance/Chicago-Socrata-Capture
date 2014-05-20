<?php

/*
echo 'php.ini: ', get_cfg_var('cfg_file_path')," <br/> ";
echo extension_loaded('pgsql') ? 'yes':'no'," <br/> ";
*/

$plink = pg_connect("host=localhost dbname=lcc user=postgres password=root");


if ($plink) {   

    //echo 'Connection attempt succeeded.';   

} else {   

   // echo 'Connection attempt failed.';
   message("Check your PostgreSQL database connection settings");

}   


?>