<?php

require_once 'DiskCache.php';

// client to server that implements of ISO/DIS 19128

define('WMS_VERSION', '1.3.0');
define('WMS_CACHE', CACHE_DIR . '/WMSCapabilities.xml');
define('WMS_SERVER', 'http://ims-pub.mit.edu/ArcGIS/services/wms/WMS/MapServer/WMSServer');
define('WMS_METERS_PER_PIXEL', 0.00028); // WMS standard definition
define('WMS_OBJECT_PADDING', 1.0); // how much context to provide around a building relative to its size
define('WMS_ZOOM_FACTOR', 2);

// for all methods below, mandatory means the WMS server must implement
// the corresponding service; optional means the server might impelemnt
// the corresponding service; neither means it is not in the WMS spec.
class WMSServer {

  private $url;
  private $diskCache;
  private $layers = array();
  private $styles = array();
  private $crs; // coord ref sys
  private $layersByTitle = array();
  private $disabledLayers = array();

  //private $zoomLevel = 0;
  //private $bbox;

  public function __construct($url) {
    $this->url = $url;
    $this->diskCache = new DiskCache(WMS_CACHE, 86400 * 7);
    $this->diskCache->preserveFormat();
    $this->getCapabilities();

    $this->disableLayer('Aerial'); // this layer is awesome, but we don't have UI to deal with it yet
    $this->disableLayer('BuildingsSelection');
    $this->disableLayer('LandmarksSelection');
    $this->disableLayer('ParkingSelection');
    $this->disableLayer('CourtyardsSelection');
  }

  public function getLayerTitles() {
    $layerTitles = array();
    foreach ($this->layersByTitle as $title => $layers) {
      // list criteria for layers that should not be disabled
      if (TRUE)
      {
        $layerTitles[$title] = $layers;
      }
    }
    return $layerTitles;
  }

  public function disableLayer($layerTitle) {
    $layerTitles = $this->getLayerTitles();
    if (array_key_exists($layerTitle, $layerTitles)
        && !in_array($layerTitle, $this->disabledLayers))
    {
      $this->disabledLayers[] = $layerTitle;
    }
  }

  public function disableAllLayers() {
    $layerTitles = $this->getLayerTitles();
    foreach ($layerTitles as $title => $layers) {
      if (!in_array($title, $this->disabledLayers)) {
        $this->disabledLayers[] = $title;
      }
    }
  }

  public function enableLayer($layerTitle) {
    if ($key = array_search($layerTitle, $this->disabledLayers)) {
       unset($this->disabledLayers[$key]);
    }
  }

  public function enableAllLayers() {
    $this->disabledLayers = array();
  }

  // mandatory
  private function getCapabilities() {

    if (!$this->diskCache->isFresh()) {
      $params = array(
        'request' => 'GetCapabilities',
        'service' => 'WMS',
        );
      $query = $this->url . '?' . http_build_query($params);
      $contents = file_get_contents($query);
      $this->diskCache->write($contents);
    }

    $xml = new DOMDocument();
    $xml->load($this->diskCache->getFullPath());

    foreach ($xml->getElementsByTagName('Layer') as $layerXml) {
      $aLayer = new WMSLayer($layerXml);
      $this->layers[$aLayer->name] = $aLayer;
      $title = $aLayer->title;
      if (!array_key_exists($title, $this->layersByTitle)) {
        $this->layersByTitle[$title] = array();
      }
      $this->layersByTitle[$title][] = $aLayer->name;
    }

    // layers may have different CRSes, but just assume they are
    // all the same and adopt the first one
    
  }

  /*
  public function setZoomLevel($zoomLevel) {
    $delta = $zoomLevel - $delta;
    $this->zoomLevel = $zoomLevel;
    $xrange = $this->bbox['xmax'] - $this->bbox['xmin'];
    $yrange = $this->bbox['ymax'] - $this->bbox['ymin'];

    // TODO: use zoom factor constant instead of 2
    $sum = 0;
    if ($delta > 0) {
      $power = 0.25;
      while ($delta > 0) {
	$sum += $power * $yrange;
	$delta--;
	$power /= 2;
      }
    } elseif ($delta < 0) {
      $power = 0.5
      while ($delta < 0) {
	$sum += power * $yrange;
	$delta++;
	$power *= 2;
      }
    }
    $this->bbox['xmin'] += $sum;
  }

  public function zoomIn() {
    $this->setZoomLevel($this->zoomLevel + 1);
  }

  public function zoomOut() {
    $this->setZoomLevel($this->zoomLevel - 1);
  }
  */

  // mandatory
  public function getMap($imageWidth, $imageHeight, $crs, $bbox=NULL) {
    $baseUrl = $this->getMapBaseUrl($bbox);

    if ($bbox === NULL) {
      //if ($this->bbox !== NULL)
      //  $bbox = $this->bbox;
      //else // default to bounding box of top layer
        $bbox = end($this->layers)->bbox;
    }

    $bboxStr = $bbox['xmin'] . ',' 
             . $bbox['ymin'] . ',' 
             . $bbox['xmax'] . ',' 
             . $bbox['ymax'];

    $params = array(
      'bbox' => $bboxStr,
      'width' => $imageWidth,
      'height' => $imageHeight,
      'crs' => $crs,
      );

    $url = $baseUrl . '&' . http_build_query($params);
    return $url;
  }

