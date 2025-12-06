# Bleikøya Kartløsning - Designdokument

## Oversikt

Interaktivt kartsystem for Bleikøya Velforening bygget på Leaflet.js med støtte for:
- Multiple kartlag (OSM, satellitt, egne kart)
- Perspektivjustering av skannet kartmateriale
- POI (Point of Interest) management
- Kalibrering av SVG-overlay

## Arkitektur

### Teknologistack

- **Leaflet.js 1.9.4** - Base mapping library
- **Leaflet.DistortableImage 0.21.9** - Perspektivjustering av bilder
- **Leaflet.Toolbar** - Verktøylinjer for distortable images
- **Mapbox Satellite** - Satellittbilder (krever API-nøkkel)
- **Kartverket WMTS** - Norske topografiske kart

### Hovedkomponenter

```
page-kart.php
├── Base Map (Leaflet)
├── Base Layers (bakgrunnskart)
│   ├── Topografisk kart (Kartverket)
│   ├── Satellitt (Mapbox)
│   └── SVG-kart (statisk overlay)
├── Overlay Layers
│   ├── Distortable Images (dynamisk perspektiv)
│   ├── SVG Overlay (kalibrert)
│   └── POI Layers (markers, rectangles, polygons)
├── Kontrollpaneler
│   ├── Kalibrering (SVG bounds/rotation)
│   ├── Bilderedigering (distortable images)
│   └── POI Manager (tegne og eksportere POI)
└── Layer Control (Leaflet standard)
```

## Kartlag

### 1. Base Layers (Bakgrunnskart)

**Topografisk kart (Kartverket)**
```javascript
L.tileLayer('https://cache.kartverket.no/v1/wmts/1.0.0/topo/default/webmercator/{z}/{y}/{x}.png')
```
- Standard bakgrunnskart
- Norsk topografi
- Gratis å bruke

**Satellitt (Mapbox)**
```javascript
L.tileLayer('https://api.mapbox.com/styles/v1/mapbox/satellite-streets-v12/tiles/{z}/{x}/{y}')
```
- Krever API-nøkkel (lagret i `mapboxToken`)
- Høyoppløselige satellittbilder
- Kombinert med veier/stedsnavn

**SVG-kart som base layer**
- Viser kun det egne SVG-kartet uten bakgrunn
- Nyttig for ren oversikt

### 2. Overlay Layers

**Distortable Images (BYM-kart, etc.)**
- Skannet kartmateriale med justerbart perspektiv
- Factory pattern: ny instans lages hver gang laget aktiveres
- Støtter full perspektivjustering (4 hjørner uavhengig)

**SVG Overlay**
- Statisk SVG-kart med kalibrerbare bounds
- Supports rotation (-15° til +15°)
- Koordinattransformasjon: SVG → lat/lng

**POI Layers (Kartpunkt System)**
- Custom post type `kartpunkt` lagret i WordPress-database
- Taxonomy `gruppe` for kategorisering
- Markers, rectangles, polygons
- Koblinger til WordPress-innhold (artikler, sider, hendelser, brukere)
- REST API for CRUD-operasjoner
- Sidebar med koblinger når POI klikkes

## Distortable Images System

### Arkitektur

**Problem som løses:**
Leaflet.DistortableImage har lifecycle-problemer når overlays legges til/fjernes via layer control. Løsningen bruker factory pattern med lazy initialization.

### Komponenter

```javascript
// 1. Konfigurasjon (persistent)
distortableImageConfigs = {
  bym: {
    name: 'BYM-kart',
    url: '/path/to/image.png',
    opacity: 0.7,
    corners: [...]
  }
}

// 2. Factory function
createDistortableOverlay(configKey)
// Lager ny instans fra config

// 3. Layer group wrapper
createDistortableLayerGroup(configKey)
// Håndterer add/remove events
// Lager ny overlay ved add
// Destruerer overlay ved remove

// 4. Active instances (runtime)
distortableImages = {}
// Inneholder kun aktive overlays
```

### Workflow: Legge til nytt distortable image

1. **Legg til konfigurasjon** (`page-kart.php:368-392`)
```javascript
distortableImageConfigs.reguleringsplan = {
  name: 'Reguleringsplan',
  url: '<?php echo get_stylesheet_directory_uri(); ?>/assets/img/reguleringsplan.png',
  opacity: 0.7,
  corners: [
    L.latLng(59.8931, 10.7314), // top-left
    L.latLng(59.8931, 10.7494), // top-right
    L.latLng(59.8854, 10.7314), // bottom-left
    L.latLng(59.8854, 10.7494)  // bottom-right
  ]
};
```

