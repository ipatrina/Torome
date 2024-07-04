<?php

	// Torome
	// Version: 3.0.2
	// Date: 2023.12

	include 'config.php';

	function startsWith($haystack, $needle)	{
		$length = strlen($needle);
 		return (substr($haystack, 0, $length) === $needle);
	}

	function endsWith($haystack, $needle) {
		$length = strlen($needle);
		if ($length == 0) {
			return true;
		}
		return (substr($haystack, -$length) === $needle);
	}

	function urlencode_url($url) {
		return str_replace(['%26', '%2F', '%3A', '%3D', '%3F'], ['&', '/', ':', '=', '?'], rawurlencode($url));
	}

	function ascii_str($str) {
		return preg_replace('/[^\x20-\x7E]/', '', $str);
	}

	function getRandomId($length) {
		$str = 'abcdefghijklmnopqrstuvwxyz';
		$strlen = 26;
		while ($length > $strlen) { 
			$str .= $str; 
			$strlen += 26;
		}
		$str = str_shuffle($str); 
		return substr($str, 0, $length); 
	}

	function isLinkIdExist($checkID) {
		global $sql_connection;
		global $table;
		$return_value = 0;
 		$query = mysqli_query($sql_connection, "SELECT * FROM ".$table." where LinkID='".str_replace("'", "", $checkID)."'");
		if (mysqli_num_rows($query) > 0) {
			$return_value = 1;
		}
		return $return_value;
	}

	function getLinkIdCount() {
		global $sql_connection;
		global $table;
		return mysqli_fetch_assoc(mysqli_query($sql_connection, "SELECT COUNT(*) AS count FROM ".$table))['count'];
	}

	if (isset($_GET['LinkID'])) {
		$fwlink_linkid = ascii_str(str_replace("'", "", $_GET['LinkID']));
		if ($fwlink_linkid != null) {
			$fwlink_result = mysqli_query($sql_connection, "SELECT * FROM ".$table." where LinkID='".$fwlink_linkid."'");
			if (mysqli_num_rows($fwlink_result) > 0) {
				$fwlink_array = mysqli_fetch_array($fwlink_result);
				$fwlink_type = $fwlink_array['Type'];
				$fwlink_data = $fwlink_array['Data'];
			} else {
				$fwlink_data = '';
			}
			if ($fwlink_data == "") {
				http_response_code(404);
			}
			else {
				if ($fwlink_type == 1) {
					header("Location: ".$fwlink_data);
				}
				else {
					$input_data = base64_decode($fwlink_data);
					$body_type = 'textbox';
					goto print_body;
				}
			}
			exit();
		}
	}

	if ($username != '' && $username != 'anonymous') {
		if (!isset($_SERVER['PHP_AUTH_USER'])) {
			http_response_code(401);
			header('WWW-Authenticate: Basic realm="Authentication Required"');
			exit();
		}
		else {
			$auth_username = $_SERVER['PHP_AUTH_USER'];
			$auth_password = $_SERVER['PHP_AUTH_PW'];
			if ($auth_username != $username || $auth_password != $password) {
				http_response_code(401);
				exit();
			}
		}
	}

	if (isset($_GET['Action'])) {
		if ($_GET['Action'] == 'Create') {
			$new_linkid = ascii_str($_POST['linkid']);
			$new_key = $_POST['key'];
			$new_data = $_POST['data'];
			if ($_POST['type'] == 'URL') {
				$new_type = 1;
			}
			else {
				$new_type = 100;
				$new_data = base64_encode($new_data);
			}
			if ($new_data == null || !is_int($new_type)) {
				$message_id = 'linkid_empty';
				$body_type = 'main';
				goto print_body;
			}
			else {				
				if ($new_type == 1 && strstr($new_data, '://') == null) {
					if (!startsWith($new_data, "./")) {
						$new_type = 100;
						$new_data = base64_encode($new_data);
					}
				}
				if ($new_type == 1) {
					$new_data = urlencode_url($new_data);
				}
				$data_compliance = 1;
				if (strstr($new_linkid, '.') != null || strstr($new_linkid, ' ') != null || strstr($new_linkid, '?') != null || strstr($new_linkid, '&') != null || strstr($new_linkid, '/') != null || strstr($new_linkid, '\\') != null || strstr($new_linkid, "'") != null || strstr($new_linkid, ";") != null || strstr($new_linkid, "#") != null || strstr($new_linkid, "%") != null || strstr($new_data, "'") != null || strstr($new_data, ";") != null) {
					$data_compliance = 0;
				} 
				if ($data_compliance == 0) {
					$message_id = 'no_compliance';
					$body_type = 'main';
					goto print_body;
				}        
				$linkid_norepeat = 0;
				if ($new_linkid == null) {
					$gen_linkid = getLinkIdCount() + 100000 + 1;
					while ($linkid_norepeat == 0) {
						if (isset($linkid_random)) {
							$gen_linkid = $gen_linkid + 1000;
						}
						$linkid_norepeat = !isLinkIdExist($gen_linkid);
						$linkid_random = 1;
					}
					$new_linkid = $gen_linkid;
				}
				else {
					if (isLinkIdExist($new_linkid)) {
						$message_id = 'linkid_exist';
						$body_type = 'main';
						goto print_body;
					}
					else {
						$linkid_norepeat = 1;
					}
				}
				if ($linkid_norepeat == 1) {
					if ($new_key == null) {
						$new_key = "0000000000000000000000000000000000000000";
					}
					else {
						$new_key = sha1($new_key);
					}
					mysqli_query($sql_connection, "INSERT INTO ".$table."(LinkID,Type,Secret,Data) VALUES ("."'".strtolower($new_linkid)."'".","."'".$new_type."'".","."'".$new_key."'".","."'".$new_data."'".")");
					if (isLinkIDexist($new_linkid)) {
						$body_type = 'href';
						$input_linkid = $new_linkid;
						$input_type = $new_type;
						$input_data = $new_data;
					}
					else {
						$message_id = 'linkid_empty';
						$body_type = 'main';
					}
					goto print_body;
				}
			}
		}
		elseif ($_GET['Action'] == 'Management') {
			$body_type = 'management';
			goto print_body;
		}
		elseif ($_GET['Action'] == "Modify") {
			if (!isLinkIDexist($_POST['linkid'])) {
				$message_id = 'linkid_notexist';
				$body_type = 'management';
				goto print_body;
			}
			else {
				$fwlink_linkid = ascii_str($_POST['linkid']);
				$fwlink_array = mysqli_fetch_array(mysqli_query($sql_connection, "SELECT * FROM ".$table." where LinkID='".str_replace("'", "", $fwlink_linkid)."'"));
				$fwlink_type = $fwlink_array['Type'];
				$fwlink_key = $fwlink_array['Secret'];
				$fwlink_data = $fwlink_array['Data'];
				if ($_POST['option'] == 'Restore') {
					if ($fwlink_type == 1) {
						$body_type = 'href';
						$input_linkid = $fwlink_linkid;
						$input_type = $fwlink_type;
						$input_data = $fwlink_data;
						goto print_body;
					}
					else {
						header("Location: ?LinkID=".$fwlink_linkid);
						exit();
					}
				}
				if ($fwlink_key == sha1($_POST['key']) || $password == $_POST['key'] || (isset($_SERVER['PHP_AUTH_PW']) && $password == $_SERVER['PHP_AUTH_PW'])) {
					mysqli_query($sql_connection, "DELETE FROM ".$table." where LinkID='".str_replace("'", "", $fwlink_linkid)."'");
					if (isLinkIDexist($fwlink_linkid)) {
						$message_id = 'incorrect_password';
						$body_type = 'main';
						goto print_body;
					}
					if ($_POST['option'] == 'Modify') {
						$message_id = 'linkid_modify';
						$input_linkid = $fwlink_linkid;
						$input_type = $fwlink_type;
						$input_key = $_POST['key'];
						$input_data = $fwlink_data;
						if ($input_type == 100) {
							$input_data = base64_decode($input_data);
						}
						$body_type = 'main';
						goto print_body;
					}
					else {
						$message_id = 'linkid_deleted';				
					}
				}
				else {
					$message_id = 'incorrect_password';			
				}
				$body_type = 'management';
				goto print_body;
			}
		}
		else {
			$body_type = 'main';
			goto print_body;
		}
	}
	else {
		$body_type = 'main';
		goto print_body;
	}

	print_body:
	header("content-type: text/html; charset=utf-8");
