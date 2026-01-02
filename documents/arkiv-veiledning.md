# Veiledning: Dokumentarkivet i Google Drive

## Mappestruktur

Arkivet er organisert i nummererte serier:

| Serie | Innhold |
|-------|---------|
| **000** | Vedtekter og styringsdokumenter |
| **010** | Generalforsamling (inkl. årsberetning og regnskap) |
| **020-021** | Styret og styremøter |
| **024** | Vårbrev og medlemskommunikasjon |
| **030** | Regnskap og budsjett (løpende) |
| **040** | Vedlikeholdsplan |
| **050-051** | Medlemmer og eierskifte |
| **070-071** | Avtaler, instrukser og utleie |
| **090** | Fellesstyret for øyene |
| **100-140** | Bank, forsikring, IT |
| **200-250** | Drift og anlegg (Velhuset, vann, strøm, renovasjon, dugnad, skjøtsel) |
| **300** | Offentlige etater (Statsbygg, PBE, Brann) |
| **500** | Prosjekter (tidsavgrensede saker) |
| **900** | Arkiv (historisk materiale) |

## Navnekonvensjoner

### Dato først
Filnavn bør starte med dato i formatet `YYYY-MM-DD`:
```
2025-06-22 Referat styremøte.docx
2024 Regnskap Bleikøya.xlsx
```

### Unngå
- STORE BOKSTAVER i hele filnavnet
- Spesialtegn som `? * : " < > |`
- Veldig lange filnavn

## Hvor legger jeg...?

### Styremøter (021)
Hvert møte har sin egen mappe:
```
021 Styremøter/
  2025-06-22 Styremøte 22. juni 2025/
    2025-06-22 Referat styremøte.docx
    2025-06-22 Vedlegg sak 3.pdf
```

### Generalforsamling (010)
Årsberetning og regnskap for år X legges i mappen for GF år X+1:
```
010 Generalforsamling/
  2025 Generalforsamling/
    2024 Årsberetning.docx      ← Årsberetning for 2024
    2024 Regnskap.xlsx          ← Regnskap for 2024
    Innkalling GF 2025.docx
    Protokoll GF 2025.pdf
```

### Avtaler og instrukser (070)
Gjeldende avtaler og instrukser. Historiske versjoner i undermappe:
```
070 Avtaler og instruks/
  Avtale VM 2025-2027.pdf
  Instruks Strandrydder.pdf
  X00 Historikk/
    Avtale VM 2017-2022.pdf
```

### Prosjekter (500-serien)
Tidsavgrensede saker med egen mappe:
```
500 2020-2024 Tomteinnløsning/
502 2024 MVA-refusjon/
510 2025 Internett til Velhuset/
```

Nummereringen er: `5XX ÅÅÅÅ Prosjektnavn`

## Tips

1. **Søk fungerer** - Google Drive indekserer innholdet i dokumenter
2. **Ikke flytt mapper** - Strukturen er satt opp bevisst
3. **Spør hvis usikker** - Bedre å spørre enn å legge feil
4. **Siste versjon i rot** - Kun gjeldende versjon på toppnivå, gamle i Historikk
