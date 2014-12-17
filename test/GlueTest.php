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
            ['GET http://resource/ POST http://not-there.net/'],
            ['GET http://resource/ POST http://dom?xpath=//img/@src POST  http://not-there.net/'],
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

    public function getPassingScripts()
    {
        return [
            ['GET http://resource/ POST http://dom/?xpath=//img/@src POST http://report/'],
        ];
    }
    
    /**
    * @dataProvider getPassingScripts
    */
    public function testScriptSuccess($script)
    {
        $this->givenARequestToGlue($script);
        $this->whenTheRequestIsMade();
        $this->thenTheResponseStatusShouldBe(200);
    }
    
    public function testGenerateAReportFromImageResources()
    {
        $script = 'GET http://resource/ POST http://dom/?xpath=//img/@src / POST http://prepend/?prepend=http://resource/ / POST http://md/ POST http://report/';
        $this->givenARequestToGlue($script);
        $this->whenTheRequestIsMade();
        $this->thenTheResponseStatusShouldBe(200);
        $result = json_decode($this->response->getBody());
        // first array in result should be table header
        $this->assertSame(['uri','type','date','size'], $result[0]);
    }
    
    public function testRunningPrependInParallelIsSameAsInSerial()
    {
        $script = 'GET http://resource/ POST http://dom/?xpath=//img/@src / POST http://prepend/?prepend=http://resource/ / POST http://md/ POST http://report/';
        $this->givenARequestToGlue($script);
        $this->whenTheRequestIsMade();
        $response_a = json_decode((string) $this->response->getBody());

        $script = 'GET http://resource/ POST http://dom/?xpath=//img/@src POST http://prepend/?prepend=http://resource/ / POST http://md/ POST http://report/';
        $this->givenARequestToGlue($script);
        $this->whenTheRequestIsMade();
        $response_b = json_decode((string) $this->response->getBody());

        $this->assertArrayContentsMatch($response_a, $response_b);
    }
    
    /**
    * Assert that $a and $b contain the same contents
    */
    protected function assertArrayContentsMatch(array $a, array $b)
    {
        $this->assertEquals(count($a), count($b));
        foreach($a as $item){
            $this->assertTrue(in_array($item, $b));
        }
        foreach($b as $item){
            $this->assertTrue(in_array($item, $a));
        }
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
