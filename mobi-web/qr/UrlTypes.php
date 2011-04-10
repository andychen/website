<?php

class UrlTypes {

  public static function getTypes() {
    return array("map" =>
        array(
            array(
                "prefix" => "http://whereis.mit.edu/?go=",
                "platform" => "computer",
            ),
            array(
                "prefix" => "http://m.mit.edu/map/detail.php?selectvalues=",
                "platform" => "mobile",
            ),
        ),
    );
  }
}