#!/usr/bin/env -S uv run --with pillow python
"""Slice a 4x4 chroma-key mascot sheet into the app's WebP mood assets."""

from __future__ import annotations

import argparse
import math
from pathlib import Path
from statistics import median

from normalize_mascot_assets import normalized_sprite
from PIL import Image, ImageChops, ImageFilter

CANVAS_SIZE = 627
MOODS = [
    "approve",
    "calculate",
    "cheer",
    "clap",
    "confident",
    "curious",
    "happy",
    "normal",
    "point",
    "rest",
    "sad",
    "sleepy",
    "study",
    "think",
    "wave",
    "write",
]
TRANSPARENT_CHANNEL_TOLERANCE = 28
OPAQUE_DISTANCE = 210
EDGE_RADIUS = 7


def border_key_color(image: Image.Image) -> tuple[int, int, int]:
    """Estimate the generated chroma-key color from the empty canvas border."""
    width, height = image.size
    samples: list[tuple[int, int, int]] = []

    for x in range(0, width, 4):
        samples.extend((image.getpixel((x, 0)), image.getpixel((x, height - 1))))
    for y in range(0, height, 4):
        samples.extend((image.getpixel((0, y)), image.getpixel((width - 1, y))))

    return tuple(round(median(channel)) for channel in zip(*samples, strict=True))


def despill_key(
    color: tuple[int, int, int],
    key: tuple[int, int, int],
) -> tuple[int, int, int]:
    """Neutralize residual magenta or green chroma-key spill."""
    red, green, blue = color

    if key[1] > key[0] * 1.4 and key[1] > key[2] * 1.4:
        excess = max(0, green - max(red, blue))

        return (
            min(255, red + round(excess * 0.15)),
            max(0, green - round(excess * 0.8)),
            min(255, blue + round(excess * 0.15)),
        )

    excess = max(0, min(red, blue) - green)

    return (
        max(0, red - round(excess * 0.5)),
        min(255, green + round(excess * 0.2)),
        max(0, blue - round(excess * 0.8)),
    )


def remove_chroma_key(image: Image.Image) -> Image.Image:
    """Recover alpha from a flat generated background and remove edge spill."""
    rgb = image.convert("RGB")
    key = border_key_color(rgb)
    channels = rgb.split()
    pure_background = ImageChops.multiply(
        ImageChops.multiply(
            channels[0].point(
                lambda value: (
                    255 if abs(value - key[0]) <= TRANSPARENT_CHANNEL_TOLERANCE else 0
                ),
            ),
            channels[1].point(
                lambda value: (
                    255 if abs(value - key[1]) <= TRANSPARENT_CHANNEL_TOLERANCE else 0
                ),
            ),
        ),
        channels[2].point(
            lambda value: (
                255 if abs(value - key[2]) <= TRANSPARENT_CHANNEL_TOLERANCE else 0
            ),
        ),
    )
    edge_neighborhood = pure_background.filter(
        ImageFilter.MaxFilter(EDGE_RADIUS * 2 + 1)
    )
    output = rgb.convert("RGBA")
    output.putalpha(ImageChops.invert(pure_background))
    source_pixels = rgb.load()
    output_pixels = output.load()
    background_pixels = pure_background.load()
    edge_pixels = edge_neighborhood.load()

    for y in range(rgb.height):
        for x in range(rgb.width):
            if background_pixels[x, y]:
                output_pixels[x, y] = (0, 0, 0, 0)
                continue

            if not edge_pixels[x, y]:
                continue

            red, green, blue = source_pixels[x, y]
            distance = math.sqrt(
                (red - key[0]) ** 2 + (green - key[1]) ** 2 + (blue - key[2]) ** 2,
            )

            if distance >= OPAQUE_DISTANCE:
                output_pixels[x, y] = (*despill_key((red, green, blue), key), 255)
                continue

            alpha = round(
                255 * distance / OPAQUE_DISTANCE,
            )
            if alpha == 0:
                output_pixels[x, y] = (0, 0, 0, 0)
                continue

            opacity = alpha / 255
            foreground = tuple(
                max(0, min(255, round((value - (1 - opacity) * key_value) / opacity)))
                for value, key_value in zip((red, green, blue), key, strict=True)
            )
            output_pixels[x, y] = (*despill_key(foreground, key), alpha)

    return output


def slice_sheet(input_path: Path, output_directory: Path) -> None:
    with Image.open(input_path) as source:
        sheet = remove_chroma_key(source)

    output_directory.mkdir(parents=True, exist_ok=True)
    width, height = sheet.size

    for index, mood in enumerate(MOODS):
        column = index % 4
        row = index // 4
        bounds = (
            round(column * width / 4),
            round(row * height / 4),
            round((column + 1) * width / 4),
            round((row + 1) * height / 4),
        )
        sprite = sheet.crop(bounds).resize(
            (CANVAS_SIZE, CANVAS_SIZE),
            Image.Resampling.LANCZOS,
        )
        sprite = normalized_sprite(sprite)
        path = output_directory / f"{mood}.webp"
        sprite.save(path, "WEBP", quality=88, method=6)
        print(f"Created {path}")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("input", type=Path)
    parser.add_argument("output_directory", type=Path)
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    slice_sheet(args.input, args.output_directory)


if __name__ == "__main__":
    main()
