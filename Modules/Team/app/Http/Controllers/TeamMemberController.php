<?php

namespace Modules\Team\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Auth\Models\User;
use Modules\Team\Http\Requests\AddTeamMemberRequest;
use Modules\Team\Models\Team;
use Modules\Team\Services\TeamMemberManagementService;

class TeamMemberController extends Controller
{
    public function __construct(
        protected TeamMemberManagementService $teamMemberManagementService
    ) {}

    /**
     * Add an existing tenant user to team.
     */
    public function store(AddTeamMemberRequest $request, Team $team): JsonResponse
    {
        /** @var User $actor */
        $actor = auth('api')->user();
        $validated = $request->validated();

        $user = $this->teamMemberManagementService->addMember(
            $actor,
            $team,
            (int) $validated['user_id']
        );

        return response()->json([
            'message' => 'Team member added successfully.',
            'member' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'role' => $user->role?->value,
            ],
        ]);
    }

    /**
     * Remove a user from team.
     */
    public function destroy(Team $team, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = auth('api')->user();

        $this->teamMemberManagementService->removeMember($actor, $team, $user);

        return response()->json([
            'message' => 'Team member removed successfully.',
        ]);
    }
}

