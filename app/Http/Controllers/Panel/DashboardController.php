<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Dashboard page - shows the dashboard view with branch highlights and other info.
     *
     * @return View
     */
    public function index()
    {
        return view('panel.dashboard');
    }
}
