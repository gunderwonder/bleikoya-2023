"""JWT authentication for WordPress users."""

import os

import jwt
from fastapi import HTTPException, Request

AGENT_AUTH_SECRET = os.environ.get("AGENT_AUTH_SECRET", "")


def require_auth(request: Request) -> dict:
    """Validate JWT from Authorization header. Returns decoded payload.

    When AGENT_AUTH_SECRET is empty (local development), auth is bypassed.
    """
    if not AGENT_AUTH_SECRET:
        return {"sub": "dev"}

    auth_header = request.headers.get("Authorization", "")
    if not auth_header.startswith("Bearer "):
        raise HTTPException(status_code=401, detail="Missing token")

    token = auth_header[7:]
    try:
        return jwt.decode(
            token,
            AGENT_AUTH_SECRET,
            algorithms=["HS256"],
            issuer="bleikoya.net",
        )
    except jwt.ExpiredSignatureError:
        raise HTTPException(status_code=401, detail="Token expired")
    except jwt.InvalidTokenError:
        raise HTTPException(status_code=401, detail="Invalid token")
