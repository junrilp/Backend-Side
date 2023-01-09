<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserSubscriptionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\UserResource;
use App\Models\Subscription;
use App\Models\User;
use App\Traits\ApiResponser;
use Caseeng\Payment\Base\Facades\Subscription as SubscriptionFacade;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    use ApiResponser;
    
    /**
     * Display the specified resource.
     *
     * @param UserSubscriptionRequest $request
     * @param Subscription $subscription
     * @return \Illuminate\Http\Response
     */
    public function show(UserSubscriptionRequest $request, Subscription $subscription)
    {
        $subscription->load([
            'plan',
            'paymentTransactions' => function($builder){
                $builder->where('success', 1);
            },
        ]);
        
        return SubscriptionResource::make($subscription)
            ->additional([
                'success' => true,
            ]);
    }

    /**
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function freeUpgrade()
    {
        $user = auth()->user();
        $user->subscription_type = UserSubscriptionType::PREMIUM;
        $user->save();
        
        return $this->successResponse(new UserResource($user), 'Account Upgraded.');
    }
}
