<?php

namespace Easy\Rest;

use Easy\Mvc\Controller\Controller;
use Easy\Network\Response;
use Easy\Rest\Metadata\RestMetadata;

class RestManager
{

    public $metadata;
    public $request;
    public $controller;

    public function __construct(Controller $controller)
    {
        $this->metadata = new RestMetadata($controller);
        $this->controller = $controller;
        $this->request = $controller->getRequest();
    }

    public function isValidMethod()
    {
        $methods = $this->metadata->getMethodAnnotation($this->request->action);
        if ($methods) {
            //Get the requested method
            $requestedMethod = $this->request->method();
            //If the requested method is in the permited array
            if (in_array($requestedMethod, (array) $methods)) {
                return true;
            } else {
                return false;
            }
        }
        return true;
    }

    public function sendResponseCode(Response $response)
    {
        $responseCode = $this->metadata->getCodeAnnotation($this->request->action);
        if ($responseCode) {
            $response->statusCode($responseCode);
        }
    }

    public function formatResult($result)
    {
        $format = $this->metadata->getFormatAnnotation($this->request->action);
        $returnType = null;

        if (is_array($format)) {
            $accepts = $this->controller->RequestHandler->accepts();
            foreach ($format as $f) {
                if (in_array($f, $accepts)) {
                    $returnType = $f;
                    break;
                }
            }
        } else {
            $returnType = $format;
        }

        if ($returnType) {
            $this->controller->setAutoRender(false);
            $this->controller->RequestHandler->respondAs($returnType);
            $result = $this->controller->Serializer->encode($result, $returnType);
        }


        return $result;
    }

}
