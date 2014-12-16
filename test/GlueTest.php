<?php

use GuzzleHttp\Client;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Exception\ClientException;

/**
* Full integration tests of running glue scripts against real services
* Requires fig linking of services
*/
class GlueTest extends PHPUnit_Framework_TestCase {
   
    private $glue_endpoint = 'http://192.168.59.103:41990/';//'http://glue/';
    
    private $client;
    private $request;
    private $response;

    public function setUp()
    {
        parent::setUp();
        $this->client = new Client();
    }

    public function getFailingScripts()
    {
        return [
            ['http://resource/ >> http://not-there.net/'],
            ['http://resource/ >> http://dom?xpath=//img/@src >>  http://not-there.net/'],
        ];
    }

    /**
    * @dataProvider getFailingScripts
    */
    public function testScriptFailure($script)
    {
        // post script to glue service endpoint
        $this->givenARequestToGlue($script);
        $this->whenTheRequestIsMade();
        // expect that response is 400
        $this->thenTheResponseStatusShouldBe(400);
        $this->thenTheResponseBodyShouldBe('');
    }

    protected function givenARequestToGlue($body)
    {
        $this->request = $this->client->createRequest('POST', $this->glue_endpoint);
        $this->request->setHeader('Content-Type', 'text/plain');
        $this->request->setBody(Stream::factory($body));
    }

    protected function whenTheRequestIsMade()
    {
        try {
            $this->response = $this->client->send($this->request);
        } catch (ClientException $ex) {
            $this->response = $ex->getResponse();
        }
        $this->status_code = $this->response->getStatusCode();
    }

    protected function thenTheResponseStatusShouldBe($code)
    {
        $this->assertEquals($code, $this->status_code);
    }

    protected function thenTheResponseBodyShouldBe($body)
    {
        $this->assertEquals($body, (string) $this->response->getBody());
    }
}
