<?php

namespace Gini\Controller\CGI;

use Gini\CGI\Response;

final class Doc extends \Gini\Controller\CGI
{
    public function actionOpenAPI($format='json')
    {
        $api = \Gini\Document\OpenAPI::scan();
        return new Response\JSON($api);
    }

    public function actionOpenRPC($format='json')
    {
        $api = \Gini\Document\OpenRPC::scan();
        return new Response\JSON($api);
    }
}