?>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<title><?php if (isset($title)) { print $title; } ?></title>
		<style>
			body {
				position: relative;
				margin: 0 auto;
				padding: 0px;
				width: 800px;
				text-align: center;
				font-family: Segoe, Segoe UI, Microsoft YaHei, DejaVu Sans, Trebuchet MS, Verdana;
			}
		</style>
	</head>
	<body>
		<div>
			<div style="left: 0px; top: 0px; width: 100%; height: 100%; position: fixed; z-index: -1; <?php print $background; ?>"></div>
			<br>
			<br>
			<br>
			<br>
<?php
	if (isset($message_id)) {
		if ($message_id == "linkid_empty") {
		    $message_str = "You must enter a valid URL.";
		}
		elseif ($message_id == "linkid_exist") {
			$message_str = "The shortened URL you selected is already taken.";
		}
		elseif ($message_id == "linkid_notexist") {
			$message_str = "Sorry, the link you accessed does not exist on our service.";
		}
		elseif ($message_id == "linkid_deleted") {
			$message_str = "The shortened URL has been deleted.";
		}
		elseif ($message_id == "linkid_modify") {
			$message_str = "Please update your shortened URL then resubmit.";
		}
		elseif ($message_id == "incorrect_password") {
			$message_str = "Management Key is incorrect.";
		}
		elseif ($message_id == "no_compliance") {
			$message_str = "Provided URL is not compliant.";
		}
		else {
			$message_str = $message_id;
		}
		print '<div><p style="color: white; background-color: red; margin: 0 auto; padding: 10px; width: 500px; word-wrap: break-word; word-break: normal; font-size: 18px;">'.$message_str.'</p></div><br><br>';
	}

	if (isset($title_image)) {
		print '<a href="." style="text-align: center;">'.$title_image.'</a><br><br>';
	}

	if (!isset($body_type)) {
		$body_type = 'main';	
	}
	
	if ($body_type == 'href') {
		$href_comment = 'Your shortened URL goes to: '.$input_data;
		if ($input_type != 1) {
			$href_comment = 'Your text message has been recorded.';
		}
?>
			<br>
			<p style="color: green; word-wrap: break-word; word-break: normal; font-size: 20px;">Your shortened URL is:</p>
			<div style="background-color: lightblue; padding: 10px; margin-top: 20px; margin-bottom: 20px; word-wrap: break-word; word-break: normal; font-size: 22px;">
				<a href="<?php print $host.$input_linkid; ?>"><?php print $host.$input_linkid; ?></a>
			</div>
			<p style="word-wrap: break-word; word-break: break-all; font-size: 16px;"><?php print $href_comment; ?></p>
<?php
	}
	elseif ($body_type == 'textbox') {
?>
			<div style="margin-top: 20px; margin-bottom: 20px; text-align: left;">
				<p style="color: black; background-color: lightyellow; padding: 15px; word-wrap: break-word; word-break: normal; font-size: 20px;"><?php if (isset($input_data)) { print nl2br(str_replace(' ', '&nbsp;', implode('\n', array_map('htmlspecialchars', explode('\n', $input_data))))); } ?></p>
			</div>
<?php
	}
	elseif ($body_type == 'management') {
?>
			<h2>Management Panel</h2>
			<br>
			<form name="management" method="post" action="?Action=Modify">
  			  <label style="color: white; background-color: blue; padding: 5px; font-size: 14px;">Shortened URL</label>&nbsp;&nbsp;<label style="font-size: 16px;"><?php if (isset($host)) { print $host; } ?></label><input name="linkid" type="text" style="font-size: 18px;" size="20">
				<br>
				<div style="margin-top: 5px;">
					<label style="color: blue; font-size: 16px;">Management Key: </label>&nbsp;<input name="key" type="password" style="font-size: 18px;" size="20">
				</div>
				<br>
				<input type="submit" name="option" value="Restore" style="width: 135px; height: 35px; font-size: 20px;">&nbsp;&nbsp;<input type="submit" name="option" value="Modify" style="width: 135px; height: 35px; font-size: 20px;">&nbsp;&nbsp;<input type="submit" name="option" value="Delete" style="width: 135px; height: 35px; font-size: 20px;">
			</form>
			<br>
<?php
	}
	else {
		$radio_selectid = 1;
		if (isset($input_type)) {
			if ($input_type == 100) {
				$radio_selectid = 2;
			}
		}
?>
			<form name="create" method="post" action="?Action=Create">
   			 <textarea name="data" type="text" rows="8" cols="80" maxlength="2500" style="font-family: Segoe, Segoe UI, Microsoft YaHei, DejaVu Sans, Trebuchet MS, Verdana; font-size: 18px; margin-top: 5px; resize: none;"><?php if (isset($input_data)) { print $input_data; } ?></textarea>
				<div style="margin-top: 10px; font-size: 16px;">
					<label>Content Type: </label><label><input name="type" type="radio" value="URL" <?php if ($radio_selectid == 1) { print 'checked="checked" '; } ?> />URL</label>&nbsp;&nbsp;<label><input name="type" type="radio" value="Text" <?php if ($radio_selectid == 2) { print 'checked="checked" '; } ?>/>Text Message</label>
				</div>
				<br>
				<br>
				<label style="color: white; background-color: purple; padding: 5px; font-size: 14px;">Customization</label>&nbsp;&nbsp;<label style="font-size: 16px;"><?php if (isset($host)) { print $host; } ?></label><input name="linkid" type="text" style="font-size: 18px;" size="20" value="<?php if (isset($input_linkid)) { print $input_linkid; } ?>">&nbsp;<label style="font-size: 14px;">(optional)</label>
				<br>
				<div style="margin-top: 5px;">
					<label style="color: blue; font-size: 16px;">Management Key: </label>&nbsp;<input name="key" type="password" style="font-size: 18px;" size="20" value="<?php if (isset($input_key)) { print $input_key; } ?>">&nbsp;<label style="font-size: 14px;">(optional)</label>
				</div>
				<br>
 			   <input type="submit" value="Shorten!" style="width: 150px; height: 40px; font-size: 22px;">
			</form>
			<br>
			<a href="?Action=Management" style="color: Black; font-size: 18px;">Management Panel</a>
<?php
	}
?>
			<h5 style="color: black; text-align: center;">© 2021-2023 Torome</h5>
			<br>
			<br>
			<br>
			<br>
		</div>
	</body>
</html>