<?php

declare(strict_types=1);

$width = 1500;
$height = 500;

$fontBold = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
$fontRegular = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
$output = __DIR__.'/../public/images/social/firephage-x-header.png';

$image = imagecreatetruecolor($width, $height);

if ($image === false) {
    fwrite(STDERR, "Unable to create image canvas.\n");
    exit(1);
}

imageantialias($image, true);
imagesavealpha($image, true);

$alloc = static fn (int $r, int $g, int $b, int $a = 0) => imagecolorallocatealpha($image, $r, $g, $b, $a);

$bgTop = [5, 13, 24];
$bgBottom = [14, 25, 44];

for ($y = 0; $y < $height; $y++) {
    $t = $y / max(1, $height - 1);
    $r = (int) round($bgTop[0] + (($bgBottom[0] - $bgTop[0]) * $t));
    $g = (int) round($bgTop[1] + (($bgBottom[1] - $bgTop[1]) * $t));
    $b = (int) round($bgTop[2] + (($bgBottom[2] - $bgTop[2]) * $t));
    imageline($image, 0, $y, $width, $y, $alloc($r, $g, $b));
}

$glowBlue = $alloc(56, 189, 248, 108);
$glowCyan = $alloc(103, 232, 249, 118);
$glowOrange = $alloc(249, 115, 22, 110);

imagefilledellipse($image, 240, 120, 520, 520, $glowBlue);
imagefilledellipse($image, 1180, 420, 440, 280, $glowBlue);
imagefilledellipse($image, 1130, 110, 220, 220, $glowOrange);
imagefilledellipse($image, 360, 420, 300, 180, $glowCyan);

$grid = $alloc(125, 211, 252, 118);
for ($x = 0; $x <= $width; $x += 96) {
    imageline($image, $x, 0, $x, $height, $grid);
}
for ($y = 0; $y <= $height; $y += 96) {
    imageline($image, 0, $y, $width, $y, $grid);
}

$panelFill = $alloc(12, 21, 36, 32);
$panelBorder = $alloc(96, 165, 250, 74);
imagefilledrectangle($image, 36, 36, $width - 36, $height - 36, $panelFill);
imagerectangle($image, 36, 36, $width - 36, $height - 36, $panelBorder);

$shieldStroke = $alloc(103, 232, 249);
$shieldCore = $alloc(125, 211, 252);
$accentOrange = $alloc(251, 146, 60);

imagesetthickness($image, 9);
$shield = [
    180, 86,
    300, 136,
    300, 250,
    286, 312,
    240, 378,
    180, 410,
    120, 378,
    74, 312,
    60, 250,
    60, 136,
];
imagepolygon($image, $shield, 10, $shieldStroke);

$core = [
    180, 174,
    222, 198,
    222, 246,
    180, 270,
    138, 246,
    138, 198,
];
imagesetthickness($image, 7);
imagepolygon($image, $core, 6, $shieldCore);
imageline($image, 180, 270, 180, 326, $shieldCore);
imagesetthickness($image, 6);
imageline($image, 180, 326, 144, 364, $shieldCore);
imageline($image, 180, 326, 216, 364, $shieldCore);

imagefilledellipse($image, 272, 132, 24, 24, $accentOrange);
imagefilledellipse($image, 272, 132, 56, 56, $alloc(251, 146, 60, 92));

$white = $alloc(244, 248, 255);
$textBlue = $alloc(147, 197, 253);
$muted = $alloc(191, 219, 254);

imagettftext($image, 62, 0, 390, 228, $white, $fontBold, 'FirePhage');
imagettftext($image, 25, 0, 392, 276, $textBlue, $fontRegular, 'Managed Edge Security for WordPress');
imagettftext($image, 18, 0, 392, 324, $muted, $fontRegular, 'WAF · CDN · Cache · Origin · Analytics');

$line = $alloc(56, 189, 248, 46);
imageline($image, 392, 350, 980, 350, $line);

for ($i = 0; $i < 3; $i++) {
    $x = 1060 + ($i * 92);
    $y = 118 + ($i * 46);
    imagefilledellipse($image, $x, $y, 8, 8, $alloc(103, 232, 249, 30));
    imagefilledellipse($image, $x + 28, $y + 34, 8, 8, $alloc(56, 189, 248, 44));
}

imagepng($image, $output);
imagedestroy($image);

fwrite(STDOUT, $output.PHP_EOL);
