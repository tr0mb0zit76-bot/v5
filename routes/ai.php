<?php

use App\Mcp\Servers\CrmServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/crm', CrmServer::class)
    ->middleware(['throttle:mcp', 'mcp.bearer']);

Mcp::local('crm', CrmServer::class);
