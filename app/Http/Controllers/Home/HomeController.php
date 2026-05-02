<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Display the public home page.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        return view('home.index');
    }
}
