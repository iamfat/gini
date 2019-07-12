<?php

namespace Gini\Controller\CGI;

use \Gini\CGI\Response;

final class Doc extends \Gini\Controller\CGI
{
    public function actionOpenAPI() {
        // 默认 format=json
        $api = \Gini\Document\OpenAPI::scan();
        return new Response\JSON($api);
    }
}
