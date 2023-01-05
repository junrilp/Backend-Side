<?php

namespace App\Http\Controllers\Api;

use App\Mail\ContactUs;
use Illuminate\Http\Request;
use App\Http\Requests\ContactUsRequest;
use App\Http\Controllers\Controller;
use App\Jobs\SendContactUs;
use Mail;

class ContactUsController extends Controller
{
    public function send(ContactUsRequest $request){
        SendContactUs::dispatch($request->all())->onQueue('high');
    }
}
