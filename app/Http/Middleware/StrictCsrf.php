<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

class StrictCsrf extends PreventRequestForgery
{
    protected function hasValidOrigin($request)
    {
        // Exigir token também em requisições classificadas como same-origin.
        return false;
    }
}
