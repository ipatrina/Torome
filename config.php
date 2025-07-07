<?php

// ================ Configuration ================ \\
// MySQL database: ( Host / Username / Password / Database Name )
	@$sql_connection = mysqli_connect('localhost', 'username', 'password', 'db_name');
// 
// MySQL table name:
	$table = 'fwlink';
//
// Administrator username:
	$username = 'anonymous';
//
// Administrator password:
	$password = 'password';
//
// Login entry:
  $login = 'login';
//
// Home URL:
  $home = "https://www.google.com";
//
// Root URL:
	$host = 'http://'.$_SERVER['HTTP_HOST'].'/';
//
// Title:
	$title = 'Torome - an editable URL shortener';
//
// Logo:
	$title_image = '<img src="https://images2.imgbox.com/29/43/ISdhjxKR_o.png" width="400" height="100" alt="Torome"/>';
//
// Background:
	$background = 'background: linear-gradient(to top, aliceblue, white 50%); background-repeat: no-repeat;';
//
// =============================================== \\

/*

The following SQL statements must be executed to initialize the data table:

CREATE TABLE fwlink (id VARCHAR(64) NOT NULL, object VARCHAR(64) NOT NULL, token VARCHAR(64) NOT NULL, life BIGINT NOT NULL DEFAULT 0, tag TEXT NOT NULL, method VARCHAR(64) NOT NULL, content MEDIUMBLOB NOT NULL, PRIMARY KEY(id), INDEX (object));
INSERT INTO fwlink (id, object, token, life, tag, method, content) VALUES ('00000000-0000-4000-8000-000000000000', '#', '0000000000000000000000000000000000000000000000000000000000000000', 0, '', 'index', '100000');

*/

?>