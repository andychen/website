<?

function day_info($time, $offset=0) {
  $time += $offset * 24 * 60 * 60;
  return array(
    "weekday"       => date('l', $time),
    "month"         => date('F', $time),
    "month_3Let"    => date('M', $time),
    "day_num"       => date('j', $time),
    "year"          => date('Y', $time),
    "month_num"     => date('m', $time),
    "day_3Let"      => date('D', $time),
    "day_num_2dig"  => date('d', $time),
    "date"          => date('Y/m/d', $time),
    "time"          => strtotime(date("Y-m-d 12:00:00", $time))
  );
}

class SearchOptions {
  private static $options = array(
    array("phrase" => "in the next 7 days",   "offset" => 7),
    array("phrase" => "in the next 15 days",  "offset" => 15),
    array("phrase" => "in the next 30 days",  "offset" => 30),
    array("phrase" => "in the past 15 days",  "offset" => -15),
    array("phrase" => "in the past 30 days",  "offset" => -30),
    array("phrase" => "this school term",     "offset" => "term"),
    array("phrase" => "this school year",     "offset" => "year")
  );

  public static function get_options($selected = 0) {
    $out_options = self::$options;
    $out_options[$selected]['selected'] = true;
    return $out_options;
  }

  public static function search_dates($option) {
    $offset = self::$options[$option]["offset"];
    $time = time();
    $day1 = day_info($time);

    if(is_int($offset)) {
      $day2 = day_info($time, $offset);
      if($offset > 0) {
        return array("start" => $day1['date'], "end" => $day2['date']);
      } else {
        return array("start" => $day2['date'], "end" => $day1['date']); 
      }
    } else {
      switch($offset) {
        case "term":
          if($day1['month_num'] < 7) {
            $end_date = "{$day1['year']}/07/01";
	  } else {
            $end_date = "{$day1['year']}/12/31";
          }
          break;

        case "year": 
          if($day1['month_num'] < 7) {
            $end_date = "{$day1['year']}/07/01";
	  } else {
            $year = $day1['year'] + 1;
            $end_date = "$year/07/01";
          }
          break;
      }    
      return array("start" => $day1['date'], "end" => $end_date); 
    }
  }
}

// URL DEFINITIONS
function dayURL($day, $type) {
  return "day.php?time={$day['time']}&type=$type";
}

function academicURL($year, $month) {
  return "academic.php?year=$year&month=$month";
}

function holidaysURL($year=NULL) {
  if(!$year) {
    $year = $_REQUEST['year'];
  }
  return "holidays.php?year=$year";
}

function religiousURL($year=NULL) {
  if(!$year) {
    $year = $_REQUEST['year'];
  }
  return "holidays.php?page=religious&year=$year";
}

function categorysURL($type=NULL) {
  $query = $type ? "?type=$type" : "";
  return "categorys.php$query";
}

function categoryURL($category, $type) {
  $category = (object) $category;
  $id = $category->catid; 
  return "category.php?id=$id&type=$type";
}

function subCategorysURL($category) {
  $id = is_array($category) ? $category['catid'] : $category->catid;
  return "sub-categorys.php?id=$id";
}

function detailURL($event) {
  $params = array("id" => $event->id);
  if(isset($event->type)) {
    $params['type'] = $event->type;
  }
  return "detail.php?" . http_build_query($params);
}

// convenience functions

// for academic and holiday calendars
function formatDayTitle(ICalEvent $event) {
  $start = $event->get_start();
  $end = $event->get_end();
  if ($start != $end) {
    // all of acad calendar time units are in days so
    // we can end 23:59:00 instead of 00:00:00 next day
    $timeRange = new TimeRange($start, $end - 1);
    $dateTitle = $timeRange->format('l F j');
  } else {
    $dateTitle = date('l F j', $start);
  }
  return $dateTitle;
}

function timeText($event) {
  if(isset($event->start->weekday)) {
    $out = substr($event->start->weekday, 0, 3) . ' ';
    $out .= substr($event->start->monthname, 0, 3) . ' ';
    $out .= (int)$event->start->day . ' ';

    $out .= MIT_Calendar::timeText($event);
    return $out;
  } else {
    return dayText($event) . ' ' . hourText($event); 
  }
}
  
function dayText($event) {
    // format example Tue Feb 12
    $day = date("D M j", $event->start);
    if(isset($event->end)) {
      $endDay = date("D M j", $event->end);
      if($endDay != $day) {
        //format example Tue Feb 12-Thu Feb 14
        $day = $day . '-' . $endDay;
      }
    }   
    return $day;
}

function hourText($event) {
    // format example 8:00pm
    $time = date("g:ia", $event->start);
    if(isset($event->end)) {
      $endTime = date("g:ia", $event->end);
      if($endTime != $time) {
        // format example 8:00pm-10:00pm
        $time = $time . '-' . $endTime;
      }
    }   
    return $time;
}

function briefLocation($event) {
  if($loc = $event->shortloc) {
    return $loc;
  } else {
    return $event->location;
  }
}

function ucname($name) {
  $new_words = array();
  foreach(explode(' ', $name) as $word) {
    $new_word = array();
    foreach(explode('/', $word) as $sub_word) {
      $new_word[] = ucwords($sub_word);
    }
    $new_word = implode('/', $new_word);
    $new_words[] = $new_word;
  } 
  return implode(' ', $new_words);
}

function hasCategories($event) {
  return isset($event->categories) && (count($event->categories) > 0);
}

function hasSubcategories($category) {
  if($category->type) {
    if($category->type == "openhouse") {
      return false;
    }
  }
  return true;
}

class CalendarForm extends Form {

  protected $catid;
  protected $search_options;
  protected $type;

  public function __construct(Page $page, $search_options, $catid=NULL, $type=NULL) {
    $this->branch = $page->branch;
    $this->catid = $catid;
    $this->type = $type;
    $this->search_options = $search_options;
  }

  public function out($total=NULL) {
    $catid = $this->catid;
    $search_options = $this->search_options;
    $type = $this->type;
    require "{$this->branch}/form.html";
  }
}

?>
