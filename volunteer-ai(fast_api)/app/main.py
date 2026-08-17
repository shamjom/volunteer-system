from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel

from app.clients.laravel import LaravelClient
from app.tools.registry import tool_registry


app = FastAPI(
    title="Volunteer AI",
    version="1.0.0",
)


class ToolRequest(BaseModel):
    tool: str
    arguments: dict = {}


@app.get("/health")
async def health():
    return {
        "status": "ok",
        "service": "volunteer-ai",
    }


@app.post("/tool")
async def execute_tool(
    request: ToolRequest,
    authorization: str | None = Header(default=None),
):
    if not authorization:
        raise HTTPException(
            status_code=401,
            detail="Authorization header is required.",
        )

    if not authorization.startswith("Bearer "):
        raise HTTPException(
            status_code=401,
            detail="Invalid Authorization header.",
        )

    if not tool_registry.has(request.tool):
        raise HTTPException(
            status_code=404,
            detail=f"Unknown tool: {request.tool}",
        )

    token = authorization.replace("Bearer ", "", 1).strip()

    client = LaravelClient(token)

    try:
        result = await client.call_tool(
            tool_name=request.tool,
            arguments=request.arguments,
        )

        return {
            "ok": True,
            "tool": request.tool,
            "result": result,
        }

    except Exception as exc:
        raise HTTPException(
            status_code=502,
            detail=str(exc),
        )

@app.get("/tools")
async def list_tools():
    return {
        "count": len(tool_registry.names()),
        "tools": tool_registry.all(),
    }