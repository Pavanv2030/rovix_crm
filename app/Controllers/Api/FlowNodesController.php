<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\FlowNodeSchemas;

class FlowNodesController extends BaseController
{
    /**
     * GET /api/flows/node-types
     * All node type schemas — loaded once by the flow editor on init.
     */
    public function getNodeTypes()
    {
        return $this->response->setJSON(FlowNodeSchemas::getAllSchemas());
    }

    /**
     * GET /api/flows/node-types/(:segment)
     * Schema for a single node type.
     */
    public function getNodeType(string $type)
    {
        $schema = FlowNodeSchemas::getSchema($type);

        if (empty($schema)) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Unknown node type: ' . $type]);
        }

        return $this->response->setJSON($schema);
    }
}
