<?php

namespace acclaro\translations\services\api;

use Craft;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use acclaro\translations\Constants;

class AcclaroApiClient
{
    protected $loggingEnabled = false;

    public function __construct(
        $apiToken,
        $sandboxMode = false,
        Client $client = null
    ) {
        $this->client = $client ?: new Client([
            'base_uri' => $sandboxMode ? Constants::SANDBOX_URL : Constants::PRODUCTION_URL,
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

        $filename = 'api-response-'.$endpoint.'-'.date('YmdHis').'.' . Constants::FILE_FORMAT_TXT;

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

        if (!isset($responseJson['data']) || $responseJson['success'] === false) {
            return null;
        }

        // // is assoc?
        if (is_array($responseJson['data']) && $responseJson['data'] === array_values($responseJson['data'])) {
            return array_map(function($row) {
                return (object) $row;
            }, $responseJson['data']);
        }

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

    // Order Endpoints
    public function createOrder($name, $comments, $dueDate, $craftOrderId, $wordCount)
    {
        $order = $this->post('CreateOrder', array(
            'name' => $name,
            'comments' => $comments,
            'duedate' => $dueDate,
            // 'clientref' => $craftOrderId,
            'delivery' => Constants::DELIVERY,
            'estwordcount' => $wordCount,
        ));

        $this->addOrderTags($order->orderid);

        return $order;
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

    public function editOrderName($orderId, $name)
    {
        return $this->post('EditOrder', array(
            'orderid' => $orderId,
            'name' => $name,
            'delivery' => Constants::DELIVERY,
        ));
    }

    public function addOrderTags($orderId, $tags = null)
    {
        return $this->post('AddOrderTag', array(
            'orderid' => $orderId,
            'tag'     => $tags ?? Constants::DEFAULT_TAG
        ));
    }

    public function removeOrderTags($orderId, $tag)
    {
        return $this->post('DeleteOrderTag', array(
            'orderid' => $orderId,
            'tag'     => $tag
        ));
    }

    public function addOrderComment($orderId, $comment)
    {
        return $this->post('AddOrderComment', array(
            'orderid'   => $orderId,
            'comment'   => $comment
        ));
    }

    public function editOrder($orderId, $name, $comment = null, $dueDate = null)
    {
        $data = [
            'orderid'   => $orderId,
            'name'      => $name
        ];

        if ($comment) $data['comment'] = $comment;
        if ($dueDate) $data['duedate'] = $dueDate;

        return $this->post('EditOrder', $data);
    }

    // File Endpoints
    public function addFileComment($orderId, $fileId, $comment)
    {
        return $this->post('AddFileComment', array(
            'orderid'   => $orderId,
            'fileid'    => $fileId,
            'comment'   => $comment
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

    public function getFileInfo($orderId)
    {
        return $this->get('GetFileInfo', array(
            'orderid' => $orderId,
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

    public function addLanguagePair($orderId, $sourceLang, $targetLang)
    {
        return $this->post('AddLanguagePair', array(
            'orderid'       => $orderId,
            'sourcelang'    => $sourceLang,
            'targetlang'    => $targetLang
        ));
    }
}
