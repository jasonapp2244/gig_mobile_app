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
        $category = ListCategory::findOrFail($id);
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

        $category = ListCategory::findOrFail($id);
        $category->update([
            'category'  => $request->category,
            'status' => $request->status === 'active' ? 1 : 0,
        ]);

        return redirect()->route('admin.categories')->with('success', 'Category updated successfully');
    }


    public function destroy($id)
    {
        $category = ListCategory::findOrFail($id);
        $category->delete();

        return redirect()->route('admin.categories')->with('success', 'Category deleted successfully');
    }
}
