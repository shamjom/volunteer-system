from __future__ import annotations

import pytest

from app.prompt.strip import counters, reset_counters, strip_think_block

TOOL_CALL = '<tool_call>\n{"name": "list_teams", "arguments": {}}\n</tool_call>'


@pytest.fixture(autouse=True)
def _clean_counters():
    reset_counters()
    yield
    reset_counters()


def test_clean_output_passes_through_untouched():
    result = strip_think_block(TOOL_CALL)
    assert result.text == TOOL_CALL
    assert not result.fired
    assert counters()["fired"] == 0


def test_paired_empty_block_is_removed():
    result = strip_think_block("<think>\n\n</think>\n\n" + TOOL_CALL)
    assert result.text == TOOL_CALL
    assert result.reason == "paired"
    assert counters()["non_empty_payload"] == 0


def test_block_with_content_is_counted_separately():
    result = strip_think_block("<think>\nأفكر\n</think>\n\nمرحباً")
    assert result.text == "مرحباً"
    assert counters()["non_empty_payload"] == 1


def test_orphan_closing_tag_is_removed():
    """The shape a drifted template produces: prompt opens, model closes."""
    result = strip_think_block("\n\n</think>\n\n" + TOOL_CALL)
    assert result.text == TOOL_CALL
    assert result.reason == "orphan_close"


def test_unterminated_block_is_flagged_as_generation_failure():
    result = strip_think_block("<think>\nما زلت أفكر ولم أنته")
    assert result.unterminated
    assert result.text == ""


def test_closing_tag_beyond_the_window_is_left_alone():
    """A </think> inside a user-authored event title must not trigger a strip."""
    payload = "الفعالية باسم " + "ـ" * 80 + " </think> شيء"
    result = strip_think_block(payload)
    assert result.text == payload
    assert not result.fired


def test_only_newlines_are_trimmed_after_the_block():
    result = strip_think_block("<think>\n\n</think>\n\n  مسافة مقصودة")
    assert result.text == "  مسافة مقصودة"
