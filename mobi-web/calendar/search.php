<?php
$docRoot = getenv("DOCUMENT_ROOT");

require_once $docRoot . "/mobi-config/mobi_web_constants.php";
require WEBROOT . "page_builder/page_header.php";
require LIBDIR . "mit_calendar.php";
require LIBDIR . "MIT150Calendar.php";
require LIBDIR . "OpenHouseCalendar.php";

require WEBROOT . "calendar/calendar_lib.php";

$search_terms = $_REQUEST['filter'];

$timeframe = isset($_REQUEST['timeframe']) ? $_REQUEST['timeframe'] : 0;
$dates = SearchOptions::search_dates($timeframe);
$startTime = strtotime($dates['start']);
$endTime = strtotime($dates['end']);

if ($search_terms) {
  $events = MIT_Calendar::fullTextSearch($search_terms, $dates['start'], $dates['end']);
} else {
  $events = MIT_Calendar::eventsInDateRange($dates['start'], $dates['end']);
  $search_terms = '';
}

$mit150Events = MIT150Calendar::searchEvents($search_terms, $startTime, $endTime);
$openHouseEvents = OpenHouseCalendar::searchEvents($search_terms, $startTime, $endTime);
$events = mergeEvents(array($events, $mit150Events, $openHouseEvents));

$content = new ResultsContent("items", "calendar", $page, array("timeframe" => $timeframe));

$form = new CalendarForm($page, SearchOptions::get_options($timeframe));
$content->set_form($form);

require "$page->branch/search.html";
$page->output();

function mergeEvents($eventGroups) {
  $allEvents = array();
  foreach($eventGroups as $eventGroup) {
    $allEvents = array_merge($allEvents, $eventGroup);
  }
  usort($allEvents, cmpEvent);
  return $allEvents;
}

function cmpEvent($event1, $event2) {
  return getUnixTime($event1->start) - getUnixTime($event2->start);
}
 
function getUnixTime($time) {
  if(isset($time->hour)) {
    return mktime($time->hour, $time->minute, 0, $time->month, $time->day, $time->year);
  } else {
    return $time;
  }
}

?>