2. **Legg til i overlays** (`page-kart.php:479-484`)
```javascript
var overlays = {
  "Reguleringsplan": createDistortableLayerGroup('reguleringsplan'),
  ...
};
```

3. **Juster perspektiv**
   - Aktiver laget i layer control
   - Åpne "🖼️ Bilderedigering"
   - Velg bildet i dropdown
   - Klikk "✏️ Start redigering"
   - Dra hjørnene
   - Klikk "📋 Eksporter hjørner"
   - Lim koordinatene inn i config

### Corner format

Corners må være i "Z-shape" order:
```
0 (NW) -------- 1 (NE)
  |              |
  |              |
2 (SW) -------- 3 (SE)
```

## SVG Overlay System

### Kalibrering

SVG-overlayet bruker geografiske bounds (lat/lng) for plassering.

**Bounds struktur:**
```javascript
currentBounds = {
  south: 59.8854,  // Sør kant (minste latitude)
  west: 10.7314,   // Vest kant (minste longitude)
  north: 59.8931,  // Nord kant (største latitude)
  east: 10.7494    // Øst kant (største longitude)
}
```

**Rotasjon:**
- CSS transform på image element
- -15° til +15° range
- Rotation origin: center center

**Koordinattransformasjon (SVG → lat/lng):**
```javascript
function svgToLatLng(svgX, svgY) {
  var normalizedX = svgX / mapWidth;
  var normalizedY = svgY / mapHeight;

  var lat = currentBounds.south + (1 - normalizedY) *
            (currentBounds.north - currentBounds.south);
  var lng = currentBounds.west + normalizedX *
            (currentBounds.east - currentBounds.west);

  return L.latLng(lat, lng);
}
```

### Workflow: Kalibrering

1. Klikk "🔧 Kalibrering" (top left)
2. Juster bounds med +/- knapper
3. Juster opacity (0-100%)
4. Juster rotation (-15° til +15°)
5. Klikk "📋 Kopier bounds" for å eksportere
6. Lim verdier inn i `currentBounds` (linje 224-228)

## Kartpunkt System (POI Management)

### Oversikt

Kartpunkt-systemet er en komplett løsning for å administrere steder på kartet, lagret i WordPress-database med full CRUD-funksjonalitet via REST API.

### Arkitektur

```
WordPress Database
├── Post Type: kartpunkt
│   ├── post_title (stedsnavn)
│   ├── post_author
│   ├── post_status (publish/draft)
│   └── Meta Fields:
│       ├── _type (marker/rectangle/polygon)
│       ├── _coordinates (JSON)
│       ├── _style (JSON: color, opacity, weight)
│       └── _connections (array of IDs)
├── Taxonomy: gruppe
│   └── Terms: Brygger, Hytter, Veier, etc.
└── Connections (bidirectional)
    ├── kartpunkt → posts/pages/events/users
    └── reverse: _connected_locations meta

REST API
├── GET /bleikoya/v1/locations
├── GET /bleikoya/v1/locations/{id}
├── POST /bleikoya/v1/locations
├── PUT /bleikoya/v1/locations/{id}
├── DELETE /bleikoya/v1/locations/{id}
└── GET /bleikoya/v1/locations/{id}/connections

Admin Interface
├── Kartpunkt edit screen
│   ├── Kartdata meta box (type, coordinates, style)
│   └── Relatert innhold meta box (AJAX search, add/remove connections)
└── Reverse meta boxes
    ├── On posts/pages/events (shows connected locations)
    └── On user profiles (shows connected locations)

Frontend
├── Map display (page-kart.php)
│   ├── Load locations from database
│   ├── Render markers/rectangles/polygons
│   └── Click handler → show connections sidebar
└── POI Manager
    ├── Draw new locations (save via REST API)
    ├── Edit locations (open in WordPress admin)
    └── Delete locations (REST API)
```

### Datafiler

