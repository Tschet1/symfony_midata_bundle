<?php

namespace PfadiZytturm\MidataBundle\DataCollector;

use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class midataDataCollector extends DataCollector
{

    public function __construct()
    {
        $this->data["cached"] = 0;
        $this->data["requests"] = 0;
    }

    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
    }

    public function getName()
    {
        return 'midata.collector';
    }

    public function reset()
    {
        $this->data = array(
            "requests" => 0,
            "cached" => 0
        );
    }

    public function count_request($cached = false){
        $this->data["requests"]+=1;
        if($cached) {
            $this->data["cached"] += 1;
        }
    }

    public function getRequestcount()
    {
        return $this->data["requests"];
    }

    public function getCachedcount()
    {
        return $this->data["cached"];
    }
}
