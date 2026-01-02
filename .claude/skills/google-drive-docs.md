# Skill: Opprett dokument i delt disk

Oppretter Google Docs-dokumenter i Bleikøya Velforenings delte disk.

## Bruk

```bash
cd /Users/gunderwonder/Prosjekter/bleikoya.net && wp eval '
$doc_title = "Dokumenttittel";
$doc_content = "Innhold her";
$doc_folder = "Mappenavn";  // valgfritt
require get_stylesheet_directory() . "/.claude/skills/google-drive-docs/create-document.php";
'
```

### Variabler

| Variabel | Påkrevd | Beskrivelse |
|----------|---------|-------------|
| `$doc_title` | Ja | Dokumentnavn |
| `$doc_content` | Nei | Tekstinnhold direkte |
| `$doc_file` | Nei | Filsti - markdown konverteres automatisk |
| `$doc_folder` | Nei | Målmappe (default: rot av delt disk) |

### Eksempler

**Enkelt dokument:**
```bash
wp eval '$doc_title = "000 Test"; require get_stylesheet_directory() . "/.claude/skills/google-drive-docs/create-document.php";'
```

**Fra markdown-fil:**
```bash
wp eval '
$doc_title = "000 Veiledning";
$doc_file = "documents/veiledning.md";
require get_stylesheet_directory() . "/.claude/skills/google-drive-docs/create-document.php";
'
```

**I spesifikk mappe:**
```bash
wp eval '
$doc_title = "2025-01-02 Referat";
$doc_folder = "021 Styremøter";
$doc_content = "Møtereferat...";
require get_stylesheet_directory() . "/.claude/skills/google-drive-docs/create-document.php";
'
```

## Forutsetninger

- Google Docs API aktivert i Google Cloud Console
- Google Drive API aktivert
- Service Account med tilgang til Shared Drive
- `.env` med `GOOGLE_APPLICATION_CREDENTIALS` og `GOOGLE_SHARED_DRIVE_ID`

## Mappestruktur

Se `documents/arkiv-veiledning.md` for navnekonvensjoner.
