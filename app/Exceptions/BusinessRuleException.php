<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class BusinessRuleException extends Exception
{
    public function render(Request $request): Response
    {
        return $request->expectsJson()
            ? response()->json(['message' => $this->getMessage()], 422)
            : back()->withErrors(['domain' => $this->getMessage()]);
    }
}
