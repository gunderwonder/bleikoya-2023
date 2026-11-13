# terrain-tiles

Lager terrengflisene til 3D-kartet (`assets/tiles/terrain/{z}/{x}/{y}.png`) fra Kartverkets høydemodell.

Kilden er `DTM`-tjenesten på hoydedata.no, som for Oslo har laserdata fra 2025 med 0,25 m rutenett («Oslo byggesone 2025 30pkt»). `NHM_DTM_25833` hos samme tjeneste er en landsdekkende mosaikk med 1 m rutenett, og gir altså lavere oppløsning.

## Bruk

```bash
cd scripts/terrain-tiles
uv sync
uv run generate.py
```

Det tar noen minutter første gang. Nedlastede GeoTIFF-er caches i `cache/` (ca. 100 MB, ikke i git). Slett mappa for å hente ferske data.

Flagg: `--min-zoom` (14), `--max-zoom` (19), `--out`, `--cache`, `--resolution` (0.25).

## Hva skriptet gjør

1. Laster ned DTM i 1000 m-ruter rundt øya, i originalprojeksjonen (UTM32) og på originalrutenettet. Da slipper vi resampling på serveren.
2. Finner Bleikøya som sammenhengende land rundt `SEEDS`. Alt annet settes til 0 (sjø, naboøyer, fastland). Uten masken blir flisene rundt seks ganger større.
3. Reprojiserer til Web Mercator på flisrutenettet for zoom 19 (ca. 0,15 m per piksel), og lager lavere zoom ved å ta snittet av 2x2 høyder.
4. Skriver PNG med høyden pakket i RGB, bare for fliser innenfor `MAP_BOUNDS`. Formatet er som Mapbox Terrain-RGB, men med 1 cm trinn i stedet for 10 cm. Med grovere trinn får terrengskyggen i kartet trappetrinn.

## Ting å vite

- Tre ting må stemme med `kartverketDemSource()` i `assets/js/map-page.js`: `MAP_BOUNDS` (`bounds`), `--max-zoom` (`maxzoom`) og `STEP` (`redFactor`, `greenFactor` og `blueFactor` er 65536, 256 og 1 ganger `STEP`).
- Endrer du `STEP` (eller annet ved kodingen), øk `?v=` i flis-URL-en i `kartverketDemSource()`. Nettlesere beholder gamle fliser i ukevis, og gamle fliser lest med nye faktorer gir terreng på minus 9000 meter.
- Serveren svarer av og til 500 på hele ruter (4000x4000 px). Skriptet henter da ruta som fire mindre biter. Tjenesten kan også være helt nede i perioder. Da er det bare å prøve igjen senere.
- Høydedata fra Kartverket er lisensiert CC BY 4.0. Kartet krediterer Kartverket i attribusjonen.
