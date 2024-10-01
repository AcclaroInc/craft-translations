<?php

namespace acclaro\translations\services\job;

use Craft;
use craft\elements\User;
use craft\mail\Message;
use craft\queue\BaseJob;
use GuzzleHttp\Exception\RequestException;

class SyncDataToHubSpotJob extends BaseJob
{
    private $accessToken;

    public function __construct()
    {
        // Fetch the HubSpot access token from environment variables
        $this->accessToken = getenv('HUBSPOT_ACCESS_TOKEN') ?: '';
    }

    public function execute($queue): void
    {
        $user = Craft::$app->getUser()->getIdentity();

        if ($user) {
            $email = $user->email;
            $siteName = Craft::$app->sites->getPrimarySite()->getName();
            $baseUrl = Craft::$app->sites->getPrimarySite()->getBaseUrl();

            // Data for syncing to HubSpot
            $data = [
                'email' => 'dpakravi93+abcde@gmail.com',
                'site' => $siteName,
                'website' => $baseUrl,
            ];
            $userId = 0;

            $this->syncToHubSpot($data, $userId);

            // Optionally, send follow-up email
            $this->sendFollowUpEmail($email, $siteName, $baseUrl, $userId);
        } else {
            Craft::error('No user found for syncing to HubSpot.', __METHOD__);
        }
    }

    /**
     * Syncs user data to HubSpot using Guzzle.
     */
    private function syncToHubSpot(array $data, &$userId = 0): void
    {
        try {
            $client = new \GuzzleHttp\Client();

            // Fetch HubSpot owners
            $response = $client->request('GET', 'https://api.hubapi.com/crm/v3/owners', [
                'headers' => $this->getHeaders(),
            ]);

            $owners = json_decode($response->getBody()->getContents(), true);
            $ownerIds = array_column($owners['results'], 'id');

            // Sync contact to HubSpot with the first available owner
            $this->createOrUpdateContact($client, $data, $ownerIds[0] ?? null, $userId);

        } catch (RequestException $e) {
            Craft::error('Error syncing data to HubSpot: ' . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Create or update contact in HubSpot.
     */
    private function createOrUpdateContact($client, array $data, ?int $ownerId, &$userId): void
    {
        try {
            $response = $client->request('POST', 'https://api.hubapi.com/crm/v3/objects/contacts', [
                'headers' => $this->getHeaders(),
                'json' => [
                    'properties' => [
                        'email' => $data['email'],
                        'company' => $data['site'],
                        'website' => $data['website'],
                        'hubspot_owner_id' => $ownerId
                    ],
                ],
            ]);

            if (in_array($response->getStatusCode(), [200, 201])) {
                Craft::info('Successfully synced data to HubSpot: ' . $response->getBody(), __METHOD__);
                $userId = $response->getBody()->id;
            } else {
                Craft::error('Failed to sync data to HubSpot. Status Code: ' . $response->getStatusCode(), __METHOD__);
            }

        } catch (RequestException $e) {
            // Handle API error if contact already exists or other conflict occurs
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 409) {
                Craft::warning('Contact already exists: ' . $e->getMessage(), __METHOD__);
            } else {
                Craft::error('Error creating or updating contact: ' . $e->getMessage(), __METHOD__);
            }
        }
    }

    /**
     * Helper to get the headers for API requests.
     */
    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ];
    }

    protected function defaultDescription(): ?string
    {
        return 'Syncing user data to HubSpot';
    }
}
