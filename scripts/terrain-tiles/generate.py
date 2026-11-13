"""Lag Terrain-RGB-tiles for MapLibre 3D fra Kartverkets høydemodell (DTM, 0,25 m).

Henter DTM som GeoTIFF fra hoydedata.no (EPSG:25832), reprojiserer til
EPSG:3857 på nøyaktig tile-rutenett for høyeste zoom, bygger lavere zoom ved å
ta gjennomsnitt av 2x2 høydeverdier, og skriver PNG med høyden pakket i RGB
(som Mapbox Terrain-RGB, men med 1 cm trinn): høyde = (R*65536 + G*256 + B) * 0.01 - 10000
"""

import argparse
import math
import shutil
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

import numpy as np
import rasterio
from PIL import Image
from rasterio.crs import CRS
from rasterio.transform import Affine, from_origin
from rasterio.warp import Resampling, reproject, transform, transform_bounds
from scipy import ndimage

HERE = Path(__file__).parent
DEFAULT_OUT = HERE.parent.parent / "assets" / "tiles" / "terrain"
DEFAULT_CACHE = HERE / "cache"

SERVICE = "https://hoydedata.no/arcgis/rest/services/DTM/ImageServer/exportImage"
USER_AGENT = "terrain-tiles/0.1 (python-urllib)"
CHUNK_M = 1000          # kantlengde på nedlastingsbit, meter
MARGIN_M = 10           # margin rundt hver blokk ved lesing av kilden
DELAY_S = 1.0           # pause mellom forespørsler
RETRIES = 5

# Tile-rektangelet defineres på z14. Dypere zoom dekker barna av dette.
BASE_ZOOM = 14
BASE_X = (8679, 8681)
BASE_Y = (4767, 4768)

# Bare Bleikøya får terreng. Alt annet (sjø, naboøyer, fastland) blir flatt 0,
# ellers blir flisene mange ganger større. Øya finnes som sammenhengende land
# rundt SEEDS innenfor ISLAND_AREA (lon/lat: vest, sør, øst, nord).
SEEDS = [(10.7373, 59.8883)]
ISLAND_AREA = (10.7285, 59.8850, 10.7508, 59.8940)
MASK_RES = 2.0          # meter per piksel i masken
MASK_LAND_M = 0.2       # høyde som regnes som land
MASK_GROW_M = 6.0       # utvid masken så strandsonen kommer med

# Samme utsnitt som «bounds» på terrengkilden i assets/js/map-page.js.
# Tiles utenfor blir aldri hentet av kartet og skrives derfor ikke.
MAP_BOUNDS = (10.715, 59.875, 10.76, 59.90)

TILE = 256
R_EARTH = 6378137.0
ORIGIN = math.pi * R_EARTH
# Laserdataene for Oslo ligger i UTM32 hos Kartverket. Ved å be om samme
# projeksjon og rutenett får vi originalpikslene, uten resampling på serveren.
SRC_EPSG = 25832
SRC_CRS = CRS.from_epsg(SRC_EPSG)
DST_CRS = CRS.from_epsg(3857)


def tile_range(z: int) -> tuple[int, int, int, int]:
    f = 2 ** (z - BASE_ZOOM)
    return BASE_X[0] * f, (BASE_X[1] + 1) * f - 1, BASE_Y[0] * f, (BASE_Y[1] + 1) * f - 1


def tiles_in_map_bounds(z: int) -> tuple[int, int, int, int]:
    """Tile-intervall (x0, x1, y0, y1) som berører MAP_BOUNDS på zoom z."""
    w, s, e, n = MAP_BOUNDS
    k = 2 ** z

    def ty(lat):
        return (1 - math.asinh(math.tan(math.radians(lat))) / math.pi) / 2 * k

    return (math.floor((w + 180) / 360 * k), math.floor((e + 180) / 360 * k),
            math.floor(ty(n)), math.floor(ty(s)))


def tile_bounds_3857(z: int, x0: int, x1: int, y0: int, y1: int):
    """Bounds (west, south, east, north) for et rektangel av tiles."""
    size = 2 * ORIGIN / 2 ** z
    return (
        -ORIGIN + x0 * size,
        ORIGIN - (y1 + 1) * size,
        -ORIGIN + (x1 + 1) * size,
        ORIGIN - y0 * size,
    )


# --- Nedlasting --------------------------------------------------------------

def chunk_path(cache: Path, cx: int, cy: int, res: float) -> Path:
    return cache / f"dtm_{SRC_EPSG}_{res}_{cx}_{cy}.tif"


