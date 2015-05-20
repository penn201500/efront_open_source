<?php
session_cache_limiter('none');          //Initialize session
session_start();

$path = "../../../libraries/";                //Define default path

/** The configuration file.*/
require_once $path."configuration.php";

//Set headers in order to eliminate browser cache (especially IE's)
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past

if (isset($_GET['export_chat']) && eF_checkParameter($_GET['export_chat'], 'id')) {
	$messages = eF_getTableData("module_chat", "*", "module_chat.to_user = '".$_GET['export_chat']."'", "id ASC");
	$data = "";
	foreach ($messages as $message) {
		$data .= $message['from_user']."\t".$message['message']."\t".$message['sent']."\r\n";
	}
	$lesson = new EfrontLesson($_GET['export_chat']);
	file_put_contents(G_UPLOADPATH.$_SESSION['s_login'].'/chat_'.$lesson->lesson['name'].'.txt', $data);
	$dataFile = new EfrontFile(G_UPLOADPATH.$_SESSION['s_login'].'/chat_'.$lesson->lesson['name'].'.txt');
	$file 	  = $dataFile -> compress();
	echo json_encode(array('file' => $file['path']));
	exit;
}elseif (isset($_GET['export_chat']) && eF_checkParameter($_GET['export_chat'], 'login')) {
	//$sql = "select * from module_chat where (module_chat.to_user = '".$_GET['export_chat']."' and module_chat.from_user ='".$_SESSION["s_login"]."') or (module_chat.from_user = '".$_GET['export_chat']."' and module_chat.to_user ='".$_SESSION["s_login"]."')  order by id ASC";
	$messages = eF_getTableData("module_chat", "*", "(module_chat.to_user = '".$_GET['export_chat']."' and module_chat.from_user ='".$_SESSION["s_login"]."') or (module_chat.from_user = '".$_GET['export_chat']."' and module_chat.to_user ='".$_SESSION["s_login"]."')", "id ASC");
	$data = "";
	foreach ($messages as $message) {
		$data .= $message['from_user']."\t".$message['message']."\t".$message['sent']."\r\n";
	}
	file_put_contents(G_UPLOADPATH.$_SESSION['s_login'].'/chat_'.$_GET['export_chat'].'.txt', $data);
	$dataFile = new EfrontFile(G_UPLOADPATH.$_SESSION['s_login'].'/chat_'.$_GET['export_chat'].'.txt');
	$file 	  = $dataFile -> compress();
	echo json_encode(array('file' => $file['path']));
    exit;
} 


if ($_SESSION['s_lessons_ID']){

	if (!isset($_SESSION["lessonid"]) || $_SESSION["lessonid"] != $_SESSION['s_lessons_ID']){

		$lsn = eF_getTableData("lessons", "name", "id='".$_SESSION['s_lessons_ID']."'");
		foreach ($lsn as $lesson){
			$link = $lesson['name'];
			//$room = str_replace(' ','_',$lesson['name']);
			//$room = str_replace('"','',$room);
			//$room = str_replace('\'','',$room);
			$_SESSION["lessonid"] = $_SESSION['s_lessons_ID'];
			$_SESSION["lessonname"] = str_replace(' ','_',$lesson['name']);
			$_SESSION["room_".$_SESSION["lessonid"]] = $_SESSION["lessonname"];
			
		echo '<p><a href="javascript:void(0)" title="'.$lesson['name'].'" onClick="javascript:chatWithLesson(\''.$_SESSION["lessonid"].'\', \''.$_SESSION["lessonname"].'\')">'.mb_substr($link,0,24).' (Room)</a><span style="float:right"><a href="javascript:void(0)" onClick="javascript:exportChat(\''.$_SESSION["lessonid"].'\');event.stopPropagation();"><img src="themes/default/images/16x16/export.png"></a></span></p>';
			if (!in_array($_SESSION["lessonid"], $_SESSION['lesson_rooms']))
				$_SESSION['lesson_rooms'][] = str_replace(' ','_',$_SESSION["lessonid"]);
			//$my_t=getdate();
			//$_SESSION["last_lesson_msg"] = $my_t[year].'-'.$my_t[mon].'-'.$my_t[mday].' '.$my_t[hours].':'.$my_t[minutes].':'.$my_t[seconds];
			$_SESSION['last_lesson_msg'] = date("Y-m-d H:i:s", time()-date("Z"));	//Fix for timezone differences
		}
	}
	else{
	$link = str_replace('_',' ',$_SESSION["lessonname"]);
	//$room = str_replace(' ','_',$_SESSION["lessonname"]);
	//$room = str_replace('"','',$room);
	//$room = str_replace('\'','',$room);
		echo '<p><a href="javascript:void(0)" title="'.$_SESSION["lessonname"].'" onClick="javascript:chatWithLesson(\''.$_SESSION["lessonid"].'\', \''.$_SESSION["lessonname"].'\')">'.mb_substr($link,0,24).' (Room)</a><span style="float:right"><a href="javascript:void(0)" onClick="javascript:exportChat(\''.$_SESSION["lessonid"].'\');event.stopPropagation();"><img src="themes/default/images/16x16/export.png"></a></span></p>';
	}
}

$onlineUsers = getConnectedUsers();



if ($_SESSION['utype'] == 'administrator') {
	foreach ($onlineUsers as $user){
		if ($user['login'] != $_SESSION['chatter'])
			echo '<p><a href="javascript:void(0)" onClick="javascript:chatWith(\''.$user['login'].'\')">'.mb_substr($user['formattedLogin'],0,30).'</a><span style="float:right"><a href="javascript:void(0)" onClick="javascript:exportChat(\''.$user['login'].'\');event.stopPropagation();"><img src="themes/default/images/16x16/export.png"></a></span></p>';
	}
}
else{
	foreach ($onlineUsers as $user){
		if ($user['login'] != $_SESSION['chatter'])
			if ($_SESSION['commonality'][$user['login']] > 0)
				echo '<p><a href="javascript:void(0)" onClick="javascript:chatWith(\''.$user['login'].'\')">'.mb_substr($user['formattedLogin'],0,30).'</a><span style="float:right"><a href="javascript:void(0)" onClick="javascript:exportChat(\''.$user['login'].'\');event.stopPropagation();"><img src="themes/default/images/16x16/export.png"></a></span></p>';
	}
}


function getConnectedUsers(){
	$usersOnline = array();
	//A user may have multiple active entries on the user_times table, one for system, one for unit etc. Pick the most recent
	$result = eF_getTableData("user_times,users,module_chat_users", "users_LOGIN, users.name, users.surname, users.user_type, timestamp_now, session_timestamp", "users.login=user_times.users_LOGIN and users.login=module_chat_users.username and session_expired=0", "timestamp_now desc");
	foreach ($result as $value) {
		if (!isset($parsedUsers[$value['users_LOGIN']])) {

			$value['login'] = $value['users_LOGIN'];
			$usersOnline[] = array('login' => $value['users_LOGIN'],
								 'formattedLogin'=> formatLogin($value['login'], $value),
								 'user_type' => $value['user_type'],
								 'timestamp_now' => $value['timestamp_now'],
								 'time' => eF_convertIntervalToTime(time() - $value['session_timestamp']));
			$parsedUsers[$value['users_LOGIN']] = true;
		}
	}
	return $usersOnline;
}
?>
