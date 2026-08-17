"""Removal of the Qwen3 thinking block from generated text.

With ``enable_thinking=False`` the chat template emits ``<think>\\n\\n</think>``
as part of the *prompt*, which means generated text should never contain it.
This module is therefore a defensive net, not a routine step: if the block shows
up in the output, the rendered prompt no longer matches training and tool
calling is about to degrade.

Every strip is counted so that drift is loud instead of silent. Watch
``counters()["fired"]`` -- a value that is not zero means the template changed
shape and the golden parity test should be re-run before trusting any output.
"""

from __future__ import annotations

from dataclasses import dataclass

# The empty think block sits at the very start of a reply. Scanning only the
# head avoids mistaking a legitimate "</think>" inside, say, an event title
# for a real thinking block.
SCAN_WINDOW = 64

_OPEN = "<think>"
_CLOSE = "</think>"

_counters: dict[str, int] = {
    "calls": 0,
    "fired": 0,
    "paired": 0,
    "orphan_close": 0,
    "unterminated": 0,
    "non_empty_payload": 0,
}


@dataclass(frozen=True)
class StripResult:
    """Outcome of one strip attempt.

    ``text`` is safe to parse. ``unterminated`` means generation ran out of
    budget inside a thinking block, so the caller must treat the turn as a
    generation failure rather than as an empty reply.
    """

    text: str
    fired: bool
    reason: str | None = None
    payload: str | None = None

    @property
    def unterminated(self) -> bool:
        return self.reason == "unterminated"


def strip_think_block(raw: str) -> StripResult:
    """Strip a leading thinking block from ``raw`` if one is present."""
    _counters["calls"] += 1
    head = raw[:SCAN_WINDOW]

    open_at = head.find(_OPEN)
    if open_at != -1:
        close_at = raw.find(_CLOSE, open_at + len(_OPEN))
        if close_at == -1:
            _bump("unterminated")
            return StripResult(text="", fired=True, reason="unterminated", payload=raw)
        payload = raw[open_at + len(_OPEN) : close_at]
        if payload.strip():
            _counters["non_empty_payload"] += 1
        _bump("paired")
        return StripResult(
            text=_trim_lead(raw[close_at + len(_CLOSE) :]),
            fired=True,
            reason="paired",
            payload=payload,
        )

    close_at = head.find(_CLOSE)
    if close_at != -1:
        # The template opened the block in the prompt and the model only emitted
        # the closing tag. This is the shape a drifted template produces.
        _bump("orphan_close")
        return StripResult(
            text=_trim_lead(raw[close_at + len(_CLOSE) :]),
            fired=True,
            reason="orphan_close",
            payload=raw[:close_at],
        )

    return StripResult(text=raw, fired=False)


def _trim_lead(text: str) -> str:
    # The template puts "\n\n" after the closing tag. Drop only newlines so that
    # meaningful leading characters of the reply survive untouched.
    return text.lstrip("\n")


def _bump(reason: str) -> None:
    _counters["fired"] += 1
    _counters[reason] += 1


def counters() -> dict[str, int]:
    return dict(_counters)


def reset_counters() -> None:
    for key in _counters:
        _counters[key] = 0
