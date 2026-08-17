from __future__ import annotations

import json
import os
from pathlib import Path

import pytest

from app.prompt.pinning import TemplatePinError, attach_pinned_template
from app.prompt.renderer import PromptRenderer, SessionContext

FIXTURES = Path(__file__).parent / "fixtures"
TRAINING_SAMPLES = FIXTURES / "training_samples.jsonl"

# The repo the adapter was trained against. Its template differs from
# Qwen/Qwen3-4B: identical output for string content, but it raises on None.
MODEL_ID = os.environ.get("VOLUNTEER_AI_MODEL", "unsloth/Qwen3-4B")


@pytest.fixture(scope="session")
def tokenizer():
    transformers = pytest.importorskip(
        "transformers", reason="transformers is required to render prompts"
    )
    try:
        tok = transformers.AutoTokenizer.from_pretrained(MODEL_ID)
    except Exception as exc:  # network, missing files, gated repo
        pytest.skip(f"tokenizer {MODEL_ID} unavailable: {exc}")
    try:
        return attach_pinned_template(tok)
    except TemplatePinError as exc:
        pytest.skip(str(exc))


@pytest.fixture(scope="session")
def renderer(tokenizer) -> PromptRenderer:
    return PromptRenderer(tokenizer)


@pytest.fixture
def ctx() -> SessionContext:
    return SessionContext(role="volunteer", today="2025-03-11")


@pytest.fixture(scope="session")
def training_samples() -> list[dict]:
    """Raw renders captured from the training pipeline.

    One JSON object per line:

        {"id": "...",
         "role": "volunteer",
         "today": "2025-03-11",
         "messages": [...],          # without the system turn
         "rendered": "<exact string apply_chat_template produced>"}

    Capture them with add_generation_prompt=False, tokenize=False, from the
    same script that built the 2790 examples.
    """
    if not TRAINING_SAMPLES.exists():
        pytest.skip(
            f"no training samples at {TRAINING_SAMPLES}; the parity test cannot "
            "run without renders captured from the training pipeline"
        )
    samples = [
        json.loads(line)
        for line in TRAINING_SAMPLES.read_text(encoding="utf-8").splitlines()
        if line.strip()
    ]
    if not samples:
        pytest.skip(f"{TRAINING_SAMPLES} is empty")
    return samples
