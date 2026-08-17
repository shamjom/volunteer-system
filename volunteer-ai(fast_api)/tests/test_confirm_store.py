from __future__ import annotations

from datetime import datetime, timedelta, timezone

import pytest

from app.confirm import (
    ConfirmationError,
    ConfirmationRefusal,
    ConfirmationStatus,
    InMemoryConfirmationStore,
    NotAMutatingTool,
    hash_arguments,
)

SESSION = "sess-1"
USER = "user-7"


class FakeClock:
    def __init__(self) -> None:
        self.now = datetime(2025, 3, 11, 12, 0, tzinfo=timezone.utc)

    def __call__(self) -> datetime:
        return self.now

    def advance(self, seconds: float) -> None:
        self.now += timedelta(seconds=seconds)


@pytest.fixture
def clock() -> FakeClock:
    return FakeClock()


@pytest.fixture
def store(clock) -> InMemoryConfirmationStore:
    return InMemoryConfirmationStore(time_source=clock)


def mint(store, *, session=SESSION, user=USER, event_id=42):
    return store.mint(
        session_id=session,
        user_id=user,
        tool_name="register_for_event",
        arguments={"event_id": event_id},
        display_label="يوم التشجير",
        turn_id="turn-1",
    )


# -- the boundary the whole design rests on --------------------------------


def test_read_tools_cannot_be_minted(store):
    with pytest.raises(NotAMutatingTool):
        store.mint(
            session_id=SESSION,
            user_id=USER,
            tool_name="list_upcoming_events",
            arguments={},
            display_label="",
            turn_id="turn-1",
        )


def test_consume_returns_the_stored_arguments(store):
    record = mint(store, event_id=42)
    claimed = store.consume(
        confirm_id=record.confirm_id, session_id=SESSION, user_id=USER
    )
    assert claimed.arguments == {"event_id": 42}
    assert claimed.args_hash == hash_arguments({"event_id": 42})
    assert claimed.status is ConfirmationStatus.CONSUMED


def test_double_tap_executes_once(store):
    record = mint(store)
    store.consume(confirm_id=record.confirm_id, session_id=SESSION, user_id=USER)
    with pytest.raises(ConfirmationError) as excinfo:
        store.consume(confirm_id=record.confirm_id, session_id=SESSION, user_id=USER)
    assert excinfo.value.reason is ConfirmationRefusal.NOT_PENDING


def test_confirm_id_cannot_be_spent_by_another_user(store):
    record = mint(store)
    with pytest.raises(ConfirmationError) as excinfo:
        store.consume(
            confirm_id=record.confirm_id, session_id=SESSION, user_id="user-99"
        )
    assert excinfo.value.reason is ConfirmationRefusal.WRONG_OWNER
    # Still spendable by its owner: a failed attempt must not burn the record.
    assert store.consume(
        confirm_id=record.confirm_id, session_id=SESSION, user_id=USER
    )


def test_unknown_confirm_id_is_refused(store):
    with pytest.raises(ConfirmationError) as excinfo:
        store.consume(confirm_id="0" * 32, session_id=SESSION, user_id=USER)
    assert excinfo.value.reason is ConfirmationRefusal.NOT_FOUND


# -- lifecycle -------------------------------------------------------------


def test_one_pending_per_session_and_the_new_one_wins(store):
    first = mint(store, event_id=1)
    second = mint(store, event_id=2)

    assert store.get(first.confirm_id).status is ConfirmationStatus.SUPERSEDED
    assert store.peek(SESSION).confirm_id == second.confirm_id

    with pytest.raises(ConfirmationError) as excinfo:
        store.consume(confirm_id=first.confirm_id, session_id=SESSION, user_id=USER)
    assert excinfo.value.reason is ConfirmationRefusal.NOT_PENDING


def test_confirmation_expires(store, clock):
    record = mint(store)
    clock.advance(181)
    with pytest.raises(ConfirmationError) as excinfo:
        store.consume(confirm_id=record.confirm_id, session_id=SESSION, user_id=USER)
    assert excinfo.value.reason is ConfirmationRefusal.EXPIRED


def test_confirmation_survives_just_inside_the_window(store, clock):
    record = mint(store)
    clock.advance(179)
    assert store.consume(
        confirm_id=record.confirm_id, session_id=SESSION, user_id=USER
    )


def test_peek_expires_a_stale_record(store, clock):
    mint(store)
    clock.advance(181)
    assert store.peek(SESSION) is None


def test_cancel_needs_no_confirm_id(store):
    record = mint(store)
    cancelled = store.cancel(SESSION)
    assert cancelled.confirm_id == record.confirm_id
    assert store.peek(SESSION) is None
    with pytest.raises(ConfirmationError):
        store.consume(confirm_id=record.confirm_id, session_id=SESSION, user_id=USER)


def test_cancel_with_nothing_pending_is_harmless(store):
    assert store.cancel(SESSION) is None


def test_unknown_outcome_never_returns_to_pending(store):
    record = mint(store)
    claimed = store.consume(
        confirm_id=record.confirm_id, session_id=SESSION, user_id=USER
    )
    store.mark_unknown(claimed.confirm_id)
    assert store.get(claimed.confirm_id).status is ConfirmationStatus.UNKNOWN
    with pytest.raises(ConfirmationError):
        store.consume(confirm_id=record.confirm_id, session_id=SESSION, user_id=USER)


def test_sessions_do_not_interfere(store):
    first = mint(store, session="sess-a", user="user-a")
    second = mint(store, session="sess-b", user="user-b")
    assert store.get(first.confirm_id).status is ConfirmationStatus.PENDING
    assert store.peek("sess-a").confirm_id == first.confirm_id
    assert store.peek("sess-b").confirm_id == second.confirm_id


def test_idempotency_key_is_fixed_at_mint_time(store):
    record = mint(store)
    claimed = store.consume(
        confirm_id=record.confirm_id, session_id=SESSION, user_id=USER
    )
    assert claimed.idempotency_key == record.idempotency_key


def test_concurrent_consumption_has_exactly_one_winner(store):
    import threading

    record = mint(store)
    outcomes: list[bool] = []
    barrier = threading.Barrier(8)

    def attempt() -> None:
        barrier.wait()
        try:
            store.consume(
                confirm_id=record.confirm_id, session_id=SESSION, user_id=USER
            )
            outcomes.append(True)
        except ConfirmationError:
            outcomes.append(False)

    threads = [threading.Thread(target=attempt) for _ in range(8)]
    for thread in threads:
        thread.start()
    for thread in threads:
        thread.join()

    assert outcomes.count(True) == 1
