<?php

namespace App\Repository\Stumble;

use App\Models\Stumble;
use App\Models\Stumbled;
use App\Models\User;

class StumbleRepository implements StumbleInterface
{

    public static function postStumble(User $user, $long, $lat)
    {
        $stumble = Stumble::where('user_id', $user->id)
            ->first();

        if ($stumble) {

            $stumble->longitude = $long;
            $stumble->latitude = $lat;
            $stumble->save();
        } else {
            $stumble = Stumble::create([
                'user_id' => $user->id,
                'longitude' => $long,
                'latitude' => $lat
            ]);
        }

        return $stumble;
    }

    public static function postStumbled($stumbles, $long, $lat)
    {
        $stumbled = [];

        foreach ($stumbles as $stumble) {

            // Get stumble with at least 500 meters
            if ($stumble['distance'] < 0.5) {
                $stumbled[] = [
                    'stumbled_ids' => authUser()->id . '-' . $stumble['user_id'],
                    'user_id_1' => authUser()->id,
                    'user_id_2' => $stumble['user_id'],
                    'user_1_longitude' => $long,
                    'user_1_latitude' => $lat,
                    'user_2_longitude' => $stumble['long'],
                    'user_2_latitude' => $stumble['lat'],
                    'distance' => $stumble['distance'],
                ];
            }
        }

        Stumbled::upsert(
            $stumbled,
            ['stumbled_ids'],
            [
                'user_1_longitude',
                'user_1_latitude',
                'user_2_longitude',
                'user_2_latitude',
                'distance'
            ]
        );

        return $stumbled;
    }

    public static function deleteStumble(Stumble $stumble)
    {
        $stumble->delete();
    }

    public static function getNearbyStumbles($lat, $long, $distance)
    {
        $nearbyUsers = Stumble::search('*', function ($meilisearch) use ($lat, $long, $distance) {
            $options = [
                "filter" => "_geoRadius ( $lat, $long, $distance)"
            ];
            return $meilisearch->search('*', $options);
        })
            ->query(function ($builder) {
                $builder->with('user');
            })
            ->get();

        // Exclude self from results
        return $nearbyUsers->where('user_id', '<>', authUser()->id);
    }

    public static function getStumbled(User $user)
    {
        // user_id_1 is the current user
        return Stumbled::where('user_id_1', $user->id)
            ->get();
    }

    public static function getNearbyStumblesByKeyword($lat, $long, $distance, $keyword)
    {
        return Stumble::search('*', function ($meilisearch) use ($lat, $long, $distance) {
            $options = [
                "filter" => "_geoRadius ( $lat, $long, $distance)"
            ];
            return $meilisearch->search('*', $options);
        })
            ->query(function ($builder) use ($keyword) {
                $builder->with('user')
                    ->whereHas('user.interests', function ($builder) use ($keyword) {
                        $builder->where('interest', $keyword);
                    });;
            })
            ->get();
    }
}
