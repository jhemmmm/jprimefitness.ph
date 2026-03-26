<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Home page - shows the home view with branch highlights and other info.
     *
     * @return View
     */
    public function index()
    {
        return view('home.index');
    }
}
