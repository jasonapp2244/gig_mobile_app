<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ListCategory;
use Illuminate\Http\Request;

class AdminCategoryController extends Controller
{
  
    public function index()
    {
        $categories = ListCategory::latest()->get();
        return view('admin.category', compact('categories'));
    }


    public function store(Request $request)
    {
        $request->validate([
            'category'  => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        ListCategory::create([
            'category'  => $request->category,
            'status' => $request->status === 'active' ? 1 : 0,
        ]);

        return redirect()->route('admin.categories')->with('success', 'Category added successfully');
    }

    public function edit($id)
    {
        $category = ListCategory::find($id);
        if (!$category) {
            return $this->notFound();
        }

        return view('admin.category', [
            'categories' => ListCategory::latest()->get(),
            'editCategory' => $category
        ]);
    }



    public function update(Request $request, $id)
    {
        $request->validate([
            'category'  => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        $category = ListCategory::find($id);
        if (!$category) {
            return $this->notFound();
        }

        $category->update([
            'category'  => $request->category,
            'status' => $request->status === 'active' ? 1 : 0,
        ]);

        return redirect()->route('admin.categories')->with('success', 'Category updated successfully');
    }


    public function destroy($id)
    {
        $category = ListCategory::find($id);
        if (!$category) {
            return $this->notFound();
        }

        $category->delete();

        return redirect()->route('admin.categories')->with('success', 'Category deleted successfully');
    }

    /** The category was already deleted (double click, old tab, back button): go back to the list instead of a 404 page. */
    private function notFound()
    {
        return redirect()->route('admin.categories')->with('error', trans('messages.category_not_found'));
    }
}
