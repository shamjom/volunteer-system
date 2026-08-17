# Golden fixtures

Two parity tests stay skipped until `training_samples.jsonl` exists here. They
are the only tests that compare against the real training pipeline; everything
else in `test_prompt_parity.py` checks structure and can pass against a prompt
that is subtly wrong.

## `training_samples.jsonl`

One JSON object per line:

```json
{
  "id": "read_block_014",
  "role": "volunteer",
  "today": "2025-03-11",
  "messages": [{"role": "user", "content": "..."}, {"role": "assistant", "...": "..."}],
  "rendered": "<|im_start|>system\n..."
}
```

* `messages` — **without** the system turn; the renderer injects it.
* `messages` must end at the assistant turn the example teaches. The template
  gives an assistant turn its `<think>` block only when it is the last message,
  so an example that continues past its target teaches a turn that inference
  will never reproduce.
* `rendered` — the exact string `apply_chat_template` returned during training.

## Capturing them

From the same script that built the 2790 examples, before tokenisation:

```python
import json

rendered = tok.apply_chat_template(
    msgs, tools=TOOLS, tokenize=False,
    add_generation_prompt=False, enable_thinking=False,
)
record = {
    "id": example_id,
    "role": role,
    "today": today,
    "messages": msgs[1:],          # drop the system turn
    "rendered": rendered,
}
out.write(json.dumps(record, ensure_ascii=False) + "\n")
```

Twenty examples is enough, but they must span the shape space, because each
covers a different branch of the template:

| coverage | why it matters |
|---|---|
| single-turn text reply | baseline |
| single-turn tool call | the `loop.last` branch for tool calls |
| multi-turn: tool call → tool result → text | re-rendering of a historical assistant turn |
| two tool calls in one assistant turn | the `loop.first and content` branch |
| a confirmation turn for each of the six mutating tools | the paths the confirmation store depends on |
| `role: team_leader` | the only other role the adapter saw |

## `one_training_render.txt`

Any single `rendered` value, as a plain UTF-8 file. Used by
`scripts/detect_tools_shape.py` to resolve whether `TOOLS` was passed wrapped
or unwrapped:

```bash
python scripts/detect_tools_shape.py --sample tests/fixtures/one_training_render.txt
```
