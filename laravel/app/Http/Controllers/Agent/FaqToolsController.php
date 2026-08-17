<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Support\AgentResponse;
use App\Support\ArabicText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqToolsController extends Controller
{
    /**
     * search_faq — ترتيب بالتطابق الضبابي العربي على النص المطبَّع.
     */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => 'required|string|max:255',
        ]);

        $tokens = ArabicText::tokens($data['query']);

        $candidates = Faq::query()->where('is_published', true);

        // تضييق مبدئي في قاعدة البيانات، ثم ترتيب دقيق في PHP
        if ($tokens) {
            $candidates->where(function ($query) use ($tokens) {
                foreach ($tokens as $token) {
                    $query->orWhere('normalized_text', 'like', '%' . $token . '%');
                }
            });
        }

        $matches = $candidates->limit(100)->get();

        if ($matches->isEmpty()) {
            $matches = Faq::where('is_published', true)->limit(100)->get();
        }

        $scored = $matches
            ->map(fn (Faq $faq) => [
                'faq'   => $faq,
                'score' => max(
                    ArabicText::similarity($data['query'], $faq->question),
                    ArabicText::similarity($data['query'], (string) $faq->keywords) * 0.9,
                    ArabicText::similarity($data['query'], $faq->answer) * 0.6
                ),
            ])
            ->filter(fn ($row) => $row['score'] >= 0.25)
            ->sortByDesc('score')
            ->take(5)
            ->values();

        if ($scored->isEmpty()) {
            return AgentResponse::fail(
                AgentResponse::NOT_FOUND,
                'لم أجد إجابة عن هذا السؤال في الأسئلة الشائعة.'
            );
        }

        return AgentResponse::ok([
            'query'   => $data['query'],
            'count'   => $scored->count(),
            'results' => $scored->map(fn ($row) => [
                'faq_id'      => $row['faq']->id,
                'question_ar' => $row['faq']->question,
                'answer_ar'   => $row['faq']->answer,
                'category'    => $row['faq']->category,
                'match_score' => round($row['score'], 3),
            ])->all(),
        ]);
    }
}