**Backend:**
- `includes/post-types/location.php` - Register custom post type and taxonomy
- `includes/api/location-connections.php` - Bidirectional connection management
- `includes/api/location-coordinates.php` - Coordinate and style helpers with validation
- `includes/api/location-rest-endpoints.php` - REST API endpoints
- `includes/admin/location-meta-boxes.php` - Admin edit UI with AJAX search
- `includes/admin/connected-locations-meta-box.php` - Reverse connections display
- `includes/admin/location-ajax.php` - AJAX handlers for connection search
- `includes/shortcodes/location-map.php` - Miniature map shortcode
- `assets/js/admin-location.js` - Admin JavaScript
- `assets/css/admin-location.css` - Admin styling

**Frontend:**
- `page-kart.php` - Map display with database integration

### Coordinate Format

**Marker:**
```json
{
  "lat": 59.8889,
  "lng": 10.7404
}
```

**Rectangle:**
```json
{
  "bounds": [
    [59.8882, 10.7395],
    [59.8883, 10.7396]
  ]
}
```

**Polygon:**
```json
{
  "latlngs": [
    [59.8882, 10.7395],
    [59.8883, 10.7396],
    [59.8884, 10.7397]
  ]
}
```

### Style Format

```json
{
  "color": "#3388ff",
  "opacity": 0.5,
  "weight": 2
}
```

### Connections (Koblinger)

Kartpunkt kan kobles til:
- **Posts** (artikler)
- **Pages** (sider)
- **tribe_events** (hendelser)
- **Users** (brukere/hytteeiere)

**Storage:**
- On kartpunkt: `_connections` meta (serialized array of IDs)
- On connected items: `_connected_locations` meta (serialized array of location IDs)
- Cleanup: `before_delete_post` hook removes bidirectional references

**Connection Data:**
```php
[
  'id' => 123,
  'title' => 'Jonbrygga',
  'type' => 'post',
  'link' => 'https://...',
  'excerpt' => '...',
  'cabin_number' => '74' // For users only
]
```

### Workflow: Opprette nytt kartpunkt (Admin)

1. Gå til **Steder → Legg til nytt**
2. Fyll inn navn (tittel)
3. Velg **gruppe** fra høyre sidebar
4. I **Kartdata** meta box:
   - Velg type (marker/rectangle/polygon)
   - Lim inn koordinater som JSON
   - Velg farge, opacity, linjestørrelse
5. I **Relatert innhold** meta box:
   - Søk etter innhold (artikler, sider, brukere)
   - Klikk "Legg til" for å koble
6. Klikk **Publiser**

### Workflow: Opprette nytt kartpunkt (POI Manager på kart)

1. Gå til kart-siden
2. Klikk "📍 POI Manager"
3. Velg eller opprett gruppe
4. Velg tegne-verktøy:
   - **📍 Legg til punkt:** Klikk på kart, skriv navn
   - **▢ Legg til firkant:** Klikk start, klikk slutt, skriv navn
   - **▱ Legg til polygon:** Klikk flere punkter, dobbeltklikk, skriv navn
5. Stedet lagres automatisk i database
6. Siden lastes på nytt for å vise det nye stedet

**Merk:** Koblinger til innhold må legges til via WordPress admin (ikke POI Manager).

### Workflow: Se koblinger til et sted

1. Klikk på et sted på kartet (marker/rectangle/polygon)
2. Sidebar åpnes til høyre
3. Koblinger vises gruppert etter type:
   - **Artikler** - med utdrag
   - **Sider** - med utdrag
   - **Hendelser** - med utdrag
   - **Brukere** - med hyttenummer
4. Klikk på koblingen for å åpne siden/profilen

### URL State Management (Dyplenker)

Kartet støtter dyplenker som bevarer kartets tilstand i URL-en. Dette muliggjør deling av spesifikke kartvisninger.

**Støttede URL-parametere:**
- `poi` - Kartpunkt-ID som skal velges og fokuseres
- `lat` / `lng` - Kartets sentrum (latitude/longitude)
- `zoom` - Zoom-nivå (1-20)
- `base` - Bakgrunnskart (`topo`, `satellite`, `svg`)
- `overlays` - Kommaseparert liste av aktive overlay-lag (gruppe-slugs)

**Eksempler:**
```
/kart/?poi=123                           # Åpne kartpunkt #123
/kart/?poi=123&overlays=hytter           # Kartpunkt #123 med hytter-laget aktivt
/kart/?lat=59.889&lng=10.740&zoom=18     # Spesifikk posisjon og zoom
/kart/?base=satellite&overlays=brygger   # Satellitt med brygger-lag
```

