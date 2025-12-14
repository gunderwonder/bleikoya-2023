# Migreringer

Manuelle endringer som må kjøres ved deploy. Hver migrering er knyttet til en commit.

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
