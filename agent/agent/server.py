"""FastAPI server for the Bleikøya chat agent."""

import json
import os
from datetime import date
from pathlib import Path

import anthropic
from dotenv import load_dotenv
from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import StreamingResponse
from fastapi.staticfiles import StaticFiles

from .prompts import SYSTEM_PROMPT
from .tools import ALL_TOOLS, execute_search, execute_get_post

load_dotenv()

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["https://bleikoya.test"],
    allow_methods=["POST", "OPTIONS"],
    allow_headers=["Content-Type"],
)

client = anthropic.AsyncAnthropic(api_key=os.environ["ANTHROPIC_API_KEY"])

WP_BASE_URL = os.environ.get("WP_BASE_URL", "https://bleikoya.test")
WP_AUTH = (os.environ["WP_USER"], os.environ["WP_APPLICATION_PASSWORD"])
MODEL = "claude-sonnet-4-5-20250929"
MAX_TOOL_ROUNDS = 5


@app.post("/chat")
async def chat(request: Request):
    body = await request.json()
    messages = body.get("messages", [])
    system = SYSTEM_PROMPT.format(today=date.today().isoformat())

    async def event_stream():
        nonlocal messages
        tool_rounds = 0

        try:
            while tool_rounds < MAX_TOOL_ROUNDS:
                async with client.messages.stream(
                    model=MODEL,
                    max_tokens=2048,
                    system=system,
                    tools=ALL_TOOLS,
                    messages=messages,
                ) as stream:
                    async for event in stream:
                        if event.type == "content_block_start":
                            if event.content_block.type == "tool_use":
                                yield f"event: tool_start\ndata: {json.dumps({'tool': event.content_block.name})}\n\n"

                        elif event.type == "content_block_delta":
                            if event.delta.type == "text_delta":
                                yield f"event: text\ndata: {json.dumps({'text': event.delta.text})}\n\n"

                    final_message = await stream.get_final_message()

                if final_message.stop_reason == "tool_use":
                    tool_rounds += 1

                    # Add assistant message (only include API-accepted fields)
                    content = []
                    for block in final_message.content:
                        if block.type == "text":
                            content.append({"type": "text", "text": block.text})
                        elif block.type == "tool_use":
                            content.append({"type": "tool_use", "id": block.id, "name": block.name, "input": block.input})
                    messages.append({"role": "assistant", "content": content})

                    # Execute all tool calls
                    tool_results = []
                    for block in final_message.content:
                        if block.type == "tool_use":
                            try:
                                if block.name == "search_bleikoya":
                                    result = await execute_search(block.input, WP_BASE_URL, WP_AUTH)
                                elif block.name == "get_post":
                                    result = await execute_get_post(block.input, WP_BASE_URL, WP_AUTH)
                                else:
                                    result = json.dumps({"error": f"Unknown tool: {block.name}"})
                            except Exception as e:
                                result = json.dumps({"error": str(e)})
                            tool_results.append({
                                "type": "tool_result",
                                "tool_use_id": block.id,
                                "content": result,
                            })

                    messages.append({"role": "user", "content": tool_results})
                    yield f"event: tool_done\ndata: {json.dumps({'tool': 'search_bleikoya'})}\n\n"
                else:
                    break
        except anthropic.AuthenticationError:
            yield f"event: error\ndata: {json.dumps({'error': 'Ugyldig API-nøkkel. Sjekk ANTHROPIC_API_KEY i .env'})}\n\n"
        except Exception as e:
            yield f"event: error\ndata: {json.dumps({'error': str(e)})}\n\n"

        yield "event: done\ndata: {}\n\n"

    return StreamingResponse(
        event_stream(),
        media_type="text/event-stream",
        headers={
            "Cache-Control": "no-cache",
            "X-Accel-Buffering": "no",
        },
    )


# Serve static files (chat UI) — must be mounted last so /chat route takes priority
static_dir = Path(__file__).parent.parent / "static"
app.mount("/", StaticFiles(directory=static_dir, html=True), name="static")


def main():
    import uvicorn
    uvicorn.run("agent.server:app", host="127.0.0.1", port=8000, reload=True)
