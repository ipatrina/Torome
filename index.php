<?php

  // Torome
  // Version: 4.0.1
  // Date: 2025.11

include "config.php";

function is_valid_linkid($id)
{
    return preg_match('/^[A-Za-z0-9._-]+$/', $id);
}

function generate_unique_linkid()
{
    global $sql_connection, $table;
    while (true) {
        $count = 0;
        mysqli_query($sql_connection, "START TRANSACTION");
        $res = mysqli_query(
            $sql_connection,
            "SELECT content FROM `$table` WHERE object = '#' LIMIT 1 FOR UPDATE"
        );
        if ($res && ($row = mysqli_fetch_row($res))) {
            $count = intval($row[0]) + 1;
        }
        $update = mysqli_prepare(
            $sql_connection,
            "UPDATE `$table` SET content = ? WHERE object = '#'"
        );
        $count_str = strval($count);
        mysqli_stmt_bind_param($update, "s", $count_str);
        mysqli_stmt_execute($update);
        mysqli_stmt_close($update);
        mysqli_query($sql_connection, "COMMIT");
        if ($count == 0) {
            return "?";
        }
        if (!object_exists($count_str)) {
            return $count_str;
        }
    }
}

function getUUID()
{
    return sprintf(
        "%04x%04x-%04x-%04x-%04x-%012x",
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        time()
    );
}

function object_exists($object)
{
    global $sql_connection, $table;
    $stmt = mysqli_prepare(
        $sql_connection,
        "SELECT id FROM `$table` WHERE object = ? LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, "s", $object);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $exists;
}

// Clean expired objects
$now = time();
$stmt = mysqli_prepare(
    $sql_connection,
    "DELETE FROM `$table` WHERE life > 0 AND life < ?"
);
mysqli_stmt_bind_param($stmt, "i", $now);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Request object read
$linkID = isset($_GET["LinkID"]) ? $_GET["LinkID"] : "";
if (!empty($linkID)) {
    $object = strtolower($linkID);
    $stmt = mysqli_prepare(
        $sql_connection,
        "SELECT method, content FROM `$table` WHERE object = ? LIMIT 1"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $object);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $method, $content);
        if (mysqli_stmt_fetch($stmt)) {
            if ($method === "url") {
                header("Location: $content", true, 302);
                mysqli_stmt_close($stmt);
                exit();
            } else {
                $input_data = $content;
                $body_type = "textbox";
                mysqli_stmt_close($stmt);
                goto print_body;
            }
        }
        mysqli_stmt_close($stmt);
    }
    http_response_code(404);
    exit();
}

// Action authentication
if ($username != "" && $username != "anonymous") {
    $auth_username = $_SERVER["PHP_AUTH_USER"] ?? "";
    $auth_password = $_SERVER["PHP_AUTH_PW"] ?? "";
    if ($auth_username != $username || $auth_password != $password) {
        if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] === $login) {
            http_response_code(401);
            header('WWW-Authenticate: Basic realm="Authentication Required"');
            exit();
        } else {
            header("Location: $home", true, 302);
            exit();
        }
    }
}

