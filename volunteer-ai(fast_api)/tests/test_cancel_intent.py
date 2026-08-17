from __future__ import annotations

import pytest

from app.confirm import normalize_arabic, reads_as_refusal

REFUSALS = [
    "لا",
    "لأ",
    "لاء",
    "كلا",
    "لا شكراً",
    "ألغِ",
    "الغاء",
    "إلغاء الطلب",
    "بطّل",
    "كنسل",
    "تراجع",
    "ما بدي",
    "مو هيك",
    "بلاش",
    "خلص لا",
    "stop",
    "cancel",
    "no",
]

NOT_REFUSALS = [
    "نعم",
    "أكيد",
    "أؤكد",
    "تمام",
    "موافق",
    "اي",
    "لا مشكلة",
    "لا بأس",
    "لا مانع",
    "أريد التسجيل في فعالية التشجير يوم الجمعة القادم",
    "ما هي الفعاليات القادمة؟",
]


@pytest.mark.parametrize("text", REFUSALS)
def test_refusals_cancel(text):
    assert reads_as_refusal(text), text


@pytest.mark.parametrize("text", NOT_REFUSALS)
def test_affirmatives_and_questions_do_not_cancel(text):
    assert not reads_as_refusal(text), text


def test_matching_survives_spelling_variation():
    """The same word written four ways must reach the same decision."""
    for spelling in ("إلغاء", "الغاء", "إلْغاء", "الــغاء"):
        assert reads_as_refusal(spelling), spelling


def test_negation_inside_a_long_sentence_is_not_a_cancellation():
    assert not reads_as_refusal("لا أعرف أي فعالية أختار من بين كل هذه الخيارات")


def test_normalization_folds_hamza_taa_marbuta_and_alef_maqsura():
    assert normalize_arabic("أإآ") == "ااا"
    assert normalize_arabic("فعاليّة") == "فعاليه"
    assert normalize_arabic("مستشفى") == "مستشفي"


def test_empty_message_is_not_a_refusal():
    assert not reads_as_refusal("")
    assert not reads_as_refusal("   ")
