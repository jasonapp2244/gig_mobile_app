<?php

namespace App\Http\Controllers;

use App\Models\ListStory;
use App\Models\ListCategory;
use App\Models\listImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ListController extends Controller
{

    public function getList(Request $request)
    {
        

        $perPage = $request->get('per_page', 10);

        $lists = ListStory::with([
            'category',
            'images',
            'user',
            'comments' => function ($q) {
                $q->orderBy('created_at', 'asc');
            }
        ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status'  => true,
            'message' => 'List stories retrieved successfully',
            'data'    => $lists
        ], 200);
    }



    public function addList(Request $request)
    {
        if ($blocked = $this->blockGuest()) return $blocked;

        try {
            $request->validate([
                'title'        => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('list_stories', 'title')->where(function ($query) {
                        return $query->where('user_id', auth()->id());
                    }),
                ],
                'category_id'  => 'required|numeric',
                'old_price'    => 'nullable|numeric',
                'new_price'    => 'nullable|numeric|lte:old_price',
                'location'     => 'required|string',
                'description'  => 'nullable|string',
                'condition'    => 'required|string',
                'images'       => 'required|array|min:1|max:3',
                'images.*'     => 'image|mimes:jpg,jpeg,png|max:2048',
            ], [
                'title.unique' => 'You have already created a list with this title.',
            ]);

            // if ($request->filled('new_price') && $request->filled('old_price')) {
            //     if ($request->new_price > $request->old_price) {
            //         return response()->json([
            //             'status'  => false,
            //             'message' => 'New price cannot be greater than old price',
            //         ], 422);
            //     }
            // }

            $list = ListStory::create([
                'user_id'     => auth()->id(),
                'category_id' => $request->category_id,
                'title'       => $request->title,
                'old_price'   => $request->old_price,
                'new_price'   => $request->new_price,
                'location'    => $request->location,
                'description' => $request->description,
                'condition'   => $request->condition,
            ]);

            if ($request->hasFile('images')) {
                $existingHashes = ListImage::where('list_id', $list->id)->pluck('hash_name')->toArray();
                $existingNames  = ListImage::where('list_id', $list->id)->pluck('image_name')->toArray();

                foreach ($request->file('images') as $image) {
                    $hash = md5_file($image->getRealPath());
                    $originalName = $image->getClientOriginalName();

                    if (in_array($hash, $existingHashes) || in_array($originalName, $existingNames)) {
                        continue;
                    }

                    if ($list->images()->count() >= 3) {
                        break;
                    }

                    $path = $image->store('list_images', 'public');

                    ListImage::create([
                        'list_id'    => $list->id,
                        'image_name' => $originalName,
                        'path'       => $path,
                        'hash_name'  => $hash
                    ]);
                }
            }

            return response()->json([
                'status'  => true,
                'message' => 'List story added successfully',
                'data'    => $list->load('category', 'images')
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while adding the list',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    public function updateList(Request $request, $id)
    {
        if ($blocked = $this->blockGuest()) return $blocked;

        $request->validate([
            'title'        => 'nullable|string|max:255',
            'category_id'  => 'nullable|numeric',
            'old_price'    => 'nullable|numeric',
            'new_price'    => 'nullable|numeric|lte:old_price',
            'location'     => 'required|string',
            'description'  => 'nullable|string',
            'condition'    => 'required|string',
            'images'       => 'nullable|array|min:1|max:3',
            'images.*'     => 'image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $list = ListStory::findOrFail($id);


        $list->update([
            'category_id' => $request->category_id ?? $list->category_id,
            'title'       => $request->title       ?? $list->title,
            'old_price'   => $request->old_price   ?? $list->old_price,
            'new_price'   => $request->new_price   ?? $list->new_price,
            'location'    => $request->location,
            'description' => $request->description ?? $list->description,
            'condition'   => $request->condition   ?? $list->condition,
        ]);


        if ($request->hasFile('images')) {

            foreach ($list->images as $oldImage) {
                Storage::disk('public')->delete($oldImage->path);
                $oldImage->delete();
            }

            foreach ($request->file('images') as $image) {
                $hash = md5_file($image->getRealPath());
                $path = $image->store('list_images', 'public');

                ListImage::create([
                    'list_id'    => $list->id,
                    'image_name' => $image->getClientOriginalName(),
                    'path'       => $path,
                    'hash_name'  => $hash
                ]);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'List story updated successfully',
            'data'    => $list->load('category', 'images')
        ], 200);
    }



    public function deleteList(Request $request, $id)
    {
        if ($blocked = $this->blockGuest()) return $blocked;

        $list = ListStory::findOrFail($id);

        // Delete associated images
        foreach ($list->images as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }

        // Delete the list story
        $list->delete();

        return response()->json([
            'status' => true,
            'message' => 'List story deleted successfully'
        ], 200);
    }

    public function searchProductList(String $cat_id)
    {
        $lists = ListStory::with([
            'category',
            'images',
            'user',
            'comments' => function ($q) {
                $q->orderBy('created_at', 'asc');
            }
        ])
            ->where('category_id', $cat_id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        if ($lists->total() > 0) {
            return response()->json([
                'status'  => true,
                'message' => 'List stories retrieved successfully',
                'data'    => $lists
            ], 200);
        }

        return response()->json([
            'status'  => false,
            'message' => 'Record not found',
            'data'    => []
        ], 200);
    }

    public function search(Request $request)
    {
        $search = $request->input('search');

        // If search is empty
        if (!$search || trim($search) == "") {
            return response()->json([
                'status' => false,
                'message' => 'Please enter a search value.',
                'data' => []
            ], 200);
        }

        $products = ListStory::with([
            'category',
            'images',
            'user',
        ])
            ->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('new_price', 'LIKE', "%{$search}%")
                    ->orWhere('location', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        if ($products->total() == 0) {
            return response()->json([
                'status' => false,
                'message' => 'No products found.',
                'data' => []
            ], 200);
        }

        return response()->json([
            'status' => true,
            'message' => 'Products found successfully.',
            'data' => $products
        ], 200);
    }
}
