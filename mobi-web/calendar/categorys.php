<?php
$docRoot = getenv("DOCUMENT_ROOT");

require_once $docRoot . "/mobi-config/mobi_web_constants.php";
require WEBROOT . "page_builder/page_header.php";
require LIBDIR . "mit_calendar.php";
require LIBDIR . "OpenHouseCalendar.php";
require WEBROOT . "calendar/calendar_lib.php";

$type = !isset($_REQUEST['type']) ? 'events' : $_REQUEST['type'];

switch ($type) {
    case 'events':
      $categorys = MIT_Calendar::Categorys();
      break;
    case 'openhouse':
      $categorys = OpenHouseCalendar::getCategories();
      break;
}

require "$page->branch/categorys.html";
$page->output();

?>
