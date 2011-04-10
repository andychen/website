<?

$docRoot = getenv("DOCUMENT_ROOT");

require_once $docRoot . "/mobi-config/mobi_lib_constants.php";
require_once "mit_ical_lib.php";
require_once "rss_services.php";

define("ACADEMIC_CALENDAR_CACHE_DIR", CACHE_DIR . "ACADEMIC_CALENDAR/");
define("ACADEMIC_CALENDAR_RSS", "http://localhost/drupal/academic_calendar/rss.xml");
define("ACADEMIC_CALENDAR_CACHE_LIFESPAN", 86400 * 30);

class AcademicCalendarRSS extends RSS {
  protected $rss_url = ACADEMIC_CALENDAR_RSS;
  protected $custom_tags = array('fiscal_year', 'ics_url');
}

AcademicCalendar::init();

class AcademicCalendar {
  private static $icals = array();
  //private static $ical;
  private static $terms = NULL;

  public static function is_holiday($time) {
    self::init();

    $year = date('Y', $time);
    $month = date('n', $time);
    $fiscal_year = ($month <= 6) ? $year : $year + 1;
    if (array_key_exists($fiscal_year, self::$icals)) {
      $ical = self::$icals[$fiscal_year];

      $events = $ical->get_day_events($time);
      foreach ($events as $event) {
	$summary = $event->get_summary();
	if (stripos($summary, 'holiday') !== FALSE
	    || stripos($summary, 'vacation') !== FALSE) {
	  if ($event->get_end() - $event->get_start() < 86400 * 3)
	    return TRUE;
	}
      }
    }
    return FALSE;
  }

  public static function get_holidays($year, $month=NULL) {
    $data = Array();
    if ($month && $month > 6) {
      $year += 1;
    }

    if (array_key_exists($year, self::$icals)) {
      $ical = self::$icals[$year];

      $holidays = $ical->search_by_title('holiday');
      $vacation = $ical->search_by_title('vacation');

      // here we take advantage of the fact that the acad calendar
      // does not have overlapping dates for vacations and holidays
      // if they do we need to account for that
      foreach ($holidays as $day) {
	$start = $day->get_start();
	$data[$start] = $day;
      }
      foreach ($vacation as $day) {
	$start = $day->get_start();
	$data[$start] = $day;
      }

      ksort($data);
    }
    return array_values($data);
  }

  public static function search_events($searchTerms, $month=NULL, $year=NULL) {
    if ($year === NULL) {
      $year = date('Y');
    }

    if ($month === NULL) {
      $month = date('n');
    }

    $fiscal_year = ($month <= 6) ? $year : $year + 1;
    if (array_key_exists($fiscal_year, self::$icals)) {
      $ical = self::$icals[$fiscal_year];
    } else {
      return array();
    }

    // adjust day starts for time zones
    // honestly i am not 100% sure these are the right params
    $month_start = day_of(mktime(0, 0, 0, $month, 1, $year));
    $month_end = increment_month($month_start);

    $monthRange = new TimeRange($month_start, $month_end);
    //$result = array();
    //foreach (self::$icals as $ical) {
    //  $result = array_merge($result, $ical->search_events($searchTerms, $monthRange));
    //}
    $result = $ical->search_events($searchTerms, $monthRange);
    return $result;
  }

  public static function get_events($month=NULL, $year=NULL) {
    return self::search_events(NULL, $month, $year);
  }

  public static function get_term($time=NULL) {
    if ($time === NULL)
      $time = time();

    if (self::$terms === NULL) {
      // if we're in the second half of the year, use next year's calendar
      $year = (date('n', $time) < 7) ? date('Y', $time) : date('Y', $time) + 1;

      // start with crude lower-bound guesses for ranges
      $last_summer_start = mktime(0, 0, 0, 6, 1, $year-1);
      $fall_start = mktime(0, 0, 0, 8, 15, $year-1);
      $iap_start = mktime(0, 0, 0, 1, 1, $year);
      $spring_start = mktime(0, 0, 0, 2, 1, $year);
      $summer_start = mktime(0, 0, 0, 6, 1, $year);
      $summer_end = mktime(0, 0, 0, 8, 15, $year);

      // academic calendars cover the second half of the summer after 
      // july 1 of the previous year and the first half before july 1
      // of the current fiscal year
      self::$terms = Array(
	'su2' => new TimeRange($last_summer_start, $fall_start),
	'fa' => new TimeRange($fall_start, $iap_start),
	'ia' => new TimeRange($iap_start, $spring_start),
	'sp' => new TimeRange($spring_start, $summer_start),
	'su1' => new TimeRange($summer_start, $summer_end),
	);

      if (array_key_exists($year, self::$icals)) {
	$ical = self::$icals[$year];
	$events = $ical->search_by_title("first day of");

	foreach ($events as $event) {
          $official_event_start = $event->get_start();

          // the effective start time is a day earlier so that the users
          // can see the term classes one day before the classes start,
          // but the official start time is used for searching thru the academic calendar
          // to classify the type of term.
	  $effective_event_start = increment_day($official_event_start, -1);
	  foreach (self::$terms as $term => $range) {
	    if ($range->contains_point($official_event_start)) {
	      self::$terms[$term]->set_start($effective_event_start);
	      switch ($term) {
	      case 'fa':
		self::$terms['su2']->set_end($effective_event_start);
		break;
	      case 'ia':
		self::$terms['fa']->set_end($effective_event_start);
		break;
	      case 'sp':
		self::$terms['ia']->set_end($effective_event_start);
		break;
	      case 'su1':
		self::$terms['sp']->set_end($effective_event_start);
		break;
	      } // switch
	      break;
	    } // if
	  } // foreach term
	} // foreach event

      } // done creating terms
    }

    foreach (self::$terms as $term => $range) {
      if ($range->contains_point($time)) {
	if ($term == 'su1' || $term == 'su2') {
	  return 'su';
	}
	return $term;
      }
    }

    return NULL;
  }

  public static function init() {
    if (!self::$icals) {
      $rss = new AcademicCalendarRSS();
      $items = $rss->get_feed();
      foreach ($items as $item) {
	$fy = $item['fiscal_year'];
	$ics_url = $item['ics_url'];

	$filename = ACADEMIC_CALENDAR_CACHE_DIR . $fy . '.ics';
	if (!file_exists($filename) || filemtime($filename) < time() - ACADEMIC_CALENDAR_CACHE_LIFESPAN) {
	  $fh = fopen($filename, 'w');
	  fwrite($fh, file_get_contents($ics_url));
	  fclose($fh);
	}
	self::$icals[$fy] = new ICalendar($filename);
      }
    }
  }

}

?>
