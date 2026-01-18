<?php
$mysqli = new mysqli("localhost", "root", "", "watchparty");
if ($mysqli->connect_errno) {
    echo json_encode(["status" => "error", "msg" => "DB fail"]);
    exit();
}

$room = $_POST['room'];
$user = $_POST['user'];
$msg  = $_POST['msg'];

$stmt = $mysqli->prepare("INSERT INTO messages (room_code, username, message) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $room, $user, $msg);
$stmt->execute();

echo json_encode(["status" => "ok"]);
?>