**Ved dyplenke til kartpunkt (`poi`):**
1. Kartet panorerer til kartpunktets posisjon
2. Zoom settes til minimum 18
3. Riktig overlay-lag aktiveres automatisk
4. Popup åpnes på markøren
5. Sidebar med koblinger vises

**Implementasjon:**
- URL oppdateres med `replaceState` (unngår historikk-forurensning)
- State leses ved sidelast via `applyUrlState()`
- Markører lagres i `markersByLocationId` for rask oppslag

### Lenke til kartpunkt fra andre sider

For å lenke til et kartpunkt fra andre deler av nettstedet:

**PHP-eksempel:**
```php
$location_id = 123;
$gruppe_terms = wp_get_post_terms($location_id, 'gruppe');
$gruppe_slug = !empty($gruppe_terms) ? $gruppe_terms[0]->slug : '';
$map_url = '/kart/?poi=' . $location_id;
if ($gruppe_slug) {
    $map_url .= '&overlays=' . $gruppe_slug;
}
echo '<a href="' . esc_url($map_url) . '">Vis på kart</a>';
```

**Brukes i:**
- `author.php` - Lenke til tilkoblede kartpunkt for hytteeiere
- Admin meta boxes - Lenke til kartpunkt fra tilkoblet innhold

### Miniature Map Shortcode

Display a small map showing one or more locations in content:

**Single location:**
```
[location_map id="123" height="300px" zoom="15"]
```

**Multiple locations:**
```
[location_map ids="123,456,789" height="400px"]
```

**Custom center:**
```
[location_map id="123" center="59.8889,10.7404" zoom="16"]
```

**Parameters:**
- `id` - Single location ID
- `ids` - Comma-separated location IDs
- `height` - Map height (default: 300px)
- `zoom` - Zoom level (default: 15)
- `center` - Custom center as "lat,lng" (default: auto-fit)

**PHP helper:**
```php
echo render_location_minimap(123, '200px');
```

## Kontrollpaneler

### 1. Kalibrering (🔧)

**Posisjon:** Top left
**Synlighet:** Toggle-knapp

**Funksjoner:**
- Nord/Sør/Øst/Vest bounds justering
- Opacity slider
- Rotation slider
- Reset rotation
- Copy bounds til clipboard
- Live preview

### 2. Bilderedigering (🖼️)

**Posisjon:** Top right
**Synlighet:** Toggle-knapp

**Funksjoner:**
- Dropdown: Velg distortable image
- Start/stopp redigering
- Eksporter hjørnekoordinater
- Live preview i panel

### 3. POI Manager (📍)

**Posisjon:** Top right
**Synlighet:** Toggle-knapp

**Funksjoner:**
- Lag management (opprette, velge)
- Tegne-verktøy (marker, rectangle, polygon)
- POI liste med slett-funksjon
- Eksport til JavaScript

## API Reference

### Global objekt: `window.bleikoyaMap`

```javascript
window.bleikoyaMap = {
  // Leaflet map instance
  map: L.Map,

  // Koordinattransformasjon
  svgToLatLng: function(svgX, svgY) -> L.LatLng,

  // SVG bounds
  currentBounds: { south, west, north, east },
  currentRotation: function() -> number,
  updateBounds: function(s, w, n, e),
  setRotation: function(deg),

  // Location/POI system (database-backed)
  deletePOI: function(locationId),
  editLocation: function(locationId),
  saveLocationToDatabase: function(locationData),
  updateLocationInDatabase: function(locationId, locationData),
  locationsData: Object, // Loaded from database

  // Distortable images
  distortableImageConfigs: Object,
  distortableImages: Object,
  updateImageSelect: function()
}
```

### REST API Reference

**Base URL:** `/wp-json/bleikoya/v1/`

**Endpoints:**

```
GET /locations
  - Returns all published locations
  - Response: Array of location objects

GET /locations/{id}
  - Returns single location
  - Response: Location object

POST /locations
  - Creates new location
  - Auth: Required (edit_posts capability)
  - Body: { title, type, coordinates, gruppe, style, connections? }
  - Response: Created location object

PUT /locations/{id}
  - Updates existing location
  - Auth: Required (edit_posts capability)
  - Body: { title?, type?, coordinates?, gruppe?, style? }
  - Response: Updated location object

DELETE /locations/{id}
  - Deletes location
  - Auth: Required (delete_posts capability)
  - Response: { deleted: true }

GET /locations/{id}/connections
  - Returns location connections with enriched data
  - Response: Array of connection objects with excerpts, thumbnails
```

