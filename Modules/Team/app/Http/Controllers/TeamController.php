<?php

namespace Modules\Team\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Teams\Http\Requests\StoreTeamRequest;
use App\Modules\Teams\Http\Requests\UpdateTeamRequest;
use App\Modules\Teams\Http\Resources\TeamResource;
use App\Modules\Teams\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TeamController extends Controller
{
    protected $service;

    public function __construct(TeamService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of teams for the tenant.
     * Input JSON: Query Params { "search": "design" }
     * Header: X-Tenant-ID (Assumed from auth or header)
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        // Assuming Tenant ID is retrieved from authenticated user or header
        $tenantId = $request->header('X-Tenant-ID') ?? auth()->user()->tenant_id;
        $search = $request->query('search');

        $teams = $this->service->listTeams($tenantId, $search);

        return TeamResource::collection($teams);
    }

    /**
     * Store a newly created team.
     * Input JSON: { "name": "Sales", "description": "Sales Team" }
     *
     * @param StoreTeamRequest $request
     * @return TeamResource
     */
    public function store(StoreTeamRequest $request)
    {
        $tenantId = $request->header('X-Tenant-ID') ?? auth()->user()->tenant_id;
        $data = $request->validated();
        $data['tenant_id'] = $tenantId;

        $team = $this->service->createTeam($data);

        return new TeamResource($team);
    }

    /**
     * Update the specified team.
     * Input JSON: { "name": "Updated Sales", "description": "..." }
     *
     * @param UpdateTeamRequest $request
     * @param int $id
     * @return TeamResource
     */
    public function update(UpdateTeamRequest $request, int $id)
    {
        $tenantId = $request->header('X-Tenant-ID') ?? auth()->user()->tenant_id;
        $team = $this->service->listTeams($tenantId, null)->find($id); // Simplified lookup

        if (!$team) {
            return response()->json(['message' => 'Team not found'], 404);
        }

        $updatedTeam = $this->service->updateTeam($team, $request->validated());

        return new TeamResource($updatedTeam);
    }

    /**
     * Remove the specified team.
     * Input JSON: (None, ID in URL)
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request, int $id)
    {
        $tenantId = $request->header('X-Tenant-ID') ?? auth()->user()->tenant_id;
        $team = $this->service->listTeams($tenantId, null)->find($id);

        if (!$team) {
            return response()->json(['message' => 'Team not found'], 404);
        }

        $this->service->deleteTeam($team);

        return response()->json(['message' => 'Team deleted successfully'], 200);
    }

    /**
     * Add a member to the team.
     * Input JSON: { "user_id": 5, "role": "admin" }
     *
     * @param Request $request
     * @param int $teamId
     * @return JsonResponse
     */
    public function addMember(Request $request, int $teamId)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role' => 'sometimes|in:owner,admin,member'
        ]);

        $tenantId = $request->header('X-Tenant-ID') ?? auth()->user()->tenant_id;
        $team = $this->service->listTeams($tenantId, null)->find($teamId);

        if (!$team) {
            return response()->json(['message' => 'Team not found'], 404);
        }

        $this->service->addMemberToTeam($team, $request->user_id, $request->role ?? 'member');

        return response()->json(['message' => 'Member added successfully'], 200);
    }

    /**
     * Remove a member from the team.
     * Input JSON: { "user_id": 5 }
     *
     * @param Request $request
     * @param int $teamId
     * @return JsonResponse
     */
    public function removeMember(Request $request, int $teamId)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $tenantId = $request->header('X-Tenant-ID') ?? auth()->user()->tenant_id;
        $team = $this->service->listTeams($tenantId, null)->find($teamId);

        if (!$team) {
            return response()->json(['message' => 'Team not found'], 404);
        }

        $this->service->removeMemberFromTeam($team, $request->user_id);

        return response()->json(['message' => 'Member removed successfully'], 200);
    }
}
