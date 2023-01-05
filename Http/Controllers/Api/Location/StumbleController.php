<?php

namespace App\Http\Controllers\Api\Location;

use App\Enums\SearchMethod;
use App\Events\DisableStumbleEvent;
use App\Events\GetNearbyUsersEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Location\AddUserLocationRequest;
use App\Http\Requests\Location\GetNearbyUsersRequest;
use App\Http\Resources\Location\StumbledResource;
use App\Http\Resources\Location\StumbleResource;
use App\Http\Resources\UserResource2;
use App\Http\Resources\UserSearchStumbleResource;
use App\Models\User;
use App\Repository\Browse\BrowseRepository;
use App\Repository\Stumble\StumbleRepository;
use App\Repository\Users\UserRepository;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\Response;

class StumbleController extends Controller
{
    use ApiResponser;

    private $stumbleRepository;
    public $browseRepository;
    public $userRepository;
    public $perPage;
    public $withLimit;
    public $woutLimit;

    public function __construct(
        BrowseRepository $browseRepository,
        StumbleRepository $stumbleRepository,
        UserRepository $userRepository
    )
    {
        $this->stumbleRepository = $stumbleRepository;
        $this->browseRepository = $browseRepository;
        $this->userRepository = $userRepository;
        $this->perPage = 12;
        $this->withLimit = true;
        $this->woutLimit = true;
    }

