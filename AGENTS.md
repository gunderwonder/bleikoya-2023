# Bleikøya 2023 WordPress Theme

## Project Overview
WordPress theme for Bleikøya Velforening (Bleikøya Residents' Association) website. Contains styling and functionality for bleikoya.net.

## Core Features
- **Posts (Oppslag)**: Standard WordPress posts
- **Events (Arrangementer)**: Using The Events Calendar plugin
- **Topics (Tema)**: Categories with custom fields
- **Image Galleries**: Photo galleries
- **Contact Forms**: Contact form functionality
- **iCal Feed**: `/featured-events.ics` - subscribable calendar feed for featured events
- **Interactive Map (Kart)**: Leaflet-based map with kartpunkt system (see `MAP_DESIGN.md`)
- **Author Pages**: Custom author template showing cabin owner info and map connections
- **Member Export**: Export member list to Excel (XLSX) or Google Sheets in Shared Drive

## Tech Stack
- PHP 8.3+
- WordPress 6.x
- The Events Calendar plugin
- Custom theme with PHP templates
- GitHub Actions for deployment

## Directory Structure
```
/
├── .github/workflows/      # GitHub Actions (auto-deploy to production)
├── .vscode/               # VS Code settings (Intelephense config)
├── includes/              # PHP functionality
│   ├── events.php         # Event calendar & iCal feed
│   ├── templating.php     # Template helpers
│   ├── utilities.php      # Utility functions
│   ├── post-types/        # Custom post types
│   │   ├── location.php   # Kartpunkt post type & gruppe taxonomy
│   │   └── link.php       # External links/bookmarks post type
│   ├── api/               # REST API endpoints
│   │   ├── location-connections.php  # Bidirectional connections
│   │   ├── location-coordinates.php  # Coordinate helpers
│   │   └── location-rest-endpoints.php
│   ├── admin/             # Admin functionality
│   │   ├── location-meta-boxes.php
│   │   ├── location-ajax.php
│   │   └── users.php      # User export, cabin owner helpers
│   └── google/            # Google Workspace integration
│       └── sheets-export.php  # Google Sheets export functionality
├── parts/                 # Template parts
│   └── post/              # Post templates (content.php, plug.php)
├── tests/                 # PHPUnit test suites
│   ├── unit/              # Unit tests (no WordPress required)
│   ├── integration/       # Integration tests (requires WP test framework)
│   ├── health/            # HTTP health checks (curl-based)
│   ├── mocks/             # Mock functions for unit tests
│   └── bootstrap.php      # Test bootstrapping
├── acf-json/              # ACF field groups (auto-synced JSON)
├── vendor/                # Composer dependencies (currently committed)
├── composer.json          # Composer dependencies
├── phpunit.xml            # PHPUnit configuration
├── author.php             # Author page template (cabin owner info)
├── page-kart.php          # Interactive map page template
├── page-stilguide.php     # Style guide template
├── MAP_DESIGN.md          # Map system documentation
├── MIGRATIONS.md          # Manual deploy steps per commit
├── TODO.md                # Project TODO list
├── functions.php          # Theme initialization
└── style.css              # Theme metadata

```

## Code Conventions
- **Function calls**: Use direct function calls for The Events Calendar functions (e.g., `tribe_get_events()`)
  - Do NOT use `function_exists()` guards or `call_user_func()`
  - Intelephense is configured to include plugin code without diagnostics
- **PHP Style**: WordPress coding standards
- **File naming**: lowercase with hyphens (e.g., `health-check.php`)

## Dependencies

### WordPress Plugins (Active)
<!-- To update: wp plugin list --format=table --path=/Users/gunderwonder/Prosjekter/bleikoya.net -->
- **The Events Calendar** - Event functionality
  - Functions: `tribe_get_events()`, `tribe_get_venue()`, `tribe_get_event_cat_ids()`, etc.
  - Custom meta fields: `_EventAllDay`, `_EventStartDate`, `_EventEndDate`
- **Advanced Custom Fields** - Custom fields (synced to `acf-json/`)
  - Field groups: Personalia (users), Kategori (categories), Media
  - To sync: edit field group in admin → save → JSON auto-exported
- **Contact Form 7** - Contact forms
- **Error Log Monitor** - Error monitoring
- **F4 Media Taxonomies** - Media taxonomy support
- **Lazy Blocks** - Custom Gutenberg blocks
- **Really Simple Captcha** - Captcha for forms
- **Safe SVG** - SVG upload support
- **Simple Local Avatars** - Local avatar uploads
- **WP Super Cache** - Caching

### WordPress Core
- Standard WP functions and hooks

### Composer Packages
- `phpoffice/phpspreadsheet` - Excel export
- `google/apiclient` - Google Sheets/Drive integration
- `sentry/sentry` - Error logging
- `monolog/monolog` - Logging
- `guzzlehttp/guzzle` - HTTP client
- `vlucas/phpdotenv` - Environment configuration
- `phpunit/phpunit` - Testing framework (dev)

## Testing

The theme uses PHPUnit with three test suites.

### Composer scripts (recommended)
```bash
composer test              # Run all tests
composer test:unit         # Unit tests only
composer test:health       # Health checks (local site)
composer test:health:core  # Core health checks only
composer test:health:prod  # Health checks against production
composer test:integration  # Integration tests (requires WP test framework)
```

### Direct PHPUnit
```bash
./vendor/bin/phpunit                      # All tests
./vendor/bin/phpunit --testsuite Unit     # Unit tests
./vendor/bin/phpunit --testsuite HealthCheck  # Health checks
```

### Test suites

| Suite | Location | Description |
|-------|----------|-------------|
| **Unit** | `tests/unit/` | Pure PHP tests with mocked WordPress functions |
| **HealthCheck** | `tests/health/` | HTTP endpoint tests against running site |
| **Integration** | `tests/integration/` | Full WordPress integration tests |

### Health checks verify
- HTTP endpoints (homepage, admin, search, events, etc.)
- REST API endpoints
- iCal feed format and headers (`/featured-events.ics`)
- 404 handling
- Content-type headers

### CI/CD
PHPUnit tests run automatically via GitHub Actions on push/PR (see `.github/workflows/phpunit.yml`)

## Deployment
- **Method**: GitHub Actions workflow (`.github/workflows/deploy.yml`)
- **Trigger**: Push to `main` branch
- **Process**: SSH to server → `git pull` in theme directory
- **Auth**: SSH key stored in GitHub Secrets as `SSH_PRIVATE_KEY`
- **Server**: bleikoya.net@ssh.bleikoya.net
- **Path**: `/www/wp-content/themes/bleikoya-2023`

## Local Development
- **Local URL**: https://bleikoya.test

## Style Guide
There is a style guide at `/stilguide` (template: `page-stilguide.php`).

**Update the style guide when making significant changes to:**
- CSS variables/colors in `assets/css/tralla.css`
- New or modified components (`.b-*` classes)
- Typography, icons, layout structures

## Google Workspace Integration

The theme integrates with Google Workspace for Nonprofits to export the member list directly to a Shared Drive.

### Setup

**1. Google Cloud Console:**
- Create project at [console.cloud.google.com](https://console.cloud.google.com)
- Enable **Google Sheets API** and **Google Drive API**
- Create Service Account (IAM & Admin → Service Accounts)
- Download JSON credentials file

**2. Shared Drive Access:**
- Add Service Account email (from JSON: `client_email`) to Shared Drive
- Give role: **Content Manager**

**3. Environment Configuration:**

Local (`.env`):
```
GOOGLE_APPLICATION_CREDENTIALS=secrets/google-credentials.json
GOOGLE_SHARED_DRIVE_ID=<drive-id-from-url>
```

Production (`.env`):
```
GOOGLE_APPLICATION_CREDENTIALS=/www/google-credentials.json
GOOGLE_SHARED_DRIVE_ID=<drive-id-from-url>
```

**4. Usage:**
- Go to WordPress Admin → Users
- Click "Eksporter til Google Sheets"
- A new spreadsheet is created in the Shared Drive with the current date

### Files
- `includes/google/sheets-export.php` - Export logic
- `admin/export-user-data-google.php` - AJAX endpoint
- `includes/admin/users.php` - Admin UI button

## Browser Debugging with Chrome DevTools MCP

This project has Chrome DevTools MCP configured for browser-based debugging and testing.

### Starting Chrome Canary with Remote Debugging
```bash
/Applications/Google\ Chrome\ Canary.app/Contents/MacOS/Google\ Chrome\ Canary --remote-debugging-port=9222 https://bleikoya.test/kart/
```

**Note:** Always use Chrome Canary (not regular Chrome) for debugging.

### Capabilities
- **Screenshots**: Visual verification of page state
- **Click/interact**: Test UI elements programmatically
- **Console logs**: View JavaScript errors and warnings
- **Network requests**: Debug API calls
- **Resize**: Test responsive layouts

### When to Use (actively)
- **Debugging**: Checking console for JavaScript errors after changes
- **Investigating bugs**: Issues that only reproduce in browser
- **Verifying fixes**: Confirming a specific bug is resolved

### When NOT to Use
- **General testing after changes**: Ask the user first - they can often test faster manually
- **Visual verification**: User can check the result themselves
- **Simple UI changes**: Let the user verify styling, layout changes etc.

### Common Commands
- `mcp__chrome-devtools__take_screenshot` - Capture current viewport
- `mcp__chrome-devtools__take_snapshot` - Get accessibility tree (element UIDs for clicking)
- `mcp__chrome-devtools__click` - Click element by UID
- `mcp__chrome-devtools__list_console_messages` - View JS console output
- `mcp__chrome-devtools__evaluate_script` - Run JavaScript in page context

## Important Notes
- iCal feed includes all upcoming featured events
- Rewrite rules require flush after theme activation (`wp rewrite flush`)
- `/featured-events.ics` redirects 301→200 (WordPress canonical redirect adds trailing slash)
