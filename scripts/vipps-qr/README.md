# vipps-qr

Lite verktøy for å generere QR-koder som peker til Vipps, med valgfri Vipps-logo i midten.

Brukes når du trenger en QR til et Vippsnummer du ikke administrerer selv (og dermed ikke kan generere en offisiell QR for via Vipps-portalen).

## Oppsett

```bash
cd scripts/vipps-qr
uv sync
```

## Bruk

```bash
# Med default logo (assets/vipps-logo.png) og default output (output/qr.png)
uv run generate.py --data "vipps://send/?phoneNumber=527998&message=Containerkasting"

# Spesifiser alt
uv run generate.py \
  --data "vipps://send/?phoneNumber=527998&message=Containerkasting" \
  --out output/containerkasting-527998.png \
  --logo assets/vipps-logo.png \
  --size 1500

# Uten logo
uv run generate.py --data "..." --no-logo
```

## Hvilken data skal QR-koden inneholde?

Vipps publiserer ikke offisielt URL-format for tredjepart, så man må teste seg fram. Tre kandidater i synkende sannsynlighet for at de "bare funker":

1. **Deep link (anbefalt å prøve først):**
   ```
   vipps://send/?phoneNumber=527998&message=Containerkasting
   ```
   Åpner Vipps-appen direkte på telefoner med Vipps installert.

2. **Universal/HTTPS link:**
   ```
   https://hopp.vipps.no/?phoneNumber=527998
   ```
   Faller tilbake til nettleser hvis appen ikke er installert.

3. **Klartekst (alltid lesbar):**
   ```
   Send Vipps til 527998 (containerkasting)
   ```
   Åpner ikke appen, men brukeren ser hva som skal gjøres.

**Test alltid den genererte QR-koden med din egen telefon** før du skriver den ut og henger den opp.

## Vipps-logo

Vipps publiserer ikke brand assets fritt på nett. Du har tre valg:

1. **Eksporter selv fra vipps.no** (anbefalt for endelig versjon):
   - Gå til en Vipps-side, høyreklikk på smiley-logoen → "Lagre bilde som"
   - Beskjær til kvadrat (Preview / Photos)
   - Lagre som `assets/vipps-logo.png`

2. **Bruk placeholder** (Vipps-aktig, men ikke offisiell):
   ```bash
   uv run make_placeholder_logo.py
   ```
   Lager `assets/vipps-logo.png` automatisk.

3. **Drop logoen helt** — bruk `--no-logo`-flagget.

## Hvorfor høy feilkorreksjon?

QR-koden bruker `ERROR_CORRECT_H` (30% redundans) slik at logoen i midten ikke ødelegger skanningen. Logoen bør holde seg under ~25% av QR-bredden (default `--logo-ratio 0.22`). Større logo → risiko for at telefonen ikke klarer å lese koden.
