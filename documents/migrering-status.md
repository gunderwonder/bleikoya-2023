# Migrering av 900 Arkiv - Status

Oppdatert: 2026-01-01

## Utført migrering

- **Totalt flyttet:** 360 filer
- **Identiske duplikater hoppet over:** 1
- **Duplikater med ulikt innhold omdøpt:** 6

## Mapper som ble flyttet automatisk

| Kildemappe | Målmappe | Kategori |
|------------|----------|----------|
| Referat | 021 Styremøter, 090 Fellesstyret | Styrereferat, Fellesstyret |
| Generalforsamling | 010 Generalforsamling | GF, Medlemsmøte, Innkalling |
| Vårbrev, Årsberetning | 024 Vårbrev, 010 GF | Vårbrev, Årsberetning |
| Avtaler og instruks | 070 Avtaler og instruks | Avtaler, Instrukser |
| Regnskap, budsjett | 010 GF (år+1) | Regnskap/Budsjett |
| Vedtekter, informasjon til hytteeierne | 000 Vedtekter og styringsdokumenter | Vedtekter |
| Tomteinnløsning | 500 2020-2024 Tomteinnløsning | Tomteinnløsning |
| Ulovlighetsoppfølging PBE | 500 2020-2025 Ulovlighetsoppfølging brygger | Ulovlighetsoppfølging |
| Frivillighetsregisteret MVA refusjon | 502 2024 MVA-refusjon | MVA-refusjon |
| Renovasjon | 230 Renovasjon | Renovasjon |
| Skjøtsel, dugnad, trær, planter | 250 Skjøtsel og miljø, 070 (regler) | Skjøtsel |
| Anbud | 500 2016 Renovering vaktmesterhytta | Vaktmesterhytta |
| Fellesstyret for øyene | 090 Fellesstyret | Fellesstyret |

## Mapper som IKKE ble flyttet (manuell håndtering)

### Bevisst utelatt fra scriptet
| Filer | Mappe | Merknad |
|------:|-------|---------|
| 86 | Strømnettet | Pågående prosjekt |
| 34 | Vann og kloakk | Pågående prosjekt |
| 6 | Bål søknad | Manuell håndtering |

### Ikke dekket av scriptet
| Filer | Kildemappe | Foreslått mål |
|------:|------------|---------------|
| 73 | Vedlikeholdsplan | 040 Vedlikeholdsplan |
| 69 | 240 Medlemmer | 050 Medlemmer |
| 52 | Utleie Velhuset | 071 Utleie av Velhuset |
| 43 | 250 Offentlige etater | 300-serien |
| 27 | Bilder | Manuell sortering |
| 9 | Styret | 020 Styret |
| 8 | Diverse | Manuell sortering |
| 2 | 130 Bank og forsikring | 100 Bank, 120 Forsikring |
| 2 | Kart | Manuell |
| 2 | Eierskifter | 051 Eierskifte |
| 2 | Grafikk | Manuell |
| 1 | Nettside | 140 Nettsider og intern-IT |

## Navnekonvensjoner brukt

### Dato-prefiks
- Datoer i filnavn flyttes til starten som `YYYY-MM-DD` prefiks
- Originalt innhold beholdes (dato fjernes ikke fra resten)
- Filer som allerede har dato på starten endres ikke
- Årstallsområder (f.eks. 2017-2018) beholdes intakt

### UPPERCASE
- Filnavn som er hovedsakelig UPPERCASE konverteres til Sentence case
- Dato-prefiks beholdes uendret

### Duplikater
- SHA-256 hash brukes for å sjekke om filer er identiske
- Identiske duplikater: hoppes over
- Ulikt innhold: omdøpes med kildemappe som suffiks

## Kommandoer

```bash
# Dry-run (vis hva som vil skje)
uv run documents/migrate_archive.py --dry-run

# Eksporter til CSV for gjennomgang
uv run documents/migrate_archive.py --dry-run --csv documents/migrering.csv

# Utfør migrering
uv run documents/migrate_archive.py
```

## Script-konfigurasjon

Kilde: `900 Arkiv/` (i Google Drive)
Mål: Rotmapper i samme Drive
Operasjon: `shutil.move` (flytter, kopierer ikke)
