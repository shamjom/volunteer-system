<?php

namespace App\Models;

use App\Support\ArabicText;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    protected $fillable = [
        'question',
        'answer',
        'category',
        'keywords',
        'normalized_text',
        'is_published',
        'sort_order',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    protected static function booted(): void
    {
        $build = function (Faq $faq) {
            $faq->normalized_text = ArabicText::normalize(
                implode(' ', array_filter([
                    $faq->question,
                    $faq->keywords,
                    $faq->category,
                    $faq->answer,
                ]))
            );
        };

        static::creating($build);
        static::updating($build);
    }
}
