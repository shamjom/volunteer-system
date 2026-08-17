import json
from pathlib import Path
from typing import Any


class ToolRegistry:
    def __init__(self, schema_path: str | Path):
        self.schema_path = Path(schema_path)
        self._tools: dict[str, dict[str, Any]] = {}
        self._load()

    def _load(self) -> None:
        if not self.schema_path.exists():
            raise FileNotFoundError(
                f"Tools schema not found: {self.schema_path}"
            )

        with self.schema_path.open("r", encoding="utf-8") as file:
            schema = json.load(file)

        tools = schema.get("tools", [])

        for item in tools:
            function = item.get("function", {})
            name = function.get("name")

            if not name:
                continue

            self._tools[name] = function

    def has(self, name: str) -> bool:
        return name in self._tools

    def get(self, name: str) -> dict[str, Any]:
        if name not in self._tools:
            raise KeyError(f"Unknown tool: {name}")

        return self._tools[name]

    def names(self) -> list[str]:
        return list(self._tools.keys())

    def all(self) -> list[dict[str, Any]]:
        return list(self._tools.values())


BASE_DIR = Path(__file__).resolve().parents[2]

TOOLS_SCHEMA_PATH = (
    BASE_DIR / "app" / "prompt" / "assets" / "tools_schema.json"
)

tool_registry = ToolRegistry(TOOLS_SCHEMA_PATH)