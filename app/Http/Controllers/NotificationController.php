<?php

namespace App\Http\Controllers;

use App\Models\SystemNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function open(SystemNotification $notification): RedirectResponse
    {
        abort_unless((int) $notification->user_id === (int) Auth::id(), 403);

        if (!$notification->read_at) {
            $notification->update(['read_at' => now('Asia/Kuala_Lumpur')]);
        }

        return redirect()->to($notification->url ?: route('client.dashboard'));
    }
}
