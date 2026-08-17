<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\VolunteerRequest;
use App\Support\AgentResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequestToolsController extends Controller
{
    /**
     * get_my_requests_status
     */
    public function myRequests(Request $request): JsonResponse
    {
        $data = $request->validate([
            'request_type' => 'sometimes|nullable|in:join_team,help,volunteering',
        ]);

        $query = VolunteerRequest::with('team')
            ->where('user_id', $request->user()->id);

        if (! empty($data['request_type'])) {
            $query->where('type', $data['request_type']);
        }

        $requests = $query->orderByDesc('submitted_at')->get();

        return AgentResponse::ok([
            'count'        => $requests->count(),
            'request_type' => $data['request_type'] ?? null,
            'requests'     => $requests->map(fn (VolunteerRequest $item) => [
                'request_id'   => $item->id,
                'type'         => $item->type,
                'subject'      => $item->subject,
                'body'         => $item->body,
                'status'       => $item->status,
                'team_id'      => $item->team_id,
                'team_name_ar' => $item->team?->name,
                'submitted_at' => optional($item->submitted_at)->toDateTimeString(),
                'resolved_at'  => optional($item->resolved_at)->toDateTimeString(),
                'admin_note'   => $item->admin_note,
            ])->all(),
        ]);
    }

    /**
     * submit_help_request
     */
    public function submitHelp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'body'    => 'required|string|max:5000',
        ]);

        $helpRequest = VolunteerRequest::create([
            'user_id'      => $request->user()->id,
            'type'         => 'help',
            'subject'      => $data['subject'],
            'body'         => $data['body'],
            'status'       => 'pending',
            'submitted_at' => now(),
        ]);

        return AgentResponse::ok([
            'request_id'   => $helpRequest->id,
            'type'         => 'help',
            'status'       => 'pending',
            'subject'      => $helpRequest->subject,
            'submitted_at' => $helpRequest->submitted_at->toDateTimeString(),
        ], 201);
    }
}
