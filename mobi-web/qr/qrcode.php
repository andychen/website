<?php

$docRoot = getenv("DOCUMENT_ROOT");
require_once $docRoot . "/mobi-config/mobi_web_constants.php";
require_once LIBDIR . "phpqrcode/qrlib.php";

if(isset($_REQUEST['url'])) {
    $url = $_REQUEST['url'];
    if(!file_exists(LIBDIR . "/cache/QR_CODE")) {
        mkdir(LIBDIR . "cache/QR_CODE");
    } 
    $cacheName = LIBDIR . "cache/QR_CODE/" . urlencode($url);
    QRcode::png($url, $cacheName, QR_ECLEVEL_H, 6, 0);
    $image = imagecreatefrompng($cacheName);

    if(isset($_REQUEST['adornment'])) {
        $artwork = imagecreatefromgif("assets/{$_REQUEST['adornment']}.gif");

        $percentMargin = 30;
        $destX = $percentMargin / 100. * imagesx($image);
        $destY = $destX;
        $destWidth = (100. - 2. * $percentMargin) / 100. * imagesx($image);
        $destHeight = $destWidth;

        $sourceWidth = imagesx($artwork);
        $sourceHeight = imagesy($artwork);

        imagecopyresized($image, $artwork, $destX, $destY, 0, 0, $destWidth, $destHeight, $sourceWidth, $sourceHeight);

    }
    header("Content-Type: image/png");
    imagepng($image);
}
