<?php

namespace acclaro\translations\services\api;

use Craft;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

class AcclaroApiClient
{
    const PRODUCTION_URL = 'https://my.acclaro.com/api2/ ';

    const SANDBOX_URL = 'https://apisandbox.acclaro.com/api2/';

    const DELIVERY = 'craftcms';

    protected $loggingEnabled = false;

    public function __construct(
        $apiToken,
        $sandboxMode = false,
        Client $client = null
    ) {
        $this->client = $client ?: new Client([
            'base_uri' => $sandboxMode ? self::SANDBOX_URL : self::PRODUCTION_URL,
            'headers' => array(
                'Authorization' => sprintf('Bearer %s', $apiToken),
                'Accept' => 'application/json',
                'User-Agent' => 'Craft'
            )
        ]);
    }

    public function getClient()
    {
        return $this->client;
    }

    public function logRequest($request, $endpoint)
    {
        $tempPath = Craft::$app->getPath()->getTempPath().'/translations';

        if (!is_dir($tempPath)) {
            mkdir($tempPath);
        }

        $filename = 'api-request-'.$endpoint.'-'.date('YmdHis').'.txt';

        $filePath = $tempPath.'/'.$filename;

        $handle = fopen($filePath, 'w+');

        fwrite($handle, (string) $request);

        fclose($handle);
    }

    public function logResponse($response, $endpoint)
    {
        Craft::$app->path->getTempPath().'/translations';

        if (!is_dir($tempPath)) {
            mkdir($tempPath);
        }

        $filename = 'api-response-'.$endpoint.'-'.date('YmdHis').'.txt';

        $filePath = $tempPath.'/'.$filename;

        $handle = fopen($filePath, 'w+');

        fwrite($handle, (string) $response);

        fclose($handle);
    }

    public function request($method, $endpoint, $query = array(), $files = array())
    {   
        $output = [];
        if ($files) {
            foreach ($files as $key => $file) {
                if ( ! is_array( $file ) ) {
                    // var_dump(pathinfo($file));
                    $response = $this->client->request($method, $endpoint.'?'.http_build_query($query, '', '&'), [
                        'multipart' => [
                            [
                                'name' => 'file',
                                'contents' => fopen( $file, 'r' ),
                                'filename' => pathinfo($file)['basename']
                            ]
                        ]
                    ]);
                    continue;
                }
            }
        } else {
            $request = new Request($method, $endpoint.'?'.http_build_query($query, '', '&'));

            if ($this->loggingEnabled) {
                $this->logRequest($request, $endpoint);
            }

            try {
                $response = $this->client->send($request, ['timeout' => 0]);
            } catch (Exception $e) {
                //@TODO

                return null;
            }
        }

        if ($this->loggingEnabled) {
            $this->logResponse($response, $endpoint);
        }

        if ($response->getStatusCode() != 200) {
            //@TODO
            return null;
        }

        $body = $response->getBody();

        $responseJson = json_decode($body->getContents(), true);

        // var_dump($endpoint);
        // var_dump($responseJson);
        // die;

        // if (empty($responseJson['success'])) {
        //     //@TODO
        //     return $responseJson['data'];
        // }

        if (!isset($responseJson['data']) || $responseJson['success'] === false) {
            return null;
        }

        // // is assoc?
        if (is_array($responseJson['data']) && $responseJson['data'] === array_values($responseJson['data'])) {
            return array_map(function($row) {
                return (object) $row;
            }, $responseJson['data']);
        }

        // var_dump($endpoint);
        // var_dump($responseJson);
        // die;

        return (object) $responseJson['data'];
    }

    public function get($endpoint, $query = array())
    {
        return $this->request('GET', $endpoint, $query);
    }

    public function post($endpoint, $query = array(), $files = array())
    {
        return $this->request('POST', $endpoint, $query, $files);
    }

    public function getAccount()
    {
        return $this->get('GetAccount');
    }

    public function createOrder($name, $comments, $dueDate, $craftOrderId, $wordCount)
    {
        return $this->post('CreateOrder', array(
            'name' => $name,
            'comments' => $comments,
            'duedate' => $dueDate,
            // 'clientref' => $craftOrderId,
            'delivery' => self::DELIVERY,
            'estwordcount' => $wordCount,
        ));
    }

    public function requestOrderCallback($orderId, $url)
    {
        return $this->post('RequestOrderCallback', array(
            'orderid' => $orderId,
            'url' => $url,
        ));
    }

    public function getOrder($orderId)
    {
        return $this->get('GetOrder', array(
            'orderid' => $orderId,
        ));
    }

    public function getFileInfo($orderId)
    {
        return $this->get('GetFileInfo', array(
            'orderid' => $orderId,
        ));
    }

    public function simulateOrderComplete($orderId)
    {
        return $this->post('SimulateOrderComplete', array(
            'orderid' => $orderId,
        ));
    }

    public function submitOrder($orderId)
    {
        return $this->post('SubmitOrder', array(
            'orderid' => $orderId,
        ));
    }

    public function addReviewUrl($orderId, $fileId, $url)
    {
        return $this->post('AddReviewURL', array(
            'orderid' => $orderId,
            'fileid' => $fileId,
            'url' => $url,
        ));
    }

    public function sendSourceFile($orderId, $sourceSite, $targetSite, $craftOrderId, $sourceFile)
    {
        // var_dump([$orderId, $sourceSite, $targetSite, $craftOrderId, $sourceFile]);
        // die;
        return $this->post('SendSourceFile', array(
            'orderid' => $orderId,
            'sourcelang' => $sourceSite,
            'targetlang' => $targetSite,
            // 'clientref' => $craftOrderId,
        ), array(
            'file' => $sourceFile,
        ));
    }

    public function getFileStatus($orderId, $fileId)
    {
        return $this->get('GetFileStatus', array(
            'orderid' => $orderId,
            'fileid' => $fileId,
        ));
    }

    public function getFile($orderId, $fileId)
    {
        $endpoint = 'GetFile';

        $query = array(
            'orderid' => $orderId,
            'fileid' => $fileId,
        );

        $request = new Request('GET', $endpoint.'?'.http_build_query($query, '', '&'));

        if ($this->loggingEnabled) {
            $this->logRequest($request, $endpoint);
        }

        try {
            $response = $this->client->send($request, ['timeout' => 2]);
        } catch (Exception $e) {
            //@TODO
            var_dump($e);
            return null;
        }

        // var_dump($response);

        if ($this->loggingEnabled) {
            $this->logResponse($response, $endpoint);
        }

        if ($response->getStatusCode() != 200) {
            //@TODO
            return null;
        }

        $body = $response->getBody();

        return $body->getContents();
    }

    public function requestFileCallback($orderId, $fileId, $url)
    {
        // var_dump($orderId);
        // var_dump($fileId);
        // var_dump($url);
        return $this->post('RequestFileCallback', array(
            'orderid' => $orderId,
            'fileid' => $fileId,
            'url' => $url,
        ));
    }
    
    public function getLanguages()
    {
        return $this->get('GetLanguages');
    }
    
    public function getLanguagePairs($sourceLang)
    {
        return $this->get('GetLanguagePairs', array(
            'sourcelang' => $sourceLang,
        ));
    }

    public function editOrderName($orderId, $name)
    {

        return $this->post('EditOrder', array(
            'OrderID' => $orderId,
            'Name' => $name,
            'delivery' => self::DELIVERY,
        ));
    }
}
