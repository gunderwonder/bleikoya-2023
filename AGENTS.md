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
- **Health Checks**: Integration tests in `test/health-check.php`
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
│   │   └── location.php   # Kartpunkt post type & gruppe taxonomy
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
├── test/                  # Integration tests
│   └── health-check.php   # HTTP endpoint health checks
├── vendor/                # Composer dependencies (currently committed)
├── composer.json          # Composer dependencies
├── author.php             # Author page template (cabin owner info)
├── page-kart.php          # Interactive map page template
├── page-stilguide.php     # Style guide template
├── MAP_DESIGN.md          # Map system documentation
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
- **Advanced Custom Fields** - Custom fields
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

## Testing
Run health checks locally:
```bash
cd test
SITE_URL="http://localhost:8888" php health-check.php
```

Tests verify:
- HTTP endpoints (homepage, admin, search, etc.)
- REST API endpoints
- iCal feed format and headers
- 404 handling

## Deployment
- **Method**: GitHub Actions workflow (`.github/workflows/deploy.yml`)
- **Trigger**: Push to `main` branch
- **Process**: SSH to server → `git pull` in theme directory
- **Auth**: SSH key stored in GitHub Secrets as `SSH_PRIVATE_KEY`
- **Server**: bleikoya.net@ssh.bleikoya.net
- **Path**: `/www/wp-content/themes/bleikoya-2023`

## Local Development
- **Local URL**: http://localhost:8888

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

## Important Notes
- iCal feed includes all upcoming featured events
- Rewrite rules require flush after theme activation (`wp rewrite flush`)
- Health check expects 301→200 redirect for `/featured-events.ics` (WordPress canonical redirect)
