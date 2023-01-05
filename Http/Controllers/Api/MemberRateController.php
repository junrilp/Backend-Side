<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\RatedMember;

class MemberRateController extends Controller
{
    public function index(Request $request)
    {
        // Render page using Inertia
        // return Inertia::render('Auth/Register');
    }

    public function sendStar(Request $request, $member_id)
    {
        try {

            DB::beginTransaction();
            $userId = Auth::user()->id;

            // Check if loggin id and member_id is exist to prevent duplicate data
            $checkIfExistRatedMember = RatedMember::where('user_id', $userId)
                                            ->where('member_id', $member_id)
                                            ->first();
            if ($checkIfExistRatedMember) {
                RatedMember::where('user_id', $userId)
                                ->where('member_id', $member_id)
                                ->update([
                                    'rate' => $request->rate,
                                    'message' => $request->message
                                ]);
            }
            else {
                RatedMember::create([
                                'user_id' => $userId,
                                'member_id' => $member_id,
                                'rate' => $request->rate,
                                'message' => $request->message
                            ]);
            }
            DB::commit();
            return response(['success' => true], 200);
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
}
