<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        $branches = Branch::query()
            ->when($search, function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('province', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => Branch::count(),
            'open' => Branch::where('status', 'open')->count(),
            'closed' => Branch::where('status', 'closed')->count(),
            'coming_soon' => Branch::where('status', 'coming_soon')->count(),
        ];

        return response()->json(compact('branches', 'stats'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:branches,slug'],
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

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        $branch = Branch::create($data);

        return response()->json($branch, 201);
    }

    public function update(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', "unique:branches,slug,{$branch->id}"],
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

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        $branch->update($data);

        return response()->json($branch->fresh());
    }

    public function destroy(Branch $branch): JsonResponse
    {
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
