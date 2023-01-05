<?php

namespace App\Http\Controllers\Api;

use App\Enums\AnyChildrenType;
use App\Enums\BodyType;
use App\Enums\DrinkerType;
use App\Enums\EducationalLevel;
use App\Enums\EthnicityType;
use App\Enums\GenderType;
use App\Enums\IncomeLevelType;
use App\Enums\RelationshipStatusType;
use App\Enums\SmokingType;
use App\Enums\ZodiacSignType;
use App\Http\Controllers\Controller;
use App\Http\Resources\InterestResource;
use App\Models\Interest;
use Illuminate\Http\Request;

class EnumReturnController extends Controller
{
    public function returnEnumDropdowns(Request $request)
    {
        if (!empty($request->only)) {
            if (is_array($request->only)) {
                $dropdowns = $request->only;
            } else {
                $dropdowns = [$request->only];
            }
        } else {
            $dropdowns = [
                'income_level',
                'ethnicity',
                'gender',
                'educational_level',
                'relationship_status',
                'body_type',
                'zodiac_sign',
                'smoking',
                'drinking',
                'interest',
                'any_children',
            ];
        }

        $data = [];

        foreach ($dropdowns as $dropdown) {
            if ($dropdown === 'income_level') {
                $data[$dropdown] = IncomeLevelType::map();
            } elseif ($dropdown === 'ethnicity') {
                $data[$dropdown] = EthnicityType::map();
            } elseif ($dropdown === 'gender') {
                $data[$dropdown] = GenderType::map();
            } elseif ($dropdown === 'educational_level') {
                $data[$dropdown] = EducationalLevel::map();
            } elseif ($dropdown === 'relationship_status') {
                $data[$dropdown] = RelationshipStatusType::map();
            } elseif ($dropdown === 'body_type') {
                $data[$dropdown] = BodyType::map();
            } elseif ($dropdown === 'zodiac_sign') {
                $data[$dropdown] = ZodiacSignType::map();
            } elseif ($dropdown === 'smoking') {
                $data[$dropdown] = SmokingType::map();
            } elseif ($dropdown === 'drinking') {
                $data[$dropdown] = DrinkerType::map();
            } elseif ($dropdown === 'interest') {
                $data[$dropdown] = InterestResource::collection(
                    Interest::where('approved', 1)->get()
                );
            } elseif ($dropdown === 'any_children') {
                $data[$dropdown] = AnyChildrenType::map();
            }
        }

        return response()->json([
            'data' => $data
        ]);
    }
}
