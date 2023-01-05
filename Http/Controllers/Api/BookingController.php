<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Inertia\Inertia;
use Carbon\Carbon;

use App\Models\Booking;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        // Render page using Inertia
        // return Inertia::render('Auth/Register');
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'booking_date' => 'required',
                'user_id' => 'required'
            ]);
            if ($validator->fails())
            {
                return response(['errors'=>$validator->errors()->all()], 422);
            }

            DB::beginTransaction();
            $bookBy = Auth::user()->id;
            $memberId = $request->user_id;

            $booking = Booking::where('booked_by', $bookBy)
                                    ->where('user_id',$memberId);
            if (!$booking->first()) {
                Booking::create([
                    'user_id' => $memberId,
                    'booked_by' => $bookBy,
                    'booking_date' => $request->booking_date,
                    'status' => 'Booked',
                    'date_status' => Carbon::now()
                ]);
            }
            else {
                $this->update($request);
            }
            
            DB::commit();
            return response(['success' => true], 200);
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function update(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'booking_date' => 'required',
                'user_id' => 'required'
            ]);
            if ($validator->fails())
            {
                return response(['errors'=>$validator->errors()->all()], 422);
            }

            DB::beginTransaction();
            $bookBy = Auth::user()->id;
            $memberId = $request->user_id;

            $booking = Booking::where('booked_by', $bookBy)
                                    ->where('user_id',$memberId);
            if ($booking->first()) {
                Booking::where('booked_by', $bookBy)
                            ->where('user_id',$memberId)
                            ->update([
                                'booking_date' => $request->booking_date,
                                'status' => 'Booked',
                                'date_status' => Carbon::now()
                            ]);
            }
            else {
                return response(['success' => false], 422);
            }

            DB::commit();
            return response(['success' => true], 200);
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function getAllMyBooking(Request $request)
    {
        $userId = Auth::user()->id;
        $bookings = Booking::with([
                            'user'
                        ])  
                        ->where('user_id', $userId)
                        ->get();
        return response(['data' => $bookings], 200);
    }

    public function changeBookingStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'booked_by' => 'required',
                'user_id' => 'required',
                'status' => 'required'
            ]);
            if ($validator->fails())
            {
                return response(['errors'=>$validator->errors()->all()], 422);
            }

            DB::beginTransaction();
            $bookBy = $request->booked_by;
            $memberId = $request->user_id;
            Booking::where('booked_by', $bookBy)
                        ->where('user_id',$memberId)
                        ->update([
                            'status' => $request->status,
                            'date_status' => Carbon::now()
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
