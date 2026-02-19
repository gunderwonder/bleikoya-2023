"""FastAPI server for the Bleikøya chat agent (Claude Agent SDK)."""

import json
import os
from collections.abc import AsyncIterator
from datetime import date
from pathlib import Path

from dotenv import load_dotenv
from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import StreamingResponse
from fastapi.staticfiles import StaticFiles

from claude_agent_sdk import (
    query,
    ClaudeAgentOptions,
    AssistantMessage,
    UserMessage,
    ResultMessage,
    TextBlock,
    ToolUseBlock,
)

from .prompts import SYSTEM_PROMPT
from .tools import configure as configure_tools, wp_mcp_server

# Load agent/.env first, then theme root .env for Google vars
load_dotenv()
load_dotenv(Path(__file__).parent.parent.parent / ".env")

# The SDK spawns Claude Code CLI as subprocess. If our own process was launched
# from within Claude Code, the CLAUDECODE env var blocks nested sessions.
os.environ.pop("CLAUDECODE", None)

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["https://bleikoya.test"],
    allow_methods=["POST", "OPTIONS"],
    allow_headers=["Content-Type"],
)

WP_BASE_URL = os.environ.get("WP_BASE_URL", "https://bleikoya.test")
WP_AUTH = (os.environ["WP_USER"], os.environ["WP_APPLICATION_PASSWORD"])
MODEL = "claude-sonnet-4-5-20250929"

# Resolve Google credentials path (relative to theme root or absolute)
_google_creds = os.environ.get("GOOGLE_APPLICATION_CREDENTIALS", "")
if _google_creds.startswith("./"):
    _google_creds = str(Path(__file__).parent.parent.parent / _google_creds[2:])

# Configure all MCP tool functions
configure_tools(
    WP_BASE_URL,
    WP_AUTH,
    google_creds_path=_google_creds,
    google_drive_id=os.environ.get("GOOGLE_SHARED_DRIVE_ID", ""),
)


def sse(event: str, data: dict) -> str:
    return f"event: {event}\ndata: {json.dumps(data)}\n\n"


async def _prompt_stream(text: str) -> AsyncIterator[dict]:
    """Wrap a string prompt as an async iterable of user messages.

    The SDK closes stdin immediately for string prompts, which breaks
    bidirectional MCP control protocol. Using an async iterable prompt
    keeps stdin open until the result is received.
    """
    yield {
        "type": "user",
        "session_id": "",
        "message": {"role": "user", "content": text},
        "parent_tool_use_id": None,
    }


@app.post("/chat")
async def chat(request: Request):
    body = await request.json()
    user_messages = body.get("messages", [])

    prompt_text = _build_prompt(user_messages)
    system = SYSTEM_PROMPT.format(today=date.today().isoformat())

    options = ClaudeAgentOptions(
        model=MODEL,
        system_prompt=system,
        mcp_servers={"wp": wp_mcp_server},
        allowed_tools=["mcp__wp__search", "mcp__wp__get_post",
                       "mcp__wp__drive_search", "mcp__wp__drive_read_doc"],
        disallowed_tools=["Bash", "Read", "Write", "Edit", "Glob", "Grep",
                          "WebFetch", "WebSearch", "Task", "Skill",
                          "NotebookEdit", "EnterPlanMode"],
        permission_mode="bypassPermissions",
        max_turns=10,
        include_partial_messages=True,
    )

    async def event_stream():
        sent_text_len = 0
        seen_tool_ids: set[str] = set()

        try:
            async for message in query(
                prompt=_prompt_stream(prompt_text), options=options
            ):
                if isinstance(message, AssistantMessage):
                    for block in message.content:
                        if isinstance(block, TextBlock):
                            new_text = block.text[sent_text_len:]
                            if new_text:
                                yield sse("text", {"text": new_text})
                                sent_text_len = len(block.text)
                        elif isinstance(block, ToolUseBlock):
                            if block.id not in seen_tool_ids:
                                seen_tool_ids.add(block.id)
                                yield sse("tool_start", {
                                    "tool": block.name,
                                    "input": block.input,
                                })

                elif isinstance(message, UserMessage):
                    # Tool results sent back to Claude — a tool round completed
                    if seen_tool_ids:
                        yield sse("tool_done", {})
                        seen_tool_ids.clear()
                    sent_text_len = 0

                elif isinstance(message, ResultMessage):
                    if seen_tool_ids:
                        yield sse("tool_done", {})
                        seen_tool_ids.clear()

        except Exception as e:
            yield sse("error", {"error": str(e)})

        if seen_tool_ids:
            yield sse("tool_done", {})
        yield sse("done", {})

    return StreamingResponse(
        event_stream(),
        media_type="text/event-stream",
        headers={
            "Cache-Control": "no-cache",
            "X-Accel-Buffering": "no",
        },
    )


def _build_prompt(messages: list[dict]) -> str:
    """Build a single prompt string from the conversation history.

    Since query() is stateless, we pack prior messages as context
    so Claude sees the conversation history.
    """
    if not messages:
        return ""

    if len(messages) == 1:
        return messages[0]["content"]

    # Format prior messages as context, then add the latest question
    parts = []
    for msg in messages[:-1]:
        role = "Bruker" if msg["role"] == "user" else "Assistent"
        parts.append(f"{role}: {msg['content']}")

    context = "\n".join(parts)
    last = messages[-1]["content"]

    return (
        f"Tidligere i samtalen:\n{context}\n\n"
        f"Nytt spørsmål fra brukeren:\n{last}"
    )


# Serve static files (chat UI) — must be mounted last so /chat route takes priority
static_dir = Path(__file__).parent.parent / "static"
app.mount("/", StaticFiles(directory=static_dir, html=True), name="static")


def main():
    import uvicorn
    uvicorn.run("agent.server:app", host="127.0.0.1", port=8000, reload=True)
