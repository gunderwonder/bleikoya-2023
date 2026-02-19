"""WordPress tools for the Bleikøya chat agent."""

import html
import re

import httpx
from typing import Any

WP_SEARCH_TOOL = {
    "name": "search_bleikoya",
    "description": (
        "Søk etter innhold på Bleikøya Velforening sin nettside. "
        "Kan søke etter oppslag (posts), kategoridokumentasjon og arrangementer (events)."
    ),
    "input_schema": {
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
}


WP_GET_POST_TOOL = {
    "name": "get_post",
    "description": (
        "Hent fullt innhold fra et spesifikt innlegg/oppslag på nettsiden via post-ID. "
        "Bruk dette etter å ha søkt for å lese hele innholdet i et innlegg."
    ),
    "input_schema": {
        "type": "object",
        "properties": {
            "post_id": {
                "type": "integer",
                "description": "Post-ID fra søkeresultatene.",
            },
        },
        "required": ["post_id"],
    },
}

ALL_TOOLS = [WP_SEARCH_TOOL, WP_GET_POST_TOOL]


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
    # Strip HTML to plain text
    text = re.sub(r"<[^>]+>", "", content_html)
    text = html.unescape(text).strip()
    # Collapse excessive whitespace
    text = re.sub(r"\n{3,}", "\n\n", text)

    return f"# {title}\n\n{text}"


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
