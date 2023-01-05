<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class PremiumController extends Controller
{
    public function changeToPremiumAccount(Request $request)
    {
        try {
            DB::beginTransaction();
            $userId = Auth::user()->id;
            User::where('id', $userId)
                    ->update([
                        'account_type' => (int)$request->account_type
                    ]);
            DB::commit();
            return response(['success' => true], 200);
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
}
