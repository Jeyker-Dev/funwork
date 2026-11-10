<?php

use App\Http\Middleware\AuthenticateMcpWithApiToken;
use App\Mcp\Servers\FunWorkServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/funwork', FunWorkServer::class)
    ->middleware(AuthenticateMcpWithApiToken::class)
    ->name('mcp.funwork');

Mcp::local('funwork', FunWorkServer::class);
