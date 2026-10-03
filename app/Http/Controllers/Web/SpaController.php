<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class SpaController extends Controller
{
    public function resident()
    {
        return view('spa.resident');
    }

    public function partner()
    {
        return view('spa.partner');
    }
}
