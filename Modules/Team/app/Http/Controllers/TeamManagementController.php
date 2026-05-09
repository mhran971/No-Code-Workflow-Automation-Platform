<?php

namespace Modules\Team\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Auth\Models\User;
use Modules\Team\Http\Requests\CreateTeamRequest;
use Modules\Team\Http\Requests\UpdateTeamManagerRequest;
use Modules\Team\Http\Requests\UpdateTeamRequest;
use Modules\Team\Models\Team;
use Modules\Team\Services\TeamManagementService;

class TeamManagementController extends Controller
{
    public function __construct(
        protected TeamManagementService $teamManagementService
    ) {}

    /**
     * List teams visible to current actor.
     */
    public function index(): JsonResponse
    {
        /** @var User $actor */
        $actor = auth('api')->user();

        $teams = $this->teamManagementService->listVisibleTeams($actor);

        return response()->json([
            'data' => $teams->map(fn (Team $team) => $this->serializeTeam($team))->values(),
        ]);
    }

    /**
     * Create a new team with manager assignment.
     */
    public function store(CreateTeamRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = auth('api')->user();

        $team = $this->teamManagementService->createByBusinessOwner(
            $actor,
            $request->validated()
        );

        return response()->json([
            'message' => 'Team created successfully.',
            'team' => $this->serializeTeam($team),
        ], 201);
    }

    /**
     * Show one team when visible to actor.
     */
    public function show(Team $team): JsonResponse
    {
        /** @var User $actor */
        $actor = auth('api')->user();

        $visibleTeam = $this->teamManagementService->getVisibleTeam($actor, $team);

        return response()->json([
            'data' => $this->serializeTeam($visibleTeam),
        ]);
    }

    /**
     * Update team name and description.
     */
    public function update(UpdateTeamRequest $request, Team $team): JsonResponse
    {
        /** @var User $actor */
        $actor = auth('api')->user();

        $updatedTeam = $this->teamManagementService->updateByBusinessOwner(
            $actor,
            $team,
            $request->validated()
        );

        return response()->json([
            'message' => 'Team updated successfully.',
            'team' => $this->serializeTeam($updatedTeam),
        ]);
    }

    /**
     * Replace current manager for a team.
     */
    public function updateManager(UpdateTeamManagerRequest $request, Team $team): JsonResponse
    {
        /** @var User $actor */
        $actor = auth('api')->user();
        $validated = $request->validated();

        $updatedTeam = $this->teamManagementService->updateManagerByBusinessOwner(
            $actor,
            $team,
            (int) $validated['manager_id']
        );

        return response()->json([
            'message' => 'Team manager updated successfully.',
            'team' => $this->serializeTeam($updatedTeam),
        ]);
    }

    /**
     * Transform team payload.
     */
    protected function serializeTeam(Team $team): array
    {
        return [
            'id' => $team->id,
            'name' => $team->name,
            'description' => $team->description,
            'tenant_id' => $team->tenant_id,
            'manager' => $team->manager ? [
                'id' => $team->manager->id,
                'first_name' => $team->manager->first_name,
                'last_name' => $team->manager->last_name,
                'position' => $team->manager->position,
                'email' => $team->manager->email,
                'role' => $team->manager->role?->value,
            ] : null,
            'members' => collect($team->members ?? [])->map(fn (User $member) => [
                'id' => $member->id,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'position' => $member->position,
                'email' => $member->email,
                'role' => $member->role?->value,
            ])->values(),
            'created_at' => $team->created_at,
            'updated_at' => $team->updated_at,
        ];
    }
}
