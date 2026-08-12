<?php

use Illuminate\Support\Facades\File;

it('ships the default mascot and every reward style in all learning moods', function () {
    $styles = collect(config('xp.levels'))
        ->pluck('style')
        ->filter(fn (?string $style): bool => $style !== null && $style !== 'default');
    $directories = collect(['default' => public_path('images/kyuchan')])
        ->merge($styles->mapWithKeys(
            fn (string $style): array => [$style => public_path("images/kyuchan/styles/{$style}")],
        ));

    expect($styles)->toHaveCount(4);

    foreach ($directories as $style => $directory) {
        foreach (['normal', 'happy', 'sad', 'cheer', 'wave', 'study', 'think', 'point', 'calculate', 'rest'] as $mood) {
            $path = "{$directory}/{$mood}.webp";

            expect(File::exists($path))->toBeTrue("Missing {$style} mascot asset: {$path}")
                ->and(File::size($path))->toBeLessThan(80 * 1024);

            $size = getimagesize($path);

            expect($size)->not->toBeFalse()
                ->and($size[0])->toBe(627)
                ->and($size[1])->toBe(627)
                ->and($size['mime'])->toBe('image/webp');
        }
    }
});
