"""Generer en stylisert QR-kode med valgfri logo i midten."""

import argparse
from pathlib import Path

import qrcode
from qrcode.constants import ERROR_CORRECT_H
from PIL import Image

HERE = Path(__file__).parent
DEFAULT_LOGO = HERE / "assets" / "vipps-logo.png"
DEFAULT_OUT = HERE / "output" / "qr.png"


def generate(
    data: str,
    out_path: Path,
    logo_path: Path | None = None,
    size: int = 1000,
    logo_ratio: float = 0.22,
) -> None:
    qr = qrcode.QRCode(
        error_correction=ERROR_CORRECT_H,
        box_size=10,
        border=4,
    )
    qr.add_data(data)
    qr.make(fit=True)

    img = qr.make_image(fill_color="black", back_color="white").convert("RGBA")
    img = img.resize((size, size), Image.Resampling.LANCZOS)

    if logo_path and logo_path.exists():
        logo = Image.open(logo_path).convert("RGBA")
        logo_size = int(size * logo_ratio)
        logo = logo.resize((logo_size, logo_size), Image.Resampling.LANCZOS)
        pos = ((size - logo_size) // 2, (size - logo_size) // 2)
        img.paste(logo, pos, logo)

    out_path.parent.mkdir(parents=True, exist_ok=True)
    img.convert("RGB").save(out_path)
    print(f"Skrev {out_path} ({size}x{size}px) — kodet: {data!r}")


def main() -> None:
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument("--data", required=True, help="Strengen QR-koden skal kode")
    p.add_argument("--out", type=Path, default=DEFAULT_OUT, help=f"Output-sti (default: {DEFAULT_OUT})")
    p.add_argument("--logo", type=Path, default=DEFAULT_LOGO, help=f"Logo-sti (default: {DEFAULT_LOGO})")
    p.add_argument("--no-logo", action="store_true", help="Hopp over logo")
    p.add_argument("--size", type=int, default=1000, help="Pikselstørrelse (default: 1000)")
    p.add_argument("--logo-ratio", type=float, default=0.22, help="Logoens andel av QR-bredden (default: 0.22)")
    args = p.parse_args()

    logo = None if args.no_logo else args.logo
    generate(args.data, args.out, logo, args.size, args.logo_ratio)


if __name__ == "__main__":
    main()
