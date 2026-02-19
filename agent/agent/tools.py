"""MCP tools for the Bleikøya chat agent (WordPress + Google Drive)."""

import html
import json
import re
from pathlib import Path

import httpx
from typing import Any

from google.oauth2 import service_account
from googleapiclient.discovery import build

from claude_agent_sdk import tool, create_sdk_mcp_server

# Module-level config — set by server.py at startup
WP_BASE_URL = ""
WP_AUTH = ("", "")
GOOGLE_CREDENTIALS = None
GOOGLE_SHARED_DRIVE_ID = ""


def configure(
    base_url: str,
    auth: tuple[str, str],
    google_creds_path: str,
    google_drive_id: str,
):
    """Set connection details before the MCP server is used."""
    global WP_BASE_URL, WP_AUTH, GOOGLE_CREDENTIALS, GOOGLE_SHARED_DRIVE_ID
    WP_BASE_URL = base_url
    WP_AUTH = auth
    GOOGLE_SHARED_DRIVE_ID = google_drive_id

    creds_path = Path(google_creds_path)
    GOOGLE_CREDENTIALS = service_account.Credentials.from_service_account_file(
        str(creds_path),
        scopes=[
            "https://www.googleapis.com/auth/drive.readonly",
            "https://www.googleapis.com/auth/spreadsheets.readonly",
        ],
    )


# ── WordPress functions ────────────────────────────────────────────────────


async def execute_search(
    input: dict[str, Any],
    base_url: str,
    auth: tuple[str, str],
) -> str:
    """Execute a WordPress content search and return the raw JSON response."""
    params: dict[str, Any] = {"limit": 10}

    if input.get("query"):
        params["q"] = input["query"]
    if input.get("type"):
        params["type"] = input["type"]
    if input.get("category"):
        params["category"] = input["category"]
    if input.get("after"):
        params["after"] = input["after"]
    if input.get("before"):
        params["before"] = input["before"]

    async with httpx.AsyncClient(verify=False) as client:
        response = await client.get(
            f"{base_url}/wp-json/bleikoya/v1/search",
            params=params,
            auth=auth,
            timeout=15.0,
        )
        response.raise_for_status()
        return response.text


async def execute_get_post(
    input: dict[str, Any],
    base_url: str,
    auth: tuple[str, str],
) -> str:
    """Fetch full post content by ID and return as plain text."""
    post_id = input["post_id"]

    async with httpx.AsyncClient(verify=False) as client:
        response = await client.get(
            f"{base_url}/wp-json/wp/v2/posts/{post_id}",
            params={"context": "view"},
            auth=auth,
            timeout=15.0,
        )
        response.raise_for_status()
        data = response.json()

    title = html.unescape(data["title"]["rendered"])
    content_html = data["content"]["rendered"]
    text = re.sub(r"<[^>]+>", "", content_html)
    text = html.unescape(text).strip()
    text = re.sub(r"\n{3,}", "\n\n", text)

    return f"# {title}\n\n{text}"


# ── Google Drive functions ─────────────────────────────────────────────────

MIME_LABELS = {
    "application/vnd.google-apps.document": "Google Docs",
    "application/vnd.google-apps.spreadsheet": "Google Sheets",
    "application/vnd.google-apps.presentation": "Google Slides",
    "application/pdf": "PDF",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document": "Word",
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet": "Excel",
}


def _drive_service():
    return build("drive", "v3", credentials=GOOGLE_CREDENTIALS)


def _sheets_service():
    return build("sheets", "v4", credentials=GOOGLE_CREDENTIALS)


def execute_drive_search(input: dict[str, Any]) -> str:
    """Search files in the Shared Drive."""
    query_parts = []

    q = input.get("query", "")
    if q:
        # Use fullText search for content and name search as fallback
        query_parts.append(f"(fullText contains '{q}' or name contains '{q}')")

    query_parts.append("trashed = false")

    drive = _drive_service()
    results = drive.files().list(
        q=" and ".join(query_parts),
        corpora="drive",
        driveId=GOOGLE_SHARED_DRIVE_ID,
        includeItemsFromAllDrives=True,
        supportsAllDrives=True,
        fields="files(id, name, mimeType, modifiedTime, webViewLink, parents)",
        pageSize=15,
        orderBy="modifiedTime desc",
    ).execute()

    files = results.get("files", [])
    if not files:
        return json.dumps({"results": [], "message": "Ingen filer funnet."})

    # Resolve parent folder names for context
    parent_ids = {p for f in files if f.get("parents") for p in f["parents"]}
    parent_names = {}
    for pid in parent_ids:
        try:
            folder = drive.files().get(
                fileId=pid,
                fields="name",
                supportsAllDrives=True,
            ).execute()
            parent_names[pid] = folder["name"]
        except Exception:
            parent_names[pid] = "Ukjent mappe"

    items = []
    for f in files:
        folder = ""
        if f.get("parents"):
            folder = parent_names.get(f["parents"][0], "")

        items.append({
            "id": f["id"],
            "name": f["name"],
            "type": MIME_LABELS.get(f.get("mimeType", ""), f.get("mimeType", "")),
            "modified": f.get("modifiedTime", ""),
            "url": f.get("webViewLink", ""),
            "folder": folder,
        })

    return json.dumps({"results": items}, ensure_ascii=False)


