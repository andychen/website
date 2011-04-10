<?
header("Content-Type: image/jpeg");
header("Cache-Control: max-age=86400");

$docRoot = getenv("DOCUMENT_ROOT");
require_once $docRoot . "/mobi-config/mobi_web_constants.php";

$imagePath = LIBDIR . "/static/TOURS/{$_REQUEST['tourId']}/images/{$_REQUEST['photoId']}.jpg";

$img = imagecreatefromjpeg($imagePath);
imagejpeg($img);

?>