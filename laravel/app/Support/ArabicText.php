<?php

namespace App\Support;

/**
 * تطبيع النص العربي ودرجة التطابق الضبابي.
 *
 * «حمله التبرعات» و«حملة التبرُّعات» و«حملة تبرعات» يجب أن تتطابق.
 */
class ArabicText
{
    /** التشكيل والتطويل. */
    private const DIACRITICS = '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u';

    private const LETTER_MAP = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ٲ' => 'ا', 'ٳ' => 'ا',
        'ة' => 'ه',
        'ى' => 'ي', 'ی' => 'ي', 'ئ' => 'ي',
        'ؤ' => 'و',
        'ء' => '',
        'ك' => 'ك', 'ک' => 'ك', 'گ' => 'ك',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    /** كلمات لا تحمل معنى تمييزياً في أسماء الفعاليات. */
    private const STOP_WORDS = ['في', 'من', 'الى', 'على', 'عن', 'مع', 'و', 'ال'];

    public static function normalize(?string $value): string
    {
        $value = (string) $value;

        $value = preg_replace(self::DIACRITICS, '', $value);
        $value = strtr($value, self::LETTER_MAP);

        // كل ما ليس حرفاً أو رقماً يصير مسافة
        $value = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim(mb_strtolower($value, 'UTF-8'));
    }

    /**
     * تقسيم لكلمات مع حذف أداة التعريف والكلمات الحشوية.
     *
     * @return array<int, string>
     */
    public static function tokens(?string $value): array
    {
        $normalized = self::normalize($value);

        if ($normalized === '') {
            return [];
        }

        $tokens = [];

        foreach (explode(' ', $normalized) as $token) {
            // حذف «ال» التعريف إن بقي بعدها جذر معقول
            if (mb_strlen($token) > 4 && mb_substr($token, 0, 2) === 'ال') {
                $token = mb_substr($token, 2);
            }

            if ($token === '' || in_array($token, self::STOP_WORDS, true)) {
                continue;
            }

            $tokens[] = $token;
        }

        return $tokens;
    }

    /**
     * درجة تطابق بين 0 و 1.
     */
    public static function similarity(?string $needle, ?string $haystack): float
    {
        $a = self::normalize($needle);
        $b = self::normalize($haystack);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        $charScore = self::ratio($a, $b);

        $needleTokens   = self::tokens($needle);
        $haystackTokens = self::tokens($haystack);

        $matched = 0.0;

        foreach ($needleTokens as $token) {
            $best = 0.0;

            foreach ($haystackTokens as $candidate) {
                $best = max($best, self::ratio($token, $candidate));
            }

            if ($best >= 0.75) {
                $matched += $best;
            }
        }

        $tokenScore = $needleTokens ? $matched / count($needleTokens) : 0.0;

        // تغطية: استعلام من كلمة واحدة مقابل عنوان من ست كلمات ليس تطابقاً تاماً
        $coverage = $haystackTokens
            ? min(1.0, count($needleTokens) / count($haystackTokens))
            : 0.0;

        $score = (0.65 * $tokenScore) + (0.25 * $charScore) + (0.10 * $coverage);

        // الاحتواء الحرفي بعد التطبيع مؤشر قوي
        if ($a !== '' && str_contains($b, $a)) {
            $score = max($score, 0.85 + (0.15 * $charScore));
        }

        return round(min(1.0, max(0.0, $score)), 3);
    }

    private static function ratio(string $a, string $b): float
    {
        $max = max(mb_strlen($a, 'UTF-8'), mb_strlen($b, 'UTF-8'));

        if ($max === 0) {
            return 0.0;
        }

        return 1 - (self::distance($a, $b) / $max);
    }

    /**
     * مسافة ليفنشتاين على مستوى المحارف لا البايتات — levenshtein() في PHP
     * تعمل على البايتات وتعطي نتائج خاطئة مع العربية.
     */
    private static function distance(string $a, string $b): int
    {
        $x = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $y = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $n = count($x);
        $m = count($y);

        if ($n === 0) {
            return $m;
        }

        if ($m === 0) {
            return $n;
        }

        $previous = range(0, $m);

        for ($i = 1; $i <= $n; $i++) {
            $current = [$i];

            for ($j = 1; $j <= $m; $j++) {
                $cost = $x[$i - 1] === $y[$j - 1] ? 0 : 1;

                $current[$j] = min(
                    $previous[$j] + 1,
                    $current[$j - 1] + 1,
                    $previous[$j - 1] + $cost
                );
            }

            $previous = $current;
        }

        return $previous[$m];
    }
}