    public function postUserLocation(User $user, AddUserLocationRequest $request)
    {
        $data = $request->validated();

        $lat = $data['lat'];
        $long = $data['long'];

        $distance = 6000000; // default to 6000 kilometers

        if (isset($data['distance'])) {
            $distance = $data['distance'] * 1000; // Convert kilometers to meters
        }

        try {
            $stumble = $this->stumbleRepository->postStumble($user, $long, $lat);

            $resource = $this->userStumbleResource($stumble, $user);

            $nearbyUsers = $this->stumbleRepository->getNearbyStumbles($lat, $long, $distance);

            $nearbyUsersResources = $this->getNearbyUsersResources($data, $nearbyUsers);

            $requestingUser = [
                'user_id' => $user->id,
                'user' => new UserResource2($user),
                'long' => $data['long'],
                'lat' => $data['lat'],
            ];

            if ($nearbyUsersResources != []) {
                broadcast(new GetNearbyUsersEvent($requestingUser, $nearbyUsersResources));
            }

            return $this->successResponse(new StumbleResource($resource));
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    private function userStumbleResource($stumble, User $user)
    {
        return [
            'user_id' => $stumble->user_id,
            'user' => new UserResource2($user),
            'long' => $stumble->longitude,
            'lat' => $stumble->latitude,
        ];
    }

    private function getNearbyUsersResources($coordinate, $nearbyUsers)
    {
        if ($nearbyUsers->count() > 0) {
            $resources = [];

            foreach ($nearbyUsers as $stumble) {
                $resources[] = $this->userStumbleResourceWithDistance($coordinate, $stumble, $stumble->user);
            }

            return $resources;
        }

        return [];
    }

    public function deleteUserLocation(User $user)
    {
        try {
            $stumble = $user->stumble;

            $resource = $this->userStumbleResource($stumble, $user);

            $requestingUser = [
                'user_id' => $user->id,
                'user' => new UserResource2($user),
                'long' => $stumble->longitude,
                'lat' => $stumble->latitude,
            ];

            $this->stumbleRepository->deleteStumble($stumble);

            broadcast(new DisableStumbleEvent($requestingUser));

            return $this->successResponse(new StumbleResource($resource));
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function getStumbledUsers(User $user)
    {
        try {
            $stumbled = $this->stumbleRepository->getStumbled($user);
            return $this->successResponse(StumbledResource::collection($stumbled));
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function getNearbyUsers(GetNearbyUsersRequest $request)
    {
        $data = $request->validated();

        $filters = $request->except([
            'page',
            'long',
            'lat',
            'limit',
            'distance',
        ]);

        $searchFilter = $this->browseRepository->searchFilter($filters);

        $searchResults = $this->browseRepository->elasticSearchBrowse(
            $searchFilter,
            $this->woutLimit,
            $this->perPage,
            $this->userRepository->getActiveUsersCount(),
            SearchMethod::ALL_MEMBERS
        );

        $lat = $data['lat'];
        $long = $data['long'];
        $distance = $data['distance'] * 1000; // Convert kilometers to meters

        if ($filters != []) {
            $nearbyUsers = $this->stumbleRepository->getNearbyStumbles($lat, $long, $distance);

            $filteredStumble = $searchResults->whereIn('id', $nearbyUsers->pluck('user_id'))->pluck('id')->all();

            $nearbyUsers = $nearbyUsers->whereIn('user_id', $filteredStumble);

            if ($nearbyUsers->count() > 0) {
                $resources = [];

                foreach ($nearbyUsers as $stumble) {
                    $resources[] = $this->userStumbleResourceWithDistance($data, $stumble, $stumble->user);
                }

                return $this->successResponse($resources);
            } else {
                return $this->successResponse([]);
            }
        }

        $nearbyUsers = $this->stumbleRepository->getNearbyStumbles($lat, $long, $distance);

        if ($nearbyUsers->count() > 0) {
            $resources = [];

            foreach ($nearbyUsers as $stumble) {
                $resources[] = $this->userStumbleResourceWithDistance($data, $stumble, $stumble->user);
            }

            $this->stumbleRepository->postStumbled($resources, $long, $lat);

            return $this->successResponse($resources);
        } else {
            return $this->errorResponse('No users nearby.', Response::HTTP_NOT_FOUND);
        }
    }

    private function filteredStumbleResource($userData, $stumble, User $user)
    {
        $distance = $this->calculateDistance($userData['lat'], $userData['long'], $stumble->latitude, $stumble->longitude);

        return [
            'user_id' => $stumble->user_id,
            'user' => new UserSearchStumbleResource($user),
            'long' => $stumble->longitude,
            'lat' => $stumble->latitude,
            'distance' => $distance,
        ];
    }

    private function calculateDistance($latFrom, $longFrom, $latTo, $longTo)
    {
        $unit = 'km';

        if (($latFrom == $latTo) && ($longFrom == $longTo)) {
            return 0;
        } else {
            $theta = $longFrom - $longTo;
            $dist = sin(deg2rad($latFrom)) * sin(deg2rad($latTo)) + cos(deg2rad($latFrom)) * cos(deg2rad($latTo)) * cos(deg2rad($theta));
            $dist = acos($dist);
            $dist = rad2deg($dist);
            $miles = $dist * 60 * 1.1515;

            if ($unit == "km") {
                return ($miles * 1.609344);
            } else if ($unit == "miles") {
                return ($miles * 0.8684);
            } else {
                return $miles;
            }
        }
    }

    private function userStumbleResourceWithDistance($userData, $stumble, User $user)
    {
        $distance = $this->calculateDistance($userData['lat'], $userData['long'], $stumble->latitude, $stumble->longitude);

        return [
            'user_id' => $stumble->user_id,
            'user' => new UserResource2($user),
            'long' => $stumble->longitude,
            'lat' => $stumble->latitude,
            'distance' => $distance,
        ];
    }

    public function getStumbleMatches(User $user)
    {
        $lat = $user->stumble->latitude;
        $long = $user->stumble->longitude;
        $coordinate = [
            'lat' => $lat,
            'long' => $long,
        ];
        $distance = 6000000; // Default 6000 kilometers

        $nearbyUsers = $this->stumbleRepository->getNearbyStumbles($lat, $long, $distance);

        $nearbyUsersResources = $this->getNearbyUsersResources($coordinate, $nearbyUsers);

        if ($nearbyUsersResources != []) {
            return $this->successResponse($nearbyUsersResources);
        }

        return $this->errorResponse('No matches nearby.', Response::HTTP_NOT_FOUND);
    }
}
