"""Server-side custody of pending mutations.

The model can propose a mutation; it cannot perform one. When it emits a call
to one of the six mutating tools the loop does not execute it -- it mints a
record here and ends the turn. The mutation runs only when the client presents
the resulting ``confirm_id``, and what runs is the stored ``arguments``, never
anything the model says afterwards.

The model has no way to produce a ``confirm_id``, so "a mutating call without a
valid confirmation is rejected" is not a check that can be bypassed: it is the
only state the model can reach.
"""

from __future__ import annotations

import hashlib
import json
import threading
import uuid
from dataclasses import dataclass, replace
from datetime import datetime, timedelta, timezone
from enum import Enum
from typing import Any, Callable, Mapping

# The six tools from the system prompt that change data. Everything else runs
# without a confirmation.
MUTATING_TOOLS = frozenset(
    {
        "register_for_event",
        "withdraw_from_event",
        "update_my_profile",
        "request_join_team",
        "submit_help_request",
        "update_task_status",
    }
)

# Long enough for an unhurried read on a phone, short enough that the world has
# not moved on -- an event filling up, a deadline passing -- before the tap.
DEFAULT_TTL = timedelta(seconds=180)


class ConfirmationStatus(str, Enum):
    PENDING = "pending"
    CONSUMED = "consumed"
    SUPERSEDED = "superseded"
    CANCELLED = "cancelled"
    EXPIRED = "expired"
    # The upstream call was sent but its outcome was never learned. Never
    # returns to PENDING; it waits for manual reconciliation.
    UNKNOWN = "unknown"


class ConfirmationRefusal(str, Enum):
    NOT_FOUND = "not_found"
    EXPIRED = "expired"
    NOT_PENDING = "not_pending"
    WRONG_OWNER = "wrong_owner"


class ConfirmationError(RuntimeError):
    """A confirmation could not be consumed. Carries a machine-readable reason."""

    def __init__(self, reason: ConfirmationRefusal, detail: str = "") -> None:
        super().__init__(detail or reason.value)
        self.reason = reason


class NotAMutatingTool(ValueError):
    """Minting was attempted for a tool that needs no confirmation."""


@dataclass(frozen=True)
class PendingConfirmation:
    confirm_id: str
    session_id: str
    user_id: str
    tool_name: str
    arguments: Mapping[str, Any]
    args_hash: str
    # Taken from a tool result, never from model prose, so a hallucinated title
    # cannot reach the confirmation card.
    display_label: str
    # Minted here rather than at click time: a key generated per click would be
    # new on every retry and would not deduplicate anything.
    idempotency_key: str
    created_at: datetime
    expires_at: datetime
    status: ConfirmationStatus
    turn_id: str

    def is_live(self, now: datetime) -> bool:
        return self.status is ConfirmationStatus.PENDING and now < self.expires_at


def canonical_arguments(arguments: Mapping[str, Any]) -> str:
    return json.dumps(arguments, sort_keys=True, ensure_ascii=False, separators=(",", ":"))


def hash_arguments(arguments: Mapping[str, Any]) -> str:
    return hashlib.sha256(canonical_arguments(arguments).encode("utf-8")).hexdigest()


def _utcnow() -> datetime:
    return datetime.now(timezone.utc)


