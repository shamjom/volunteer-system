"""Detection of refusal in ordinary text, checked before the model runs.

A pending confirmation must die the moment the user says no, without waiting
for a round trip through the model and without needing a confirm_id. Since a
wrong cancellation costs nothing -- the user simply asks again -- the matcher
is deliberately generous, with one narrow exception for affirmatives that
happen to contain "لا".
"""

from __future__ import annotations

import re
import unicodedata

# Applied to the user's text only, never to text that reaches the prompt.
_TASHKEEL = re.compile(r"[ؐ-ًؚ-ٰٟۖ-ۭ]")
_TATWEEL = re.compile(r"ـ")
_NON_WORD = re.compile(r"[^\w\s]", re.UNICODE)
_SPACES = re.compile(r"\s+")

_ALEF = str.maketrans({"أ": "ا", "إ": "ا", "آ": "ا", "ٱ": "ا"})
_YEH = str.maketrans({"ى": "ي", "ئ": "ي"})
_TEH = str.maketrans({"ة": "ه"})
_WAW = str.maketrans({"ؤ": "و"})

# Phrases that contain a refusal token but mean the opposite. Checked first.
_AFFIRMATIVE_WITH_LA = (
    "لا مشكله",
    "لا باس",
    "لا شك",
    "لا مانع",
    "لا يهم",
)

_REFUSALS = (
    "لا",
    "لأ",
    "لاء",
    "كلا",
    "الغاء",
    "الغ",
    "الغي",
    "بطل",
    "كنسل",
    "تراجع",
    "ما بدي",
    "مو هيك",
    "مش هيك",
    "خلص لا",
    "بلاش",
    "توقف",
    "stop",
    "cancel",
    "no",
)

# Standalone refusals only match a short message: "لا" inside a long sentence
# is usually negation, not a decision about the card.
_SHORT_MESSAGE_WORDS = 4


def normalize_arabic(text: str) -> str:
    """Fold orthographic variation so matching does not depend on spelling.

    Hamza forms, taa marbuta and alef maqsura are unified, diacritics and
    tatweel dropped, digits left alone.
    """
    text = unicodedata.normalize("NFKC", text)
    text = _TASHKEEL.sub("", text)
    text = _TATWEEL.sub("", text)
    text = text.translate(_ALEF).translate(_YEH).translate(_TEH).translate(_WAW)
    text = _NON_WORD.sub(" ", text)
    return _SPACES.sub(" ", text).strip().casefold()


def reads_as_refusal(text: str) -> bool:
    """True when the message should cancel a pending confirmation."""
    normalized = normalize_arabic(text)
    if not normalized:
        return False
    words = normalized.split()

    for phrase in _AFFIRMATIVE_WITH_LA:
        # Whole words only: as a raw substring "لا شك" also matches "لا شكراً",
        # which is a refusal.
        if _contains_words(words, normalize_arabic(phrase).split()):
            return False

    for phrase in _REFUSALS:
        needle = normalize_arabic(phrase).split()
        if len(needle) > 1:
            if _contains_words(words, needle):
                return True
        elif needle[0] in words and len(words) <= _SHORT_MESSAGE_WORDS:
            return True
    return False


def _contains_words(haystack: list[str], needle: list[str]) -> bool:
    span = len(needle)
    return any(
        haystack[i : i + span] == needle for i in range(len(haystack) - span + 1)
    )
