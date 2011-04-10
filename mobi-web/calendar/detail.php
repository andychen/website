<?php
$docRoot = getenv("DOCUMENT_ROOT");

require_once $docRoot . "/mobi-config/mobi_web_constants.php";
require WEBROOT . "page_builder/page_header.php";
require_once LIBDIR . "mit_calendar.php";
require_once LIBDIR . "MIT150Calendar.php";
require_once LIBDIR . "OpenHouseCalendar.php";
require WEBROOT . "calendar/calendar_lib.php";

$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : 'events';

if($type == 'events' || $type == 'exhibits') {
  $event = MIT_Calendar::getEvent($_REQUEST['id']);
  $time_of_day = MIT_Calendar::timeText($event);

  $day_num = (string)(int)$event->start->day;
  $date_str = "{$event->start->weekday}, {$event->start->monthname} {$day_num}, {$event->start->year}"; 

} else {
  if($type == 'mit150') {
    $event = MIT150Calendar::getEventById($_REQUEST['id']);
  } else if($type == 'openhouse') {
    $event = OpenHouseCalendar::getEventById($_REQUEST['id']);
  }
  $time_of_day = hourText($event);
  $date_str = dayText($event);
}


$urlize = URLize($event->infourl);

function phoneURL($number) {
  if($number) {
    $number = preg_replace('/\W/', '', map_mit_phone($number));
    return "tel:1$number";
  }
}

function URLize($web_address) {
  if(preg_match('/^http\:\/\//', $web_address)) {
    return $web_address;
  } else {
    return 'http://' . $web_address;
  }
}

require "$page->branch/detail.html";
$page->output();

?>
