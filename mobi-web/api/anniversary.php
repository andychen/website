<?php

$welcomeContents = file_get_contents("anniversary/welcome.html");

echo json_encode(array("content" => $welcomeContents));

?>