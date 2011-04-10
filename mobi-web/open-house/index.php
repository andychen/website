<?php
$docRoot = getenv("DOCUMENT_ROOT");

require_once $docRoot . "/mobi-config/mobi_web_constants.php";
require WEBROOT . "page_builder/page_header.php";
require LIBDIR . "OpenHouseCalendar.php";


$identifier = $_REQUEST['identifier'];
$categorys = OpenHouseCalendar::getCategories();

if($page->is_computer()) {
  if($identifier) {
    header("Location: http://mit150.mit.edu/open-house/themes/$identifier");
  } else {
    header("Location: http://mit150.mit.edu/open-house");
  }
} else {

  // find the category
  $catId = NULL;
  if($identifier) {
    foreach($categorys as $category) {
      if($category->identifier == $identifier) {
        $catId = $category->catid;
        break;
      }
    }
  }

  if($catId) {
    header("Location: ../calendar/category.php?id=$catId&type=openhouse");
  } else {
    header("Location: ../calendar/categorys.php?type=openhouse");
  }
}