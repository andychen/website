<?php
  $docRoot = getenv("DOCUMENT_ROOT");

// this file has too much overlap with detail.php
// likewise with Webkit/detail-fullscreen.html and Webkit/detail.html
// probably should make into a single include file in Webkit

require_once $docRoot . "/mobi-config/mobi_web_constants.php";
require_once WEBROOT . "page_builder/page_header.php";
require_once LIBDIR . "WMSServer.php";

$selectvalue = $_REQUEST['selectvalues'];
$bbox = split(',', $_REQUEST['bbox']);
$projection = isset($_REQUEST['crs']) ? $_REQUEST['crs'] : 'EPSG:26786';
$minx = $bbox[0];
$miny = $bbox[1];
$maxx = $bbox[2];
$maxy = $bbox[3];

$bbox = split(',', $_REQUEST['bboxSelect']);
$minxSelect = $bbox[0];
$minySelect = $bbox[1];
$maxxSelect = $bbox[2];
$maxySelect = $bbox[3];

$wms = new WMSServer(WMS_SERVER);

$mapInitURL = $wms->getMapBaseURL(NULL, TRUE);
$urlParts = parse_url($mapInitURL);
parse_str($urlParts['query'], $queryParts);
$mapLayers = $queryParts['layers'];
unset($queryParts['layers']);
unset($queryParts['styles']);
$queryParts['crs'] = $projection;
$urlParts['query'] = http_build_query($queryParts);

$mapBaseURL = $urlParts['scheme'] . '://'
            . $urlParts['host']
            . $urlParts['path'] . '?'
            . $urlParts['query'];

$detailUrlOptions = http_build_query(array(
  'selectvalues' => $_REQUEST['selectvalues'],
  'crs' => $projection,
  ));

$mapOptions = '&' . http_build_query(array(
  //'crs' => $projection,
  'selectfield' => $_REQUEST['selectfield'],
  'selectlayer' => $_REQUEST['selectlayer'],
  ));

// what is rotateScreen()?
$onorientationchange = "scrollTo(0,1); rotateScreen(); setTimeout('rotateMap()',500)";
$extra_onload = $onorientationchange;

require "$page->branch/detail-fullscreen.html";

?>
