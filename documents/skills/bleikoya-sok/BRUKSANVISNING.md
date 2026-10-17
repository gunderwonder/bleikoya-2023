# Bruksanvisning: søk på bleikoya.net fra Claude

Dette er en «skill» for Claude Code som lar Claude søke i innholdet på bleikoya.net, inkludert de private innleggene (styrereferater, innkallinger, styredokumenter). Du trenger tre ting: et applikasjonspassord fra nettsidene, en liten fil med innloggingen på din egen maskin, og selve skillen i Claude Code-mappa di. Det tar ti minutter.

Skillen bruker bare lesetilgang. Den kan ikke endre, publisere eller slette noe.

## 1. Lag et applikasjonspassord på bleikoya.net

Et applikasjonspassord er et eget passord som bare gir programmer tilgang via API-et. Det vanlige passordet ditt forblir hemmelig, og du kan trekke tilbake applikasjonspassordet når som helst uten at det påvirker innloggingen din ellers.

1. Logg inn på https://bleikoya.net/wp-admin/
2. Gå til **Brukere → Profil** (eller klikk navnet ditt øverst til høyre).
3. Bla helt ned til **Applikasjonspassord**.
4. Skriv et navn i feltet, f.eks. `Claude`, og klikk **Legg til nytt applikasjonspassord**.
5. WordPress viser passordet én gang, som 24 tegn i grupper på fire (`abcd efgh ijkl mnop qrst uvwx`). Kopier det med en gang. Du får ikke se det igjen, men du kan alltid lage et nytt.

Noter også brukernavnet ditt. Det står under «Brukernavn» øverst på samme side.

## 2. Lagre innloggingen på maskinen din

Åpne Terminal (ligger i Programmer → Verktøy) og lim inn, linje for linje. Bytt ut brukernavn og passord med dine:

```bash
mkdir -p ~/.config/bleikoya
cat > ~/.config/bleikoya/credentials <<'EOT'
BLEIKOYA_USER=brukernavnet-ditt
BLEIKOYA_APP_PASSWORD=abcd efgh ijkl mnop qrst uvwx
EOT
chmod 600 ~/.config/bleikoya/credentials
```

Mellomrommene i passordet kan stå. Siste linje gjør at bare din bruker på maskinen kan lese fila.

## 3. Legg skillen i Claude Code

Claude Code leser skills fra mappa `~/.claude/skills/`. Mappa `bleikoya-sok` (den du fikk denne bruksanvisningen i) skal ligge der:

```bash
mkdir -p ~/.claude/skills
cp -R /sti/til/bleikoya-sok ~/.claude/skills/
```

Bytt ut `/sti/til/bleikoya-sok` med der du pakket ut mappa, f.eks. `~/Downloads/bleikoya-sok`. Etterpå skal `~/.claude/skills/bleikoya-sok/SKILL.md` finnes.

Fikk du en `.skill`-fil i stedet for en mappe, er det en vanlig zip-fil. Pakk den ut (dobbeltklikk, eller `unzip bleikoya-sok.skill`), så får du mappa.

## 4. Test at det virker

I Terminal:

```bash
bash ~/.claude/skills/bleikoya-sok/scripts/sok.sh --sjekk
```

Svaret skal være `OK: Innlogget som <brukernavn>. Private innlegg blir med i søk.` Står det `IKKE INNLOGGET` eller `FEIL`, er det som regel en skrivefeil i fila fra steg 2.

Så i Claude Code (start den på nytt hvis den alt var åpen): spør om noe som står på nettsidene, f.eks. «hva sier vedtektene om framleie?» eller «finn referatet fra siste styremøte». Claude skal da bruke skillen av seg selv. Du kan også kalle den direkte med `/bleikoya-sok` etterfulgt av det du lurer på.

## Hvis noe går galt

- **«Innloggingen ble avvist»**: Sjekk at brukernavnet er riktig (ikke e-postadressen), og at passordet er kopiert komplett. Lag evt. et nytt applikasjonspassord og oppdater fila.
- **Private innlegg mangler**: Kjør `--sjekk` som i steg 4. Står det `IKKE INNLOGGET`, finner ikke skriptet fila, eller den er tom.
- **Claude bruker ikke skillen**: Sjekk at mappa ligger på riktig sted (`ls ~/.claude/skills/bleikoya-sok`), og start Claude Code på nytt.

## Trekke tilbake tilgangen

Gå til Brukere → Profil → Applikasjonspassord på bleikoya.net og klikk **Tilbakekall** ved passordet som heter Claude. Da slutter skillen å virke med en gang. Slett gjerne fila `~/.config/bleikoya/credentials` også.

## Hva skillen gjør, teknisk

Skillen består av to filer. `SKILL.md` forteller Claude hvordan søket virker og når det skal brukes. `scripts/sok.sh` kaller søke-API-et på `https://bleikoya.net/wp-json/bleikoya/v1/search` med innloggingen fra fila di. Ingenting sendes andre steder enn til bleikoya.net.
