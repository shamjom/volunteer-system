"""Rendering of the prompt fed to the fine-tuned Qwen3-4B adapter.

There is exactly one render path. Training and inference differ by a single
argument, ``for_training``, which maps to ``add_generation_prompt``. Anything
else that differed between the two would reproduce the asymmetry that cost
three training runs, so no second path is offered.

The rendered string then goes through :func:`apply_training_transform`, which
reproduces a string edit the data pipeline applied *after*
``apply_chat_template``. Without it the service would feed the adapter a shape
its training data does not contain; see that function for the details.

Three invariants this module enforces rather than assumes:

* The system message is used as ``str.replace`` produced it. It ends with
  ``"## الأدوات المتاحة\\n\\n"`` -- a heading followed by blank space, because
  ``{{TOOLS_JSON}}`` is substituted with an empty string and the tool list
  travels through ``tools=`` instead. Calling ``.strip()`` here, which most
  implementations do reflexively, changes the bytes the model was trained on.

* An assistant turn carrying tool calls must be followed by its tool results.
  A dangling tool call would be re-rendered on the next turn in a shape the
  model never saw during training.

* Every message content is a string. The pinned unsloth template indexes
  ``message.content`` without a type guard and raises on ``None``, where the
  upstream Qwen template silently substitutes an empty string. Passing a
  string keeps the two byte-identical.
"""

from __future__ import annotations

import json
import re
from dataclasses import dataclass
from enum import Enum
from pathlib import Path
from typing import Any, Iterable, Mapping, Sequence

ASSETS_DIR = Path(__file__).parent / "assets"

SYSTEM_TEMPLATE_PATH = ASSETS_DIR / "system_prompt_v2.txt"
TOOLS_SCHEMA_PATH = ASSETS_DIR / "tools_schema.json"

# The adapter saw only the first two. hr_admin is accepted but is outside the
# distribution the 150-case benchmark measured; see PromptRenderer.render.
ROLES_TRAINED = frozenset({"volunteer", "team_leader"})
ROLES_ALLOWED = frozenset({"volunteer", "team_leader", "hr_admin"})

_TODAY_RE = re.compile(r"^\d{4}-\d{2}-\d{2}$")
_PLACEHOLDER_RE = re.compile(r"\{\{[A-Z_]+\}\}")

_MESSAGE_ROLES = frozenset({"user", "assistant", "tool"})


class PromptRenderError(RuntimeError):
    """The prompt could not be rendered in a shape matching training."""


class HistoryShapeError(PromptRenderError):
    """The message list violates an invariant the chat template depends on."""


class ToolsShape(str, Enum):
    """How the tool list was handed to ``apply_chat_template`` during training.

    The template serialises each entry with ``{{ tool | tojson }}``, so the two
    shapes produce different bytes inside ``<tools>`` and the model was trained
    on exactly one of them. ``scripts/detect_tools_shape.py`` resolves which,
    given a single raw training string.
    """

    WRAPPED = "wrapped"  # {"type": "function", "function": {...}}
    UNWRAPPED = "unwrapped"  # {"name": ..., "description": ..., "parameters": {...}}


def load_tools(
    path: Path | None = None, shape: ToolsShape = ToolsShape.WRAPPED
) -> list[dict[str, Any]]:
    """Load the frozen tool list, preserving key order from the file.

    Key order survives ``json.load`` and reaches ``tojson`` unchanged, which
    matters because reordering keys rewrites every byte of the tools block.
    """
    path = path or TOOLS_SCHEMA_PATH
    document = json.loads(path.read_text(encoding="utf-8"))
    tools = document["tools"]
    if shape is ToolsShape.UNWRAPPED:
        return [tool["function"] for tool in tools]
    return tools


def load_system_template(path: Path | None = None) -> str:
    path = path or SYSTEM_TEMPLATE_PATH
    return path.read_text(encoding="utf-8")


_BARE_TOOL_CALL_TURN = "<|im_start|>assistant\n<tool_call>"
_THINK_TOOL_CALL_TURN = "<|im_start|>assistant\n<think>\n\n</think>\n\n<tool_call>"


def apply_training_transform(rendered: str) -> str:
    """Re-apply the string edit the data pipeline performed after templating.

    The template gives an assistant turn a ``<think>`` block only when that
    turn is the last message. Since a tool-calling turn is always followed by
    its result, no tool call in the 2790 examples would have carried one --
    while inference injects a think block on every turn. The pipeline closed
    that gap by patching the serialised string directly, so the strings the
    adapter actually saw contain a think block before every tool call.

    The service must reproduce that edit or its history will not match what
    was trained. Deliberately *not* covered, because the pipeline did not
    cover it either:

    * Intermediate assistant turns that are plain text (865 of them, including
      every confirmation prompt) still render with no think block. That
      asymmetry is baked into the current adapter and is documented by
      ``test_intermediate_text_turn_carries_no_think_block``.

    Idempotent: a turn that already carries a think block does not match.
    """
    return rendered.replace(_BARE_TOOL_CALL_TURN, _THINK_TOOL_CALL_TURN)


