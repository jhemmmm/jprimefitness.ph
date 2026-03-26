<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index($branch): View
    {
        $branch = Branch::where('slug', $branch)->firstOrFail();

        $branch->load(['ratePlans', 'ptProducts']);

        return view('home.branch', compact('branch'));
    }
}