def fetch(cx: int, cy: int, size_m: int, res: float) -> bytes:
    """Hent ett utsnitt som GeoTIFF-bytes, med nye forsøk ved feil."""
    n = round(size_m / res)
    params = {
        "bbox": f"{cx},{cy},{cx + size_m},{cy + size_m}",
        "bboxSR": str(SRC_EPSG),
        "imageSR": str(SRC_EPSG),
        "size": f"{n},{n}",
        "format": "tiff",
        "pixelType": "F32",
        "interpolation": "RSP_NearestNeighbor",
        "f": "image",
    }
    url = SERVICE + "?" + urllib.parse.urlencode(params)
    last = None
    for attempt in range(1, RETRIES + 1):
        try:
            req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
            with urllib.request.urlopen(req, timeout=300) as resp:
                data = resp.read()
            if data[:4] not in (b"II*\x00", b"MM\x00*", b"II+\x00", b"MM\x00+"):
                raise RuntimeError(f"ikke GeoTIFF: {data[:200]!r}")
            time.sleep(DELAY_S)
            return data
        except (urllib.error.URLError, TimeoutError, RuntimeError, ConnectionError) as e:
            last = e
            wait = 2 ** attempt
            print(f"  forsøk {attempt} feilet for {cx},{cy} ({size_m} m): {e}; venter {wait} s")
            time.sleep(wait)
    raise RuntimeError(f"ga opp {cx},{cy} ({size_m} m): {last}")


def download_chunk(cache: Path, cx: int, cy: int, res: float) -> Path:
    """Last ned bit med sørvest-hjørne (cx, cy) i EPSG:25832, hvis den ikke er cachet.

    Serveren svarer av og til 500 på hele biter (4000x4000 px). Da hentes
    biten som fire kvadranter og settes sammen lokalt.
    """
    path = chunk_path(cache, cx, cy, res)
    if path.exists():
        return path
    cache.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(".part")
    try:
        tmp.write_bytes(fetch(cx, cy, CHUNK_M, res))
    except RuntimeError as e:
        print(f"  {e}; prøver fire kvadranter")
        half = CHUNK_M // 2
        n = round(CHUNK_M / res)
        h = n // 2
        arr = np.zeros((n, n), dtype=np.float32)
        profile = None
        for dx in (0, 1):
            for dy in (0, 1):
                try:
                    data = fetch(cx + dx * half, cy + dy * half, half, res)
                except RuntimeError as e2:
                    raise SystemExit(str(e2))
                with rasterio.MemoryFile(data) as mem, mem.open() as ds:
                    q, _ = clean_heights(ds.read(1), ds.nodata)
                    profile = ds.profile
                # rad 0 er nord: dy=1 (nordlig kvadrant) havner øverst
                arr[(1 - dy) * h:(2 - dy) * h, dx * h:(dx + 1) * h] = q
        profile.update(width=n, height=n, nodata=None, compress="deflate",
                       transform=from_origin(cx, cy + CHUNK_M, res, res))
        with rasterio.open(tmp, "w", **profile) as dst:
            dst.write(arr, 1)
    tmp.rename(path)
    print(f"  lastet ned {path.name} ({path.stat().st_size / 1e6:.1f} MB)")
    return path


def chunks_for_bounds(bounds, margin=0.0):
    w, s, e, n = bounds
    x0 = math.floor((w - margin) / CHUNK_M) * CHUNK_M
    x1 = math.floor((e + margin) / CHUNK_M) * CHUNK_M
    y0 = math.floor((s - margin) / CHUNK_M) * CHUNK_M
    y1 = math.floor((n + margin) / CHUNK_M) * CHUNK_M
    return [(x, y) for x in range(x0, x1 + 1, CHUNK_M) for y in range(y0, y1 + 1, CHUNK_M)]


def clean_heights(a: np.ndarray, nodata) -> tuple[np.ndarray, np.ndarray]:
    """Gi (høyder med nodata/absurde verdier = 0 og negative klippet til 0, maske over ugyldige)."""
    a = a.astype(np.float32, copy=False)
    bad = ~np.isfinite(a) | (a < -100) | (a > 1000)
    if nodata is not None and np.isfinite(nodata):
        bad |= a == np.float32(nodata)
    out = np.where(bad, 0, a).astype(np.float32)
    np.maximum(out, 0, out=out)
    return out, bad


