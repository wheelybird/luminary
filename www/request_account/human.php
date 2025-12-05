<?php

// Initialize session - use the standard session initialization from web_functions
// This ensures CAPTCHA and validation use the same session backend
set_include_path( ".:" . __DIR__ . "/../includes/");

define('LDAP_USER_MANAGER', true);
include_once "config_registry.inc.php";
include_once "ldap_functions.inc.php";
include_once "ldap_app_data_functions.inc.php";
include_once "ldap_session_handler.inc.php";

// Use the same session initialization as the rest of the application
ldap_session_init();

$image_width=180;
$image_height=60;

##

function random_string($length = 6) {

   // Use unambiguous characters only: exclude I, O, 0, 1, $, S, 5, B, 8, @
   // to prevent confusion between similar-looking characters
   $charset = str_split('ACDEFGHKLMNPQRTVWXYZ23467');
   $randomstr = "";
   for($i = 0; $i < $length; $i++) {
     $randomstr .= $charset[array_rand($charset, 1)];
   }
   return $randomstr;

}

##

$image = imagecreatetruecolor($image_width, $image_height);
imageantialias($image, true);

$cols = [];

$r = rand(100, 200);
$g = rand(100, 200);
$b = rand(100, 200);
 
for($i = 0; $i < 5; $i++) {
  $cols[] = imagecolorallocate($image, $r - 20*$i, $g - 20*$i, $b - 20*$i);
}
 
imagefill($image, 0, 0, $cols[0]);

$thickness = rand(2, 10);

for($i = 0; $i < 10; $i++) {
  imagesetthickness($image, $thickness);
  $line_col = $cols[rand(1,4)];
  imagerectangle($image, rand(-$thickness, ($image_width - $thickness)),
                         rand(-$thickness, $thickness),
                         rand(-$thickness, ($image_width - $thickness)),
                         rand(($image_height - $thickness), ($image_width / 2)),
                 $line_col);
}
 
$black = imagecolorallocate($image, 0, 0, 0);
$white = imagecolorallocate($image, 255, 255, 255);
$textcols = [$black, $white];

$fonts = glob(dirname(__FILE__).'/fonts/*.ttf');
$num_chars = 6;
$human_proof = random_string($num_chars);

$_SESSION['proof_of_humanity'] = $human_proof;

for($i = 0; $i < $num_chars; $i++) {
  $gap = ($image_width-15)/$num_chars;
  $size = rand(20,30);
  $angle = rand(-30,30);
  $txt_x = 10 + ($i * $gap);
  $txt_y = rand(30, ($image_height-15));
  $txt_col = $textcols[rand(0,1)];
  $txt_font =  $fonts[array_rand($fonts)];
  $txt = $human_proof[$i];
  imagettftext($image, $size, $angle, (int)$txt_x, (int)$txt_y, $txt_col, $txt_font, $txt);
}

header('Content-type: image/png');
imagepng($image);
imagedestroy($image);
?>