@dataclass(frozen=True)
class SessionContext:
    """Per-session values injected into the system message.

    Both come from the server: the role from the session store, the date from
    the server clock. The model is forbidden from computing dates, so this is
    its only source of today's date.
    """

    role: str
    today: str

    def __post_init__(self) -> None:
        if self.role not in ROLES_ALLOWED:
            raise PromptRenderError(
                f"Unknown role {self.role!r}; expected one of {sorted(ROLES_ALLOWED)}"
            )
        if not _TODAY_RE.match(self.today):
            raise PromptRenderError(
                f"today must be YYYY-MM-DD, got {self.today!r}"
            )

    @property
    def role_is_trained(self) -> bool:
        return self.role in ROLES_TRAINED


class PromptRenderer:
    """Builds the exact string the adapter was fine-tuned on.

    ``tokenizer`` should already carry the pinned template; see
    ``app.prompt.pinning.attach_pinned_template``.
    """

    def __init__(
        self,
        tokenizer,
        *,
        system_template: str | None = None,
        tools: Sequence[Mapping[str, Any]] | None = None,
    ) -> None:
        self._tokenizer = tokenizer
        self._system_template = (
            system_template if system_template is not None else load_system_template()
        )
        self._tools = list(tools) if tools is not None else load_tools()
        self.untrained_role_calls = 0

    @property
    def tools(self) -> list[dict[str, Any]]:
        return [dict(tool) for tool in self._tools]

    def render_system(self, ctx: SessionContext) -> str:
        """Substitute the three placeholders. No trimming, by design."""
        text = (
            self._system_template.replace("{{ROLE}}", ctx.role)
            .replace("{{TODAY}}", ctx.today)
            .replace("{{TOOLS_JSON}}", "")
        )
        leftover = _PLACEHOLDER_RE.search(text)
        if leftover:
            raise PromptRenderError(
                f"Unsubstituted placeholder {leftover.group(0)} in system prompt"
            )
        return text

    def render(
        self,
        ctx: SessionContext,
        messages: Iterable[Mapping[str, Any]],
        *,
        for_training: bool = False,
    ) -> str:
        """Render the full prompt string.

        ``for_training=True`` reproduces the training-time call and therefore
        includes the final assistant turn. ``for_training=False`` stops at the
        generation prompt. The first is required to be a byte-exact prefix of
        the second; ``tests/test_prompt_parity.py`` asserts it.
        """
        if not ctx.role_is_trained:
            self.untrained_role_calls += 1

        history = [dict(message) for message in messages]
        _validate_history(history, allow_trailing_tool_call=for_training)

        payload = [{"role": "system", "content": self.render_system(ctx)}, *history]
        rendered = self._tokenizer.apply_chat_template(
            payload,
            tools=self._tools,
            tokenize=False,
            add_generation_prompt=not for_training,
            enable_thinking=False,
        )
        if not isinstance(rendered, str):
            raise PromptRenderError(
                "apply_chat_template returned tokens; tokenize=False was ignored"
            )
        return apply_training_transform(rendered)


def _validate_history(
    messages: Sequence[Mapping[str, Any]], *, allow_trailing_tool_call: bool
) -> None:
    """Reject message lists the chat template would render into a shape the
    adapter never saw."""
    pending_results = 0

    for index, message in enumerate(messages):
        role = message.get("role")
        if role == "system":
            raise HistoryShapeError(
                f"messages[{index}] is a system message; the renderer owns the "
                "system turn and injects it itself"
            )
        if role not in _MESSAGE_ROLES:
            raise HistoryShapeError(
                f"messages[{index}] has unsupported role {role!r}"
            )

        content = message.get("content")
        if not isinstance(content, str):
            raise HistoryShapeError(
                f"messages[{index}] has non-string content {type(content).__name__}; "
                "the pinned template raises on None where upstream Qwen renders "
                "an empty string, so the two would stop agreeing"
            )

        if role == "tool":
            if pending_results == 0:
                raise HistoryShapeError(
                    f"messages[{index}] is a tool result with no preceding "
                    "assistant tool call"
                )
            pending_results -= 1
            continue

        if pending_results:
            raise HistoryShapeError(
                f"messages[{index}] interrupts {pending_results} unanswered tool "
                "call(s); a dangling tool call re-renders in a shape the model "
                "was never trained on"
            )

        if role == "assistant":
            pending_results = len(message.get("tool_calls") or ())
            if pending_results and content:
                # Renders as "assistant\n<text>\n<tool_call>", which the
                # training transform does not match -- so no such turn exists
                # in the data the adapter saw. The service never builds one:
                # accompanying text is committed separately from the tool call.
                raise HistoryShapeError(
                    f"messages[{index}] carries both text and tool calls; that "
                    "shape has no counterpart in the training data"
                )

    if pending_results and not allow_trailing_tool_call:
        raise HistoryShapeError(
            f"history ends with {pending_results} unanswered tool call(s); "
            "commit the tool result before rendering the next turn"
        )