def mosaic(cache: Path, bounds, res: float, stats: dict | None = None) -> tuple[np.ndarray, Affine]:
    """Sett sammen DTM for bounds (25832) på 0,25 m-rutenett. Ugyldig/manglende = 0."""
    w, s, e, n = bounds
    w, s = math.floor(w / res) * res, math.floor(s / res) * res
    e, n = math.ceil(e / res) * res, math.ceil(n / res) * res
    width, height = round((e - w) / res), round((n - s) / res)
    arr = np.zeros((height, width), dtype=np.float32)
    covered = np.zeros((height, width), dtype=bool)
    transform = from_origin(w, n, res, res)
    for cx, cy in chunks_for_bounds((w, s, e, n)):
        path = chunk_path(cache, cx, cy, res)
        if not path.exists():
            continue  # utenfor ISLAND_AREA: ikke lastet ned, blir 0
        with rasterio.open(path) as ds:
            t = ds.transform
            col0 = round((t.c - w) / res)
            row0 = round((n - t.f) / res)
            # utsnitt som overlapper mosaikken
            r0, c0 = max(row0, 0), max(col0, 0)
            r1, c1 = min(row0 + ds.height, height), min(col0 + ds.width, width)
            if r1 <= r0 or c1 <= c0:
                continue
            win = rasterio.windows.Window(c0 - col0, r0 - row0, c1 - c0, r1 - r0)
            data, bad = clean_heights(ds.read(1, window=win), ds.nodata)
            arr[r0:r1, c0:c1] = data
            covered[r0:r1, c0:c1] = ~bad
    if stats is not None:
        stats["pixels"] += arr.size
        stats["missing"] += int((~covered).sum())
    return arr, transform


def island_bounds() -> tuple[float, float, float, float]:
    """ISLAND_AREA i EPSG:25832, rundet utover til MASK_RES-rutenettet."""
    w, s, e, n = transform_bounds(CRS.from_epsg(4326), SRC_CRS, *ISLAND_AREA, densify_pts=21)
    m = MASK_RES
    return math.floor(w / m) * m, math.floor(s / m) * m, math.ceil(e / m) * m, math.ceil(n / m) * m


