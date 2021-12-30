<?php

namespace acclaro\translations\services\api;

use Craft;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use acclaro\translations\Constants;
use GuzzleHttp\HandlerStack;
use Spatie\GuzzleRateLimiterMiddleware\RateLimiterMiddleware;

class AcclaroApiClient
{
    protected $loggingEnabled = false;

    public function __construct(
        $apiToken,
        $sandboxMode = false,
        Client $client = null
    ) {
		$stack = HandlerStack::create();
		$stack->push(RateLimiterMiddleware::perSecond(3));

        $this->client = $client ?: new Client([
            'base_uri' => $sandboxMode ? Constants::SANDBOX_URL : Constants::PRODUCTION_URL,
            'headers' => array(
                'Authorization' => sprintf('Bearer %s', $apiToken),
                'Accept' => 'application/json',
                'User-Agent' => 'Craft'
			),
			'handler' => $stack
        ]);
    }

    /**
     * Get Api Client
     *
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Log api request call
     *
     * @param Request $request
     * @param string $endpoint
     * @return void
     */
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

    /**
     * Log api response data
     *
     * @param JsonResponse $response
     * @param [type] $endpoint
     * @return void
     */
    public function logResponse($response, $endpoint)
    {
        $tempPath = Craft::$app->path->getTempPath().'/translations';

        if (!is_dir($tempPath)) {
            mkdir($tempPath);
        }

        $filename = 'api-response-'.$endpoint.'-'.date('YmdHis').'.' . Constants::FILE_FORMAT_TXT;

        $filePath = $tempPath.'/'.$filename;

        $handle = fopen($filePath, 'w+');

        fwrite($handle, (string) $response);

        fclose($handle);
    }

    /**
     * Add query params to api endpoint
     *
     * @param string $endpoint
     * @param array $query
     * @return string
     */
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

