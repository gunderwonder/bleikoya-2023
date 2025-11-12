# Error Logging Oppsett

Dette temaet er konfigurert for å sende feilmeldinger og logger til både **Sentry** (for error tracking) og **Grafana Cloud Loki** (for loggaggregering).

## Oversikt

### Sentry
- **Formål**: Detaljert error tracking med stack traces, breadcrumbs og kontekst
- **Gratis tier**: 5,000 events/måned, 30 dagers retention
- **Nettside**: https://sentry.io

### Grafana Cloud Loki
- **Formål**: Loggaggregering og visualisering med dashboards
- **Gratis tier**: 50 GB logs/måned, 14 dagers retention
- **Nettside**: https://grafana.com/products/cloud/

## 1. Sette opp Sentry

### 1.1 Opprett Sentry-konto
1. Gå til https://sentry.io og opprett en gratis konto
2. Opprett et nytt prosjekt og velg "PHP" som plattform
3. Gi prosjektet et navn (f.eks. "bleikoya-net")

### 1.2 Hent DSN
1. Etter å ha opprettet prosjektet, gå til **Settings** → **Projects** → **[ditt prosjekt]**
2. Klikk på **Client Keys (DSN)** i venstre meny
3. Kopier **DSN**-strengen (ser ut som: `https://xxxx@xxxx.ingest.sentry.io/xxxx`)

### 1.3 Konfigurer DSN

Opprett en `.env`-fil i temaets rotmappe (samme sted som `functions.php`):

```bash
# Kopier eksempelfilen
cp .env.example .env
```

Rediger `.env`-filen og sett inn din Sentry DSN:

```env
SENTRY_DSN=https://xxxx@xxxx.ingest.sentry.io/xxxx
```

**Viktig**: `.env`-filen er allerede lagt til i `.gitignore` og vil ikke bli committet til git.

## 2. Sette opp Grafana Cloud Loki

### 2.1 Opprett Grafana Cloud-konto
1. Gå til https://grafana.com/products/cloud/
2. Klikk på "Start for free" og opprett en konto
3. Opprett en ny Grafana Cloud-stack (velg region nærmest deg)

### 2.2 Hent Loki-credentials
1. Gå til din Grafana Cloud-stack
2. I venstremenyen, klikk på **Connections** → **Data sources**
3. Finn **Loki** i listen over data sources
4. Klikk på "Details" for Loki
5. Noter deg:
   - **URL**: `https://logs-prod-XXX.grafana.net/loki/api/v1/push`
   - **Username**: Et tall (f.eks. "123456")
   - **Password**: API-nøkkelen (må eventuelt opprettes under "Access Policies")

### 2.3 Opprett API-nøkkel (hvis nødvendig)
1. Gå til **Administration** → **Access Policies** (eller **API Keys**)
2. Klikk på "Create access policy" / "Create API key"
3. Gi nøkkelen et navn (f.eks. "bleikoya-logging")
4. Gi nøkkelen skrivetilgang til Loki:
   - **Role**: "MetricsPublisher" eller "Editor"
   - **Scopes**: Velg "logs:write"
5. Kopier API-nøkkelen (den vises bare én gang!)

### 2.4 Konfigurer Loki-credentials

Legg til Loki-credentials i din `.env`-fil (samme fil som Sentry DSN):

```env
SENTRY_DSN=https://xxxx@xxxx.ingest.sentry.io/xxxx

LOKI_URL=https://logs-prod-XXX.grafana.net/loki/api/v1/push
LOKI_USERNAME=123456
LOKI_PASSWORD=glc_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Filen din `.env` skal nå inneholde alle fire variabler.

## 3. Bruke loggeren i kode

Logger-klassen er allerede integrert og vil automatisk fange opp PHP-feil, warnings og exceptions.

### Manuell logging

```php
// Debug-meldinger (kun i development)
BleikoyaLogging\Logger::debug('Debug message', ['key' => 'value']);

// Info-meldinger
BleikoyaLogging\Logger::info('User logged in', ['user_id' => 123]);

// Warnings
BleikoyaLogging\Logger::warning('Deprecated function used', ['function' => 'old_func']);