def execute_drive_read_doc(input: dict[str, Any]) -> str:
    """Read the content of a Google Drive file."""
    file_id = input["file_id"]
    drive = _drive_service()

    # Get file metadata to determine type
    meta = drive.files().get(
        fileId=file_id,
        fields="name, mimeType",
        supportsAllDrives=True,
    ).execute()

    name = meta["name"]
    mime = meta["mimeType"]

    # Google Docs → export as plain text
    if mime == "application/vnd.google-apps.document":
        content = drive.files().export(
            fileId=file_id,
            mimeType="text/plain",
        ).execute()
        text = content.decode("utf-8") if isinstance(content, bytes) else content
        return f"# {name}\n\n{text}"

    # Google Sheets → read via Sheets API
    if mime == "application/vnd.google-apps.spreadsheet":
        sheets = _sheets_service()
        spreadsheet = sheets.spreadsheets().get(
            spreadsheetId=file_id
        ).execute()

        parts = [f"# {name}\n"]
        for sheet in spreadsheet["sheets"]:
            sheet_name = sheet["properties"]["title"]
            result = sheets.spreadsheets().values().get(
                spreadsheetId=file_id,
                range=sheet_name,
            ).execute()
            values = result.get("values", [])

            parts.append(f"\n## {sheet_name}\n")
            if not values:
                parts.append("(tom)\n")
            else:
                for row in values[:100]:  # Cap at 100 rows
                    parts.append(" | ".join(str(cell) for cell in row))

        return "\n".join(parts)

    # Other formats (PDF, Word, etc.) → try export as text
    try:
        content = drive.files().export(
            fileId=file_id,
            mimeType="text/plain",
        ).execute()
        text = content.decode("utf-8") if isinstance(content, bytes) else content
        return f"# {name}\n\n{text}"
    except Exception:
        return f"Kan ikke lese innholdet i «{name}» (type: {mime}). Prøv å åpne filen direkte i Google Drive."


# ── MCP tool wrappers ──────────────────────────────────────────────────────


@tool(
    "search",
    "Søk etter innhold på Bleikøya Velforening sin nettside (bleikoya.net). "
    "Kan søke etter oppslag (posts), kategoridokumentasjon og arrangementer (events).",
    {
        "type": "object",
        "properties": {
            "query": {
                "type": "string",
                "description": "Søkeord (f.eks. 'dugnad', 'vedtekter', 'båtplass')",
            },
            "type": {
                "type": "string",
                "enum": ["all", "posts", "categories", "category", "events"],
                "description": (
                    "Type innhold å søke i. "
                    "Bruk 'category' sammen med category-parameter for å hente full dokumentasjon for en kategori."
                ),
            },
            "category": {
                "type": "string",
                "description": "Kategori-slug (brukes med type='category'). F.eks. 'dugnad', 'vedtekter', 'styret'.",
            },
            "after": {
                "type": "string",
                "description": "For arrangementer: bare vis arrangementer etter denne datoen (YYYY-MM-DD).",
            },
            "before": {
                "type": "string",
                "description": "For arrangementer: bare vis arrangementer før denne datoen (YYYY-MM-DD).",
            },
        },
        "required": [],
    },
)
async def search_tool(args: dict[str, Any]) -> dict[str, Any]:
    result = await execute_search(args, WP_BASE_URL, WP_AUTH)
    return {"content": [{"type": "text", "text": result}]}


@tool(
    "get_post",
    "Hent fullt innhold fra et spesifikt innlegg/oppslag på nettsiden via post-ID. "
    "Bruk dette etter å ha søkt for å lese hele innholdet i et innlegg.",
    {
        "type": "object",
        "properties": {
            "post_id": {
                "type": "integer",
                "description": "Post-ID fra søkeresultatene.",
            },
        },
        "required": ["post_id"],
    },
)
async def get_post_tool(args: dict[str, Any]) -> dict[str, Any]:
    result = await execute_get_post(args, WP_BASE_URL, WP_AUTH)
    return {"content": [{"type": "text", "text": result}]}


@tool(
    "drive_search",
    "Søk i Bleikøya Velforenings dokumentarkiv i Google Drive. "
    "Finner dokumenter, regneark, referater, avtaler og annen dokumentasjon. "
    "Søker i både filnavn og innhold.",
    {
        "type": "object",
        "properties": {
            "query": {
                "type": "string",
                "description": "Søkeord (f.eks. 'vaktmester avtale', 'regnskap 2024', 'strømnett')",
            },
        },
        "required": ["query"],
    },
)
async def drive_search_tool(args: dict[str, Any]) -> dict[str, Any]:
    result = execute_drive_search(args)
    return {"content": [{"type": "text", "text": result}]}


@tool(
    "drive_read_doc",
    "Les innholdet i et dokument fra Google Drive-arkivet. "
    "Bruk file_id fra drive_search-resultatene.",
    {
        "type": "object",
        "properties": {
            "file_id": {
                "type": "string",
                "description": "Google Drive file ID fra søkeresultatene.",
            },
        },
        "required": ["file_id"],
    },
)
async def drive_read_doc_tool(args: dict[str, Any]) -> dict[str, Any]:
    result = execute_drive_read_doc(args)
    return {"content": [{"type": "text", "text": result}]}


# ── MCP server ─────────────────────────────────────────────────────────────

wp_mcp_server = create_sdk_mcp_server(
    name="wp",
    tools=[search_tool, get_post_tool, drive_search_tool, drive_read_doc_tool],
)
