<?php

use App\Http\Middleware\AuthenticateMcpWithApiToken;
use App\Mcp\Servers\FunWorkServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/funwork', FunWorkServer::class)
    ->middleware(['throttle:60,1', AuthenticateMcpWithApiToken::class])
    ->name('mcp.funwork');