class IslandMask:
    """Maske (MASK_RES m) over land som henger sammen med SEEDS."""

    def __init__(self, cache: Path, res: float, stats: dict):
        self.w, self.s, self.e, self.n = island_bounds()
        arr, _ = mosaic(cache, (self.w, self.s, self.e, self.n), res, stats)
        k = round(MASK_RES / res)
        coarse = arr.reshape(arr.shape[0] // k, k, arr.shape[1] // k, k).max(axis=(1, 3))
        labels, _ = ndimage.label(coarse > MASK_LAND_M)
        xs, ys = transform(CRS.from_epsg(4326), SRC_CRS, [p[0] for p in SEEDS], [p[1] for p in SEEDS])
        keep = set()
        for x, y in zip(xs, ys):
            lab = labels[int((self.n - y) / MASK_RES), int((x - self.w) / MASK_RES)]
            if lab == 0:
                raise SystemExit(f"Startpunkt {x:.0f},{y:.0f} ligger ikke på land")
            keep.add(lab)
        mask = np.isin(labels, list(keep))
        edge = mask[0].any() or mask[-1].any() or mask[:, 0].any() or mask[:, -1].any()
        if edge:
            raise SystemExit("Øya når kanten av ISLAND_AREA. Utvid området.")
        self.mask = ndimage.binary_dilation(mask, iterations=round(MASK_GROW_M / MASK_RES))
        print(f"Maske: {mask.sum() * MASK_RES ** 2 / 1e4:.1f} ha land")

    def intersects(self, bounds) -> bool:
        w, s, e, n = bounds
        return w < self.e and e > self.w and s < self.n and n > self.s

    def apply(self, arr: np.ndarray, tf: Affine) -> None:
        """Nullstill alt i arr (nord opp, transform tf) som ligger utenfor masken."""
        h, w = arr.shape
        cols = np.floor((tf.c + (np.arange(w) + 0.5) * tf.a - self.w) / MASK_RES).astype(int)
        rows = np.floor((self.n - (tf.f + (np.arange(h) + 0.5) * tf.e)) / MASK_RES).astype(int)
        cok = (cols >= 0) & (cols < self.mask.shape[1])
        rok = (rows >= 0) & (rows < self.mask.shape[0])
        m = self.mask[np.ix_(rows.clip(0, self.mask.shape[0] - 1), cols.clip(0, self.mask.shape[1] - 1))]
        m &= rok[:, None] & cok[None, :]
        arr[~m] = 0


# --- Pyramide og koding -------------------------------------------------------

def downsample(a: np.ndarray) -> np.ndarray:
    return a.reshape(a.shape[0] // 2, 2, a.shape[1] // 2, 2).mean(axis=(1, 3), dtype=np.float32)


STEP = 0.01             # høydetrinn i meter, se encode()
BASE_SHIFT = 10000.0


def encode(h: np.ndarray) -> np.ndarray:
    """Pakk høyder i RGB: høyde = (R*65536 + G*256 + B) * STEP - BASE_SHIFT.

    Med STEP = 0.1 er dette Mapbox Terrain-RGB. Vi bruker finere trinn fordi
    skyggeleggingen i kartet ellers får trappetrinn. MapLibre leser det som
    encoding «custom» med redFactor = 65536*STEP, greenFactor = 256*STEP,
    blueFactor = STEP og baseShift = BASE_SHIFT (se assets/js/map-page.js).
    """
    v = np.rint((h.astype(np.float64) + BASE_SHIFT) / STEP).astype(np.uint32)
    rgb = np.empty(h.shape + (3,), dtype=np.uint8)
    rgb[..., 0] = (v >> 16) & 255
    rgb[..., 1] = (v >> 8) & 255
    rgb[..., 2] = v & 255
    return rgb


class Writer:
    def __init__(self, tmp: Path, minz: int, maxz: int):
        self.tmp, self.minz, self.maxz = tmp, minz, maxz
        self.count = {z: 0 for z in range(minz, maxz + 1)}
        self.bytes = {z: 0 for z in range(minz, maxz + 1)}
        self.hmin, self.hmax = math.inf, -math.inf

    def write_level(self, z: int, arr: np.ndarray, x0: int, y0: int):
        """Skriv alle tiles i arr, der øvre venstre tile er (x0, y0) på zoom z."""
        if not (self.minz <= z <= self.maxz):
            return
        self.hmin = min(self.hmin, float(arr.min()))
        self.hmax = max(self.hmax, float(arr.max()))
        mx0, mx1, my0, my1 = tiles_in_map_bounds(z)
        for ty in range(arr.shape[0] // TILE):
            for tx in range(arr.shape[1] // TILE):
                if not (mx0 <= x0 + tx <= mx1 and my0 <= y0 + ty <= my1):
                    continue
                block = arr[ty * TILE:(ty + 1) * TILE, tx * TILE:(tx + 1) * TILE]
                p = self.tmp / str(z) / str(x0 + tx) / f"{y0 + ty}.png"
                p.parent.mkdir(parents=True, exist_ok=True)
                Image.fromarray(encode(block), "RGB").save(p, optimize=True)
                self.count[z] += 1
                self.bytes[z] += p.stat().st_size


def render_block(src, src_tf, z: int, bx0, bx1, by0, by1, resampling=Resampling.bilinear):
    """Reprojiser til EPSG:3857 på tile-rutenettet for z, for blokken av tiles."""
    w, s, e, n = tile_bounds_3857(z, bx0, bx1, by0, by1)
    width, height = (bx1 - bx0 + 1) * TILE, (by1 - by0 + 1) * TILE
    dst = np.zeros((height, width), dtype=np.float32)
    dst_tf = Affine((e - w) / width, 0, w, 0, -(n - s) / height, n)
    reproject(
        src, dst,
        src_transform=src_tf, src_crs=SRC_CRS,
        dst_transform=dst_tf, dst_crs=DST_CRS,
        resampling=resampling,
    )
    np.maximum(dst, 0, out=dst)
    return dst


def swap_in(tmp: Path, out: Path, zooms):
    out.mkdir(parents=True, exist_ok=True)
    old = out.parent / (out.name + ".old")
    if old.exists():
        shutil.rmtree(old)
    old.mkdir()
    for z in zooms:
        if (out / str(z)).exists():
            (out / str(z)).rename(old / str(z))
        (tmp / str(z)).rename(out / str(z))
    shutil.rmtree(old)
    shutil.rmtree(tmp)


def main():
    ap = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    ap.add_argument("--min-zoom", type=int, default=14)
    ap.add_argument("--max-zoom", type=int, default=19)
    ap.add_argument("--out", type=Path, default=DEFAULT_OUT)
    ap.add_argument("--cache", type=Path, default=DEFAULT_CACHE)
    ap.add_argument("--resolution", type=float, default=0.25)
    a = ap.parse_args()
    minz, maxz, res = a.min_zoom, a.max_zoom, a.resolution
    if minz < 0 or minz > maxz:
        raise SystemExit("Ugyldig zoom-intervall")

    # Blokkzoom: nok til at hver blokk får plass i minnet (z15 -> 4096 px ved z19).
    bz = min(15, maxz)
    bx0, bx1, by0, by1 = tile_range(bz)
    blocks = [(x, y) for y in range(by0, by1 + 1) for x in range(bx0, bx1 + 1)]
    # Blokker er alltid et helt antall tiles ved bz; ved bz < 14 er rektangelet avrundet.

    # 1. Finn og last ned nødvendige biter (bare området rundt øya).
    needed = set(chunks_for_bounds(island_bounds()))
    block_src_bounds = {}
    for bx, by in blocks:
        b = tile_bounds_3857(bz, bx, bx, by, by)
        sb = transform_bounds(DST_CRS, SRC_CRS, *b, densify_pts=21)
        block_src_bounds[(bx, by)] = sb
    print(f"{len(blocks)} blokker, {len(needed)} biter à {CHUNK_M} m")
    for cx, cy in sorted(needed):
        download_chunk(a.cache, cx, cy, res)

    # 2. Reprojiser og skriv.
    tmp = a.out.parent / (a.out.name + ".tmp")
    if tmp.exists():
        shutil.rmtree(tmp)
    tmp.mkdir(parents=True)
    wr = Writer(tmp, minz, maxz)
    stats = {"pixels": 0, "missing": 0}
    island = IslandMask(a.cache, res, stats)
    full = np.zeros(((by1 - by0 + 1) * TILE, (bx1 - bx0 + 1) * TILE), dtype=np.float32)
    for i, (bx, by) in enumerate(blocks, 1):
        w, s, e, n = block_src_bounds[(bx, by)]
        f = 2 ** (maxz - bz)
        if island.intersects((w, s, e, n)):
            src, src_tf = mosaic(a.cache, (w - MARGIN_M, s - MARGIN_M, e + MARGIN_M, n + MARGIN_M), res)
            island.apply(src, src_tf)
            # Alle tiles i blokken ved maxz
            arr = render_block(src, src_tf, maxz, bx * f, (bx + 1) * f - 1, by * f, (by + 1) * f - 1)
            del src
        else:
            arr = np.zeros((f * TILE, f * TILE), dtype=np.float32)
        z = maxz
        x0, y0 = bx * f, by * f
        while True:
            wr.write_level(z, arr, x0, y0)
            if z == bz:
                break
            arr = downsample(arr)
            z -= 1
            x0, y0 = x0 // 2, y0 // 2
        full[(by - by0) * TILE:(by - by0 + 1) * TILE, (bx - bx0) * TILE:(bx - bx0 + 1) * TILE] = arr
        print(f"blokk {i}/{len(blocks)} ferdig (z{bz} {bx}/{by})")

    # Lavere zoom fra samlet bz-nivå (pad til partall tiles ved behov).
    z, x0, y0 = bz, bx0, by0
    while z > minz:
        if x0 % 2:
            full = np.pad(full, ((0, 0), (TILE, 0)), mode="edge")
            x0 -= 1
        if y0 % 2:
            full = np.pad(full, ((TILE, 0), (0, 0)), mode="edge")
            y0 -= 1
        if (full.shape[1] // TILE) % 2:
            full = np.pad(full, ((0, 0), (0, TILE)), mode="edge")
        if (full.shape[0] // TILE) % 2:
            full = np.pad(full, ((0, TILE), (0, 0)), mode="edge")
        full = downsample(full)
        # full er nå 2x2 piksler mindre; tiles ved z-1 er fortsatt 256 px
        z -= 1
        x0, y0 = x0 // 2, y0 // 2
        wr.write_level(z, full, x0, y0)

    swap_in(tmp, a.out, range(minz, maxz + 1))

    # 3. Oppsummering.
    print("\nZoom  Tiles      Bytes")
    for z in range(minz, maxz + 1):
        print(f"{z:>4}  {wr.count[z]:>6}  {wr.bytes[z]:>10}")
    print(f"Sum   {sum(wr.count.values()):>6}  {sum(wr.bytes.values()):>10}")
    print(f"Høyde: min {wr.hmin:.2f} m, maks {wr.hmax:.2f} m")
    pct = 100 * stats["missing"] / max(stats["pixels"], 1)
    print(f"Området rundt øya uten gyldige kildedata (inkl. sjø): {pct:.1f} %")


if __name__ == "__main__":
    main()