// Errors (sendes også til Sentry)
BleikoyaLogging\Logger::error('Database query failed', ['query' => $sql]);

// Critical errors (sendes også til Sentry)
BleikoyaLogging\Logger::critical('Payment processing failed', ['order_id' => 456]);
```

## 4. Verifisere at logging fungerer

### Automatisk test-script

Vi har inkludert et test-script som sender testmeldinger til både Sentry og Loki:

```bash
# Via CLI
php test-logging.php

# Eller besøk via nettleser:
# https://bleikoya.net/wp-content/themes/bleikoya-2023/test-logging.php
```

**Viktig**: Slett eller kommenter ut `test-logging.php` i produksjon for sikkerhetens skyld.

### Test Sentry
1. Kjør test-scriptet over, eller
2. Trigger en test-error på nettstedet (f.eks. besøk en side som ikke finnes)
3. Gå til Sentry-prosjektet ditt → **Issues**
4. Se at feilen dukker opp i Sentry innen få sekunder

### Test Grafana Loki
1. Kjør test-scriptet over
2. Gå til Grafana Cloud-dashboard
3. Klikk på **Explore** i venstremenyen
4. Velg Loki som data source
5. Bruk query: `{app="bleikoya-net"}`
6. Se at logger dukker opp (kan ta 1-2 minutter første gang)

## 5. Opprette dashboards i Grafana

### 5.1 Opprett dashboard for error tracking
1. Gå til **Dashboards** → **New** → **New Dashboard**
2. Klikk på "Add visualization"
3. Velg Loki som data source
4. Bruk query: `{app="bleikoya-net", level="error"}`
5. Velg visualiseringstype (f.eks. "Time series" eller "Table")
6. Lagre dashboard

### 5.2 Nyttige queries
```logql
# Alle errors
{app="bleikoya-net", level="error"}

# Alle warnings og errors
{app="bleikoya-net"} |= `level="warning"` or `level="error"`

# Errors fra spesifikk fil
{app="bleikoya-net", level="error", file="functions.php"}

# Count errors per time
count_over_time({app="bleikoya-net", level="error"}[5m])

# Search for specific text
{app="bleikoya-net"} |= "database"
```

## 6. Troubleshooting

### Logger sendes ikke til Sentry
- Sjekk at `SENTRY_DSN` er satt riktig
- Sjekk at serveren kan gjøre utgående HTTP-requests
- Sjekk PHP error log for feilmeldinger

### Logger sendes ikke til Loki
- Sjekk at alle tre Loki-variabler er satt riktig
- Verifiser at URL-en er riktig (skal ende med `/loki/api/v1/push`)
- Sjekk at API-nøkkelen har skrivetilgang til Loki
- Sjekk PHP error log for feilmeldinger

### Får for mange logger / nærmer meg gratis tier-grensene
- Øk `min_level` i `includes/config/logging.php` fra "WARNING" til "ERROR"
- Reduser `traces_sample_rate` for Sentry
- Legg til flere mønstre i `exclude_patterns`

### Lokal logging
I development-modus (når `WP_DEBUG` er `true`) logges også alle meldinger til:
```
wp-content/themes/bleikoya-2023/logs/app.log
```

## 7. Sikkerhet

- **Aldri** commit API-nøkler eller DSN-er til git
- Bruk alltid miljøvariabler for sensitive credentials
- Sett `send_default_pii` til `false` i Sentry-config for å unngå logging av personlig informasjon

## 8. Kostnader

Begge tjenestene har generøse gratis tiers:

| Tjeneste | Gratis tier | Estimat for bleikoya.net |
|----------|-------------|--------------------------|
| Sentry | 5,000 events/mnd | ~100-500 events/mnd |
| Grafana Loki | 50 GB logs/mnd | ~1-5 GB/mnd |

Med lav trafikk vil nettstedet trolig holde seg godt innenfor gratis tier.

## 9. Videre utvikling

- Legg til alerting i Grafana for kritiske errors
- Sett opp Slack/email-notifikasjoner fra Sentry
- Opprett custom dashboards for performance monitoring
- Integrer med frontend error tracking (Sentry JavaScript SDK)
