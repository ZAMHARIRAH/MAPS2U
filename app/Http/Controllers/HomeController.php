<?php

namespace App\Http\Controllers;

use App\Models\Announcement;

class HomeController extends Controller
{
    public function index()
    {
        return view('home', [
            'announcements' => Announcement::active()->ordered()->get(),
        ]);
    }
}
