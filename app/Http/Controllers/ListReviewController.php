<?php

namespace App\Http\Controllers;

use App\Models\ListReview;
use Illuminate\Http\Request;

class ListReviewController extends Controller
{
    public function addListReview(Request $request)
    {

        $request->validate([
            'list_id'       => 'required|exists:list_stories,id',
            'review'        => 'nullable|string|max:500',
            'rating'        => 'required|integer|min:1|max:5',
            'is_anonymous'  => 'nullable|boolean',
            'is_verified'   => 'nullable|boolean',
        ]);

        $existingReview = ListReview::where('user_id', auth()->id())
            ->where('list_id', $request->list_id)
            ->first();

        if ($existingReview) {
            return response()->json([
                'status'=>false,
                'message' => 'You have already reviewed this list.'
            ], 400);
        }

        $listReview = ListReview::create([
            'user_id'      => auth()->id(),
            'list_id'      => $request->list_id,
            'review'       => $request->review,
            'rating'       => $request->rating,
            'status'       => true,
            'is_anonymous' => $request->is_anonymous ?? false,
            'is_verified'  => $request->is_verified ?? false,
        ]);

        return response()->json([
            'message' => 'Review added successfully',
            'data'    => $listReview
        ], 200);
    }

    public function getListReviews($listId)
    {

        $reviews = ListReview::where('list_id', $listId)
            ->with('user')
            ->get();

        if ($reviews->isEmpty()) {
            return response()->json([
                'message' => 'No reviews found for this list.',
                'total_reviews' => 0,
                'average_rating' => 0.0
            ], 400);
        }


        $totalReviews = $reviews->count();
        $averageRating = round($reviews->avg('rating'), 1);

        return response()->json([
            'message'        => 'List reviews retrieved successfully',
            'total_reviews'  => $totalReviews,
            'average_rating' => $averageRating,
            'rating_text'    => "{$averageRating} out of 5",
            'data'           => $reviews
        ], 200);
    }
}