**Location Object:**
```json
{
  "id": 123,
  "title": "Jonbrygga",
  "type": "marker",
  "coordinates": { "lat": 59.8889, "lng": 10.7404 },
  "style": { "color": "#3388ff", "opacity": 0.5, "weight": 2 },
  "gruppe": {
    "names": ["Brygger"],
    "slugs": ["brygger"]
  },
  "connections": [456, 789],
  "permalink": "https://...",
  "edit_link": "https://.../wp-admin/post.php?post=123&action=edit",
  "author": { "id": 1, "name": "Admin" },
  "created": "2025-01-23 12:00:00",
  "modified": "2025-01-23 14:30:00"
}
```

### Eksempel bruk

```javascript
// Få current rotation
var rotation = window.bleikoyaMap.currentRotation();

// Oppdater bounds programmatisk
window.bleikoyaMap.updateBounds(59.8854, 10.7314, 59.8931, 10.7494);

// Konverter SVG koordinat til lat/lng
var latlng = window.bleikoyaMap.svgToLatLng(1532.5, 1115.5);

// Slett location via REST API
window.bleikoyaMap.deletePOI(123);

// Opprett ny location programmatisk
window.bleikoyaMap.saveLocationToDatabase({
  title: 'Nytt sted',
  type: 'marker',
  coordinates: { lat: 59.8889, lng: 10.7404 },
  gruppe: 'Brygger',
  style: { color: '#ff0000', opacity: 0.8, weight: 2 }
});

// Hent location data fra database
var bryggerLocations = window.bleikoyaMap.locationsData.brygger;
```

### PHP API Reference

**Helper Functions:**

```php
// Coordinates
get_location_coordinates($location_id) // Returns decoded JSON
update_location_coordinates($location_id, $data)
validate_coordinates($data) // Type-based validation
get_location_type($location_id) // marker/rectangle/polygon
get_location_style($location_id) // color, opacity, weight
get_location_data($location_id) // All data in one call

// Connections
get_location_connections($location_id) // Returns [{id, type}, ...] with explicit types
get_location_connection_ids($location_id) // Returns array of IDs only (backwards compat)
add_location_connection($location_id, $post_id, $type = 'post')
remove_location_connection($location_id, $post_id, $type = 'post')
get_connected_locations($post_id, $type = 'post') // Reverse lookup
get_location_connections_full($location_id) // With enriched data
migrate_connections_format() // Migrate old format to new (run once)

// Shortcodes
[location_map id="123" height="300px" zoom="15"]
render_location_minimap($location_id, $height = '200px') // PHP helper
```

## Tekniske detaljer

### Koordinatsystemer

**Leaflet:** Standard Web Mercator (EPSG:3857)
**Mapbox:** WGS84 (EPSG:4326)
**Kartverket:** Web Mercator
**SVG:** Egendefinert viewBox → transformeres til lat/lng

### Bounds kalibrering

**Initielle bounds:**
```javascript
south: 59.8854,
west: 10.7314,
north: 59.8931,
east: 10.7494
```

**Justeringssteg:**
- 0.001 grader ≈ 111 meter
- Buttons: ±0.001
- Input fields: 0.0001 presisjon

### Z-index hierarki

```css
#map-wrapper: z-index: 1
.leaflet-container: z-index: 0
Controls: auto (Leaflet standard)
```

Dette sikrer at kartet ikke påvirker andre DOM-elementer på siden.

## Vedlikehold

### Oppdatere biblioteker

Biblioteker lastes fra CDN (unpkg):
```html
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
```

For å oppdatere, endre versjonsnummer i URL-ene.

### Mapbox API-nøkkel

Nøkkelen er hardkodet i `page-kart.php:287`:
```javascript
var mapboxToken = 'pk.eyJ1Ijoi...';
```

**Sikkerhet:** Nøkkelen er scoped til bleikoya.net og har kun read-tilgang.
**Fornyelse:** Logg inn på mapbox.com, opprett ny token, erstatt i koden.

### Legge til nye kartlag

**WMTS/WMS tiles:**
```javascript
var newLayer = L.tileLayer('https://tile-server/{z}/{x}/{y}.png', {
  attribution: '...',
  maxZoom: 18
});

baseLayers["Nytt lag"] = newLayer;
```

