<?php

use Illuminate\Support\Facades\File;

it('ships every mascot reward style in all learning moods', function () {
    $styles = collect(config('xp.levels'))
        ->pluck('style')
        ->filter(fn (?string $style): bool => $style !== null && $style !== 'default');

    expect($styles)->toHaveCount(4);

    foreach ($styles as $style) {
        foreach (['normal', 'happy', 'sad', 'cheer'] as $mood) {
            $path = public_path("images/kyuchan/styles/{$style}/{$mood}.webp");

            expect(File::exists($path))->toBeTrue("Missing mascot asset: {$path}")
                ->and(File::size($path))->toBeLessThan(80 * 1024);

            $size = getimagesize($path);

            expect($size)->not->toBeFalse()
                ->and($size[0])->toBe(627)
                ->and($size[1])->toBe(627)
                ->and($size['mime'])->toBe('image/webp');
        }
    }
});
