<?php
require "UrlTypes.php";

$urlTypesJson = json_encode(UrlTypes::getTypes());
require "desktop/generate.html";