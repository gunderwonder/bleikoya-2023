---
name: wp-search
description: Search bleikoya.net for content like bylaws (vedtekter), meeting notes (referater), rules, and documentation. Use when you need information from the website to inform development decisions.
---

# WordPress Content Search

Search the bleikoya.net production site for content and documentation.

## API Endpoint

Base URL: `https://bleikoya.net/wp-json/bleikoya/v1/search`

## Quick Examples

**Get dugnad rules:**
```bash
curl -s "https://bleikoya.net/wp-json/bleikoya/v1/search?type=category&category=dugnad" | jq '.category.documentation'
```

**Get vedtekter (bylaws):**
```bash
curl -s "https://bleikoya.net/wp-json/bleikoya/v1/search?type=category&category=vedtekter" | jq '.category.documentation'
```

**Search all content:**
```bash
curl -s "https://bleikoya.net/wp-json/bleikoya/v1/search?q=gebyr" | jq '.'
```

## API Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `q` | string | Search query |
| `type` | string | `all`, `posts`, `categories`, or `category` |
| `category` | string | Category slug (when type=category) |
| `limit` | int | Max results (default 10, max 50) |

## Key Categories

| Slug | Description |
|------|-------------|
| `dugnad` | Volunteer work rules (6t dugnad + 2t strandrydding) |
| `vedtekter` | Bylaws and regulations |
| `styret` | Board information |
| `generalforsamling` | Annual meeting documentation |
| `avfall` | Waste and recycling rules |
| `brannsikring` | Fire safety |

## Instructions

1. Start by searching for the relevant category if looking for rules
2. Use `type=category&category=<slug>` for full documentation
3. Use `q=<keyword>` to search across all content
4. Parse the JSON response and summarize relevant findings

## Response Format

```json
{
  "categories": [
    {
      "id": 56,
      "name": "Dugnad og Strandrydding",
      "slug": "dugnad",
      "documentation": "Full text content..."
    }
  ],
  "posts": [
    {
      "id": 123,
      "title": "Post title",
      "excerpt": "...matched text...",
      "url": "https://..."
    }
  ]
}
```
