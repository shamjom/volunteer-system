<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\VolunteerRequest;
use App\Support\AgentResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamToolsController extends Controller
{
    /**
     * list_teams
     */
    public function listTeams(Request $request): JsonResponse
    {
        $teams = Team::query()
            ->where('is_active', true)
            ->withCount('members')
            ->orderBy('name')
            ->get();

        return AgentResponse::ok([
            'count' => $teams->count(),
            'teams' => $teams->map(fn (Team $team) => [
                'team_id'       => $team->id,
                'name_ar'       => $team->name,
                'description'   => $team->description,
                'members_count' => (int) $team->members_count,
            ])->all(),
        ]);
    }

    /**
     * request_join_team — يُنشئ طلباً معلّقاً لا انضماماً مباشراً.
     */
    public function requestJoin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'team_id'    => 'required|integer',
            'motivation' => 'sometimes|nullable|string|max:2000',
        ]);

        $user = $request->user();
        $team = Team::where('is_active', true)->find($data['team_id']);

        if (! $team) {
            return AgentResponse::fail(
                AgentResponse::NOT_FOUND,
                'لم أجد هذا الفريق.'
            );
        }

        if ($team->members()->where('users.id', $user->id)->exists()) {
            return AgentResponse::fail(
                AgentResponse::ALREADY_REGISTERED,
                'أنت عضو في هذا الفريق بالفعل.'
            );
        }

        $hasPending = VolunteerRequest::where('user_id', $user->id)
            ->where('team_id', $team->id)
            ->where('type', 'join_team')
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return AgentResponse::fail(
                AgentResponse::ALREADY_REGISTERED,
                'لديك طلب انضمام معلّق لهذا الفريق.'
            );
        }

        try {
            $volunteerRequest = VolunteerRequest::create([
                'user_id'      => $user->id,
                'type'         => 'join_team',
                'team_id'      => $team->id,
                'subject'      => 'طلب انضمام لفريق ' . $team->name,
                'body'         => $data['motivation'] ?? null,
                'status'       => 'pending',
                'submitted_at' => now(),
            ]);
        } catch (QueryException $e) {
            // الفهرس الفريد على (user_id, team_id) للطلبات المعلّقة
            if ($this->isDuplicate($e)) {
                return AgentResponse::fail(
                    AgentResponse::ALREADY_REGISTERED,
                    'لديك طلب انضمام معلّق لهذا الفريق.'
                );
            }

            throw $e;
        }

        return AgentResponse::ok([
            'request_id'   => $volunteerRequest->id,
            'type'         => 'join_team',
            'status'       => 'pending',
            'team_id'      => $team->id,
            'team_name_ar' => $team->name,
            'submitted_at' => $volunteerRequest->submitted_at->toDateTimeString(),
        ], 201);
    }

    private function isDuplicate(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }
}
