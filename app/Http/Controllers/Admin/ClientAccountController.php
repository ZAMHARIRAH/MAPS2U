<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ClientAccountController extends Controller
{
    public function show(User $client)
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);
        /** @var User $admin */
        $admin = Auth::user();
        abort_unless(in_array($client->sub_role, $admin->handledClientRoles(), true), 403);
        return view('admin.clients.show', compact('client', 'admin'));
    }
}
