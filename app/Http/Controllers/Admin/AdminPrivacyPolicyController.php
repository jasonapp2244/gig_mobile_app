<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePrivacyPolicyRequest;
use App\Models\PrivacyPolicy;

class AdminPrivacyPolicyController extends Controller
{
    public function index()
    {
        $policy = PrivacyPolicy::where('is_active', true)->latest()->first();

        return view('admin.privacy-policy.index', compact('policy'));
    }

    public function store(StorePrivacyPolicyRequest $request)
    {
        PrivacyPolicy::query()->update(['is_active' => false]);

        PrivacyPolicy::create([
            'title' => $request->title,
            'content' => $request->content,
            'is_active' => true,
            'effective_date' => $request->effective_date,
        ]);

        return redirect()->route('admin.privacy-policy.index')
            ->with('success', 'Privacy policy has been published successfully.');
    }
}