class InMemoryConfirmationStore:
    """Reference implementation of the confirmation contract.

    Ported to SQL, the invariants map as follows and must be enforced by the
    database rather than by this code:

    * one pending record per session
        ``UNIQUE (session_id) WHERE status = 'pending'``
    * exactly one winner per confirmation
        ``UPDATE ... SET status='consumed'
          WHERE confirm_id=? AND session_id=? AND user_id=?
            AND status='pending' AND expires_at > now()``
        and only the caller that sees one affected row may call upstream.

    A race here is not theoretical: a double tap, or a new message arriving
    while the user reads the card, both hit it.
    """

    def __init__(
        self,
        *,
        ttl: timedelta = DEFAULT_TTL,
        time_source: Callable[[], datetime] = _utcnow,
    ) -> None:
        self._ttl = ttl
        self._now = time_source
        self._lock = threading.Lock()
        self._by_id: dict[str, PendingConfirmation] = {}
        self._pending_by_session: dict[str, str] = {}

    # -- minting -----------------------------------------------------------

    def mint(
        self,
        *,
        session_id: str,
        user_id: str,
        tool_name: str,
        arguments: Mapping[str, Any],
        display_label: str,
        turn_id: str,
    ) -> PendingConfirmation:
        """Take custody of a proposed mutation. Supersedes any earlier one."""
        if tool_name not in MUTATING_TOOLS:
            raise NotAMutatingTool(
                f"{tool_name} is a read tool; it executes without confirmation"
            )
        now = self._now()
        record = PendingConfirmation(
            confirm_id=uuid.uuid4().hex,
            session_id=session_id,
            user_id=user_id,
            tool_name=tool_name,
            arguments=dict(arguments),
            args_hash=hash_arguments(arguments),
            display_label=display_label,
            idempotency_key=uuid.uuid4().hex,
            created_at=now,
            expires_at=now + self._ttl,
            status=ConfirmationStatus.PENDING,
            turn_id=turn_id,
        )
        with self._lock:
            self._retire_pending(session_id, ConfirmationStatus.SUPERSEDED)
            self._by_id[record.confirm_id] = record
            self._pending_by_session[session_id] = record.confirm_id
        return record

    # -- consumption -------------------------------------------------------

    def consume(
        self, *, confirm_id: str, session_id: str, user_id: str
    ) -> PendingConfirmation:
        """Claim a confirmation for execution. At most one caller succeeds.

        The identity checks are what stop a confirm_id leaked from one session
        being spent in another.
        """
        now = self._now()
        with self._lock:
            record = self._by_id.get(confirm_id)
            if record is None:
                raise ConfirmationError(ConfirmationRefusal.NOT_FOUND)
            if record.session_id != session_id or record.user_id != user_id:
                raise ConfirmationError(ConfirmationRefusal.WRONG_OWNER)
            if record.status is not ConfirmationStatus.PENDING:
                raise ConfirmationError(
                    ConfirmationRefusal.NOT_PENDING, f"status={record.status.value}"
                )
            if now >= record.expires_at:
                self._store(replace(record, status=ConfirmationStatus.EXPIRED))
                self._pending_by_session.pop(session_id, None)
                raise ConfirmationError(ConfirmationRefusal.EXPIRED)

            claimed = replace(record, status=ConfirmationStatus.CONSUMED)
            self._store(claimed)
            self._pending_by_session.pop(session_id, None)
            return claimed

    def mark_unknown(self, confirm_id: str) -> None:
        """Upstream was called but its outcome was never learned.

        Deliberately one-way. Returning the record to PENDING would let a
        retry write twice.
        """
        with self._lock:
            record = self._by_id.get(confirm_id)
            if record is not None:
                self._store(replace(record, status=ConfirmationStatus.UNKNOWN))

    # -- cancellation ------------------------------------------------------

    def cancel(self, session_id: str) -> PendingConfirmation | None:
        """Drop the session's pending confirmation. Needs no confirm_id.

        A wrong cancellation costs nothing, so this is deliberately easy to
        reach -- from a button, or from a refusal in ordinary text.
        """
        with self._lock:
            return self._retire_pending(session_id, ConfirmationStatus.CANCELLED)

    # -- reads -------------------------------------------------------------

    def peek(self, session_id: str) -> PendingConfirmation | None:
        """The session's live confirmation, expiring it in passing if stale."""
        now = self._now()
        with self._lock:
            confirm_id = self._pending_by_session.get(session_id)
            if confirm_id is None:
                return None
            record = self._by_id[confirm_id]
            if now >= record.expires_at:
                self._store(replace(record, status=ConfirmationStatus.EXPIRED))
                self._pending_by_session.pop(session_id, None)
                return None
            return record

    def get(self, confirm_id: str) -> PendingConfirmation | None:
        with self._lock:
            return self._by_id.get(confirm_id)

    # -- internals (call with the lock held) -------------------------------

    def _retire_pending(
        self, session_id: str, status: ConfirmationStatus
    ) -> PendingConfirmation | None:
        confirm_id = self._pending_by_session.pop(session_id, None)
        if confirm_id is None:
            return None
        retired = replace(self._by_id[confirm_id], status=status)
        self._store(retired)
        return retired

    def _store(self, record: PendingConfirmation) -> None:
        self._by_id[record.confirm_id] = record
