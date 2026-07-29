"""Lag en placeholder Vipps-aktig logo (orange avrundet kvadrat med hvit smile).

Brukes hvis du ikke har den offisielle logoen tilgjengelig. Output:
assets/vipps-logo.png (512x512, RGBA).
"""

from pathlib import Path
from PIL import Image, ImageDraw

HERE = Path(__file__).parent
OUT = HERE / "assets" / "vipps-logo.png"

VIPPS_ORANGE = (255, 91, 36, 255)
WHITE = (255, 255, 255, 255)


def make(size: int = 512) -> None:
    img = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)

    radius = int(size * 0.18)
    d.rounded_rectangle((0, 0, size - 1, size - 1), radius=radius, fill=VIPPS_ORANGE)

    eye_r = int(size * 0.045)
    eye_y = int(size * 0.40)
    left_eye_x = int(size * 0.36)
    right_eye_x = int(size * 0.64)
    d.ellipse((left_eye_x - eye_r, eye_y - eye_r, left_eye_x + eye_r, eye_y + eye_r), fill=WHITE)
    d.ellipse((right_eye_x - eye_r, eye_y - eye_r, right_eye_x + eye_r, eye_y + eye_r), fill=WHITE)

    smile_box = (
        int(size * 0.30),
        int(size * 0.42),
        int(size * 0.70),
        int(size * 0.78),
    )
    d.arc(smile_box, start=15, end=165, fill=WHITE, width=int(size * 0.08))

    OUT.parent.mkdir(parents=True, exist_ok=True)
    img.save(OUT)
    print(f"Skrev placeholder-logo til {OUT}")


if __name__ == "__main__":
    make()
