<?php

use App\Mcp\Servers\CrmServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/crm', CrmServer::class)
    ->middleware(['auth:sanctum', 'throttle:mcp']);

Mcp::local('crm', CrmServer::class);
