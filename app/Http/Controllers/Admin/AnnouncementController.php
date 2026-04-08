<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function index()
    {
        return view('admin.announcements.index', [
            'announcements' => Announcement::ordered()->get(),
        ]);
    }

    public function create()
    {
        return view('admin.announcements.form', [
            'announcement' => new Announcement(['priority' => Announcement::PRIORITY_MEDIUM, 'is_active' => true]),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request)
    {
        Announcement::create($this->validatedData($request) + [
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.announcements.index')->with('success', 'Announcement created successfully.');
    }

    public function edit(Announcement $announcement)
    {
        return view('admin.announcements.form', [
            'announcement' => $announcement,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $announcement->update($this->validatedData($request) + [
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.announcements.index')->with('success', 'Announcement updated successfully.');
    }

    public function toggle(Request $request, Announcement $announcement)
    {
        $announcement->update([
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Announcement status updated successfully.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::in([
                Announcement::PRIORITY_HIGH,
                Announcement::PRIORITY_MEDIUM,
                Announcement::PRIORITY_LOW,
            ])],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
