---
name: bleikoya-sok
description: "Søk i innholdet på bleikoya.net (Bleikøya Velforening): vedtekter, dugnadsregler, styrereferater, innkallinger, generalforsamlingsdokumenter, arrangementer og annen dokumentasjon, inkludert private innlegg som bare styret og medlemmer ser. Bruk denne skillen hver gang brukeren spør om noe som kan stå på nettsidene til vellet, f.eks. «hva sier vedtektene om …», «når var dugnaden i fjor», «finn referatet fra styremøtet i mars», «hva har vi skrevet om VA-saken», også når de ikke nevner nettsiden eksplisitt."
---

# Søk på bleikoya.net

Nettsidene til Bleikøya Velforening har et søke-API som dekker innlegg, sider, kategorier (med «dokumentasjon»-tekst) og arrangementer. Mye av det interessante for styret er private innlegg (referater, innkallinger, styredokumenter). De kommer bare med når søket er autentisert med et applikasjonspassord. Oppsettet er beskrevet i `BRUKSANVISNING.md`.

Bruk alltid hjelpeskriptet. Det henter innlogging fra fila brukeren har satt opp, legger på autentisering og formaterer svaret:

```bash
bash <skill-mappe>/scripts/sok.sh q=vedtekter
```

`<skill-mappe>` er mappa denne SKILL.md ligger i. Argumentene er `nøkkel=verdi`-par som sendes rett videre til API-et.

## Parametre

| Parameter | Verdi | Forklaring |
|---|---|---|
| `q` | tekst | Fritekstsøk i tittel og innhold |
| `type` | `all`, `posts`, `categories`, `category`, `events` | Hva det søkes i. Standard er `all` |
| `category` | slug | Hent én kategori med full dokumentasjonstekst (krever `type=category`) |
| `limit` | tall | Maks antall treff (standard 10, maks 50) |
| `after` | ÅÅÅÅ-MM-DD | Arrangementer som slutter etter datoen (standard: i dag når `type=events`) |
| `before` | ÅÅÅÅ-MM-DD | Arrangementer som starter før datoen |

## Vanlige oppslag

```bash
# Dugnadsreglene (full tekst fra kategorien)
bash <skill-mappe>/scripts/sok.sh type=category category=dugnad

# Vedtektene
bash <skill-mappe>/scripts/sok.sh type=category category=vedtekter

# Fritekst på tvers av alt innhold
bash <skill-mappe>/scripts/sok.sh q=gebyr

# Styrereferater (private, krever innlogging)
bash <skill-mappe>/scripts/sok.sh q=referat limit=20

# Kommende arrangementer
bash <skill-mappe>/scripts/sok.sh type=events

# Arrangementer i et bestemt år
bash <skill-mappe>/scripts/sok.sh type=events after=2025-01-01 before=2025-12-31

# Sjekk at innloggingen virker
bash <skill-mappe>/scripts/sok.sh --sjekk
```

## Kategorier som ofte er relevante

| Slug | Innhold |
|---|---|
| `dugnad` | Dugnadsregler (6 t dugnad + 2 t strandrydding) |
| `vedtekter` | Vedtekter og reglement |
| `styret` | Styret, styrereferater |
| `generalforsamling` | Innkallinger, årsberetninger, protokoller |
| `avfall` | Avfall og kildesortering |
| `brannsikring` | Brannsikkerhet |

Kategorilista kan ha endret seg. Søk med `type=categories` hvis du ikke finner det du leter etter.

## Framgangsmåte

1. Kjør søket med hjelpeskriptet. Start gjerne med en kategori hvis spørsmålet gjelder regler, og fritekst (`q=`) hvis det gjelder en konkret sak eller et dokument.
2. Se på `meta.includes_private` i svaret. Står det `false`, er ikke innloggingen på plass, og private innlegg mangler. Si fra til brukeren og vis til `BRUKSANVISNING.md` i stedet for å konkludere på ufullstendig grunnlag.
3. Treff i `posts` har en `url`. Ta den med i svaret, så brukeren kan åpne innlegget selv. Private innlegg krever at brukeren er logget inn i nettleseren.
4. `excerpt` er bare et utdrag rundt treffet. Trenger du hele teksten i et innlegg, hent den fra `https://bleikoya.net/wp-json/wp/v2/posts/<id>` med samme innlogging (skriptet godtar `--post <id>`).
5. Oppsummer det som er relevant for spørsmålet, med dato og lenke. Skill mellom hva som står på nettsidene og hva som er din egen tolkning.

## Svarformat fra API-et

```json
{
  "categories": [{"id": 56, "name": "Dugnad og Strandrydding", "slug": "dugnad", "documentation": "…"}],
  "posts": [
    {"id": 123, "title": "…", "type": "post", "status": "private", "excerpt": "…", "url": "https://…", "date": "2025-05-25 12:00:00"},
    {"id": 456, "title": "Generalforsamling", "type": "tribe_events", "status": "publish", "url": "https://…", "start_date": "2025-05-25 12:00:00", "end_date": "…", "venue": "Velhuset", "all_day": false}
  ],
  "meta": {"authenticated": true, "includes_private": true}
}
```

Med `type=events` heter lista `events` i stedet for `posts`.
