<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        return view('admin.departments.index', [
            'departments' => Department::orderBy('name')->paginate(20)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('admin.departments.form', ['department' => new Department(), 'mode' => 'create']);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required','string','max:255'], 'is_active' => ['nullable','boolean']]);
        $data['is_active'] = $request->boolean('is_active');
        Department::create($data);
        return redirect()->route('admin.departments.index')->with('success','Department created successfully.');
    }

    public function edit(Department $department)
    {
        return view('admin.departments.form', ['department' => $department, 'mode' => 'edit']);
    }

    public function update(Request $request, Department $department)
    {
        $data = $request->validate(['name' => ['required','string','max:255'], 'is_active' => ['nullable','boolean']]);
        $data['is_active'] = $request->boolean('is_active');
        $department->update($data);
        return redirect()->route('admin.departments.index')->with('success','Department updated successfully.');
    }

    public function destroy(Department $department)
    {
        $department->delete();
        return redirect()->route('admin.departments.index')->with('success','Department deleted successfully.');
    }
}