// Request object create
if (isset($_GET["Action"]) && $_GET["Action"] === "Create") {
    $linkid = isset($_POST["linkid"]) ? $_POST["linkid"] : "";
    $key = isset($_POST['key']) ? $_POST['key'] : '';
    $data = isset($_POST["data"]) ? $_POST["data"] : "";
    $privacy = isset($_POST["privacy"]) ? $_POST["privacy"] : "";
    $type = isset($_POST["type"]) ? $_POST["type"] : "";

    if (empty($data)) {
        $message_id = "linkid_empty";
        $body_type = "main";
        goto print_body;
    }

    if (!empty($linkid)) {
        if (!is_valid_linkid($linkid)) {
            $message_id = "no_compliance";
            $body_type = "main";
            goto print_body;
        }
        $id = strtolower($linkid);
        if (object_exists($id)) {
            $message_id = "linkid_exist";
            $body_type = "main";
            goto print_body;
        }
    } else {
        $id = generate_unique_linkid();
    }

    $object = $id;
    $token = empty($key) ? str_repeat('0', 64) : hash('sha256', $key);

    if (strtoupper($type) === "URL" && strpos($data, "://") === false) {
        $method = "text";
    } else {
        $method = strtoupper($type) === "URL" ? "url" : "text";
    }

    $life = $privacy ? time() + 1800 : 0;
    $tag = "";
    $id = getUUID();

    $stmt = mysqli_prepare(
        $sql_connection,
        "INSERT INTO `$table` (id, object, token, life, tag, method, content) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if ($stmt) {
        mysqli_stmt_bind_param(
            $stmt,
            "sssisss",
            $id,
            $object,
            $token,
            $life,
            $tag,
            $method,
            $data
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $body_type = "href";
    $input_linkid = $object;
    $input_type = $method;
    $input_data = $data;
    goto print_body;
}

// Request management page
if (isset($_GET["Action"]) && $_GET["Action"] === "Management") {
    $body_type = "management";
    goto print_body;
}

// Request object modify
if (isset($_GET["Action"]) && $_GET["Action"] === "Modify") {
    if (!object_exists($_POST["linkid"])) {
        $message_id = "linkid_notexist";
        $body_type = "management";
        goto print_body;
    }

    $fwlink_linkid = preg_replace('/[^\x20-\x7E]/', "", $_POST["linkid"]);

    $query = "SELECT method, token, content FROM `$table` WHERE object = ? LIMIT 1";
    $stmt = mysqli_prepare($sql_connection, $query);
    mysqli_stmt_bind_param($stmt, "s", $fwlink_linkid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $fwlink_type, $fwlink_key, $fwlink_data);
    $fetched = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if (!$fetched) {
        $message_id = "linkid_notexist";
        $body_type = "management";
        goto print_body;
    }

    if ($_POST["option"] == "Restore") {
        if ($fwlink_type == "url") {
            $body_type = "href";
            $input_linkid = $fwlink_linkid;
            $input_type = $fwlink_type;
            $input_data = $fwlink_data;
            goto print_body;
        } else {
            header("Location: ?LinkID=" . $fwlink_linkid, true, 302);
            exit();
        }
    }

    $input_key_sha1 = sha1($_POST["key"]);
    $input_key_sha256 = hash("sha256", $_POST["key"]);
    if (is_valid_linkid($fwlink_linkid) && (strtolower($fwlink_key) === strtolower($input_key_sha1) || strtolower($fwlink_key) === strtolower($input_key_sha256) || $password === $_POST["key"] || (isset($_SERVER["PHP_AUTH_PW"]) && $password === $_SERVER["PHP_AUTH_PW"]))) {
        $delete_stmt = mysqli_prepare(
            $sql_connection,
            "DELETE FROM `$table` WHERE object = ?"
        );
        mysqli_stmt_bind_param($delete_stmt, "s", $fwlink_linkid);
        mysqli_stmt_execute($delete_stmt);
        mysqli_stmt_close($delete_stmt);

        if (object_exists($fwlink_linkid)) {
            $message_id = "incorrect_password";
            $body_type = "main";
            goto print_body;
        }

        if ($_POST["option"] == "Modify") {
            $message_id = "linkid_modify";
            $input_linkid = $fwlink_linkid;
            $input_type = $fwlink_type;
            $input_key = $_POST["key"];
            $input_data = $fwlink_data;
            $body_type = "main";
            goto print_body;
        } else {
            $message_id = "linkid_deleted";
        }
    } else {
        $message_id = "incorrect_password";
    }

    $body_type = "management";
    goto print_body;
}

// Render HTML UI
print_body:
header("Content-Type: text/html; charset=utf-8");
?>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<title><?php if (isset($title)) {
      print $title;
  } ?></title>
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
    } elseif ($message_id == "linkid_exist") {
        $message_str = "The shortened URL you selected is already taken.";
    } elseif ($message_id == "linkid_notexist") {
        $message_str =
            "Sorry, the link you accessed does not exist on our service.";
    } elseif ($message_id == "linkid_deleted") {
        $message_str = "The shortened URL has been deleted.";
    } elseif ($message_id == "linkid_modify") {
        $message_str = "Please update your shortened URL then resubmit.";
    } elseif ($message_id == "incorrect_password") {
        $message_str = "Management Key is incorrect.";
    } elseif ($message_id == "no_compliance") {
        $message_str = "Provided URL is not compliant.";
    } else {
        $message_str = $message_id;
    }
    print '<div><p style="color: white; background-color: red; margin: 0 auto; padding: 10px; width: 500px; word-wrap: break-word; word-break: normal; font-size: 18px;">' .
        $message_str .
        "</p></div><br><br>";
}

if (isset($title_image)) {
    print '<a href="." style="text-align: center;">' .
        $title_image .
        "</a><br><br>";
}

if (!isset($body_type)) {
    $body_type = "main";
}