**Statiske bilder:**
1. Bruk distortable image system (se over)
2. Eller: `L.imageOverlay(url, bounds)` for enkel overlay

## Feilsøking

### Distortable image vises ikke andre gang

**Løsning:** Factory pattern er implementert - skal nå fungere.
**Debug:** Sjekk console for "Created new overlay for: [navn]"

### SVG-kart feil plassert

**Løsning:** Bruk kalibreringsverktøy.
**Debug:**
- Sammenlign med satellittbilder
- Bruk kjente referansepunkter (hytte 74 = g595 i SVG)

### POI forsvinner ved refresh

**Løst:** POI (kartpunkt) lagres nå permanent i WordPress-database.
**Alternativ:** Bruk POI Manager på kart-siden eller admin-panel for å opprette/redigere.

### Performance issues

**Symptom:** Tregt kart, laggy zoom
**Løsning:**
- Reduser antall POI
- Bruk lavere maxZoom
- Optimaliser bildestørrelser

## Fremtidige forbedringer

### Nylig implementert (v2.1)
- [x] URL state management med dyplenker (`poi`, `lat`, `lng`, `zoom`, `base`, `overlays`)
- [x] Automatisk popup-åpning ved dyplenke til kartpunkt
- [x] Taxonomy term connections (kategorier kan kobles til kartpunkt)
- [x] Author page template med kartlenker for hytteeiere

### Implementert (v2.0)
- [x] Database-lagring av POI (kartpunkt custom post type)
- [x] REST API for CRUD-operasjoner
- [x] Bidireksjonale koblinger mellom kartpunkt og innhold
- [x] Admin UI med AJAX-søk
- [x] Reverse meta boxes på posts/pages/events/users
- [x] Frontend sidebar med koblinger
- [x] Miniature map shortcode

### Kort sikt
- [ ] Batch-import av kartpunkt fra CSV/GeoJSON
- [ ] Flere tegne-verktøy (sirkel, frihånd)
- [ ] Opacity slider for distortable images
- [ ] Undo/redo for kartpunkt editing
- [ ] Bildeopplasting for kartpunkt (featured image)
- [ ] Kategorier/tags for å filtrere kartpunkt på kart
- [ ] Søkefunksjon for kartpunkt

### Lang sikt
- [ ] Export til GeoJSON
- [ ] Import fra GeoJSON
- [ ] Mobile-optimalisering (touch gestures)
- [ ] Offline-støtte (service worker)
- [ ] Print-funksjon
- [ ] Måleverktøy (distanse, areal)
- [ ] Bruker-tilgangsstyring per kartpunkt
- [ ] Versjonering av kartpunkt-endringer
- [ ] Notifikasjoner når kartpunkt oppdateres

## Lisenser og krediteringer

- **Leaflet.js:** BSD 2-Clause License
- **Leaflet.DistortableImage:** BSD 2-Clause License (Public Lab)
- **Mapbox:** Krever API-nøkkel, attribution required
- **Kartverket:** CC BY 4.0
- **OpenStreetMap:** ODbL

## Support og kontakt

**Dokumentasjon:** `MAP_DESIGN.md` (denne filen)
**Kode:** `page-kart.php`
**Issues:** GitHub repository eller kontakt utvikler

---

*Sist oppdatert: 2025-11-28*
*Versjon: 2.1 - URL State Management*

## Changelog

### v2.1 (2025-11-28)
- Implemented URL state management for deep linking
- Added support for `poi`, `lat`, `lng`, `zoom`, `base`, `overlays` URL parameters
- Deep links now open popup on marker automatically
- Markers stored in `markersByLocationId` for efficient lookup
- Added support for taxonomy term connections (in addition to posts/users)
- Created `author.php` template with cabin owner info and map links

### v2.0 (2025-01-23)
- Added kartpunkt custom post type with gruppe taxonomy
- Implemented REST API for CRUD operations
- Created bidirectional connections system
- Built admin UI with AJAX search for connections
- Added reverse meta boxes on posts/pages/events/users
- Implemented frontend sidebar for displaying connections
- Created miniature map shortcode [location_map]
- Added comprehensive admin and frontend styling

### v1.0 (2025-01-15)
- Initial release with Leaflet.js integration
- Distortable images system with factory pattern
- SVG overlay calibration tools
- POI Manager (in-memory)
