<?php

require_once 'includes/auth.php'; 
// Generowanie losowego kodu
$random_alpha = md5(rand());
$captcha_code = substr($random_alpha, 0, 6); // 6 znaków
$_SESSION['captcha_code'] = $captcha_code;

// Tworzenie obrazka
$width = 150;
$height = 40;
$image = imagecreatetruecolor($width, $height);

// Kolory
$bg_color = imagecolorallocate($image, 255, 255, 255); // Białe tło
$text_color = imagecolorallocate($image, 0, 0, 0); // Czarny tekst
$line_color = imagecolorallocate($image, 64, 64, 64); // Szare linie
$pixel_color = imagecolorallocate($image, 0, 0, 255); // Niebieskie kropki

imagefilledrectangle($image, 0, 0, $width, $height, $bg_color);

// Dodanie szumu (linii)
for($i = 0; $i < 5; $i++) {
    imageline($image, 0, rand() % $height, $width, rand() % $height, $line_color);
}

// Dodanie szumu (kropek)
for($i = 0; $i < 100; $i++) {
    imagesetpixel($image, rand() % $width, rand() % $height, $pixel_color);
}

// Dodanie tekstu )

imagestring($image, 5, 45, 12, $captcha_code, $text_color);

// Ustawienie nagłówka i wyświetlenie
header("Content-type: image/png");
imagepng($image);
imagedestroy($image);
?>