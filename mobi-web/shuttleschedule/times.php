<?php

$docRoot = getenv("DOCUMENT_ROOT");

require_once $docRoot . "/mobi-config/mobi_web_constants.php";
require_once WEBROOT . "page_builder/page_header.php";

require_once LIBDIR."TransitDataParser.php";

$route = stripslashes($_REQUEST['route']);

$view = new TransitDataView();
$routeInfo = $view->getRouteInfo($route);

if (!isset($routeInfo) || !count($routeInfo)) {
  $page->prepare_error_page('Shuttle Schedule', 'shuttle', 
    '<p>The route '.$route.' does not exist.  '.
    'Please update your bookmarks accordingly.  '.
    'For more information see the <a href="help.php">help page</a>.</p>');
    
} else if (!$routeInfo['inService']) {
  $page->prepare_error_page('Shuttle Schedule', 'shuttle', 
    '<p>The route '.$routeInfo['name'].' is not currently in service.  '.
    'For more information see the <a href="help.php">help page</a>.</p>');

} else {
  // determine size of route map to display on each device
  $size = 270;
  switch ($page->branch) {
    case 'Webkit':
      $size = 270;
      break;
    case 'Touch':
      $size = 200;
      break;
    case 'Basic':
      $size = 200;
      break;
  }

  $last_refreshed = time();
  $imageSrc = $view->getMapImageForRoute($route, $size, $size);
  $image_tag = $imageSrc ? '<img src="'.$imageSrc.'" />' : '';

  if ($page->branch == 'Basic') {
    function format_shuttle_time($tstamp) {
      if ($tstamp === 0) return 'finished';
      return date('g:i', $tstamp) . substr(date('a', $tstamp), 0, 1);
    }
  } else {
    function format_shuttle_time($tstamp) {
      if ($tstamp === 0) return 'finished';
      return date('g:i', $tstamp) . '<span class="ampm">' . date('A', $tstamp) . '</span>';
    }
  }

  require "$page->branch/times.html";
}

$page->prevent_caching($page->branch);
$page->output();

function selfURL() {
  return "times.php?route={$_REQUEST['route']}&now=" . time() . "&rand=" . rand();
}
