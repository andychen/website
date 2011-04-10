<?php
$docRoot = getenv("DOCUMENT_ROOT");

require_once $docRoot . "/mobi-config/mobi_web_constants.php";
require_once WEBROOT . "page_builder/page_header.php";
require "UrlTypes.php";

$types = UrlTypes::getTypes();
$platform = $page->is_computer() ? "computer" : "mobile";

foreach($types as $typeName => $typeUrls) {
    if(isset($_REQUEST[$typeName])) {
        foreach($typeUrls as $urlInfo) {
            if($urlInfo['platform'] == $platform) {
                $url = $urlInfo['prefix'] . urlencode($_REQUEST[$typeName]);
                break;
            }
        }
    }
}

header("Location: $url");



