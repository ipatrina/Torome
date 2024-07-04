<?php

// ================ Configuration ================ \\
// MySQL database: ( Host / Username / Password / Database Name )
	@$sql_connection = mysqli_connect('localhost', 'username', 'password', 'db_name');
// 
// MySQL table name: { CREATE TABLE fwlink (LinkID varchar(100), Type int, Secret varchar(100), Data varchar(10000), PRIMARY KEY(LinkID)); }
	$table = 'fwlink';
//
// Administrator username:
	$username = 'anonymous';
//
// Administrator password:
	$password = 'password';
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
	$background = 'background: linear-gradient(to top, lightyellow, white 50%); background-repeat: no-repeat;';
//
// =============================================== \\

?>