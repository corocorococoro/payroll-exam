#!/usr/bin/env -S uv run --with pillow python
"""Normalize mascot sprites to a shared visual scale and baseline."""

from __future__ import annotations

import argparse
from pathlib import Path

from PIL import Image


CANVAS_SIZE = 627
TARGET_HEIGHT = 570
MAX_WIDTH = 585
BASELINE = 612
ALPHA_THRESHOLD = 32


def visible_bounds(image: Image.Image) -> tuple[int, int, int, int]:
    alpha = image.getchannel("A")
    mask = alpha.point(lambda value: 255 if value > ALPHA_THRESHOLD else 0)
    bounds = mask.getbbox()
    if bounds is None:
        raise ValueError("image has no visible pixels")

    return bounds


def normalize(path: Path) -> None:
    with Image.open(path) as source:
        sprite = source.convert("RGBA")

    sprite = sprite.crop(visible_bounds(sprite))
    scale = min(TARGET_HEIGHT / sprite.height, MAX_WIDTH / sprite.width)
    width = round(sprite.width * scale)
    height = round(sprite.height * scale)
    sprite = sprite.resize((width, height), Image.Resampling.LANCZOS)

    canvas = Image.new("RGBA", (CANVAS_SIZE, CANVAS_SIZE), (0, 0, 0, 0))
    x = (CANVAS_SIZE - width) // 2
    y = BASELINE - height
    canvas.alpha_composite(sprite, (x, y))

    temporary = path.with_name(f"{path.stem}.normalized.webp")
    canvas.save(temporary, "WEBP", quality=88, method=6)
    temporary.replace(path)
    print(f"Normalized {path}")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("paths", nargs="*", type=Path)
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    paths = args.paths or sorted(Path("public/images/kyuchan").rglob("*.webp"))
    for path in paths:
        normalize(path)


if __name__ == "__main__":
    main()
