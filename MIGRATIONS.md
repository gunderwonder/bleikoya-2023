# Migreringer

Manuelle endringer som må kjøres ved deploy. Hver migrering er knyttet til en commit.

---

## 2026-01-02: Google Docs med tabellstøtte

**Commit:** `Add Python-based markdown to Google Docs with table support`

### Forutsetninger

For å opprette Google Docs-dokumenter fra markdown med tabeller kreves `uv` (Python package manager).

### Lokal installasjon

```bash
# macOS/Linux
curl -LsSf https://astral.sh/uv/install.sh | sh
```

### Produksjonsserver

```bash
ssh bleikoya.net@ssh.bleikoya.net
curl -LsSf https://astral.sh/uv/install.sh | sh
```

Verifiser at uv er installert:
```bash
uv --version
```

### Bruk

Se `.claude/skills/google-drive-docs.md` for dokumentasjon.

---

## 2026-01-01: Dugnadsoversikt til Google Sheets

**Commit:** `Add dugnad tracking export to Google Sheets`

### Forutsetninger

Google Sheets-eksporten krever:
1. En Service Account i Google Cloud Console
2. Service Account lagt til i Shared Drive med "Content Manager"-rolle
3. Credentials-fil (JSON) på serveren

### Sikker oppsett av credentials på produksjon

**1. Koble til serveren via SSH:**
```bash
ssh bleikoya.net@ssh.bleikoya.net
```

**2. Opprett en skjult mappe for secrets:**
```bash
mkdir -p /www/.secrets
chmod 700 /www/.secrets
```

**3. Opprett credentials-filen:**
```bash
nano /www/.secrets/google-credentials.json
```
- Åpne `secrets/google-credentials.json` lokalt
- Kopier hele innholdet
- Lim inn i nano på serveren
- Lagre med Ctrl+O, avslutt med Ctrl+X

```bash
chmod 600 /www/.secrets/google-credentials.json
```

**4. Beskytt mappen fra web-tilgang:**
```bash
echo "Deny from all" > /www/.secrets/.htaccess
```

**5. Oppdater .env i temaet:**
```bash
nano /www/wp-content/themes/bleikoya-2023/.env
```

**NB: Bruk full path, ikke /www/ (symlink fungerer ikke i web-kontekst):**
```
GOOGLE_APPLICATION_CREDENTIALS=/customers/4/4/4/bleikoya.net/httpd.www/.secrets/google-credentials.json
GOOGLE_SHARED_DRIVE_ID=0AETdcDN7VmqDUk9PVA
```

**6. Test at det fungerer:**
- Gå til WordPress Admin → Brukere
- Klikk "Opprett dugnadsoversikt"
- Sjekk at regnearket opprettes i Shared Drive

### Sikkerhetsnotater

- **Aldri** commit credentials til git
- **Aldri** send credentials over usikre kanaler (epost, chat)
- Credentials-filen inneholder en privat nøkkel som gir full tilgang til Service Account
- Ved mistanke om lekkasje: Slett nøkkelen i Google Cloud Console og lag ny
- Bruk full path (`/customers/...`) i .env, ikke `/www/` symlinken

---

## 2024-12-14: Lenke-posttype (link)

**Commit:** `Add link post type for external bookmarks`

### Må kjøres etter deploy:

```bash
# Deaktiver gammel Link Manager
wp option update link_manager_enabled 0

# Flush rewrite rules for ny posttype
wp rewrite flush
```

### Hva ble gjort:
- Ny posttype `link` for eksterne lenker/bokmerker
- Lenker vises i autocomplete-søk med ekstern URL
- Lenker vises på infosider under hver kategori
- Eksterne lenker åpnes i ny fane med ↗-ikon
