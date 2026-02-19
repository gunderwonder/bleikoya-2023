FROM python:3.12-slim

COPY --from=ghcr.io/astral-sh/uv:latest /uv /usr/local/bin/uv

# Create non-root user (Claude Code CLI refuses --dangerously-skip-permissions as root)
RUN useradd --create-home agent
USER agent

WORKDIR /home/agent/app

# Copy dependency files first for caching
COPY --chown=agent pyproject.toml uv.lock ./

# Install dependencies (frozen from lockfile)
RUN uv sync --frozen --no-dev --no-editable

# Copy application code and static assets
COPY --chown=agent agent/ agent/
COPY --chown=agent static/ static/

EXPOSE 8000

CMD ["uv", "run", "uvicorn", "agent.server:app", "--host", "0.0.0.0", "--port", "8000"]
