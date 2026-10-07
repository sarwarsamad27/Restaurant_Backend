<?php

namespace App\Services;

use GuzzleHttp\Client;
use Exception;

class GoogleMapsService
{
    protected $client;
    protected $apiKey;
    protected $baseUrl = 'https://maps.googleapis.com/maps/api';

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = config('services.google_maps.api_key');
    }

    /**
     * Calculate distance between two points
     */
    public function calculateDistance($origin, $destination)
    {
        try {
            $response = $this->client->get($this->baseUrl . '/distancematrix/json', [
                'query' => [
                    'origins' => $origin,
                    'destinations' => $destination,
                    'key' => $this->apiKey,
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if ($data['status'] === 'OK' && isset($data['rows'][0]['elements'][0])) {
                $element = $data['rows'][0]['elements'][0];
                
                if ($element['status'] === 'OK') {
                    return [
                        'success' => true,
                        'distance' => $element['distance']['value'], // in meters
                        'distance_text' => $element['distance']['text'],
                        'duration' => $element['duration']['value'], // in seconds
                        'duration_text' => $element['duration']['text'],
                    ];
                }
            }

            return ['success' => false, 'error' => 'Unable to calculate distance'];
        } catch (Exception $e) {
            \Log::error('Google Maps Distance Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get directions between two points
     */
    public function getDirections($origin, $destination, $waypoints = [])
    {
        try {
            $params = [
                'origin' => $origin,
                'destination' => $destination,
                'key' => $this->apiKey,
            ];

            if (!empty($waypoints)) {
                $params['waypoints'] = implode('|', $waypoints);
            }

            $response = $this->client->get($this->baseUrl . '/directions/json', [
                'query' => $params,
            ]);

            $data = json_decode($response->getBody(), true);

            if ($data['status'] === 'OK') {
                return [
                    'success' => true,
                    'routes' => $data['routes'],
                ];
            }

            return ['success' => false, 'error' => $data['status']];
        } catch (Exception $e) {
            \Log::error('Google Maps Directions Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Geocode address to coordinates
     */
    public function geocodeAddress($address)
    {
        try {
            $response = $this->client->get($this->baseUrl . '/geocode/json', [
                'query' => [
                    'address' => $address,
                    'key' => $this->apiKey,
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if ($data['status'] === 'OK' && isset($data['results'][0])) {
                $location = $data['results'][0]['geometry']['location'];
                return [
                    'success' => true,
                    'latitude' => $location['lat'],
                    'longitude' => $location['lng'],
                    'formatted_address' => $data['results'][0]['formatted_address'],
                ];
            }

            return ['success' => false, 'error' => 'Unable to geocode address'];
        } catch (Exception $e) {
            \Log::error('Google Maps Geocode Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Reverse geocode coordinates to address
     */
    public function reverseGeocode($latitude, $longitude)
    {
        try {
            $response = $this->client->get($this->baseUrl . '/geocode/json', [
                'query' => [
                    'latlng' => "{$latitude},{$longitude}",
                    'key' => $this->apiKey,
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if ($data['status'] === 'OK' && isset($data['results'][0])) {
                return [
                    'success' => true,
                    'address' => $data['results'][0]['formatted_address'],
                    'results' => $data['results'],
                ];
            }

            return ['success' => false, 'error' => 'Unable to reverse geocode'];
        } catch (Exception $e) {
            \Log::error('Google Maps Reverse Geocode Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Find nearby restaurants
     */
    public function findNearbyPlaces($latitude, $longitude, $radius = 5000, $type = 'restaurant')
    {
        try {
            $response = $this->client->get($this->baseUrl . '/place/nearbysearch/json', [
                'query' => [
                    'location' => "{$latitude},{$longitude}",
                    'radius' => $radius,
                    'type' => $type,
                    'key' => $this->apiKey,
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if ($data['status'] === 'OK') {
                return [
                    'success' => true,
                    'places' => $data['results'],
                ];
            }

            return ['success' => false, 'error' => $data['status']];
        } catch (Exception $e) {
            \Log::error('Google Maps Nearby Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
