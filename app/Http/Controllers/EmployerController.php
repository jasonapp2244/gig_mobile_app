<?php

namespace App\Http\Controllers;

use App\Models\Employer;
use App\Models\Task;
use Illuminate\Http\Request;
use Carbon\Exceptions\Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EmployerController extends Controller
{

    public function getEmployer()
    {
        try {
            $employer = Employer::where('user_id', Auth::id())
                ->where('status', true)
                ->get();

            if ($employer) {
                return response()->json([
                    'status' => true,
                    'message' => 'Employer fetched successfully',
                    'data' => $employer
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => 'Employer not found'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch employer',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function filterByEmployer(Request $request)
    {
        $request->validate([
            'location' => 'nullable|string',
            'working_hours' => 'nullable|numeric',
            'straight_time' => 'nullable|string',
        ]);

        $employers = Employer::whereHas('tasks', function ($query) use ($request) {
            if ($request->filled('location')) {
                $query->where('location', $request->location);
            }

            if ($request->filled('working_hours')) {
                $query->where('working_hours', $request->working_hours);
            }

            if ($request->filled('straight_time')) {
                $query->where('straight_time', $request->straight_time);
            }
        })
            ->with(['tasks' => function ($query) use ($request) {
                if ($request->filled('location')) {
                    $query->where('location', $request->location);
                }

                if ($request->filled('working_hours')) {
                    $query->where('working_hours', $request->working_hours);
                }

                if ($request->filled('straight_time')) {
                    $query->where('straight_time', $request->straight_time);
                }
            }])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $employers
        ], 200);
    }

    public function updateEmployer(Request $request, $id)
    {
        try {
            $user = Auth::user();

            $request->validate([
                'employer_name' => 'nullable|string|max:255',
                'location' => 'nullable|string',
                'description' => 'nullable|string',
                'employer_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'job_type' => 'nullable|array',
                'salary' => 'nullable|numeric'
            ]);

            $employer = Employer::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employer not found'
                ], 404);
            }

            if ($request->hasFile('employer_image')) {
                if ($employer->employer_image) {
                    Storage::disk('public')->delete('employer_images/' . $employer->employer_image);
                }

                $image = $request->file('employer_image');
                $imageName = uniqid('employer_') . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('employer_images', $imageName, 'public');

                $employer->employer_image = $imageName;
            }

            $employer->employer_name = $request->employer_name ?? $employer->employer_name;
            $employer->job_type = $request->job_type ? json_encode($request->job_type) : $employer->job_type;
            $employer->salary = $request->salary ?? $employer->salary;
            $employer->location = $request->location ?? $employer->location;
            $employer->description = $request->description ?? $employer->description;
            $employer->status = true;
            $employer->save();


            Task::where('employer_id', $id)
                ->update([
                    'employer' => $employer->employer_name
                ]);


            $employers = Employer::where('user_id', $user->id)->get()->map(function ($e) {
                $e->job_type = $e->job_type ? json_decode($e->job_type) : [];
                return $e;
            });

            return response()->json([
                'status' => true,
                'message' => 'Employer updated successfully',
                'data' => $employers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function deleteEmployer($id)
    {
        try {
            $user = Auth::user();

            $employer = Employer::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employer not found'
                ], 404);
            }

            $taskCount = Task::where('employer_id', $employer->id)->count();

            if ($taskCount > 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'This employer has existing tasks. Please update or remove the tasks first.',
                    'task_count' => $taskCount
                ], 200);
            }

            if ($employer->employer_image) {
                Storage::disk('public')->delete('employer_images/' . $employer->employer_image);
            }

            $employer->delete();

            return response()->json([
                'status' => true,
                'message' => 'Employer deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete employer',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
