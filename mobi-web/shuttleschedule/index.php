<?php
$docRoot = getenv("DOCUMENT_ROOT");

require_once $docRoot . "/mobi-config/mobi_web_constants.php";
require_once WEBROOT . "page_builder/page_header.php";

require_once LIBDIR."TransitDataParser.php";


$allRoutes = array();

$view = new TransitDataView();
$routeConfigs = $view->getRoutes();

$allRoutes = array_keys($routeConfigs);

$nightRoutes = array_intersect(array(
  'saferidebostone', 
  'saferidebostonw', 
  'saferidebostonall', 
  'saferidecambeast', 
  'saferidecambwest', 
  'saferidecamball', 
), $allRoutes);
$dayRoutes = array_diff($allRoutes, $nightRoutes);

require "$page->branch/index.html";
$page->output();
