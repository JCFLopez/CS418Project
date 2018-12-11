<?php
/*
	References: https://www.youtube.com/watch?v=pfFdbpPgg4M&list=PLBOh8f9FoHHhRk0Fyus5MMeBsQ_qwlAzG&index=14
				https://www.youtube.com/watch?v=tVLHGHshNdU&index=15&list=PLBOh8f9FoHHhRk0Fyus5MMeBsQ_qwlAzG
				I referenced these 2 videos when writing the 'likes' code 
				https://www.youtube.com/watch?v=82hnvUYY6QA   <- this one for ajax 
				https://www.youtube.com/watch?v=gdEpUPMh63s&index=31&list=WL&t=0s  <- this one for pagination in home.php and messages.php
*/
	include ('connection.php');
	session_start();
	
	if(!isset($_SESSION['email'])){
		header("Location: index.php?msg=" . urlencode('needs_to_log_in'));
	}
	if(isset($_GET['id'])) {
		$groupID = $_GET['id'];	
	} else {
		$groupID = "1";
	}
	if (isset($_GET['page'])) {
		$page = $_GET['page'];
	} else {
		$page = 1;
	}
	
	//getUserID();
	//turn these into functions soon.
	//retrieve UserID from database
	$userEmail = $_SESSION['email'];
	$queryID = "SELECT id FROM users WHERE email = " . "'$userEmail';";
	$userEmail = $conn->query($queryID);
	if ($userEmail->num_rows > 0) { 
		// output data of each row
		while($row = $userEmail->fetch_assoc()) {
			$userID = $row['id'];  
		} 
	}
	//admin query (the logic for all the admin checks currently holds for only 1 admin. If more are added then it may break)
	$adminQuery = "SELECT id FROM users WHERE admin = 1";
	$result = $conn->query($adminQuery);
	if ($result->num_rows > 0) {
		while ($row = $result->fetch_assoc()) {
			$adminID = $row['id'];
		}
	}
	/*
		Section of code which restricts user's access to groups which they are not members of or which are private
		/
		/
		/
		/
		/
	*/
	$getAccessGroupIDs = "select b.group_id from users a, groups b, group_users c where a.id = c.user_id and b.group_id = c.group_id and a.id = ".$userID." union select group_id from groups where type = 'public'";
	$resultAccessGroupIDs = $conn->query($getAccessGroupIDs);
	if ($resultAccessGroupIDs->num_rows > 0) {
		while ($row = $resultAccessGroupIDs->fetch_assoc()) {
			$accessGroupIDsArray[] = $row['group_id']; //group IDs that a user is in or public
		}
	}
	$getAllGroupIDs = "select group_id from groups";
	$resultAllGroupIDs = $conn->query($getAllGroupIDs);
	if ($resultAllGroupIDs->num_rows > 0) {
		while ($row = $resultAllGroupIDs->fetch_assoc()) {
			$allGroupIDsArray[] = $row['group_id']; //all group IDs
		}
	}
	
	//allows admin users to access every group
	if ($adminID == $userID) {
		$restrictedGroupIDs = 0;
	} else {
		$restrictedGroupIDs = array_diff($allGroupIDsArray,$accessGroupIDsArray);
	} 
	$_SESSION['restricted'] = $restrictedGroupIDs; //group IDs which a user does not have access to
	foreach ($_SESSION['restricted'] as $key=>$value) {
		$restrictedID = $value;
		if ($groupID == $restrictedID) {
			header("Location: home.php?msg=" . urlencode('access_denied'));
		}
	}
	/*
		Section of code which deals with 'likes'. Reference for this section of code is found at the top of this file
		/
		/
		/
		/
		/
	*/
	if (isset($_GET['liked'])) {
		$archivedQuery = "SELECT isArchived FROM groups WHERE group_id = $groupID";
		$archived = $conn->query($archivedQuery);
		if ($archived->num_rows > 0) {
			while ($row = $archived->fetch_assoc()) {
				$resultArchived = $row['isArchived'];
			}
		}
		if ($resultArchived == 0) {
			$isinQuery = "SELECT * FROM group_users a WHERE a.user_id = $userID AND a.group_id = $groupID";
			$isinResult = $conn->query($isinQuery);
			if ($isinResult->num_rows > 0) {
				$hasUserLikedQuery = "SELECT `user_id` FROM `messages_likes` WHERE `msg_id` = " . $_GET['liked'] . " AND `user_id` = " . $userID . "";
				$userLiked = $conn->query($hasUserLikedQuery);
				if (!$userLiked->num_rows > 0) { 
					// output data of each row
					$likedQuery = "UPDATE `messages` SET `likes` = `likes`+1 WHERE `messages`.`msg_id` = " . $_GET['liked'] . "";
					$postLikesQuery = "INSERT INTO `messages_likes` (`msg_id`, `user_id`) VALUES ('" . $_GET['liked'] . "', '" . $userID . "')";
					$conn->query($likedQuery);
					$conn->query($postLikesQuery);
					
					//make sure user can't like and dislike a post
					$hasUserDisLikedQuery = "SELECT `user_id` FROM `messages_dislikes` WHERE `msg_id` = " . $_GET['liked'] . " AND `user_id` = " . $userID . "";
					$userDisLiked = $conn->query($hasUserDisLikedQuery);
					if (!$userDisLiked->num_rows > 0) {
					} else {
						$unDislikedQuery = "UPDATE `messages` SET `dislikes` = `dislikes`-1 WHERE `messages`.`msg_id` = " . $_GET['liked'] . "";
						$postunDisLikesQuery = "DELETE FROM `messages_dislikes` WHERE `messages_dislikes`.`msg_id` = " . $_GET['liked'] . " AND `messages_dislikes`.`user_id` = " . $userID . "";
						$conn->query($unDislikedQuery);
						$conn->query($postunDisLikesQuery);
					}
					header("Location: home.php?id=" . $groupID . "");
				} else {
					$unlikedQuery = "UPDATE `messages` SET `likes` = `likes`-1 WHERE `messages`.`msg_id` = " . $_GET['liked'] . "";
					$postunLikesQuery = "DELETE FROM `messages_likes` WHERE `messages_likes`.`msg_id` = " . $_GET['liked'] . " AND `messages_likes`.`user_id` = " . $userID . "";
					$conn->query($unlikedQuery);
					$conn->query($postunLikesQuery);
					header("Location: home.php?id=" . $groupID . "");
				}
			} else {
				header("Location: home.php?id=" . $groupID . "");
			}
		} else {
			header("Location: home.php?id=" . $groupID . "");
		}
	}
	if (isset($_GET['disliked'])) {
		$archivedQuery = "SELECT isArchived FROM groups WHERE group_id = $groupID";
		$archived = $conn->query($archivedQuery);
		if ($archived->num_rows > 0) {
			while ($row = $archived->fetch_assoc()) {
				$resultArchived = $row['isArchived'];
			}
		}
		if ($resultArchived == 0) {
			$isinQuery = "SELECT * FROM group_users a WHERE a.user_id = $userID AND a.group_id = $groupID";
			$isinResult = $conn->query($isinQuery);
			if ($isinResult->num_rows > 0) {
				$hasUserDisLikedQuery = "SELECT `user_id` FROM `messages_dislikes` WHERE `msg_id` = " . $_GET['disliked'] . " AND `user_id` = " . $userID . "";
				$userDisLiked = $conn->query($hasUserDisLikedQuery);
				if (!$userDisLiked->num_rows > 0) { 
					// output data of each row
					$dislikedQuery = "UPDATE `messages` SET `dislikes` = `dislikes`+1 WHERE `messages`.`msg_id` = " . $_GET['disliked'] . "";
					$postDisLikesQuery = "INSERT INTO `messages_dislikes` (`msg_id`, `user_id`) VALUES ('" . $_GET['disliked'] . "', '" . $userID . "')";
					$conn->query($dislikedQuery);
					$conn->query($postDisLikesQuery);
					//make sure user can't like and dislike a post
					$hasUserLikedQuery = "SELECT `user_id` FROM `messages_likes` WHERE `msg_id` = " . $_GET['disliked'] . " AND `user_id` = " . $userID . "";
					$userLiked = $conn->query($hasUserLikedQuery);
					if (!$userLiked->num_rows > 0) {
					} else {
						$unlikedQuery = "UPDATE `messages` SET `likes` = `likes`-1 WHERE `messages`.`msg_id` = " . $_GET['disliked'] . "";
						$postunLikesQuery = "DELETE FROM `messages_likes` WHERE `messages_likes`.`msg_id` = " . $_GET['disliked'] . " AND `messages_likes`.`user_id` = " . $userID . "";
						$conn->query($unlikedQuery);
						$conn->query($postunLikesQuery);
					}
					header("Location: home.php?id=" . $groupID . "");
				} else {
					$unDislikedQuery = "UPDATE `messages` SET `dislikes` = `dislikes`-1 WHERE `messages`.`msg_id` = " . $_GET['disliked'] . "";
					$postunDisLikesQuery = "DELETE FROM `messages_dislikes` WHERE `messages_dislikes`.`msg_id` = " . $_GET['disliked'] . " AND `messages_dislikes`.`user_id` = " . $userID . "";
					$conn->query($unDislikedQuery);
					$conn->query($postunDisLikesQuery);
					header("Location: home.php?id=" . $groupID . "");
				}
			} else {
				header("Location: home.php?id=" . $groupID . "");
			}
		} else {
			header("Location: home.php?id=" . $groupID . "");
		}
	}
	
	if (isset($_POST['msg'])) {
		$archivedQuery = "SELECT isArchived FROM groups WHERE group_id = $groupID";
		$archived = $conn->query($archivedQuery);
		if ($archived->num_rows > 0) {
			while ($row = $archived->fetch_assoc()) {
				$resultArchived = $row['isArchived'];
			}
		}
		if ($resultArchived == 0) {
			$isinQuery = "SELECT * FROM group_users a WHERE a.user_id = $userID AND a.group_id = $groupID";
			$isinResult = $conn->query($isinQuery);
			if ($isinResult->num_rows > 0) {
				$message = mysqli_real_escape_string($conn, $_POST['msg']);
				$query = "INSERT INTO `messages` (`msg_id`, `user_id`, `msg`, `post_time`, `group_id`, `likes`, `dislikes`, `parent_id`, `hasChildren`) VALUES (NULL, '" . $userID . "', '" . $message . "', CURRENT_TIMESTAMP, '" . $groupID . "',0,0,0,0);";
				$conn->query($query); 
				$conn->close();
			} else {
			}
		}
	}	
	if (isset($_POST['rply']) && isset($_POST['commentid'])) {
		$archivedQuery = "SELECT isArchived FROM groups WHERE group_id = $groupID";
		$archived = $conn->query($archivedQuery);
		if ($archived->num_rows > 0) {
			while ($row = $archived->fetch_assoc()) {
				$resultArchived = $row['isArchived'];
			}
		}
		if ($resultArchived == 0) {
			$isinQuery = "SELECT * FROM group_users a WHERE a.user_id = $userID AND a.group_id = $groupID";
			$isinResult = $conn->query($isinQuery);
			if ($isinResult->num_rows > 0) {
				$message = mysqli_real_escape_string($conn, $_POST['rply']);
				$query = "INSERT INTO `messages` (`msg_id`, `user_id`, `msg`, `post_time`, `group_id`, `likes`, `dislikes`, `parent_id`, `hasChildren`) VALUES (NULL, '" . $userID . "', '" . $message . "', CURRENT_TIMESTAMP, '" . $groupID . "',0,0,".$_POST['commentid'].",0);";
				$query2 = "UPDATE `messages` SET `hasChildren` = '1' WHERE `messages`.`msg_id` = ".$_POST['commentid']."";
				$conn->query($query); 
				$conn->query($query2); 
				$conn->close();
			} else {
			}
		}
	}	
	if (isset($_POST['deleteID'])) {
		$archivedQuery = "SELECT isArchived FROM groups WHERE group_id = $groupID";
		$archived = $conn->query($archivedQuery);
		if ($archived->num_rows > 0) {
			while ($row = $archived->fetch_assoc()) {
				$resultArchived = $row['isArchived'];
			}
		}
		if ($resultArchived == 0) {
			$query = "DELETE FROM `messages` WHERE `messages`.`msg_id` = ".$_POST['deleteID']."";
			$conn->query($query); 
			$conn->close();
		}
	}	
	if (isset($_POST['archiveID'])) {
		$archivedQuery = "SELECT isArchived FROM groups WHERE group_id = $groupID";
		$archived = $conn->query($archivedQuery);
		if ($archived->num_rows > 0) {
			while ($row = $archived->fetch_assoc()) {
				$resultArchived = $row['isArchived'];
			}
		}
		if ($resultArchived == 0) {
			$query = "UPDATE `groups` SET `isArchived` = '1' WHERE `groups`.`group_id` = ".$_POST['archiveID']."";
			$conn->query($query); 
			$conn->close();
		} else {
			$query = "UPDATE `groups` SET `isArchived` = '0' WHERE `groups`.`group_id` = ".$_POST['archiveID']."";
			$conn->query($query);
			$conn->close();
		}
	}
