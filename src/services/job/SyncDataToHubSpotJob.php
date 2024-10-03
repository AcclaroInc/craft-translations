<?php

namespace acclaro\translations\services\job;

use acclaro\translations\Constants;
use Craft;
use craft\queue\BaseJob;
use GuzzleHttp\Client;

class SyncDataToHubSpotJob extends BaseJob
{
    public function execute($queue): void
    {
        \Craft::WARNING('API call was successful: ');
        $currentUser = Craft::$app->getUser()->getIdentity();

        if ($currentUser) {
            $email = $currentUser->email;
            $username = $currentUser->username;

            $siteName = Craft::$app->sites->getPrimarySite()->getName();
            $baseUrl = Craft::$app->sites->getPrimarySite()->getBaseUrl();

            $data = [
                'email' => $email,
                'username' => $username,
                'siteName' => $siteName,
                'baseUrl' => $baseUrl
            ];

            $apiEndpointN8N = Constants::API_ENDPOINT_N8N;
        
            // Initialize the Guzzle HTTP client
            $client = new Client();
        
            try {
                // Make the POST request
                $response = $client->post($apiEndpointN8N, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $data,
                ]);
        
                // Check if the request was successful
                if ($response->getStatusCode() === 200) {
                    $responseData = json_decode($response->getBody()->getContents(), true);
                    Craft::info('API call was successful: ' . json_encode($responseData), __METHOD__);
                } else {
                    Craft::error('API call failed with status: ' . $response->getStatusCode(), __METHOD__);
                }
            } catch (\Exception $e) {
                // Handle any exceptions during the request
                Craft::error('API call failed: ' . $e->getMessage(), __METHOD__);
            }
        }
    }

}
