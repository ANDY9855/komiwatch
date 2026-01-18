<?php
$mysqli = new mysqli("localhost", "root", "", "watchparty");
$room = $_GET['room'];

$stmt = $mysqli->prepare("SELECT username, message, created_at FROM messages WHERE room_code=? ORDER BY id ASC");
$stmt->bind_param("s", $room);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

echo json_encode($messages);
?>
