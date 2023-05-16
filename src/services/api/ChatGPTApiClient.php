<?php

namespace acclaro\translations\services\api;

use acclaro\translations\Constants;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Psr7\Query;

use Craft;
use GuzzleHttp\Promise\Is;

class ChatGPTApiClient
{
    /**
     * Access key

     */
    private string $accessKey;

    /**
     * Access key
     */
    private string $orgId;

    /**
     * Prompt
     */
    private string $prompt;

    /**
     * Http client

     */
    private \GuzzleHttp\ClientInterface $client;

    public function __construct($token, $org, $prompt)
    {
        $this->accessKey = $token;
        $this->orgId = $org;
        $this->prompt = $prompt;
        
        $this->client = new HttpClient([
            'base_uri' => Constants::CHATGPT_TRANSLATE_API_URL,
            'headers' => array(
                'Content-Type'  => 'application/json'            
            ),
        ]);
    }

    public function testAuthentication()
    {
        $options = [
            "model" => "gpt-3.5-turbo",
            "messages" => [[
                "role" =>"user", 
                "content" => "Say this is a test!"
            ]],
            "temperature" => 0.7
        ];

        $request = $this->postCompletionsRequest($this->orgId, $options);
        $response = $this->request($request, false);

        return $response;

    }

    public function postCompletionsRequest($open_ai_organization = null, $completion_payload = null)
    {

        $resourcePath = '/completions';
        $queryParams = [];
        $headerParams = [];
        $headers = [];
        $httpBody = '';


        // header params
        if ($open_ai_organization !== null) {
            $headerParams['OpenAI-Organization'] = $open_ai_organization;
        }



        $headers['Content-Type'] = ['application/json'];

        $httpBody = json_encode($completion_payload);

        //echo "httpBody: " . $httpBody . "\n\n";

        // this endpoint requires API key authentication
        $apiKey = $this->accessKey;
        if ($apiKey !== null) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        $defaultHeaders = [];


        $headers = array_merge(
            $defaultHeaders,
            $headerParams,
            $headers
        );

        $query = Query::build($queryParams);
        return new Request(
            'POST',
            Constants::CHATGPT_TRANSLATE_API_URL . $resourcePath,
            $headers,
            $httpBody
        );
    }


    public function translate(string|array $text, string $targetLanguage, string $sourceLanguage = "English")
    {
        // validate if required fields has being filled.
        if (empty($text)) {
            return ['success' => true, 'data' => [""]];
        }

        if (empty($targetLanguage)) {
            throw new \Exception("Empty targetLanaguage");
        }

        if (empty($sourceLanguage)) {
            throw new \Exception("Empty sourceLanaguage");
        }

        if (empty($this->prompt)) {
            throw new \Exception("Empty prompt");
        }

        // used to return the same type of variable used in the text
        $onceResult = !is_array($text);

        // prepare the string
        $text = is_array($text) ? $text : [$text];

        // query params
        $options = [
            "model" => "gpt-3.5-turbo",
            "temperature" => 0.7
        ];

        $messages = [];

        $prompt = str_replace(["{targetLanguage}", "{sourceLanguage}"], [$targetLanguage, $sourceLanguage], $this->prompt);

        $messages[] = ["role" => "system", "content" => $prompt];

        $messages[] = ["role" => "user", "content" => json_encode($text)];
        $options["messages"] = $messages;

        $request = $this->postCompletionsRequest($this->orgId, $options);

        $result = $this->request($request);

        return $result; 
    }
 
    private function request($request, bool $cleanResponse = true)
    {
        $returnResponse = null;
        for ($i = 0; $i < 5; $i++) {
            
            try {
                $response =  $this->client->send($request);
                
                
                $parsedResponse = $this->parseResponse($response, $cleanResponse);
                //echo "parsedResponse = " . json_encode($parsedResponse) . "\n\n";
                $returnResponse = ['success' => true, 'data' => $parsedResponse];
                return $returnResponse;
                
            } catch (\Exception $e) {
                throw new \Exception('Invalid response: ' . $e->getMessage());
            }
        }
        return $returnResponse;

    }
    
    
    private function parseResponse(Response $res)
    {
        $resultJson = json_decode($res->getBody(), true);
        
        if (
            !is_array($resultJson) ||
            !array_key_exists('choices', $resultJson) ||
            !count($resultJson["choices"])
        ) {
            throw new \Exception('Invalid response');
        } else {
            $choice = $resultJson["choices"][0];
            if (!array_key_exists('message', $choice) ||
                !array_key_exists('content', $choice["message"])) {
                    throw new \Exception('Invalid response');
            } else {       
                
                return json_decode($choice["message"]["content"]);

            }
        }

        return $resultJson;
    }
}