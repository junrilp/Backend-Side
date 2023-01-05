<?php

namespace App\Repository\Interests;

use App\Models\Interest;
use App\Repository\Interests\InterestInterface;

class InterestRepository implements InterestInterface
{

    /**
     * @param mixed $interestName
     *
     * @return @var mixed $interest
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public static function store($interestName){

        $interest = Interest::updateOrCreate(
            [
                'interest' => $interestName
            ],
            [
                'interest'  => $interestName,
                'slug'      => strtolower(str_replace(' ', '-', $interestName)),
                'media_id'  => 0,
                'approved'  => 1
            ]
        );

        return $interest;

    }


}
