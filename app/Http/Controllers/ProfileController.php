<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show() { return view('profile.show', ['user' => Auth::user()]); }
    public function edit() { return view('profile.edit', ['user' => Auth::user()]); }

    public function update(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if ($user->isTechnician()) {
            $request->validate(['profile_photo' => ['nullable', 'image', 'max:2048']]);
        } elseif ($user->isAdmin()) {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
                'phone_number' => ['required', 'string', 'max:30'], 'address' => ['nullable', 'string', 'max:1000'],
                'profile_photo' => ['nullable', 'image', 'max:2048'],
            ]);
            $user->fill(['name' => $data['name'], 'email' => $data['email'], 'phone_number' => $data['phone_number'], 'address' => $data['address'] ?? null]);
        } else {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
                'phone_number' => ['required', 'string', 'max:30'], 'address' => ['required', 'string', 'max:1000'],
                'profile_photo' => ['nullable', 'image', 'max:2048'],
            ]);
            $user->fill(['name' => $data['name'], 'email' => $data['email'], 'phone_number' => $data['phone_number'], 'address' => $data['address']]);
        }
        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) { Storage::disk('public')->delete($user->profile_photo_path); }
            $user->profile_photo_path = $request->file('profile_photo')->store('profile-photos', 'public');
        }
        $user->save();
        return redirect()->route('profile.show')->with('success', 'Profile updated successfully.');
    }

    public function destroy(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->isClient(), 403);
        if ($user->profile_photo_path) { Storage::disk('public')->delete($user->profile_photo_path); }
        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home')->with('success', 'Your client account has been deleted.');
    }
}
