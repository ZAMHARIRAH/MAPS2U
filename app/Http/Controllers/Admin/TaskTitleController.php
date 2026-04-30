<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaskTitle;
use Illuminate\Http\Request;

class TaskTitleController extends Controller
{
    public function index()
    {
        return view('admin.tasks.index', [
            'tasks' => TaskTitle::orderBy('title')->paginate(20)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('admin.tasks.form', [
            'task' => new TaskTitle(),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255', 'unique:task_titles,title'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        TaskTitle::create($data);

        return redirect()->route('admin.tasks.index')->with('success', 'Task title created successfully.');
    }

    public function edit(TaskTitle $task)
    {
        return view('admin.tasks.form', [
            'task' => $task,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, TaskTitle $task)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255', 'unique:task_titles,title,' . $task->id],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $task->update($data);

        return redirect()->route('admin.tasks.index')->with('success', 'Task title updated successfully.');
    }

    public function destroy(TaskTitle $task)
    {
        $task->delete();

        return redirect()->route('admin.tasks.index')->with('success', 'Task title deleted successfully.');
    }
}
