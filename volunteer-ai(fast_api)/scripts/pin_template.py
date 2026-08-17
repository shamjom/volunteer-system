"""Vendor the Qwen3 chat template from a real tokenizer and record its digest.

Run once per model/tokenizer version:

    python scripts/pin_template.py --model Qwen/Qwen3-4B

Re-pinning after the digest has been recorded overwrites the guarantee the
lock file provides, so it requires --force and prints both digests first.
"""

from __future__ import annotations

import argparse
import sys
from datetime import datetime, timezone
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from app.prompt.pinning import (  # noqa: E402
    LOCK_FILENAME,
    TEMPLATE_FILENAME,
    ASSETS_DIR,
    TemplateLock,
    digest,
)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--model",
        default="Qwen/Qwen3-4B",
        help="Base model path or hub id to read the tokenizer from",
    )
    parser.add_argument("--assets-dir", type=Path, default=ASSETS_DIR)
    parser.add_argument(
        "--force",
        action="store_true",
        help="Overwrite an existing pin (changes what the service considers correct)",
    )
    args = parser.parse_args()

    import transformers
    from transformers import AutoTokenizer

    tokenizer = AutoTokenizer.from_pretrained(args.model, trust_remote_code=False)
    template = tokenizer.chat_template
    if not isinstance(template, str) or not template.strip():
        print(
            f"error: tokenizer for {args.model} exposes no chat_template string",
            file=sys.stderr,
        )
        return 2

    args.assets_dir.mkdir(parents=True, exist_ok=True)
    template_path = args.assets_dir / TEMPLATE_FILENAME
    lock_path = args.assets_dir / LOCK_FILENAME
    new_sha = digest(template)

    if lock_path.exists():
        existing = TemplateLock.from_file(lock_path)
        if existing.sha256 == new_sha:
            print(f"unchanged: {new_sha}")
            return 0
        print(f"existing pin: {existing.sha256} (from {existing.source_model})")
        print(f"new template: {new_sha} (from {args.model})")
        if not args.force:
            print(
                "refusing to overwrite without --force; run the golden parity "
                "test against the new template first",
                file=sys.stderr,
            )
            return 1

    # newline="" writes the template bytes verbatim so the digest survives a
    # Windows checkout.
    template_path.write_text(template, encoding="utf-8", newline="")
    lock = TemplateLock(
        sha256=new_sha,
        source_model=args.model,
        transformers_version=transformers.__version__,
        pinned_at=datetime.now(timezone.utc).isoformat(timespec="seconds"),
    )
    lock_path.write_text(lock.to_json() + "\n", encoding="utf-8")

    print(f"pinned {template_path} ({len(template)} chars)")
    print(f"sha256 {new_sha}")
    print(f"transformers {lock.transformers_version}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
