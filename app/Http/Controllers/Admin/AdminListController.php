<?php

namespace App\Http\Controllers\Admin;

use App\Models\ListStory;
use App\Models\ListCategory;
use App\Models\listImage;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class AdminListController extends Controller
{
    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function imageUrl(?string $path): ?string
    {
        // Relative URL — works regardless of APP_URL or host (localhost vs 127.0.0.1)
        return $path ? '/storage/' . $path : null;
    }

    private function formatList(ListStory $list, int $index): array
    {
        $firstImage = $list->images->first();

        return [
            'id'          => $list->id,
            'index'       => $index + 1,
            'title'       => $list->title,
            'user_name'   => optional($list->user)->name ?? 'Admin',
            'category'    => optional($list->category)->category ?? 'N/A',
            'new_price'   => $list->new_price,
            'location'    => $list->location,
            'condition'   => $list->condition,
            'description' => $list->description,
            'status'      => $list->status ? 'active' : 'inactive',
            'image_url'   => $this->imageUrl(optional($firstImage)->path),
            'all_images'  => $list->images->map(fn($img) => $this->imageUrl($img->path)),
            'created_at'  => optional($list->created_at)->format('M d, Y'),
            'edit_url'    => route('admin.list.edit', $list->id),
        ];
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index()
    {
        $lists      = ListStory::with(['user', 'category', 'images'])->orderBy('created_at', 'desc')->get();
        $categories = ListCategory::orderBy('category')->get();

        return view('admin.list_management', compact('lists', 'categories'));
    }

    // ─── AJAX Fetch (auto-refresh) ────────────────────────────────────────────

    public function fetchLists()
    {
        $lists = ListStory::with(['user', 'category', 'images'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->values()
            ->map(fn($list, $i) => $this->formatList($list, $i));

        return response()->json(['success' => true, 'lists' => $lists]);
    }

    // ─── Show (AJAX – View Modal) ─────────────────────────────────────────────

    public function show($id)
    {
        $list = ListStory::with(['user', 'category', 'images'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'list'    => $this->formatList($list, 0),
        ]);
    }

    // ─── Toggle Status ────────────────────────────────────────────────────────

    public function toggleStatus($id)
    {
        $list         = ListStory::findOrFail($id);
        $list->status = $list->status ? 0 : 1;
        $list->save();

        $statusLabel = $list->status ? 'active' : 'inactive';

        return response()->json([
            'success' => true,
            'status'  => $statusLabel,
            'message' => 'Status updated to ' . $statusLabel,
        ]);
    }

    // ─── Create / Store ───────────────────────────────────────────────────────

    public function create()
    {
        $categories = ListCategory::orderBy('category')->get();
        return view('admin.list_add', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'category_id' => 'required|numeric',
            'location'    => 'required|string',
            'condition'   => 'required|in:new,used',
            'old_price'   => 'nullable|numeric',
            'new_price'   => 'nullable|numeric',
            'description' => 'nullable|string',
            'status'      => 'required|in:active,inactive',
            'images'      => 'nullable|array|max:3',
            'images.*'    => 'image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $list = ListStory::create([
            'user_id'     => null, // admin-created lists have no user
            'category_id' => $request->category_id,
            'title'       => $request->title,
            'old_price'   => $request->old_price,
            'new_price'   => $request->new_price,
            'location'    => $request->location,
            'description' => $request->description,
            'condition'   => $request->condition,
            'status'      => $request->status === 'active' ? 1 : 0,
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('list_images', 'public');
                listImage::create([
                    'list_id'    => $list->id,
                    'image_name' => $image->getClientOriginalName(),
                    'path'       => $path,
                    'hash_name'  => md5_file($image->getRealPath()),
                ]);
            }
        }

        return redirect()->route('admin.list.index')->with('success', 'List added successfully.');
    }

    // ─── Edit / Update ────────────────────────────────────────────────────────

    public function edit($id)
    {
        $list       = ListStory::with(['images', 'category'])->findOrFail($id);
        $categories = ListCategory::orderBy('category')->get();

        return view('admin.list_edit', compact('list', 'categories'));
    }

    public function update(Request $request, $id)
    {
        $list = ListStory::findOrFail($id);

        $request->validate([
            'title'       => 'required|string|max:255',
            'category_id' => 'required|numeric',
            'location'    => 'required|string',
            'condition'   => 'required|in:new,used',
            'old_price'   => 'nullable|numeric',
            'new_price'   => 'nullable|numeric',
            'description' => 'nullable|string',
            'status'      => 'required|in:active,inactive',
            'images'      => 'nullable|array|max:3',
            'images.*'    => 'image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $list->update([
            'title'       => $request->title,
            'category_id' => $request->category_id,
            'location'    => $request->location,
            'condition'   => $request->condition,
            'old_price'   => $request->old_price,
            'new_price'   => $request->new_price,
            'description' => $request->description,
            'status'      => $request->status === 'active' ? 1 : 0,
        ]);

        if ($request->hasFile('images')) {
            foreach ($list->images as $oldImage) {
                Storage::disk('public')->delete($oldImage->path);
                $oldImage->delete();
            }
            foreach ($request->file('images') as $image) {
                $path = $image->store('list_images', 'public');
                listImage::create([
                    'list_id'    => $list->id,
                    'image_name' => $image->getClientOriginalName(),
                    'path'       => $path,
                    'hash_name'  => md5_file($image->getRealPath()),
                ]);
            }
        }

        return redirect()->route('admin.list.index')->with('success', 'List updated successfully.');
    }

    // ─── Destroy (full list) ──────────────────────────────────────────────────

    public function destroy($id)
    {
        $list = ListStory::findOrFail($id);

        foreach ($list->images as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }

        $list->delete();

        return response()->json(['success' => true, 'message' => 'List deleted successfully.']);
    }

    // ─── Delete single image ───────────────────────────────────────────────────

    public function destroyImage($imageId)
    {
        $image = listImage::findOrFail($imageId);
        Storage::disk('public')->delete($image->path);
        $image->delete();

        return response()->json(['success' => true, 'message' => 'Image deleted successfully.']);
    }
}
