<?php

namespace acclaro\translations\services\job;

use acclaro\translations\Constants;
use acclaro\translations\Translations;
use Craft;
use craft\queue\BaseJob;
use GuzzleHttp\Client;

class SyncDataToHubSpotJob extends BaseJob
{
    public function execute($queue): void
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if ($currentUser) {
            $email = $currentUser->email;
            $firstName = $currentUser->firstName;
            $lastName = $currentUser->lastName;

            $siteName = Craft::$app->sites->getPrimarySite()->getName();
            $baseUrl = Craft::$app->sites->getPrimarySite()->getBaseUrl();

            $data = [
                'email' => $email,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'companyName' => $siteName,
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
                    Translations::$plugin->logHelper->log('API call was successful: ' . json_encode($responseData) . ' ' . __METHOD__, Constants::LOG_LEVEL_INFO);
                } else {
                    Translations::$plugin->logHelper->log('API call failed with status: ' . $response->getStatusCode() . ' ' . __METHOD__, Constants::LOG_LEVEL_ERROR);
                }
            } catch (\Exception $e) {
                // Handle any exceptions during the request
                Craft::error('API call failed: ' . $e->getMessage(), __METHOD__);
                Translations::$plugin->logHelper->log('API call failed: ' . $e->getMessage() . ' ' .  __METHOD__, Constants::LOG_LEVEL_ERROR);
            }
        }
    }

}
