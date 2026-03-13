<?php
// ping.php - Test paling simple
header('Content-Type: application/json');
echo json_encode(['status'=>'ok','time'=>date('H:i:s'),'php'=>phpversion()]);
?>
