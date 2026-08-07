<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The three category icons the purchased theme does not ship
|--------------------------------------------------------------------------
|
| The theme ships six part icons (public/assets/images/categories/categories-01..06):
| a brake pad, a battery, an alloy wheel, a tyre, a headlight and an air filter.
| Everything from categories-07 upwards is a grey box with its own dimensions
| printed on it — a placeholder, not artwork. The shop has eight departments, so
| three of them had nothing to show and were rendering "98X96" and "184X120".
|
| These three are drawn to match the theme's own icons rather than sourced from
| elsewhere: the same #F73312, the same ~4px stroke, the same 70px box, flat line
| art with a transparent background. Drawn at 4x and downsampled, which is what
| gives the strokes the same soft edge as the originals.
|
| Committed as a script rather than run once and forgotten, so the assets can be
| explained and regenerated instead of being three mystery PNGs in the tree.
|
| Run:  php scripts/generate-category-icons.php
*/

const SCALE = 4;
const SIZE = 70;
const STROKE = 4;

$target = dirname(__DIR__).'/public/app/images/categories';

if (! is_dir($target) && ! mkdir($target, 0o755, true)) {
    fwrite(STDERR, "Could not create {$target}\n");
    exit(2);
}

foreach (['engine' => 'piston', 'suspension' => 'coilSpring', 'interior' => 'carSeat'] as $slug => $drawing) {
    $canvas = canvas();
    $drawing($canvas, colour($canvas));

    $file = $target.'/'.$slug.'.png';
    imagepng(downsample($canvas), $file);
    echo "wrote {$file}\n";
}

exit(0);

/** A transparent 4x canvas. Alpha blending off, so the drawn colour is kept exactly. */
function canvas(): GdImage
{
    $image = imagecreatetruecolor(SIZE * SCALE, SIZE * SCALE);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagealphablending($image, true);
    imagesetthickness($image, STROKE * SCALE);

    return $image;
}

/** The theme's orange, read off its own icons rather than guessed. */
function colour(GdImage $image): int
{
    return imagecolorallocate($image, 0xF7, 0x33, 0x12);
}

/**
 * Down to 70px with resampling.
 *
 * The theme's icons are anti-aliased raster art. GD draws hard-edged pixels, so
 * drawing at 4x and shrinking is what matches them — done the other way round the
 * icon looks jagged beside the six it sits with.
 */
function downsample(GdImage $source): GdImage
{
    $out = imagecreatetruecolor(SIZE, SIZE);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $source, 0, 0, 0, 0, SIZE, SIZE, imagesx($source), imagesy($source));

    return $out;
}

/** Engine — a piston: crown with two ring grooves, rod, and the big end. */
function piston(GdImage $im, int $c): void
{
    imagerectangle($im, 44, 14, 236, 148, $c);      // crown
    imagesetthickness($im, 12);
    imageline($im, 72, 62, 208, 62, $c);            // upper ring groove
    imageline($im, 72, 104, 208, 104, $c);          // lower ring groove
    imagesetthickness($im, STROKE * SCALE);

    imageline($im, 140, 148, 140, 186, $c);         // connecting rod
    // imagearc, not imageellipse: GD's ellipse ignores the pen thickness, so the big end
    // came out hairline-thin next to every other stroke.
    imagearc($im, 140, 230, 92, 92, 0, 360, $c);    // big end
}

/** Suspension — a coil spring between its two mount plates. */
function coilSpring(GdImage $im, int $c): void
{
    imageline($im, 34, 18, 246, 18, $c);             // top mount
    imageline($im, 34, 262, 246, 262, $c);           // bottom mount

    $left = 48;
    $right = 232;

    for ($i = 0; $i < 3; $i++) {
        $y = 54 + $i * 66;
        imageline($im, $left, $y, $right, $y + 33, $c);
        imageline($im, $right, $y + 33, $left, $y + 66, $c);
    }
}

/** Interior — a car seat in profile: headrest, backrest, cushion. */
function carSeat(GdImage $im, int $c): void
{
    imageline($im, 72, 20, 152, 14, $c);            // headrest

    /*
     | ONE closed outline, not a backrest shape plus a cushion shape. Drawn as two separate
     | rectangles the icon read as two unrelated objects stacked up — a battery over a bar —
     | because at 70px the gap between them is wider than the strokes around them.
    */
    imagepolygon($im, [
        62, 50,     // top of the backrest, leaning back
        148, 42,
        162, 198,   // where the backrest meets the cushion
        264, 206,
        264, 264,   // front edge of the cushion
        74, 264,
    ], $c);
}