    /**
     * Create a request object and send the api request
     *
     * @param string $method
     * @param string $endpoint
     * @param array $query
     * @param array $files
     * @return void|object
     */
    public function request($method, $endpoint, $query = array(), $files = array())
    {
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
			Craft::info('Acclaro Api Error Response. ErrorCode:'. $response->getStatusCode());
			Craft::error('Acclaro Api Error Response. Error'. $response->getBody());
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

    /**
     * Create and send a GET api request
     *
     * @param string $endpoint
     * @param array $query
     * @return void|object
     */
    public function get($endpoint, $query = array())
    {
        return $this->request(Constants::REQUEST_METHOD_GET, $endpoint, $query);
    }

    /**
     * Create and send a POST api request
     *
     * @param string $endpoint
     * @param array $query
     * @param array $files
     * @return void|object
     */
    public function post($endpoint, $query = array(), $files = array())
    {
        return $this->request(Constants::REQUEST_METHOD_POST, $endpoint, $query, $files);
    }

    /**
     * Get acclaro account details
     *
     * @return void|object
     */
    public function getAccount()
    {
        return $this->get(Constants::ACCLARO_API_GET_ACCOUNT);
    }

    // Order Endpoints

    /**
     * Create a new order
     *
     * @param string $name
     * @param string $comments
     * @param string $dueDate
     * @param int|string $craftOrderId
     * @param int|string $wordCount
     * @return void|object
     */
    public function createOrder($name, $comments, $dueDate, $wordCount)
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

    /**
     * Request an order callback
     *
     * @param int|string $orderId
     * @param string $url
     * @return void|object
     */
    public function requestOrderCallback($orderId, $url)
    {
        return $this->post(Constants::ACCLARO_API_REQUEST_ORDER_CALLBACK, array(
            'orderid' => $orderId,
            'url' => $url,
        ));
    }

    /**
     * Get Order
     *
     * @param int|string $orderId
     * @return void|object
     */
    public function getOrder($orderId)
    {
        return $this->get(Constants::ACCLARO_API_GET_ORDER, array(
            'orderid' => $orderId,
        ));
    }

    /**
     * Submit order
     *
     * @param int|string $orderId
     * @return void|object
     */
    public function submitOrder($orderId)
    {
        return $this->post(Constants::ACCLARO_API_SUBMIT_ORDER, array(
            'orderid' => $orderId,
        ));
    }

    /**
     * Edit order name
     *
     * @param int|string $orderId
     * @param string $name
     * @return void|object
     */
    public function editOrderName($orderId, $name)
    {
        return $this->post(Constants::ACCLARO_API_EDIT_ORDER, array(
            'orderid' => $orderId,
            'name' => $name,
            'delivery' => Constants::DELIVERY,
        ));
    }

    /**
     * Add tags to order
     *
     * @param int|string $orderId
     * @param null|string $tags
     * @return void|object
     */
    public function addOrderTags($orderId, $tags = null)
    {
        return $this->post(Constants::ACCLARO_API_ADD_ORDER_TAG, array(
            'orderid' => $orderId,
            'tag'     => $tags ?? Constants::DEFAULT_TAG
        ));
    }

    /**
     * Remove order tags
     *
     * @param int|string $orderId
     * @param null|string $tag
     * @return void|object
     */
    public function removeOrderTags($orderId, $tag)
    {
        return $this->post(Constants::ACCLARO_API_DELETE_ORDER_TAG, array(
            'orderid' => $orderId,
            'tag'     => $tag
        ));
    }

    /**
     * Add order comment
     *
     * @param int|string $orderId
     * @param string $comment
     * @return void|object
     */
    public function addOrderComment($orderId, $comment)
    {
        return $this->post(Constants::ACCLARO_API_ADD_ORDER_COMMENT, array(
            'orderid'   => $orderId,
            'comment'   => $comment
        ));
    }

    // File Endpoints

    /**
     * Add file comment
     *
     * @param int|string $orderId
     * @param int|string $fileId
     * @param string $comment
     * @return void|object
     */
    public function addFileComment($orderId, $fileId, $comment)
    {
        return $this->post(Constants::ACCLARO_API_ADD_FILE_COMMENT, array(
            'orderid'   => $orderId,
            'fileid'    => $fileId,
            'comment'   => $comment
        ));
    }

    /**
     * Add review url
     *
     * @param int|string $orderId
     * @param int|string $fileId
     * @param string $url
     * @return void|object
     */
    public function addReviewUrl($orderId, $fileId, $url)
    {
        return $this->post(Constants::ACCLARO_API_ADD_FILE_REVIEW_URL, array(
            'orderid' => $orderId,
            'fileid' => $fileId,
            'url' => $url,
        ));
    }

    /**
     * Send source file
     *
     * @param int|string $orderId
     * @param string $sourceSite
     * @param string $targetSite
     * @param int|string $craftOrderId
     * @param string|binary $sourceFile
     * @return void|object
     */
    public function sendSourceFile($orderId, $sourceSite, $targetSite, $sourceFile)
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

    /**
     * Get file status
     *
     * @param int|string $orderId
     * @param int|string $fileId
     * @return void|object
     */
    public function getFileStatus($orderId, $fileId)
    {
        return $this->get(Constants::ACCLARO_API_GET_FILE_STATUS, array(
            'orderid' => $orderId,
            'fileid' => $fileId,
        ));
    }

    /**
     * Get file info
     *
     * @param int|string $orderId
     * @return void|object
     */
    public function getFileInfo($orderId)
    {
        return $this->get(Constants::ACCLARO_API_GET_ORDER_FILES_INFO, array(
            'orderid' => $orderId,
        ));
    }

    /**
     * Get file
     *
     * @param int|string $orderId
     * @param int|string $fileId
     * @return void|object
     */
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

    /**
     * Request a file callback
     *
     * @param int|string $orderId
     * @param int|string $fileId
     * @param string $url
     * @return void|object
     */
    public function requestFileCallback($orderId, $fileId, $url)
    {
        return $this->post(Constants::ACCLARO_API_REQUEST_FILE_CALLBACK, array(
            'orderid' => $orderId,
            'fileid' => $fileId,
            'url' => $url,
        ));
    }

    /**
     * Get Languages
     *
     * @return void|object
     */
    public function getLanguages()
    {
        return $this->get(Constants::ACCLARO_API_GET_LANGUAGES);
    }

    /**
     * Get language pairs
     *
     * @param string $sourceLang
     * @return void|object
     */
    public function getLanguagePairs($sourceLang)
    {
        return $this->get(Constants::ACCLARO_API_GET_LANGUAGE_PAIRS, array(
            'sourcelang' => $sourceLang,
        ));
    }
}
