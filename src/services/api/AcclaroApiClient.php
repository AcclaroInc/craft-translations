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

    private function prepareEndpoint($endpoint, $query)
    {
        $result = $endpoint;

        if (! empty($query)) {
            foreach ($query as $param => $value) {
                $result = strtr($result, ["{" . $param . "}" => $value]);
            }
        }

        return $result;
    }

    public function request($method, $endpoint, $query = array(), $files = array())
    {
        $output = [];

        $endpoint = $this->prepareEndpoint($endpoint, $query);

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
        return $this->request(Constants::REQUEST_METHOD_GET, $endpoint, $query);
    }

    public function post($endpoint, $query = array(), $files = array())
    {
        return $this->request(Constants::REQUEST_METHOD_POST, $endpoint, $query, $files);
    }

    public function getAccount()
    {
        return $this->get(Constants::ACCLARO_API_GET_ACCOUNT);
    }

    // Order Endpoints
    public function createOrder($name, $comments, $dueDate, $craftOrderId, $wordCount)
    {
        $order = $this->post(Constants::ACCLARO_API_CREATE_ORDER, array(
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
        return $this->post(Constants::ACCLARO_API_REQUEST_ORDER_CALLBACK, array(
            'orderid' => $orderId,
            'url' => $url,
        ));
    }

    public function getOrder($orderId)
    {
        return $this->get(Constants::ACCLARO_API_GET_ORDER, array(
            'orderid' => $orderId,
        ));
    }

    public function submitOrder($orderId)
    {
        return $this->post(Constants::ACCLARO_API_SUBMIT_ORDER, array(
            'orderid' => $orderId,
        ));
    }

    public function editOrderName($orderId, $name)
    {
        return $this->post(Constants::ACCLARO_API_EDIT_ORDER, array(
            'orderid' => $orderId,
            'name' => $name,
            'delivery' => Constants::DELIVERY,
        ));
    }

    public function addOrderTags($orderId, $tags = null)
    {
        return $this->post(Constants::ACCLARO_API_ADD_ORDER_TAG, array(
            'orderid' => $orderId,
            'tag'     => $tags ?? Constants::DEFAULT_TAG
        ));
    }

    public function removeOrderTags($orderId, $tag)
    {
        return $this->post(Constants::ACCLARO_API_DELETE_ORDER_TAG, array(
            'orderid' => $orderId,
            'tag'     => $tag
        ));
    }

    public function addOrderComment($orderId, $comment)
    {
        return $this->post(Constants::ACCLARO_API_ADD_ORDER_COMMENT, array(
            'orderid'   => $orderId,
            'comment'   => $comment
        ));
    }

    // File Endpoints
    public function addFileComment($orderId, $fileId, $comment)
    {
        return $this->post(Constants::ACCLARO_API_ADD_FILE_COMMENT, array(
            'orderid'   => $orderId,
            'fileid'    => $fileId,
            'comment'   => $comment
        ));
    }

    public function addReviewUrl($orderId, $fileId, $url)
    {
        return $this->post(Constants::ACCLARO_API_ADD_FILE_REVIEW_URL, array(
            'orderid' => $orderId,
            'fileid' => $fileId,
            'url' => $url,
        ));
    }

    public function sendSourceFile($orderId, $sourceSite, $targetSite, $craftOrderId, $sourceFile)
    {
        return $this->post(Constants::ACCLARO_API_SEND_SOURCE_FILE, array(
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
        return $this->get(Constants::ACCLARO_API_GET_FILE_STATUS, array(
            'orderid' => $orderId,
            'fileid' => $fileId,
        ));
    }

    public function getFileInfo($orderId)
    {
        return $this->get(Constants::ACCLARO_API_GET_ORDER_FILES_INFO, array(
            'orderid' => $orderId,
        ));
    }

    public function getFile($orderId, $fileId)
    {
        $query = array(
            'orderid' => $orderId,
            'fileid' => $fileId,
        );

        $endpoint = $this->prepareEndpoint(Constants::ACCLARO_API_GET_FILE, $query);

        $request = new Request(Constants::REQUEST_METHOD_GET, $endpoint.'?'.http_build_query($query, '', '&'));

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
        return $this->post(Constants::ACCLARO_API_REQUEST_FILE_CALLBACK, array(
            'orderid' => $orderId,
            'fileid' => $fileId,
            'url' => $url,
        ));
    }

    public function getLanguages()
    {
        return $this->get(Constants::ACCLARO_API_GET_LANGUAGES);
    }

    public function getLanguagePairs($sourceLang)
    {
        return $this->get(Constants::ACCLARO_API_GET_LANGUAGE_PAIRS, array(
            'sourcelang' => $sourceLang,
        ));
    }
}
