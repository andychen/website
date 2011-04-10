<?php
$docRoot = getenv("DOCUMENT_ROOT");

require_once $docRoot . "/mobi-config/mobi_web_constants.php";
require WEBROOT . "page_builder/page_header.php";
require LIBDIR . "mit_calendar.php";
require LIBDIR . "MIT150Calendar.php";

//defines all the variables related to being today
require WEBROOT . "calendar/calendar_lib.php";

$time = $_REQUEST['time'];
$current = day_info($time);
$next = day_info($time, 1);
$prev = day_info($time, -1);
$type = $_REQUEST['type'];

if($type == 'events' || $type == 'exhibits') {
  $Type = ucwords($type); // type name with appropriate capitilization
  $methodName = "Todays{$Type}Headers";
  $events = MIT_Calendar::$methodName($current['date']);
} else if($type == 'mit150') {
  $Type = strtoupper($type);
  $events = MIT150Calendar::getEventsByDate($current['date']);
}

$dayHasEvents = count($events) > 0;
$noEventsMessage = "No events found";

require "$page->branch/day.html";
$page->output();

?>
