<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\MemberPtPackage;
use App\Models\MemberPtSessionUsage;
use App\Models\User;
use App\Services\MemberPtPackageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * A coach's own PT clients and session logs. Every query is keyed on the
 * authenticated user; routes are gated by `permission:log pt sessions` in routes/web.php.
 * Only the member's name crosses the wire — no contact details.
 */
class CoachPtSessionsController extends Controller
{
    public function __construct(
        private MemberPtPackageService $memberPtPackageService,
    ) {}

    /**
     * Display the clients list page.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        return view('panel.pt-sessions');
    }

    /**
     * Return a paginated list of the coach's clients.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        $coachId = auth()->id();
        $search = trim((string) $request->query('search', ''));
        $filter = $request->query('filter', '');

        $ownPackages = fn ($query) => $query->where('coach_id', $coachId);
        $ownActivePackages = fn ($query) => $ownPackages($query)->where('status', MemberPtPackage::STATUS_ACTIVE);

        $clients = User::query()
            ->whereHas('memberPtPackages', $filter === 'active' ? $ownActivePackages : $ownPackages)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($filter === 'inactive', fn ($query) => $query->whereDoesntHave('memberPtPackages', $ownActivePackages))
            ->with(['memberPtPackages' => fn ($query) => $ownPackages($query)
                ->with('ptProduct:id,name')
                ->withMax('usages as last_session_at', 'used_at')
                ->orderByDesc('assigned_at')
                ->orderByDesc('id')])
            ->orderBy('name')
            ->paginate(15)
            ->through(function (User $member) {
                $active = $member->memberPtPackages->where('status', MemberPtPackage::STATUS_ACTIVE);

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'status' => $member->status,
                    'current_plan' => $active->first()?->ptProduct?->name,
                    'active_packages' => $active->count(),
                    'packages_count' => $member->memberPtPackages->count(),
                    'remaining_sessions' => (int) $active->sum('remaining_sessions'),
                    'total_sessions' => (int) $active->sum('total_sessions'),
                    'last_session_at' => $member->memberPtPackages->max('last_session_at'),
                ];
            })
            ->withQueryString();

        $stats = MemberPtPackage::query()
            ->where('coach_id', $coachId)
            ->selectRaw('COUNT(DISTINCT user_id) AS total, COUNT(DISTINCT CASE WHEN status = ? THEN user_id END) AS active', [MemberPtPackage::STATUS_ACTIVE])
            ->first();

        return response()->json([
            'clients' => $clients,
            'stats' => ['total' => (int) $stats->total, 'active' => (int) $stats->active],
        ]);
    }

    /**
     * Display one client's page.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function show(User $member): View
    {
        $this->assertOwnClient($member);

        return view('panel.pt-session', [
            'client' => $member->only(['id', 'name', 'status']),
        ]);
    }

    /**
     * Return one client's packages and session logs.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function data(User $member): JsonResponse
    {
        return response()->json($this->clientPayload($member));
    }

    /**
     * Log a PT session against one of the coach's own packages.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $coach = auth()->user();

        $data = $request->validate([
            'member_pt_package_id' => ['required', 'integer'],
            'sessions_used' => ['required', 'integer', 'min:1'],
            'used_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $package = MemberPtPackage::query()
            ->where('coach_id', $coach->id)
            ->with('member:id,name,status')
            ->find($data['member_pt_package_id'])
            ?? throw ValidationException::withMessages(['member_pt_package_id' => 'The selected PT package is not assigned to you.']);

        abort_unless($package->member, 404, 'This member is no longer active.');

        $this->memberPtPackageService->logUsage($package, [...$data, 'coach_id' => $coach->id], $coach->id);

        return response()->json($this->clientPayload($package->member), 201);
    }

    private function assertOwnClient(User $member): void
    {
        abort_unless(
            MemberPtPackage::query()->where('coach_id', auth()->id())->where('user_id', $member->id)->exists(),
            404
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function clientPayload(User $member): array
    {
        $packages = MemberPtPackage::query()
            ->where('coach_id', auth()->id())
            ->where('user_id', $member->id)
            ->with(['ptProduct:id,name', 'usages.coach:id,name', 'usages.recordedBy:id,name'])
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->get();

        abort_if($packages->isEmpty(), 404);

        return [
            'client' => [
                'id' => $member->id,
                'name' => $member->name,
                'status' => $member->status,
                'client_since' => $packages->min('assigned_at')?->toDateString(),
            ],
            'packages' => $packages->map(fn (MemberPtPackage $package) => [
                'id' => $package->id,
                'member_name' => $member->name,
                'plan_name' => $package->ptProduct?->name,
                'status' => $package->status,
                'remaining_sessions' => (int) $package->remaining_sessions,
                'total_sessions' => (int) $package->total_sessions,
                'assigned_at' => $package->assigned_at?->toDateString(),
                'expires_at' => $package->expires_at?->toDateString(),
                'usages' => $package->usages->map(fn (MemberPtSessionUsage $usage) => [
                    'id' => $usage->id,
                    'used_at' => $usage->used_at?->toISOString(),
                    'sessions_used' => (int) $usage->sessions_used,
                    'coach_name' => $usage->coach?->name,
                    'recorded_by_name' => $usage->recordedBy?->name,
                    'confirmed_by' => $usage->confirmed_by,
                    'notes' => $usage->notes,
                ])->values(),
            ])->values(),
        ];
    }
}