/*
	if (isset($_POST['reply_submit'])) {
		$message = mysqli_real_escape_string($conn, $_POST['reply']);
		$query = "INSERT INTO `messages` (`msg_id`, `user_id`, `msg`, `post_time`, `group_id`, `likes`, `parent_id`) VALUES (NULL, '" . $userID . "', '" . $message . "', CURRENT_TIMESTAMP, '" . $groupID . "',0,".$row['msg_id'].");";
		$conn->query($query);
		header("Location: home.php?id=" . $groupID . ""); 
		$conn->close();
	}*/
?>

<!doctype HTML>
<html>
	<head>
		<meta name="viewport" content="width=device-width, height=device-height, initial-scale=1.0">
		<title>Social Media Prototype Testing</title>
		<link rel="stylesheet" type="text/css" href="css/style.css">
		<link href="https://fonts.googleapis.com/css?family=Exo+2" rel="stylesheet">
		<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js"></script>
		<script src="script/dropdown.js" type="text/javascript"></script>
		<script>

		/*
		Reference: https://www.youtube.com/watch?v=BkcOqyq8W2M
		*/
		function imagepreview(input) {
			if (input.files && input.files[0]) {
				var filerd = new FileReader();
				filerd.onload=function (e) {
					$("#imgpreview").attr("src", e.target.result);
				};
				filerd.readAsDataURL(input.files[0]);
			}
		}

		function displayMessages() {
			var xhr = new XMLHttpRequest();
			var ID = "<?php echo $groupID ?>";
			var page = "<?php echo $page ?>";
			var adminID = "<?php echo $adminID ?>";
			var userID = "<?php echo $userID ?>";
			xhr.open('GET', 'messages.php?gid='+ID+'&page='+page, true);
			xhr.onload = function (){
				if(this.status == 200) {
					var msgs = JSON.parse(this.responseText);
					var output = '';
					for(var i in msgs){
						if (ID == msgs[i].group_id){
							if(msgs[i].img == ''){
								var gravatar = "https://www.gravatar.com/avatar/"+msgs[i].email+"?d=retro";
								if(gravatar){
									if (msgs[i].image != null) {
										output+= "<div id='msgWrapper"+msgs[i].msg_id+"'><span><img id ='chat_avatar' width='50' height='50' src="+gravatar+" alt='Profile Pic'><h2 id ='userName'>"+msgs[i].username+": <img class='msgimage' src='uploads/"+msgs[i].image+"' alt='Profile Pic'></h2><div class='time'>"+msgs[i].post_time+"</div></span><div class='reply_pos'><form id="+msgs[i].msg_id+" onsubmit='postReply(event,"+msgs[i].msg_id+")'><input id='replying"+msgs[i].msg_id+"' class='replying' type='text' name='reply' placeholder='Post Your Reply...'><input type='hidden' name='commentID' id='commentID' value='"+msgs[i].msg_id+"'/><input id='reply_submit' type='submit' name='reply_submit' value='Reply!'></form></div><form action='home.php?id="+ID+" &liked="+msgs[i].msg_id+"' method='POST'><div class='likeys'><input id='like_input'type='submit' name='like' value='Like'> "+msgs[i].likes+" likes</div></form><form action='home.php?id="+ID+" &disliked="+msgs[i].msg_id+"' method='POST'><div class='dislikeys'><input id='dislike_input'type='submit' name='dislike' value='Dislike'> "+msgs[i].dislikes+" dislikes</div></form><span><button type='button' id='show_replies' onclick='toggleReplies(event,"+msgs[i].msg_id+")'>Show Replies</button></span>";										
									} else if (msgs[i].file != null) {
										output+= "<div id='msgWrapper"+msgs[i].msg_id+"'><span><img id ='chat_avatar' width='50' height='50' src="+gravatar+" alt='Profile Pic'><h2 id ='userName'>"+msgs[i].username+": <a href='uploads/"+msgs[i].file+"' download>"+msgs[i].cleanName+"</a></h2><div class='time'>"+msgs[i].post_time+"</div></span><div class='reply_pos'><form id="+msgs[i].msg_id+" onsubmit='postReply(event,"+msgs[i].msg_id+")'><input id='replying"+msgs[i].msg_id+"' class='replying' type='text' name='reply' placeholder='Post Your Reply...'><input type='hidden' name='commentID' id='commentID' value='"+msgs[i].msg_id+"'/><input id='reply_submit' type='submit' name='reply_submit' value='Reply!'></form></div><form action='home.php?id="+ID+" &liked="+msgs[i].msg_id+"' method='POST'><div class='likeys'><input id='like_input'type='submit' name='like' value='Like'> "+msgs[i].likes+" likes</div></form><form action='home.php?id="+ID+" &disliked="+msgs[i].msg_id+"' method='POST'><div class='dislikeys'><input id='dislike_input'type='submit' name='dislike' value='Dislike'> "+msgs[i].dislikes+" dislikes</div></form><span><button type='button' id='show_replies' onclick='toggleReplies(event,"+msgs[i].msg_id+")'>Show Replies</button></span>";										
									} else {
										output+= "<div id='msgWrapper"+msgs[i].msg_id+"'><span><img id ='chat_avatar' width='50' height='50' src="+gravatar+" alt='Profile Pic'><h2 id ='userName'>"+msgs[i].username+": "+msgs[i].msg+"</h2><div class='time'>"+msgs[i].post_time+"</div></span><div class='reply_pos'><form id="+msgs[i].msg_id+" onsubmit='postReply(event,"+msgs[i].msg_id+")'><input id='replying"+msgs[i].msg_id+"' class='replying' type='text' name='reply' placeholder='Post Your Reply...'><input type='hidden' name='commentID' id='commentID' value='"+msgs[i].msg_id+"'/><input id='reply_submit' type='submit' name='reply_submit' value='Reply!'></form></div><form action='home.php?id="+ID+" &liked="+msgs[i].msg_id+"' method='POST'><div class='likeys'><input id='like_input'type='submit' name='like' value='Like'> "+msgs[i].likes+" likes</div></form><form action='home.php?id="+ID+" &disliked="+msgs[i].msg_id+"' method='POST'><div class='dislikeys'><input id='dislike_input'type='submit' name='dislike' value='Dislike'> "+msgs[i].dislikes+" dislikes</div></form><span><button type='button' id='show_replies' onclick='toggleReplies(event,"+msgs[i].msg_id+")'>Show Replies</button></span>";
									}
								} else {
									if (msgs[i].image != null) {
										output+= "<div id='msgWrapper"+msgs[i].msg_id+"'><span><img id ='chat_avatar' width='50' height='50' src='uploads/profiledefault.png' alt='Profile Pic'><h2 id ='userName'>"+msgs[i].username+": <img class='msgimage' src='uploads/"+msgs[i].image+"' alt='Profile Pic'></h2><div class='time'>"+msgs[i].post_time+"</div></span><div class='reply_pos'><form id="+msgs[i].msg_id+" onsubmit='postReply(event,"+msgs[i].msg_id+")'><input id='replying"+msgs[i].msg_id+"' class='replying' type='text' name='reply' placeholder='Post Your Reply...'><input type='hidden' name='commentID' id='commentID' value='"+msgs[i].msg_id+"'/><input id='reply_submit' type='submit' name='reply_submit' value='Reply!'></form></div><form action='home.php?id="+ID+" &liked="+msgs[i].msg_id+"' method='POST'><div class='likeys'><input id='like_input'type='submit' name='like' value='Like'> "+msgs[i].likes+" likes</div></form><form action='home.php?id="+ID+" &disliked="+msgs[i].msg_id+"' method='POST'><div class='dislikeys'><input id='dislike_input'type='submit' name='dislike' value='Dislike'> "+msgs[i].dislikes+" dislikes</div></form><span><button type='button' id='show_replies' onclick='toggleReplies(event,"+msgs[i].msg_id+")'>Show Replies</button></span>";									
									} else if (msgs[i].file != null) {
										output+= "<div id='msgWrapper"+msgs[i].msg_id+"'><span><img id ='chat_avatar' width='50' height='50' src='uploads/profiledefault.png' alt='Profile Pic'><h2 id ='userName'>"+msgs[i].username+": <a href='uploads/"+msgs[i].file+"' download>"+msgs[i].cleanName+"</a></h2><div class='time'>"+msgs[i].post_time+"</div></span><div class='reply_pos'><form id="+msgs[i].msg_id+" onsubmit='postReply(event,"+msgs[i].msg_id+")'><input id='replying"+msgs[i].msg_id+"' class='replying' type='text' name='reply' placeholder='Post Your Reply...'><input type='hidden' name='commentID' id='commentID' value='"+msgs[i].msg_id+"'/><input id='reply_submit' type='submit' name='reply_submit' value='Reply!'></form></div><form action='home.php?id="+ID+" &liked="+msgs[i].msg_id+"' method='POST'><div class='likeys'><input id='like_input'type='submit' name='like' value='Like'> "+msgs[i].likes+" likes</div></form><form action='home.php?id="+ID+" &disliked="+msgs[i].msg_id+"' method='POST'><div class='dislikeys'><input id='dislike_input'type='submit' name='dislike' value='Dislike'> "+msgs[i].dislikes+" dislikes</div></form><span><button type='button' id='show_replies' onclick='toggleReplies(event,"+msgs[i].msg_id+")'>Show Replies</button></span>";
									} else {
										output+= "<div id='msgWrapper"+msgs[i].msg_id+"'><span><img id ='chat_avatar' width='50' height='50' src='uploads/profiledefault.png' alt='Profile Pic'><h2 id ='userName'>"+msgs[i].username+": "+msgs[i].msg+"</h2><div class='time'>"+msgs[i].post_time+"</div></span><div class='reply_pos'><form id="+msgs[i].msg_id+" onsubmit='postReply(event,"+msgs[i].msg_id+")'><input id='replying"+msgs[i].msg_id+"' class='replying' type='text' name='reply' placeholder='Post Your Reply...'><input type='hidden' name='commentID' id='commentID' value='"+msgs[i].msg_id+"'/><input id='reply_submit' type='submit' name='reply_submit' value='Reply!'></form></div><form action='home.php?id="+ID+" &liked="+msgs[i].msg_id+"' method='POST'><div class='likeys'><input id='like_input'type='submit' name='like' value='Like'> "+msgs[i].likes+" likes</div></form><form action='home.php?id="+ID+" &disliked="+msgs[i].msg_id+"' method='POST'><div class='dislikeys'><input id='dislike_input'type='submit' name='dislike' value='Dislike'> "+msgs[i].dislikes+" dislikes</div></form><span><button type='button' id='show_replies' onclick='toggleReplies(event,"+msgs[i].msg_id+")'>Show Replies</button></span>";
									}
								}
							} else {
								if (msgs[i].image != null) {
									output+= "<div id='msgWrapper"+msgs[i].msg_id+"'><span><img id ='chat_avatar' width='50' height='50' src='uploads/"+msgs[i].img+"' alt='Profile Pic'><h2 id ='userName'>"+msgs[i].username+": <img class='msgimage' src='uploads/"+msgs[i].image+"' alt='Profile Pic'></h2><div class='time'>"+msgs[i].post_time+"</div></span><div class='reply_pos'><form id="+msgs[i].msg_id+" onsubmit='postReply(event,"+msgs[i].msg_id+")'><input id='replying"+msgs[i].msg_id+"' class='replying' type='text' name='reply' placeholder='Post Your Reply...'><input type='hidden' name='commentID' id='commentID' value='"+msgs[i].msg_id+"'/><input id='reply_submit' type='submit' name='reply_submit' value='Reply!'></form></div><form action='home.php?id="+ID+" &liked="+msgs[i].msg_id+"' method='POST'><div class='likeys'><input id='like_input'type='submit' name='like' value='Like'> "+msgs[i].likes+" likes</div></form><form action='home.php?id="+ID+" &disliked="+msgs[i].msg_id+"' method='POST'><div class='dislikeys'><input id='dislike_input'type='submit' name='dislike' value='Dislike'> "+msgs[i].dislikes+" dislikes</div></form><span><button type='button' id='show_replies' onclick='toggleReplies(event,"+msgs[i].msg_id+")'>Show Replies</button></span>";							
								} else if ((msgs[i].file != null)) {
									output+= "<div id='msgWrapper"+msgs[i].msg_id+"'><span><img id ='chat_avatar' width='50' height='50' src='uploads/"+msgs[i].img+"' alt='Profile Pic'><h2 id ='userName'>"+msgs[i].username+": <a href='uploads/"+msgs[i].file+"' download>"+msgs[i].cleanName+"</a></h2><div class='time'>"+msgs[i].post_time+"</div></span><div class='reply_pos'><form id="+msgs[i].msg_id+" onsubmit='postReply(event,"+msgs[i].msg_id+")'><input id='replying"+msgs[i].msg_id+"' class='replying' type='text' name='reply' placeholder='Post Your Reply...'><input type='hidden' name='commentID' id='commentID' value='"+msgs[i].msg_id+"'/><input id='reply_submit' type='submit' name='reply_submit' value='Reply!'></form></div><form action='home.php?id="+ID+" &liked="+msgs[i].msg_id+"' method='POST'><div class='likeys'><input id='like_input'type='submit' name='like' value='Like'> "+msgs[i].likes+" likes</div></form><form action='home.php?id="+ID+" &disliked="+msgs[i].msg_id+"' method='POST'><div class='dislikeys'><input id='dislike_input'type='submit' name='dislike' value='Dislike'> "+msgs[i].dislikes+" dislikes</div></form><span><button type='button' id='show_replies' onclick='toggleReplies(event,"+msgs[i].msg_id+")'>Show Replies</button></span>";	
								} else {
									output+= "<div id='msgWrapper"+msgs[i].msg_id+"'><span><img id ='chat_avatar' width='50' height='50' src='uploads/"+msgs[i].img+"' alt='Profile Pic'><h2 id ='userName'>"+msgs[i].username+": "+msgs[i].msg+"</h2><div class='time'>"+msgs[i].post_time+"</div></span><div class='reply_pos'><form id="+msgs[i].msg_id+" onsubmit='postReply(event,"+msgs[i].msg_id+")'><input id='replying"+msgs[i].msg_id+"' class='replying' type='text' name='reply' placeholder='Post Your Reply...'><input type='hidden' name='commentID' id='commentID' value='"+msgs[i].msg_id+"'/><input id='reply_submit' type='submit' name='reply_submit' value='Reply!'></form></div><form action='home.php?id="+ID+" &liked="+msgs[i].msg_id+"' method='POST'><div class='likeys'><input id='like_input'type='submit' name='like' value='Like'> "+msgs[i].likes+" likes</div></form><form action='home.php?id="+ID+" &disliked="+msgs[i].msg_id+"' method='POST'><div class='dislikeys'><input id='dislike_input'type='submit' name='dislike' value='Dislike'> "+msgs[i].dislikes+" dislikes</div></form><span><button type='button' id='show_replies' onclick='toggleReplies(event,"+msgs[i].msg_id+")'>Show Replies</button></span>";
								}
							}
						
							if (adminID == userID){
								output+= "<span><form id='deleteMsg' onsubmit='deleteMsg(event,"+msgs[i].msg_id+")'><input id='deleting"+msgs[i].msg_id+"' class='deleting' type='submit' name='delete' value='Delete' data-id='"+msgs[i].msg_id+"'></form></span><div class='underline'></div></div>";
							} else {
								output+= "<div class='underline'></div></div>";
							}
						}
					}
					document.getElementsByClassName("feed")[0].innerHTML = output;
					if (msgs == null){
						var noMsgs = "<h2 id ='userName'>No messages in this channel yet. Come back soon!</h2>";
						document.getElementsByClassName("feed")[0].innerHTML = noMsgs; 
					}
				}
			}
			xhr.send();
		}
			window.onload=displayMessages;
		</script>
	</head>
	<body>
		<div class="header">
			<?php 
				echo "<div id='logo'>";
					echo $_SESSION['username'];
				echo "</div>";
				echo "<div id='group_logo'>";
					$queryGroups = "SELECT groups.group_id,groups.group_name FROM groups WHERE groups.group_id = ".$groupID."";
					$userGroups = $conn->query($queryGroups);
					if ($userGroups->num_rows > 0) { 
					// output data of each row
						while($row = $userGroups->fetch_assoc()) {
							if($row['group_id']) {
								echo  $row['group_name']. "";
							}
						}
					} 
				echo "</div>";
			?>

			<div class="menu">
				<ul>
					<li><a href="loggedout.php">Log Out</a></li>
				</ul>
			</div>
		</div>

		<div class="sidemenu">
			<ul>
				<li class="active"><a href="home.php">Home</a></li>
			</ul>

			<ul>
				<li><a href="profile.php">Profile</a></li>
				<li><a href="search_users.php">Search Users</a></li>
			</ul>

			<ul id="submenu">
				<li>
					<span>Groups</span>
					<ul>
						<?php
							//Finds the groups that a user is in
							$queryGroups = "SELECT groups.group_id,groups.group_name,groups.type FROM users, groups, group_users WHERE users.id = group_users.user_id AND groups.group_id = group_users.group_id AND users.id = " .$userID."";
							$userGroups = $conn->query($queryGroups);
							if ($userGroups->num_rows > 0) { 
								// output data of each row
								while($row = $userGroups->fetch_assoc()) {
									$count++;
									if($row['group_id'] != 1) {
										echo "<li><a href='./home.php?id=" . $row['group_id'] ."&type=".$row['type']."'>" . $row['group_name'] . "</a></li>";
									}
								} 
								if ($count == 1) {
									echo "<li><a href='./home.php'>User is only in the global group</a></li>";
								}
							}
						?>
					</ul>
				</li>
			</ul>
			<ul>
			 	<li><a href="invite_groups.php">Groups Invites</a></li>
				<li><a href="create_groups.php">Create Groups</a></li>
				<li><a href="search_groups.php">Search Groups</a></li>
				<?php
                    if ($_SESSION['adminID'] == $userID) {
                        echo "<li><a href='groupadmin.php'>Group Administration</a></li>";
                    }
				?>
				<?php
                    if ($_SESSION['adminID'] == $userID) {
                        echo "<li><a href='adminhelp.php'>Help</a></li>";
                    }
                    else{
                        echo "<li><a href='help.php'>Help</a></li>";
                    }
                ?>
            </ul>
		</div>
		<div class = "feed">

		</div>
		
		<div class="posting">
		<?php			
			echo "<form id=enterMsg>
			<input id='messeging' type='text' required='required' name='message' placeholder='Post Your Status...'>
			<input id='msg_submit' type='submit' name='submit' value='Post!'>
			</form>";

			/*
			references: https://www.youtube.com/watch?v=BkcOqyq8W2M
						http://talkerscode.com/webtricks/upload-image-from-url-using-php.php
			*/
			echo "<a href='#modal' class='modal-trigger-img'>Upload!</a>";

			echo	"<div class='modal' id='modal'>
						<div class='modal__dialog'>
							<section class='modal__content'>
								<header class='modal__header'>
									Upload Files and Images
									<a href='#' class='modal__close'>Close</a>
								</header>

								<div class='modal__body'>
								<form action='upload.php?gID=$groupID' method='post' enctype='multipart/form-data'>
									<input type='file' name='fileToUpload' id='fileToUpload' onchange='imagepreview(this);'>
									<img id='imgpreview' alt='Image Preview'/>
									<input id='up_submit' type='submit' value='Upload!' name='submit'>
								</form>
								</div>
								<div class='modal__body'>
								<form action='urlimage.php?gID=$groupID' method='post'>
									<input type='text' name='img_url' placeholder='Enter Image URL'>
									<input id='up_submit' type='submit' value='Upload!' name='urlimg'>
								</form>
								</div>
							</section>
						</div>
					</div>";

			/*
			<form id=enterReply>
			<input id='replying' type='text' name='reply' placeholder='Post Your Reply...'>
			<input id='reply_submit' type='submit' name='reply_submit' value='Reply!'>
			</form>
			*/
			if ($adminID == $userID) {
				$archivedQuery = "SELECT isArchived FROM groups WHERE group_id = $groupID";
				$archived = $conn->query($archivedQuery);
				if ($archived->num_rows > 0) {
					while ($row = $archived->fetch_assoc()) {
						$resultArchived = $row['isArchived'];
					}
				}
				if ($resultArchived == 0) {
					echo "<form id='archiveGroup' onsubmit='archive(event, $groupID)'><div class='dislikeys'><input id='archiving$groupID' class='archive' type='submit' name='delete' value='Archive' data-id=$groupID></div></form>";
				} else {
					echo "<form id='archiveGroup' onsubmit='archive(event, $groupID)'><div class='dislikeys'><input id='archiving$groupID' class='archive' type='submit' name='delete' value='UnArchive' data-id=$groupID></div></form>";
				}
			}
		?>
		</div>
		<div class='pagination'>
		<?php
			/*
				Section to display pagination links
				(The actual msg retrieval and display is done through messages.php and displayMessages(), this is simply to show the links 1.2.3.4....)
			*/
			$numPerPage = 10; //results per page
			$numMsgs = "SELECT COUNT(msg_id) FROM messages WHERE parent_id = 0 AND group_id = $groupID"; //total number of messages (parents only) in the database
			$resultNum = $conn->query($numMsgs);
			if ($resultNum->num_rows > 0) {
				while($row = $resultNum->fetch_assoc()) {
					$numOfMsgs = $row['COUNT(msg_id)'];
				}
			}
			$numOfPages = ceil($numOfMsgs/$numPerPage); //number of total pages
			$pageFirstResult = ($page-1)*$numPerPage; //the limit starting number
			for ($page=1;$page<=$numOfPages;$page++) {
				echo '<a href="home.php?id='.$groupID.'&page='.$page.'">' .$page. '</a>'; //display page links
			}
		?>
		</div>
		<script>
		
			document.getElementById('enterMsg').addEventListener('submit', postMessage);
			document.getElementById('enterMsg').addEventListener('submit', displayMessages);

			/*
			function myFunction() {
				//document.getElementById('logo').innerHTML = 'timeee';
				var x = document.getElementsByClassName("time");
    			x[1].innerHTML = "Hello World!";
			}*/
			//document.getElementById('816').addEventListener('submit', postReply);
			//document.getElementById('816').addEventListener('submit', displayMessages);
			function postMessage(e) {
				e.preventDefault();
				
				var msg = document.getElementById('messeging').value;
				var params = "msg="+msg;
				var ID = "<?php echo $groupID ?>";
				var xhr = new XMLHttpRequest();
				xhr.open('POST', 'home.php?id='+ID,false);
				xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
				xhr.send(params);
				document.getElementById('enterMsg').reset();
			}

			function postReply(e,num) {
				e.preventDefault();
				//var commentID = document.getElementById('commentID').value;
				//console.log(num);
				var reply = document.getElementById("replying"+num).value;
				//var commentID = document.getElementById('commentID').value;
				var params = "rply="+reply+"&commentid="+num;
				var ID = "<?php echo $groupID ?>";
				var xhr = new XMLHttpRequest();
				xhr.open('POST', 'home.php?id='+ID,false);
				xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
				xhr.send(params);
				//document.getElementById('enterReply').reset();
				displayMessages();
			}
			function deleteMsg(e,num) {
				e.preventDefault();
				//var commentID = document.getElementById('commentID').value;
				//console.log(num);
				var reply = document.getElementById("deleting"+num).value;
				//var commentID = document.getElementById('commentID').value;
				var params = "deleteID="+num;
				var ID = "<?php echo $groupID ?>";
				var xhr = new XMLHttpRequest();
				xhr.open('POST', 'home.php?id='+ID,false);
				xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
				xhr.send(params);
				//document.getElementById('enterReply').reset();
				displayMessages();
			}
			function archive(e,num) {
				e.preventDefault();
				//var z = $("#archiving"+num).css("color","red");
				//document.getElementById("#archiving"+num).innerHTML = ;
				//var commentID = document.getElementById('commentID').value;
				//console.log(num);
				//var reply = document.getElementById("deleting"+num).value;
				//var commentID = document.getElementById('commentID').value;
				var params = "archiveID="+num;
				var ID = "<?php echo $groupID ?>";
				var xhr = new XMLHttpRequest();
				xhr.open('POST', 'home.php?id='+ID,false);
				xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
				xhr.send(params);
				//document.getElementById('enterReply').reset();
				displayMessages();
			}
			function displayReplies(e,num) {
				//console.log('heyyyy');
				var xhr = new XMLHttpRequest();
				var ID = "<?php echo $groupID ?>";
				var adminID = "<?php echo $adminID ?>";
				var userID = "<?php echo $userID ?>";
				xhr.open('GET', 'replies.php?gid='+ID+'&cid='+num, true);
				xhr.onload = function (){
					if(this.status == 200) {
						var msgs = JSON.parse(this.responseText);
						var output = '';
						for(var i in msgs){
							if (ID == msgs[i].group_id){
								if(msgs[i].img == ''){
									var gravatar = "https://www.gravatar.com/avatar/"+msgs[i].email+"?d=retro";
									if(gravatar){
										output+= "<div class='reply_indent'><span><img id ='chat_avatar' width='50' height='50' src="+gravatar+" alt='Profile Pic'><h2 id ='userName'>"+msgs[i].username+": "+msgs[i].msg+"</h2><div class='time'>"+msgs[i].post_time+"</div></span><form action='home.php?id="+ID+" &liked="+msgs[i].msg_id+"' method='POST'><div class='likeys'><input id='like_input'type='submit' name='like' value='Like'> "+msgs[i].likes+" likes</div></form><form action='home.php?id="+ID+" &disliked="+msgs[i].msg_id+"' method='POST'><div class='dislikeys'><input id='dislike_input'type='submit' name='dislike' value='Dislike'> "+msgs[i].dislikes+" dislikes</div></form></div>";
									} else {
										output+= "<div class='reply_indent'><span><img id ='chat_avatar' width='50' height='50' src='uploads/profiledefault.png' alt='Profile Pic'><h2 id ='userName'>"+msgs[i].username+": "+msgs[i].msg+"</h2><div class='time'>"+msgs[i].post_time+"</div></span><form action='home.php?id="+ID+" &liked="+msgs[i].msg_id+"' method='POST'><div class='likeys'><input id='like_input'type='submit' name='like' value='Like'> "+msgs[i].likes+" likes</div></form><form action='home.php?id="+ID+" &disliked="+msgs[i].msg_id+"' method='POST'><div class='dislikeys'><input id='dislike_input'type='submit' name='dislike' value='Dislike'> "+msgs[i].dislikes+" dislikes</div></form></div>";
									}
								} else {
									output+= "<div class='reply_indent'><span><img id ='chat_avatar' width='50' height='50' src='uploads/"+msgs[i].img+"' alt='Profile Pic'><h2 id ='userName'>"+msgs[i].username+": "+msgs[i].msg+"</h2><div class='time'>"+msgs[i].post_time+"</div></span><form action='home.php?id="+ID+" &liked="+msgs[i].msg_id+"' method='POST'><div class='likeys'><input id='like_input'type='submit' name='like' value='Like'> "+msgs[i].likes+" likes</div></form><form action='home.php?id="+ID+" &disliked="+msgs[i].msg_id+"' method='POST'><div class='dislikeys'><input id='dislike_input'type='submit' name='dislike' value='Dislike'> "+msgs[i].dislikes+" dislikes</div></form></div>";
								}
								if (adminID == userID){
									output+= "<span><form id='deleteMsg' onsubmit='deleteMsg(event,"+msgs[i].msg_id+")'><input id='deleting"+msgs[i].msg_id+"' class='deleting' type='submit' name='delete' value='Delete' data-id='"+msgs[i].msg_id+"'></form></span><div class='underline'></div></div>";
								} else {
									output+= "<div class='underline'></div>";
								}
							}
						}
						var newDiv = document.createElement('div');
						newDiv.setAttribute("id", "replies"+num);
						newDiv.innerHTML = output;
						document.getElementById("msgWrapper"+num).appendChild(newDiv);
						/*
						if (msgs == null){
							var noMsgs = "<h2 id ='userName'>No messages in this channel yet. Come back soon!</h2>";
							document.getElementsByClassName("feed")[0].innerHTML = noMsgs; 
						}*/
					}
				}
				xhr.send();
			}
			function toggleReplies(e,num) {
				var x = $('#replies'+num).css("display");
	
				if (x == undefined){
					displayReplies(e,num);
					$('#replies'+num).toggle();
				} else {
					$('#replies'+num).toggle();
				}
				
				//displayReplies(e,num);
				//$('#replies').toggle();
				var display = $('#replies').css("display");
				//console.log(display);
			}
	
		</script>
	</body>
</html>