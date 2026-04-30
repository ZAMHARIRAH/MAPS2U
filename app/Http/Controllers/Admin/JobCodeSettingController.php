<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JobCodeSettingController extends Controller
{
    public function edit()
    {
        return view('admin.job-code-settings.edit', [
            'currentCode' => DB::table('maps2u_settings')->where('key', 'next_job_request_code')->value('value') ?: 'W0001',
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'next_job_request_code' => ['required', 'regex:/^[A-Za-z]+[0-9]+$/'],
        ]);

        DB::table('maps2u_settings')->updateOrInsert(
            ['key' => 'next_job_request_code'],
            ['value' => Str::upper($validated['next_job_request_code']), 'updated_at' => now(), 'created_at' => now()]
        );

        return back()->with('success', 'Next job request code updated. New client request will start from ' . Str::upper($validated['next_job_request_code']) . '.');
    }
}
