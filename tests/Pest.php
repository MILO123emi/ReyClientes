<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function jpegWithExif(int $width, int $height, int $orientation = 6): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, 0xCCCCCC);
    ob_start();
    imagejpeg($image, null, 90);
    $jpeg = (string) ob_get_clean();
    imagedestroy($image);

    $make = "ReyPass Test\0";
    $tiff = "II\x2a\0".pack('V', 8).pack('v', 2)
        .pack('vvVV', 0x010F, 2, strlen($make), 38)
        .pack('vvVv', 0x0112, 3, 1, $orientation)."\0\0"
        .pack('V', 0).$make;
    $exif = "Exif\0\0".$tiff;

    return substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
}

function pngHeaderOnly(int $width, int $height): string
{
    $ihdr = 'IHDR'.pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);

    return "\x89PNG\r\n\x1a\n".pack('N', 13).$ihdr.pack('N', crc32($ihdr));
}
