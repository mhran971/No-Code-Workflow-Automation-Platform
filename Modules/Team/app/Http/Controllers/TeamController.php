<?php

namespace Modules\Team\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Team\app\Http\Requests\StoreTeamRequest;
use Modules\Team\app\Http\Requests\UpdateTeamRequest;
use Modules\Team\App\Http\Resource\TeamResource;
use Modules\Team\Services\TeamService;

class TeamController extends Controller
{
    protected $service;

    /**
     * Inject TeamService dependency.
     */
    public function __construct(TeamService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of teams for the tenant.
     * Supports search via query parameter: ?search=name
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Retrieve Tenant ID from Header or Authenticated User
        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId && auth()->check()) {
            $tenantId = auth()->user()->tenant_id;
        }

        if (!$tenantId) {
            return response()->json(['message ' => 'Tenant ID is required. Provide X-Tenant-ID header or authenticate.'], 400);
        }

        $search = $request->query('search');

        $teams = $this->service->listTeams($tenantId, $search);

        return TeamResource::collection($teams);
    }

    /**
     * Store a newly created team.
     *
     * @param StoreTeamRequest $request
     * @return TeamResource
     */
    public function store(StoreTeamRequest $request): TeamResource
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId && auth()->check()) {
            $tenantId = auth()->user()->tenant_id;
        }

        if (!$tenantId) {
            abort(400, 'Tenant ID is required. Provide X-Tenant-ID header or authenticate.');
        }

        $data = $request->validated();
        $data['tenant_id'] = $tenantId;

        $team = $this->service->createTeam($data);

        return new TeamResource($team);
    }

    /**
     * Update the specified team.
     *
     * @param UpdateTeamRequest $request
     * @param int $id
     * @return TeamResource
     */
    public function update(UpdateTeamRequest $request, int $id): TeamResource
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId && auth()->check()) {
            $tenantId = auth()->user()->tenant_id;
        }

        if (!$tenantId) {
            abort(400, 'Tenant ID is required. Provide X-Tenant-ID header or authenticate.');
        }

        $team = $this->service->getTeamById($tenantId, $id);

        if (!$team) {
            abort(404, 'Team not found');
        }

        $updatedTeam = $this->service->updateTeam($team, $request->validated());

        return new TeamResource($updatedTeam);
    }

    /**
     * Remove the specified team (Soft Delete).
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId && auth()->check()) {
            $tenantId = auth()->user()->tenant_id;
        }

        if (!$tenantId) {
            return response()->json(['message' => 'Tenant ID is required. Provide X-Tenant-ID header or authenticate.'], 400);
        }

        $team = $this->service->getTeamById($tenantId, $id);

        if (!$team) {
            return response()->json(['message' => 'Team not found'], 404);
        }

        $this->service->deleteTeam($team);

        return response()->json(['message' => 'Team deleted successfully'], 200);
    }

    /**
     * Add a member to the team.
     * Expected JSON: { "user_id": 1, "role": "member" }
     *
     * @param Request $request
     * @param int $teamId
     * @return JsonResponse
     */
    public function addMember(Request $request, int $teamId): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role' => 'sometimes|in:owner,admin,member'
        ]);

        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId && auth()->check()) {
            $tenantId = auth()->user()->tenant_id;
        }

        if (!$tenantId) {
            return response()->json(['message' => 'Tenant ID is required. Provide X-Tenant-ID header or authenticate.'], 400);
        }

        $team = $this->service->getTeamById($tenantId, $teamId);

        if (!$team) {
            return response()->json(['message' => 'Team not found'], 404);
        }

        $this->service->addMemberToTeam($team, $request->input('user_id'), $request->input('role', 'member'));

        return response()->json(['message' => 'Member added successfully'], 200);
    }

    /**
     * Remove a member from the team.
     * Expected JSON: { "user_id": 1 }
     *
     * @param Request $request
     * @param int $teamId
     * @return JsonResponse
     */
    public function removeMember(Request $request, int $teamId): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId && auth()->check()) {
            $tenantId = auth()->user()->tenant_id;
        }

        if (!$tenantId) {
            return response()->json(['message' => 'Tenant ID is required. Provide X-Tenant-ID header or authenticate.'], 400);
        }

        $team = $this->service->getTeamById($tenantId, $teamId);

        if (!$team) {
            return response()->json(['message' => 'Team not found'], 404);
        }

        $this->service->removeMemberFromTeam($team, $request->input('user_id'));

        return response()->json(['message' => 'Member removed successfully'], 200);
    }
}
