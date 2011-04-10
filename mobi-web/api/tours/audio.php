<?
header("Content-Type: audio/mpeg");
header("Cache-Control: max-age=86400");

$docRoot = getenv("DOCUMENT_ROOT");
require_once $docRoot . "/mobi-config/mobi_web_constants.php";

$audioPath = LIBDIR . "/static/TOURS/{$_REQUEST['tourId']}/audio/{$_REQUEST['audioId']}.mp3";
header("Content-Length: " . filesize($audioPath));

$audioHandle = fopen($audioPath, "r");
while(!feof($audioHandle)) {
  echo fread($audioHandle, 1024);
}

?>