<?
$docRoot = getenv("DOCUMENT_ROOT");

require_once $docRoot . "/mobi-config/mobi_web_constants.php";
require_once WEBROOT . "page_builder/page_header.php";

$device_names = Array(
  'iphone' => 'iOS device',
  'android' => 'Android',
  'webos' => 'webOS',
  'winmo' => 'Windows Mobile',
  'blackberry' => 'Blackberry',
  'symbian' => 'Symbian',
  'palmos' => 'Palm OS',
  'featurephone' => 'Feature Phone',
  'computer' => 'Computer',
  'spider' => 'Robot',
  );

$device_apps = Array(
  'android' => 'market://search?q=pname:edu.mit.mitmobile2',
  'blackberry' => 'media/MITMobileWeb.jad',
  'iphone' => 'http://itunes.apple.com/us/app/mit-mobile/id353590319',
  );

$device_instructions = Array(
  'android' => 'Tap the button below to access the Android Market.',
  'blackberry' => 'Instructions: On the next screen, click "Download", and "OK" or "Run" once the download is complete.',
  'iphone' => 'Tap the button below to access the iTunes App Store.',
  );

$download_items = Array(
  'android' => 'MIT Mobile',
  'blackberry' => 'the MIT Mobile Web shortcut',
  'iphone' => 'MIT Mobile',
);

$device_name = $device_names[$page->platform];

if (array_key_exists($page->platform, $device_apps)) {

  $download_url = $device_apps[$page->platform];
  $instructions = $device_instructions[$page->platform];
  $download_item = $download_items[$page->platform];
  require "$page->branch/index.html";
  $page->cache();

} else {

  $page->prepare_error_page('Download', 'download', 'Sorry, we do not have downloads for ' . $device_name . ' devices yet');
}

$page->output();

?>