<?php
$docRoot = getenv("DOCUMENT_ROOT");

require_once $docRoot . "/mobi-config/mobi_web_constants.php";
require_once WEBROOT . "page_builder/page_header.php";
require_once LIBDIR . "campus_map.php";

define('ZOOM_FACTOR', 2);
define('MOVE_FACTOR', 0.40);
define('PROJECTION', 'EPSG:26786'); // this is what the arcgis server uses

// enforce a minimum range in feet (their units) for map context
define('MIN_MAP_CONTEXT', 250);

define('MAP_PHOTO_SERVER', 'http://web.mit.edu/campus-map/objimgs');

$name = $_REQUEST['selectvalues'];

$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : 'Map';
$data = Buildings::bldg_info($name);
$projection = isset($_REQUEST['crs']) ? $_REQUEST['crs'] : PROJECTION;

if ($tab == 'Map') {
  require_once LIBDIR . '/WMSServer.php';
  $wms = new WMSServer(WMS_SERVER);
  $bbox = isset($_REQUEST['bbox']) ? bboxStr2Arr($_REQUEST['bbox']) : NULL;

  switch ($page->branch) {
   case 'Webkit':
     $imageWidth = 290; $imageHeight = 190;
     break;
   case 'Touch':
     $imageWidth = 200; $imageHeight = 200;
     break;
   case 'Basic':
     $imageWidth = 200; $imageHeight = 200;
     break;
  }

  if (!$bbox) {
    require_once LIBDIR . '/ArcGISServer.php';

    // the first letter of the object id tells us which layer to search
    switch (substr($name, 0, 1)) {
    case 'P':
      $searchResults = ArcGISServer::getParkingGeometry($name);
      break;
    case 'G':
      $searchResults = ArcGISServer::getCourtGeometry($name);
      break;
    case 'L':
      $searchResults = ArcGISServer::getLandmarkGeometry($name);
      break;
    default:
      $searchResults = ArcGISServer::getBuildingGeometry($name);
      break;
    }

    if ($searchResults 
	&& array_key_exists('features', $searchResults)
	&& count($searchResults['features']))
    {
      $result = $searchResults['features'][0];
      foreach ($result['attributes'] as $field => $value) {
        $details[$field] = $value;
      }
      switch ($searchResults['geometryType']) {
       case 'esriGeometryPolygon':
         $rings = $result['geometry']['rings'];
         $xmin = PHP_INT_MAX;
         $xmax = 0;
         $ymin = PHP_INT_MAX;
         $ymax = 0;
         foreach ($rings[0] as $point) {
           if ($xmin > $point[0]) $xmin = $point[0];
           if ($xmax < $point[0]) $xmax = $point[0];
           if ($ymin > $point[1]) $ymin = $point[1];
           if ($ymax < $point[1]) $ymax = $point[1];
         }

	 $xrange = $xmax - $xmin;
	 if ($xrange < MIN_MAP_CONTEXT) {
	   $xmax += (MIN_MAP_CONTEXT - $xrange) / 2;
	   $xmin -= (MIN_MAP_CONTEXT - $xrange) / 2;
         }
	 $yrange = $ymax - $ymin;
	 if ($yrange < 200) {
	   $ymax += (MIN_MAP_CONTEXT - $yrange) / 2;
	   $ymin -= (MIN_MAP_CONTEXT - $yrange) / 2;
         }

         break;
       case 'esriGeometryPoint':
       default:
         $pointBuffer = MIN_MAP_CONTEXT / 2;
         $xmin = $result->geometry->x - $pointBuffer;
         $xmax = $result->geometry->x + $pointBuffer;
         $ymin = $result->geometry->y - $pointBuffer;
         $ymax = $result->geometry->y + $pointBuffer;
         break;
      }
    
      $minBBox = array(
        'xmin' => $xmin,
        'ymin' => $ymin,
        'xmax' => $xmax,
        'ymax' => $ymax,
        );
  
      //$bbox = $minBBox;
      $bbox = $wms->calculateBBox($imageWidth, $imageHeight, $minBBox);

    } else { // no search results
      // use lat/lon from our xml file and create arbitrary bounding box around it
      $projection = 'EPSG:4326'; // this projection is wgs84 with x=lat and y=lon
      $minBBox = array(
        'xmin' => $data['lat_wgs84'] - 0.0005,
        'ymin' => $data['long_wgs84'] - 0.0005,
        'xmax' => $data['lat_wgs84'] + 0.0005,
        'ymax' => $data['long_wgs84'] + 0.0005,
	);
      $bbox = $wms->calculateBBox($imageWidth, $imageHeight, $minBBox);

      //$imageUrl = 'images/map_not_found_placeholder.jpg';
    }

  }

  if ($bbox) {
    $imageUrl = $wms->getMap($imageWidth, $imageHeight, $projection, $bbox);

    // build urls for panning/zooming
    $params = $_GET;
    $params['crs'] = $projection;

    $params['bbox'] = bboxArr2Str(shiftBBox($bbox, 0, -1, 0));
    $scrollNorth = 'detail.php?' . http_build_query($params);
    $params['bbox'] = bboxArr2Str(shiftBBox($bbox, 0, 1, 0));
    $scrollSouth = 'detail.php?' . http_build_query($params);
    $params['bbox'] = bboxArr2Str(shiftBBox($bbox, 1, 0, 0));
    $scrollEast = 'detail.php?' . http_build_query($params);
    $params['bbox'] = bboxArr2Str(shiftBBox($bbox, -1, 0, 0));
    $scrollWest = 'detail.php?' . http_build_query($params);
    $params['bbox'] = bboxArr2Str(shiftBBox($bbox, 0, 0, 1));
    $zoomInURL = 'detail.php?' . http_build_query($params);
    $params['bbox'] = bboxArr2Str(shiftBBox($bbox, 0, 0, -1));
    $zoomOutURL = 'detail.php?' . http_build_query($params);
  }

  $hasMap = true;
  if (!$bbox) {
    $hasMap = false;
    $bbox = array(
        'xmin' => 0,
        'ymax' => 0,
        'ymin' => 0,
        'xmax' => 0,
    );
  }

}

