import httpx


LARAVEL_BASE_URL = "http://127.0.0.1:8000"


class LaravelClient:
    def __init__(self, token: str):
        self.token = token

    def headers(self) -> dict[str, str]:
        return {
            "Accept": "application/json",
            "Content-Type": "application/json",
            "Authorization": f"Bearer {self.token}",
        }

    async def call_tool(
        self,
        tool_name: str,
        arguments: dict | None = None,
    ) -> dict:

        url = f"{LARAVEL_BASE_URL}/api/agent/{tool_name}"

        async with httpx.AsyncClient(timeout=20.0) as client:
            response = await client.post(
                url,
                headers=self.headers(),
                json=arguments or {},
            )

        try:
            data = response.json()
        except ValueError:
            data = {
                "ok": False,
                "data": None,
                "error": {
                    "code": "INVALID_LARAVEL_RESPONSE",
                    "message": response.text,
                },
            }

        if response.status_code >= 400:
            raise RuntimeError(
                f"Laravel returned HTTP {response.status_code}: {data}"
            )

        return data