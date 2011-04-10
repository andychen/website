<?php
$docRoot = getenv("DOCUMENT_ROOT");

require_once $docRoot . "/mobi-config/mobi_web_constants.php";
require WEBROOT . "page_builder/page_header.php";
require LIBDIR . "/mit_calendar.php";
require LIBDIR . "/OpenHouseCalendar.php";
require WEBROOT . "calendar/calendar_lib.php";

$catid = $_REQUEST['id'];
$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : 'Events';
$timeframe = isset($_REQUEST['timeframe']) ? $_REQUEST['timeframe'] : defaultTimeFrame($type);
$search_terms = isset($_REQUEST['filter']) ? $_REQUEST['filter'] : "";


$dates = SearchOptions::search_dates($timeframe);
$start = $dates['start'];
$end = $dates['end'];

$events = searchCategory($start, $end, $type, $catid, $search_terms);

$content = new ResultsContent(
  "items", "calendar", $page,
  array(
    "id" => $catid,
    "timeframe" => $timeframe,
    "type" => $type,
  )
);

$form = new CalendarForm($page, SearchOptions::get_options($timeframe), $catid, $type);
$content->set_form($form);

require "$page->branch/category.html";
$page->output();

function defaultTimeFrame($type) {
  if($type == 'openhouse') {
    return 2; // option for next 30 days;
  }
  return 0;
}

function searchCategory($start, $end, $type, $id, $searchTerms) {
  if($type == 'events') {
    $category =  MIT_Calendar::Category($id);
    if($searchTerms) {
      return MIT_Calendar::fullTextSearch($searchTerms, $dates['start'], $dates['end'], $category);
    } else {
      return MIT_Calendar::CategoryEventsHeaders($category, $start, $end);
    }
  } elseif ($type == 'openhouse') {
    return OpenHouseCalendar::searchCategoryByDate($searchTerms, $id, $start, $end);
  }
}


?>