function scrollURL($direction) {
  return "";
}
function zoomInURL() {
  return "";
}
function zoomOutURL() {
  return "";
}

$tabs = new Tabs(selfURL($details), "tab", array("Map", "Photo", "What's Here"));

function imageURL() {
  global $imageUrl;
  return $imageUrl;
}

function photoURL() {
  $url = MAP_PHOTO_SERVER . '/object-' . $_REQUEST['selectvalues'] . '.jpg';
  $result = file_get_contents($url, FILE_BINARY, NULL, 0, 100);
  if ($result)
    return $url;
  return '';
}

$photoURL = photoURL();
if ($photoURL) {
  $photoWidth = "90%";

} else {
  $photoWidth = 'auto';
  $tabs->hide("Photo");
}

$whats_here = array();
if (array_key_exists('contents', $data)) {
  foreach ($data['contents'] as $content) {
    $whats_here[] = $content['name'];
  }
}
if (!$whats_here) $tabs->hide("What's Here");

$building_title = $data['name'];
$tabs_html = $tabs->html($page->branch);

require "$page->branch/detail.html";

$page->output();

function selfURL($details) {
  $params = array(
    'selectvalues' => $_GET['selectvalues'],
    'xoff' => $_GET['xoff'],
    'yoff' => $_GET['yoff'],
    'zoom' => $_GET['zoom'],
    'snippets' => $_GET['snippets'],
    );
  return 'detail.php?' . http_build_query($params);
}

function bboxArr2Str($bbox) {
  return implode(',', array_values($bbox));
}

function bboxStr2Arr($bboxStr) {
  $values = explode(',', $bboxStr);
  return array(
    'xmin' => $values[0],
    'ymin' => $values[1],
    'xmax' => $values[2],
    'ymax' => $values[3],
    );
}

// all args can be -1, 0, or 1
function shiftBBox($bbox, $east, $south, $in) {
  global $projection;

  if ($projection == 'EPSG:4326') { // this flips x and y
    $xmin = 'ymin';
    $xmax = 'ymax';
    $ymin = 'xmin';
    $ymax = 'xmax';
  }
  else {
    $xmin = 'xmin';
    $xmax = 'xmax';
    $ymin = 'ymin';
    $ymax = 'ymax';
  }

  $xrange = $bbox[$xmax] - $bbox[$xmin];
  $yrange = $bbox[$ymax] - $bbox[$ymin];
  if ($east != 0) {
    $bbox['xmin'] += $east * $xrange * MOVE_FACTOR;
    $bbox['xmax'] += $east * $xrange * MOVE_FACTOR;
  }
  if ($south != 0) { // south means positive or negative depends on coordinate system
    $bbox['ymin'] -= $south * $yrange * MOVE_FACTOR;
    $bbox['ymax'] -= $south * $yrange * MOVE_FACTOR;
  }
  if ($in != 0) {
    if ($in == 1)
      $inset = 0.5;
    else
      $inset = -1;

    $bbox['xmin'] += ($xrange / 2) * $inset;
    $bbox['ymin'] += ($yrange / 2) * $inset;
    $bbox['xmax'] -= ($xrange / 2) * $inset;
    $bbox['ymax'] -= ($yrange / 2) * $inset;
  }

  return $bbox;
}

function cleanStreet($data) {    
  // remove things such as '(rear)' at the end of an address
  $street = preg_replace('/\(.*?\)$/', '', $data['street']);

  //remove 'Access Via' that appears at the begginning of some addresses
  return preg_replace('/^access\s+via\s+/i', '', $street);
} 

