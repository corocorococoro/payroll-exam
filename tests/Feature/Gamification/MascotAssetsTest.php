<?php

use Illuminate\Support\Facades\File;

it('configures exactly two unique mascot styles for every relationship level', function () {
    $levels = collect(config('xp.levels'))->keyBy('level');
    $styles = collect(config('xp.styles'));

    expect($levels)->toHaveCount(10)
        ->and($styles)->toHaveCount(20)
        ->and($styles->pluck('slug')->unique())->toHaveCount(20)
        ->and($styles->pluck('name')->unique())->toHaveCount(20)
        ->and($styles->pluck('threshold')->all())->toBe([
            0, 50, 100, 175, 250, 375, 500, 700, 900, 1150,
            1400, 1750, 2100, 2500, 2900, 3400, 3900, 4550, 5200, 6000,
        ])
        ->and($styles->pluck('threshold')->unique())->toHaveCount(20);

    foreach ($levels as $level => $reward) {
        $levelStyles = $styles->where('level', $level)->values();
        $primaryStyle = $levelStyles->firstWhere('slug', $reward['style']);
        $bonusStyle = $levelStyles->firstWhere('slug', '!=', $reward['style']);
        $nextThreshold = $levels->get($level + 1)['threshold'] ?? null;

        expect($levelStyles)->toHaveCount(2)
            ->and($primaryStyle)->not->toBeNull()
            ->and($primaryStyle['name'])->toBe($reward['style_name'])
            ->and($primaryStyle['threshold'])->toBe((int) $reward['threshold'])
            ->and($bonusStyle)->not->toBeNull()
            ->and($bonusStyle['threshold'])->toBeGreaterThan((int) $reward['threshold']);

        if ($nextThreshold !== null) {
            expect($bonusStyle['threshold'])->toBeLessThan((int) $nextThreshold);
        }
    }
});

it('ships the default mascot and every reward style in all learning moods', function () {
    $moods = [
        'normal', 'happy', 'sad', 'cheer', 'wave', 'study', 'think', 'point',
        'calculate', 'rest', 'approve', 'clap', 'curious', 'confident', 'sleepy', 'write',
    ];
    $styles = collect(config('xp.styles'))
        ->pluck('slug')
        ->filter(fn (string $style): bool => $style !== 'default');
    $directories = collect(['default' => public_path('images/kyuchan')])
        ->merge($styles->mapWithKeys(
            fn (string $style): array => [$style => public_path("images/kyuchan/styles/{$style}")],
        ));
    $shippedStyles = collect(File::directories(public_path('images/kyuchan/styles')))
        ->map(fn (string $directory): string => basename($directory))
        ->sort()
        ->values();

    expect($styles)->toHaveCount(19)
        ->and($shippedStyles->all())->toBe($styles->sort()->values()->all());

    foreach ($directories as $style => $directory) {
        expect(File::files($directory))->toHaveCount(count($moods));

        foreach ($moods as $mood) {
            $path = "{$directory}/{$mood}.webp";

            expect(File::exists($path))->toBeTrue("Missing {$style} mascot asset: {$path}")
                ->and(File::size($path))->toBeLessThan(80 * 1024);

            $size = getimagesize($path);

            expect($size)->not->toBeFalse()
                ->and($size[0])->toBe(627)
                ->and($size[1])->toBe(627)
                ->and($size['mime'])->toBe('image/webp');

            $image = imagecreatefromwebp($path);
            $corner = imagecolorsforindex($image, imagecolorat($image, 0, 0));
            imagedestroy($image);

            expect($corner['alpha'])->toBe(127, "Opaque background in {$style} mascot asset: {$path}");
        }
    }
});