  // for clients who want to calculate width/height/bbox with javascript
  public function getMapBaseUrl($bbox=NULL,$noLabels=FALSE) {
    if ($bbox !== NULL) {
      $xrange = $bbox['xmax'] - $bbox['xmin'];
      $yrange = $bbox['ymax'] - $bbox['ymin'];

      // don't know how to get this automatically yet, eyeballing what looks good
      if ($xrange < 600) $fontsize = 14;
      elseif ($xrange < 900) $fontsize = 12;
      elseif ($xrange < 1200) $fontsize = 10;
      else $fontsize = 8;

      foreach (array('landmarks', 'parking', 'greens', 'road', 'bldg') as $prefix) {
	foreach (array(8, 10, 12, 14) as $size) {
	  if ($size == $fontsize) {
	    $this->enableLayer("$prefix-iden-$size");
	  } else {
	    $this->disableLayer("$prefix-iden-$size");
	  }
	}
      }
    }

    if ($noLabels) {
      foreach (array('landmarks', 'parking', 'greens', 'road', 'bldg') as $prefix) {
	foreach (array(8, 10, 12, 14) as $size) {
	  $this->disableLayer("$prefix-iden-$size");
	}
      }
    }

    // use all layers; for each layer use the first associated style
    $layerNames = array();
    $styleNames = array();
    foreach ($this->layers as $layer) {
      if (!in_array($layer->title, $this->disabledLayers)) {
        $layerNames[] = $layer->name;
        $styleNames[] = $layer->getDefaultStyle()->name;
      }
    }

    $params = array(
      'request' => 'GetMap',
      'version' => WMS_VERSION,
      'layers' => implode(',', $layerNames),
      'styles' => implode(',', $styleNames),
      'format' => 'png',
      );

    $url = WMS_SERVER . '?' . http_build_query($params);
    return $url;
  }

  // optional, and only on layers where queryable == 1.
  // issued to get more information about features associated
  // with a pixel on a map image returned by getMap()
  public function getFeatureInfo() {
  }

  public function calculateBBox($imageWidth, $imageHeight, $bbox=NULL) {
    if ($bbox === NULL) { // default to bounding box of top layer
      $bbox = end($this->layers)->bbox;

    } else { // add buffering to all sides
      $xrange = $bbox['xmax'] - $bbox['xmin'];
      $yrange = $bbox['ymax'] - $bbox['ymin'];

      $imageRatio = $imageWidth / $imageHeight;
      $bboxRatio = $xrange / $yrange;

      if ($imageRatio > $bboxRatio) { // need more horizontal padding
        $ypadding = $yrange * WMS_OBJECT_PADDING;
        $xpadding = ($yrange * (1 + WMS_OBJECT_PADDING)) * $imageRatio - $xrange;
      } else { // need more vertical padding
        $xpadding = $xrange * WMS_OBJECT_PADDING;
        $ypadding = ($xrange * (1 + WMS_OBJECT_PADDING)) / $imageRatio - $yrange;
      }
      
      $bbox['ymin'] -= $ypadding / 2;
      $bbox['ymax'] += $ypadding / 2;
      $bbox['xmin'] -= $xpadding / 2;
      $bbox['xmax'] += $xpadding / 2;
    }

    return $bbox;
  }

}

// contained within a WMSServer
class WMSLayer {
  public $title;
  public $name;
  public $queryable;
  public $crs; // coordinate reference systems
  public $bbox;
  private $styles = array();
  private $maxScaleDenom;
  private $minScaleDenom;
  
  // optional
  private $abstract;
  private $keywordList = array();

  public function __construct($xmlNode) {
    $maybeQueryable = $xmlNode->attributes->getNamedItem('queryable');
    if ($maybeQueryable) {
      $this->queryable = $maybeQueryable->nodeValue;
    }
    $this->name = $xmlNode->getElementsByTagName('Name')->item(0)->nodeValue;
    $this->title = $xmlNode->getElementsByTagName('Title')->item(0)->nodeValue;
    $this->abstract = $this->getOptional('Abstract', $xmlNode);
    $this->maxScaleDenom = $this->getOptional('MaxScaleDenominator', $xmlNode);
    $this->minScaleDenom = $this->getOptional('MinScaleDenominator', $xmlNode);

    // there can be multiple CRSes in each layer,
    // but we only need to deal one at a time so use the first
    $this->crs = $this->getOptional('CRS', $xmlNode);

    // same for bounding box
    $bboxes = $xmlNode->getElementsByTagName('BoundingBox');
    if ($bboxes->length > 0) {
      $aBbox = $bboxes->item(0);
      $this->bbox = array(
        'xmin' => $aBbox->attributes->getNamedItem('minx')->nodeValue,
        'xmax' => $aBbox->attributes->getNamedItem('maxx')->nodeValue,
        'ymin' => $aBbox->attributes->getNamedItem('miny')->nodeValue,
        'ymax' => $aBbox->attributes->getNamedItem('maxy')->nodeValue,
        );
    }

    foreach ($xmlNode->getElementsByTagName('Style') as $style) {
      $this->styles[] = new WMSStyle($style);
    }

  }

  public function getDefaultStyle() {
    return $this->styles[0];
  }

  private function getOptional($fieldName, $xmlNode) {
    $maybeField = $xmlNode->getElementsByTagName($fieldName);
    if ($maybeField->length > 0) {
      return $maybeField->item(0)->nodeValue;
    }
    return NULL;
  }

}

// contained with a WMSLayer
class WMSStyle {

  public $title;
  public $name;

  public function __construct($xmlNode) {
    $this->title = $xmlNode->getElementsByTagName('Title')->item(0)->nodeValue;
    $this->name = $xmlNode->getElementsByTagName('Name')->item(0)->nodeValue;
  }

}

