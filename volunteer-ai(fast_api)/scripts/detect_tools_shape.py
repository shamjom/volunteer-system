"""Resolve how the tool list was serialised during training, from evidence.

The chat template renders each entry with ``{{ tool | tojson }}``, so passing
the wrapped list (``{"type": "function", "function": {...}}``) and passing the
unwrapped one produce different bytes inside ``<tools>``. The adapter was
trained on exactly one of them, and guessing wrong silently rewrites ~2200
tokens of every prompt.

Give this script one raw rendered training sample and it reports which
combination of shape and JSON escaping is actually present:

    python scripts/detect_tools_shape.py --sample tests/fixtures/one_training_render.txt

It also reports the ``ensure_ascii`` setting, which is the transformers-version
sensitive half of the same question: with Arabic tool descriptions, the
difference between "أ" and "\\u0623" changes the whole block.
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from app.prompt.renderer import ToolsShape, load_tools  # noqa: E402


def candidate_block(shape: ToolsShape, ensure_ascii: bool) -> str:
    tools = load_tools(shape=shape)
    return "\n".join(
        json.dumps(tool, ensure_ascii=ensure_ascii) for tool in tools
    )


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--sample",
        type=Path,
        required=True,
        help="File holding one raw rendered training string",
    )
    args = parser.parse_args()

    raw = args.sample.read_text(encoding="utf-8", newline="")
    matches = []

    for shape in ToolsShape:
        for ensure_ascii in (False, True):
            block = candidate_block(shape, ensure_ascii)
            hit = block in raw
            print(
                f"{shape.value:<10} ensure_ascii={str(ensure_ascii):<5} "
                f"{'MATCH' if hit else 'no'}"
            )
            if hit:
                matches.append((shape, ensure_ascii))

    print()
    if len(matches) == 1:
        shape, ensure_ascii = matches[0]
        print(f"resolved: ToolsShape.{shape.name}, ensure_ascii={ensure_ascii}")
        return 0
    if not matches:
        print(
            "no match. The tool list in assets/tools_schema.json differs from "
            "the one used in training (order, descriptions, or fields), or the "
            "sample is not a raw render.",
            file=sys.stderr,
        )
        return 1
    print(f"ambiguous: {len(matches)} candidates matched", file=sys.stderr)
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
