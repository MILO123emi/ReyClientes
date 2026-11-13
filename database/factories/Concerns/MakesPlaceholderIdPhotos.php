<?php

namespace Database\Factories\Concerns;

use App\Enums\PhotoOwner;

trait MakesPlaceholderIdPhotos
{
    protected function placeholderIdPhoto(?PhotoOwner $owner = null): string
    {
        if ($owner !== null) {
            $group = $owner === PhotoOwner::Client ? 'owner' : 'authorized';
            $paths = glob(database_path('seeders/assets/id-photos/'.$group.'-*.png')) ?: [];
            if ($paths !== []) {
                $contents = file_get_contents(fake()->randomElement($paths));
                if ($contents !== false) {
                    return $contents;
                }
            }
        }

        $canvas = imagecreatetruecolor(1000, 630);
        imagefilledrectangle($canvas, 0, 0, 999, 629, 0xEEEEEE);
        imagerectangle($canvas, 20, 20, 979, 609, 0x999999);

        $text = imagecreatetruecolor(220, 80);
        imagefilledrectangle($text, 0, 0, 219, 79, 0xEEEEEE);
        $title = 'CEDULA DE PRUEBA';
        $titleX = (int) ((220 - imagefontwidth(5) * strlen($title)) / 2);
        imagestring($text, 5, $titleX, 15, $title, 0x444444);
        imageline($text, $titleX + imagefontwidth(5) + 3, 12, $titleX + imagefontwidth(5) + 7, 8, 0x444444);

        $number = (string) random_int(1000, 9999);
        imagestring($text, 5, (int) ((220 - imagefontwidth(5) * 4) / 2), 48, $number, 0x666666);
        imagecopyresampled($canvas, $text, 60, 155, 0, 0, 880, 320, 220, 80);

        ob_start();
        imagejpeg($canvas, null, 90);
        $jpeg = (string) ob_get_clean();
        imagedestroy($text);
        imagedestroy($canvas);

        return $jpeg;
    }
}
