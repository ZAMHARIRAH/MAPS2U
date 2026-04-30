<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    public function index(string $type)
    {
        $locationType = $type === 'hq' ? Location::TYPE_HQ : Location::TYPE_BRANCH;

        return view('admin.locations.index', [
            'type' => $locationType,
            'locations' => Location::where('type', $locationType)->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function create(string $type)
    {
        return view('admin.locations.form', [
            'type' => $type === 'hq' ? Location::TYPE_HQ : Location::TYPE_BRANCH,
            'location' => new Location(),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request, string $type)
    {
        $locationType = $type === 'hq' ? Location::TYPE_HQ : Location::TYPE_BRANCH;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        Location::create($data + ['type' => $locationType, 'is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('admin.locations.index', $locationType)->with('success', 'Location created successfully.');
    }

    public function edit(Location $location)
    {
        return view('admin.locations.form', ['type' => $location->type, 'location' => $location, 'mode' => 'edit']);
    }

    public function update(Request $request, Location $location)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'type' => ['required', Rule::in([Location::TYPE_HQ, Location::TYPE_BRANCH])],
        ]);
        $location->update($data + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.locations.index', $location->type)->with('success', 'Location updated successfully.');
    }

    public function destroy(Location $location)
    {
        $type = $location->type;
        $location->delete();
        return redirect()->route('admin.locations.index', $type)->with('success', 'Location deleted successfully.');
    }
}
