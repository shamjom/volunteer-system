<?php

namespace App\Support;

/**
 * بصمة (اسم الأداة + معاملاتها) — تُحسب عند إصدار التأكيد وعند استهلاكه،
 * فلا يصلح تأكيدُ «سجّلني في فعالية ٣» لتنفيذ «سجّلني في فعالية ٧».
 */
class ConfirmationFingerprint
{
    public static function make(string $tool, array $arguments): string
    {
        return hash('sha256', $tool . '|' . self::canonical($arguments));
    }

    private static function canonical(array $arguments): string
    {
        // القيم الفارغة لا تُغيّر العملية، والترتيب لا يجب أن يُغيّر البصمة
        $arguments = array_filter(
            $arguments,
            fn ($value) => $value !== null && $value !== ''
        );

        ksort($arguments);

        foreach ($arguments as $key => $value) {
            if (is_array($value)) {
                $arguments[$key] = self::canonical($value);
            }
        }

        return json_encode($arguments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
