"""Pinning of the Qwen3 chat template.

The template decides where ``<think>`` blocks land, how historical assistant
turns are re-rendered, and how the ``<tools>`` block is serialised. A silent
upstream change to it is indistinguishable from a fine-tune that stopped
working, so the template is vendored into ``assets/`` and its digest is checked
on every startup.

The vendored file is produced by ``scripts/pin_template.py`` from the real
tokenizer -- never written by hand. A hand-written template would be close but
not identical, and "close" fails silently here.
"""

from __future__ import annotations

import hashlib
import json
from dataclasses import dataclass
from pathlib import Path

TEMPLATE_FILENAME = "chat_template.jinja"
LOCK_FILENAME = "template.lock.json"

ASSETS_DIR = Path(__file__).parent / "assets"


class TemplatePinError(RuntimeError):
    """The vendored template is missing or does not match its recorded digest."""


@dataclass(frozen=True)
class TemplateLock:
    sha256: str
    source_model: str
    transformers_version: str
    pinned_at: str

    @classmethod
    def from_file(cls, path: Path) -> "TemplateLock":
        data = json.loads(path.read_text(encoding="utf-8"))
        return cls(
            sha256=data["sha256"],
            source_model=data["source_model"],
            transformers_version=data["transformers_version"],
            pinned_at=data["pinned_at"],
        )

    def to_json(self) -> str:
        return json.dumps(
            {
                "sha256": self.sha256,
                "source_model": self.source_model,
                "transformers_version": self.transformers_version,
                "pinned_at": self.pinned_at,
            },
            ensure_ascii=False,
            indent=2,
        )


def digest(template_text: str) -> str:
    return hashlib.sha256(template_text.encode("utf-8")).hexdigest()


def load_pinned_template(assets_dir: Path | None = None) -> str:
    """Read the vendored template and verify it against the lock file."""
    assets_dir = assets_dir or ASSETS_DIR
    template_path = assets_dir / TEMPLATE_FILENAME
    lock_path = assets_dir / LOCK_FILENAME

    if not template_path.exists() or not lock_path.exists():
        raise TemplatePinError(
            f"No pinned template in {assets_dir}. Run:\n"
            f"    python scripts/pin_template.py --model <base-model-path-or-id>"
        )

    # newline="" keeps the bytes exactly as written, so the digest is stable on
    # Windows checkouts where git may otherwise rewrite line endings.
    text = template_path.read_text(encoding="utf-8", newline="")
    lock = TemplateLock.from_file(lock_path)
    actual = digest(text)
    if actual != lock.sha256:
        raise TemplatePinError(
            f"Chat template digest mismatch in {assets_dir}.\n"
            f"  expected {lock.sha256} (pinned from {lock.source_model}, "
            f"transformers {lock.transformers_version})\n"
            f"  actual   {actual}\n"
            "The prompt no longer renders the way the adapter was trained. "
            "Re-run the golden parity test before re-pinning."
        )
    return text


def attach_pinned_template(tokenizer, assets_dir: Path | None = None):
    """Force ``tokenizer`` to use the vendored template instead of its own."""
    tokenizer.chat_template = load_pinned_template(assets_dir)
    return tokenizer
