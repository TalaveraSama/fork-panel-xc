<?php

namespace ForkPanelXc\Modules\ExampleModule\Controllers;

use ForkPanelXc\Core\Http\Request;
use ForkPanelXc\Core\Http\Response;

class PublicController
{
    public function getData(Request $request): Response
    {
        // Public API endpoint - no authentication required
        return Response::json([
            'data' => [
                'module' => 'example_module',
                'version' => '1.0.0',
                'status' => 'active',
                'timestamp' => time(),
            ],
        ]);
    }
}