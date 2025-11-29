# Historiske flybilder for Bleikøya-kartet

## Bakgrunn
Ønske om å legge til historiske satellitt-/flybilder i kartet på bleikoya.net, med mulighet for å velge mellom spesifikke årstall. Dekning ønskes så langt tilbake som mulig (fra 1935).

## Alternativer

### 1. Kartverket bestilling (1935+)
- **Pris**: 1 950 kr + mva per skannet/digitalisert bilde
- **Dekning**: Fra 1935 og fremover
- **Prosess**:
  1. Send forespørsel til Kartverket
  2. Arkivsøk utføres
  3. Gjennomgang av tilgjengelige bilder
  4. Bindende ordre
- **Format**: Enkeltbilder (ikke API-tilgang)
- **Implementering**: Kan brukes som distortable image overlays (støtte finnes allerede i kartet)
- **Kontakt**: https://www.kartverket.no/til-lands/flyfoto-og-historiske-kart

### 2. Norge i bilder / Norgeibilder.no (gratis WMTS)
- **Pris**: Gratis
- **Dekning**: Varierer, har historiske ortofoto for mange områder
- **Tilgang**: Åpen WMTS-tjeneste, ingen medlemskap nødvendig
- **WMTS-URL**: https://waapi.webatlas.no/maptiles/tiles/webatlas-orto-newup/wa_grid/{z}/{x}/{y}.jpeg
- **Begrensning**: Kan mangle eldre bilder for Bleikøya-området
- **Sjekk tilgjengelighet**: https://norgeibilder.no

### 3. Google Earth Engine Timelapse (1984+)
- **Pris**: Gratis
- **Dekning**: Satellittbilder fra 1984
- **Oppløsning**: Lavere enn flyfoto (Landsat/Sentinel)
- **Tilgang**: Kan embeddes eller lenkes til
- **URL**: https://earthengine.google.com/timelapse/
- **Implementering**: Enkel lenke eller iframe, ikke direkte kartintegrasjon

### 4. Norkart API (kommersielt)
- **Pris**: Lisensbasert (krever avtale)
- **Dekning**: Varierer etter avtale
- **Fordel**: Profesjonell API med god dokumentasjon
- **Kontakt**: https://www.norkart.no

## Anbefalt løsning

### Fase 1: Sjekk gratis kilder
1. Undersøk Norge i bilder for tilgjengelige historiske ortofoto
2. Sjekk hvilke år som dekker Bleikøya-området
3. Implementer WMTS-lag for tilgjengelige år

### Fase 2: Bestill fra Kartverket
1. Velg ut nøkkelår (f.eks. 1935, 1950, 1970, 1990)
2. Send forespørsel til Kartverket
3. Estimert kostnad: 4 bilder × 1 950 kr = 7 800 kr + mva

### Fase 3: Implementering i kartet
1. Legg til årstall-velger i kartets kontrollpanel
2. Vis historiske bilder som byttbare bakgrunnslag
3. Bruk distortable image overlay for Kartverket-bilder (krever georeferering)

## Teknisk implementering

### Alternativ A: WMTS-lag (enklest)
```javascript
var historiskLag = L.tileLayer('WMTS_URL/{z}/{x}/{y}.jpeg', {
    attribution: 'Historiske flybilder: Kartverket'
});
```

### Alternativ B: Distortable Image Overlay (for enkeltbilder)
Eksisterende støtte i kartet kan brukes. Krever:
- Georefererte hjørnepunkter for hvert bilde
- Bildefiler lastet opp til server

## Status
- [ ] Sjekk Norge i bilder for tilgjengelige år
- [ ] Kontakt Kartverket for prisforespørsel på Bleikøya-området
- [ ] Beslutning om hvilke år som skal bestilles
- [ ] Implementer årstall-velger i kartet

## Notater
- Bleikøya Velforening er ikke medlem av Norge digitalt
- Villig til å betale for områdespesifikk dekning
- Prioritet: Så langt tilbake som mulig (1935+)

---
*Opprettet: 2025-11-29*
