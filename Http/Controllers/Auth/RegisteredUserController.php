<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;

use Inertia\Inertia;

use App\Forms\RegistrationForm;
use App\Mail\ValidateEmailRegistration;
use Illuminate\Support\Facades\Auth;

class RegisteredUserController extends Controller
{
    private $registrationForm;

    public function __construct(RegistrationForm $registrationForm) 
    {
        $this->registrationForm = $registrationForm;
    }

    public function create()
    {
        return Inertia::render('Auth/Register');
    }

    public function generateUserName(Request $request)
    {
        return Inertia::render('Register', [
            'userName' =>  RegistrationForm::generateUserName()
        ]);
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $data = $this->registrationForm->store($request);
            DB::commit();
            return response(['success' => true, 'data' => $data], 201);
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function validateAccount(Request $request)
    {
        try {
            DB::beginTransaction();
            $data = $this->registrationForm->validateAccount($request);
            //Check agent redirect to mobile if for mobile and web if web
            DB::commit();
            $agent = new \Jenssegers\Agent\Agent;
            $isMobile = $agent->isMobile();
            if ($data) {
                if ($isMobile) {
                    // return data to mobile
                    return redirect('fb://register')->with(['users' => $data]);
                    // return response(['users' => $data],200);
                }
                else {
                    return redirect()->route('password-creation', [
                        'validationToken' => $request->token
                    ]);
                }
            }
            else {
                return 404;
            }
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }       
    }

    public function activateAccount(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_name'         => ['required'],
                'password'          => ['required','min:6'],
                'validate_token'   => ['required']
            ]);
            if ($validator->fails())
            {
                return response(['errors'=>$validator->errors()->all()], 422);
            }

            DB::beginTransaction();
            $data = $this->registrationForm->activateAccount($request);
            DB::commit();
            return response(['success' => true, 'data' => $data], 201);
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }       
    }

    public function stepsForm(Request $request, $user_id)
    {
        try {
            DB::beginTransaction();
            if (! $user_id) {
                $user_id = $request->user_id;
            }
            
            $data = $this->registrationForm->stepsForm($request, $user_id);
            DB::commit();
            return response(['success' => true, 'data' => $data], 201);
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }       
    }

    public function uploadPhoto(Request $request)
    {
        try {
            DB::beginTransaction();
            $data = $this->registrationForm->uploadPhoto($request);
            DB::commit();
            return response(['success' => true, 'data' => $data], 201);
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }  
    }
  
}