if ($body_type == "href") {

    $href_comment = "Your shortened URL goes to: " . $input_data;
    if ($input_type != "url") {
        $href_comment = "Your text message has been recorded.";
    }
    ?>
			<br>
			<p style="color: green; word-wrap: break-word; word-break: normal; font-size: 20px;">Your shortened URL is:</p>
			<div style="background-color: lightblue; padding: 10px; margin-top: 20px; margin-bottom: 20px; word-wrap: break-word; word-break: normal; font-size: 22px;">
				<a href="<?php print $host . $input_linkid; ?>"><?php print $host .
    $input_linkid; ?></a>
			</div>
			<p style="word-wrap: break-word; word-break: break-all; font-size: 16px;"><?php print $href_comment; ?></p>
<?php
} elseif ($body_type == "textbox") { ?>
			<div style="margin-top: 20px; margin-bottom: 20px; text-align: left;">
				<p style="color: black; background-color: lightyellow; padding: 15px; word-wrap: break-word; word-break: normal; font-size: 20px;"><?php if (
        isset($input_data)
    ) {
        print nl2br(
            str_replace(
                " ",
                "&nbsp;",
                implode(
                    '\n',
                    array_map("htmlspecialchars", explode('\n', $input_data))
                )
            )
        );
    } ?></p>
			</div>
<?php } elseif ($body_type == "management") { ?>
			<h2>Management Panel</h2>
			<br>
			<form name="management" method="post" action="?Action=Modify">
  			  <label style="color: white; background-color: blue; padding: 5px; font-size: 14px;">Shortened URL</label>&nbsp;&nbsp;<label style="font-size: 16px;"><?php if (
           isset($host)
       ) {
           print $host;
       } ?></label><input name="linkid" type="text" style="font-size: 18px;" size="20">
				<br>
				<div style="margin-top: 5px;">
					<label style="color: blue; font-size: 16px;">Management Key: </label>&nbsp;<input name="key" type="password" style="font-size: 18px;" size="20">
				</div>
				<br>
				<input type="submit" name="option" value="Restore" style="width: 135px; height: 35px; font-size: 20px;">&nbsp;&nbsp;<input type="submit" name="option" value="Modify" style="width: 135px; height: 35px; font-size: 20px;">&nbsp;&nbsp;<input type="submit" name="option" value="Delete" style="width: 135px; height: 35px; font-size: 20px;">
			</form>
			<br>
<?php } else {
    $radio_selectid = 1;
    if (isset($input_type)) {
        if ($input_type !== "url") {
            $radio_selectid = 2;
        }
    }
    ?>
			<form name="create" method="post" action="?Action=Create">
   			 <textarea name="data" type="text" rows="8" cols="80" maxlength="2500" style="font-family: Segoe, Segoe UI, Microsoft YaHei, DejaVu Sans, Trebuchet MS, Verdana; font-size: 18px; margin-top: 5px; resize: none;"><?php if (
           isset($input_data)
       ) {
           print $input_data;
       } ?></textarea>
				<div style="margin-top: 10px; font-size: 16px;">
					<label>Content Type: </label><label><input name="type" type="radio" value="URL" <?php if (
         $radio_selectid == 1
     ) {
         print 'checked="checked" ';
     } ?> />URL</label>&nbsp;&nbsp;<label><input name="type" type="radio" value="Text" <?php if (
     $radio_selectid == 2
 ) {
     print 'checked="checked" ';
 } ?>/>Text Message</label>
				</div>
				<br>
				<br>
				<label style="color: white; background-color: purple; padding: 5px; font-size: 14px;">Customization</label>&nbsp;&nbsp;<label style="font-size: 16px;"><?php if (
        isset($host)
    ) {
        print $host;
    } ?></label><input name="linkid" type="text" style="font-size: 18px;" size="20" value="<?php if (
    isset($input_linkid)
) {
    print $input_linkid;
} ?>">&nbsp;<label style="font-size: 14px;">(optional)</label>
				<br>
				<div style="margin-top: 5px;">
					<label style="color: blue; font-size: 16px;">Management Key: </label>&nbsp;<input name="key" type="password" style="font-size: 18px;" size="20" value="<?php if (
         isset($input_key)
     ) {
         print $input_key;
     } ?>">&nbsp;<label style="font-size: 14px;">(optional)</label>
				</div>
				<div style="margin-top: 10px; margin-bottom: 10px;">
        <label><input style="color: black; font-size: 16px;" type="checkbox" name="privacy" value="1">Delete after 30 minutes</label>&nbsp;<label style="font-size: 14px;">(privacy option)</label>
        </div>
				<br>
 			   <input type="submit" value="Shorten!" style="width: 150px; height: 40px; font-size: 22px;">
			</form>
			<br>
			<a href="?Action=Management" style="color: Black; font-size: 18px;">Management Panel</a>
<?php }
?>
			<h5 style="color: black; text-align: center;">© 2021-2025 Torome</h5>
			<br>
			<br>
			<br>
			<br>
		</div>
	</body>
</html>