<?php

namespace App\Http\Controllers;

use App\Models\ListStory;
use Illuminate\Http\Request;
use App\Models\ListCommit;
class listCommitController extends Controller
{
    public function addListCommits(Request $request)
{
        if ($blocked = $this->blockGuest()) return $blocked;

    $request->validate([
        'list_id' => 'required|integer',
        'commit_message' => 'required|string|max:255',
    ]);

    $listExists = ListStory::find($request->list_id);

    if (!$listExists) {
        return response()->json([
            'status' => false,
            'message' => 'List not found'
        ], 400);
    }

    try {
        $listCommit = new ListCommit();
        $listCommit->user_id = auth()->id();
        $listCommit->list_id = $request->list_id;
        $listCommit->commit = $request->commit_message;
        $listCommit->status = true;
        $listCommit->save();

        return response()->json([
            'status' => true,
            'message' => 'Commit added successfully',
            'data' => [
                'list_id' => $request->list_id,
                'commit_message' => $request->commit_message,
            ]
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Error adding commit',
            'error' => $e->getMessage()
        ], 500);
    }
}


    public function getListCommits(Request $request, $listId)
    {
        $commits = ListCommit::where('list_id', $listId)->with('user')->get();

        return response()->json([
            'message' => 'List commits retrieved successfully',
            'data' => $commits
        ], 200);
    }

}
