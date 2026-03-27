<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BranchesController extends Controller
{
    /**
     * Index
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        return view('panel.branches');
    }

    public function list(Request $request): JsonResponse
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $branchesQuery = Branch::query()
            ->when(! auth()->user()->hasRole('super admin'), fn ($q) => $q->whereKey(auth()->user()->branches()->pluck('branches.id')))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('province', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($q, $s) => $q->where('status', $s));

        $branches = (clone $branchesQuery)
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => (clone $branchesQuery)->count(),
            'open' => (clone $branchesQuery)->where('status', 'open')->count(),
            'closed' => (clone $branchesQuery)->where('status', 'closed')->count(),
            'coming_soon' => (clone $branchesQuery)->where('status', 'coming_soon')->count(),
        ];

        return response()->json(compact('branches', 'stats'));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->hasRole('super admin'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:open,closed,coming_soon'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'opening_time' => ['nullable', 'date_format:H:i'],
            'closing_time' => ['nullable', 'date_format:H:i'],
            'facebook_url' => ['nullable', 'url', 'max:500'],
            'messenger_url' => ['nullable', 'url', 'max:500'],
            'whatsapp_url' => ['nullable', 'url', 'max:500'],
            'map_url' => ['nullable', 'url', 'max:500'],
        ]);

        $branch = Branch::create($data);

        return response()->json($branch, 201);
    }

    public function update(Request $request, Branch $branch): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);

        if (auth()->user()->hasRole('super admin')) {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'status' => ['required', 'in:open,closed,coming_soon'],
                'city' => ['required', 'string', 'max:255'],
                'province' => ['nullable', 'string', 'max:255'],
                'address' => ['nullable', 'string', 'max:500'],
                'phone' => ['nullable', 'string', 'max:50'],
                'email' => ['nullable', 'email', 'max:255'],
                'opening_time' => ['nullable', 'date_format:H:i'],
                'closing_time' => ['nullable', 'date_format:H:i'],
                'facebook_url' => ['nullable', 'url', 'max:500'],
                'messenger_url' => ['nullable', 'url', 'max:500'],
                'whatsapp_url' => ['nullable', 'url', 'max:500'],
                'map_url' => ['nullable', 'url', 'max:500'],
            ]);
        } else {
            $data = $request->validate([
                'city' => ['required', 'string', 'max:255'],
                'province' => ['nullable', 'string', 'max:255'],
                'address' => ['nullable', 'string', 'max:500'],
                'phone' => ['nullable', 'string', 'max:50'],
                'email' => ['nullable', 'email', 'max:255'],
                'opening_time' => ['nullable', 'date_format:H:i'],
                'closing_time' => ['nullable', 'date_format:H:i'],
                'facebook_url' => ['nullable', 'url', 'max:500'],
                'messenger_url' => ['nullable', 'url', 'max:500'],
                'whatsapp_url' => ['nullable', 'url', 'max:500'],
                'map_url' => ['nullable', 'url', 'max:500'],
            ]);
        }

        $branch->update($data);

        return response()->json($branch->fresh());
    }

    public function destroy(Branch $branch): JsonResponse
    {
        abort_unless(auth()->user()->hasRole('super admin'), 403);

        // Delete all stored photos from disk
        if ($branch->photos) {
            foreach ($branch->photos as $path) {
                Storage::disk('public')->delete($path);
            }
        }

        $branch->delete();

        return response()->json(null, 204);
    }

    public function storePhoto(Request $request, Branch $branch): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $path = $request->file('photo')->store("branches/{$branch->id}", 'public');

        $photos = $branch->photos ?? [];
        $photos[] = $path;
        $branch->update(['photos' => $photos]);

        return response()->json(['path' => $path, 'url' => asset('storage/'.$path)], 201);
    }

    public function destroyPhoto(Branch $branch, int $index): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);

        $photos = $branch->photos ?? [];

        if (! array_key_exists($index, $photos)) {
            return response()->json(['message' => 'Photo not found'], 404);
        }

        Storage::disk('public')->delete($photos[$index]);
        array_splice($photos, $index, 1);
        $branch->update(['photos' => $photos]);

        return response()->json(null, 204);
    }
}
