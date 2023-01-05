<?php

namespace App\Http\Controllers\Api;


use App\Http\Resources\UserVideoResource;
use Throwable;
use App\Models\Plan;
use App\Models\User;
use App\Forms\MediaForm;
use App\Models\UserPhoto;
use App\Models\UserDevice;
use App\Forms\InterestForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\UsersResource;
use App\Repository\Users\UserRepository;
use App\Models\Subscription;
use Caseeng\Payment\Base\Common\GatewayInterface;
use Caseeng\Payment\Base\Contracts\CreditCard;
use Caseeng\Payment\Base\Facades\Payment;
use App\Http\Requests\PremiumUpgradeRequest;
use App\Http\Requests\UserDeviceRequest;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\PlanResourceCollection;
use Caseeng\Payment\Base\Models\PaymentSubscription;
use App\Http\Resources\SubscriptionResourceCollection;
use Caseeng\Payment\Base\Subscription\SubscriptionResponse;
use Caseeng\Payment\Base\Facades\Subscription as SubscriptionFacade;
use Caseeng\Payment\Base\Subscription\SubscriptionResponseInterface;
use Caseeng\Payment\Base\Repositories\SettingInterface as PaymentSettingInterface;
use App\Traits\ApiResponser;

class UserController extends Controller
{
    use ApiResponser;

    private $userRepository;
    /**
     * @var PaymentSettingInterface
     */
    private $paymentSetting;

    public function __construct(UserRepository $userRepository, PaymentSettingInterface $paymentSetting)
    {
        $this->userRepository = $userRepository;
        $this->paymentSetting = $paymentSetting;
    }

    /**
     * @param Request $request
     * @param InterestForm $interestForm
     *
     * @return [type]
     */
    public function getUsers(Request $request)
    {
        $whereRaw = (Auth::check() ? Auth::user()->id : '');

        $user = User::when($whereRaw, function($q) use ($whereRaw) {
            $q->whereRaw($whereRaw);
        })
        ->get();

        return response()->json([
            'success' => true,
            'data' => new UsersResource($user)
        ], 200);
    }

    /**
     * @param Request $request
     * @param InterestForm $interestForm
     *
     * @return [type]
     */
    public function getProfile(Request $request)
    {
        $userId = Auth::user()->id;
        if (!empty($userId)) {
            $user = new UserResource(User::with(['profile', 'preferences', 'photos'])->findOrFail($userId));
        } else {
            $user = UserResource::collection(User::all());
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ], 200);
    }

    public function getAdditionalPhotosByUserId($userId)
    {
        $images = UserPhoto::where('user_id', $userId)
                    ->get();

        $data = [];
        foreach($images as $photo) {
            $data[] = [
                'id' => $photo->id,
                'image' => MediaForm::getImageURLById($photo->media_id)
            ];
        }
        return $data;
    }

    /**
     * Get premium upgrade details
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function premiumUpgradeDetails(Request $request)
    {
        $user = $request->user('api');

        $plans = Plan::query()->scopes(['active'])->with('billingCycles')->get();
        return response()->json([
            'success' => true,
            'data' => [
                'active_plan' => $user->hasActiveSubscription()
                    ? new PlanResource($user->getActiveSubscription()->plan) : null,
                'plans' => new PlanResourceCollection($plans),
                'payment_methods' => $this->getPaymentMethods(),
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                ],
            ],
        ]);
    }

    /**
     * @return Plan
     */
    private function getDefaultUpgradePlan(): ?Plan
    {
        $defaultPlanId = $this->paymentSetting->getByKey('default_plan_id');

        if ($defaultPlanId) {
            return Plan::query()
                ->where('id', $defaultPlanId)
                ->where([
                    'id' => $defaultPlanId,
                    'status' => Plan::STATUS_ACTIVE,
                ])
                ->with([
                    'billingCycles',
                ])
                ->first();
        } else {
            return Plan::query()
                ->where([
                    'name' => 'Premium Account',
                    'status' => Plan::STATUS_ACTIVE,
                ])
                ->with([
                    'billingCycles',
                ])
                ->first();
        }
    }

    /**
     * @return array
     */
    private function getPaymentMethods(): array
    {
        $gateways = Payment::getGateways();
        $nameMap = [
            'paypal-rest' => 'PayPal',
            'stripe-payment-intent' => 'Stripe',
        ];
        return array_map(function (GatewayInterface $gateway) use ($nameMap) {
            $paymentId = $gateway->getIdentifier();
            return [
                'id' => $paymentId,
                'name' => $nameMap[$paymentId] ?? $gateway->getName(),
            ];
        }, array_values($gateways));
    }

    /**
     * @param PremiumUpgradeRequest $request
     * @todo Validate and handle duplicate subscription
     */
    public function premiumUpgrade(PremiumUpgradeRequest $request)
    {
        $user = $request->user('api');
        $paymentMethod = $request->post('payment_method');

        $responseTemplate = [
            'success' => null,
            'subscription' => null,
            'message' => null,
            'is_redirect' => false,
            'redirect_url' => null,
            'redirect_data' => null,
            'return_url' => null,
            'cancel_url' => null,
        ];

        try {

            if($user->hasActiveSubscription() && ($subscription = $user->getActiveSubscription())) {
                throw new \Exception(sprintf(
                    "You already have an active subscription to \"%s\".",
                    $subscription->title
                ));
            }

            $plan = $request->has('plan_id')
                ? Plan::query()->findOrFail($request->post('plan_id'))
                : $this->getDefaultUpgradePlan();

            /** @var SubscriptionResponse $subscriptionResponse */
            $subscriptionResponse = DB::transaction(function () use ($request, $paymentMethod, $plan, $user) {
                // Prepare customer details
                $customer = SubscriptionFacade::customers()
                    ->addCustomer([
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'address_1' => $request->post('address_1'),
                        'city' => $request->post('city'),
                        'state' => $request->post('state'),
                        'country' => $request->post('country'),
                        'postal_code' => $request->post('postal_code'),
                    ]);

                // Create card
                $cardExpiryParts = explode('/', $request->post('card_expiry'));
                $creditCard = (new CreditCard())->initialize([
                    'firstName' => $request->post('first_name'),
                    'lastName' => $request->post('last_name'),
                    'number' => $request->post('card_number'),
                    'expiryMonth' => $cardExpiryParts[0],
                    'expiryYear' => $cardExpiryParts[1],
                    'cvv' => $request->post('card_cvv'),
                    'email' => $request->post('email'),
                    'billingAddress1' => $request->post('address_1'),
                    'billingCity' => $request->post('city'),
                    'billingState' => $request->post('state'),
                    'billingCountry' => $request->post('country'),
                    'billingPostcode' => $request->post('postal_code'),
                ]);
                $card = SubscriptionFacade::cards()->addCard($creditCard, $customer, $paymentMethod);

                // Prepare subscription
                $subscriptionAttributes = [
                    'user_id' => $user->id,
                ]; // any custom or overrides
                $subscription = SubscriptionFacade::createSubscription(
                    $subscriptionAttributes,
                    $paymentMethod,
                    $plan,
                    $customer,
                    $card,
                );

                // Execute subscription
                return SubscriptionFacade::subscribe($subscription, [
                    'returnUrl' => route('user.premium-upgrade.payment.callback', [
                        'payment_method' => $paymentMethod,
                        'subscription_id' => $subscription->id,
                        'status' => 'success',
                    ]),
                    'cancelUrl' => route('user.premium-upgrade.payment.callback', [
                        'payment_method' => $paymentMethod,
                        'subscription_id' => $subscription->id,
                        'status' => 'cancelled',
                    ]),
                ]);
            });

            $isRedirect = $subscriptionResponse->isRedirect();
            $redirectUrl = $subscriptionResponse->getGatewayResponse()->getRedirectUrl();
            $redirectData = $subscriptionResponse->getGatewayResponse()->getRedirectData();

            $response = array_merge($responseTemplate, [
                'success' => $subscriptionResponse->isSuccessful(),
                'is_redirect' => $isRedirect,
                'redirect_url' => $redirectUrl,
                'redirect_data' => $redirectData,
            ]);
            if ($subscriptionResponse->isSuccessful()) {
                return response()->json(array_merge($response, [
                    'success' => $subscriptionResponse->isSuccessful(),
                    'message' => 'Subscription successful.',
                    'subscription' => new SubscriptionResource($subscriptionResponse->getSubscriptionModel()),
                ]));
            } else if ($isRedirect) {
                return response()->json(array_merge($response, [
                    'success' => false,
                    'message' => 'Payment approval required.',
                    'subscription' => new SubscriptionResource($subscriptionResponse->getSubscriptionModel()),
                    'return_url' => $subscriptionResponse->getGatewayResponse()->getRequest()->getReturnUrl(),
                    'cancel_url' => $subscriptionResponse->getGatewayResponse()->getRequest()->getCancelUrl(),
                ]));
            } else {
                return response()->json(array_merge($response, [
                    'success' => false,
                    'message' => 'Subscription unsuccessful. ' .
                        $subscriptionResponse->getGatewayResponse()->getMessage(),
                    'is_redirect' => false,
                    'data' => $subscriptionResponse->getGatewayResponse()->getData(),
                ]), 400);
            }
        } catch (Throwable $exception) {
            return response()->json(array_merge($responseTemplate, [
                'success' => false,
                'message' => $exception->getMessage(),
            ]), 400);
        }
    }

    /**
     * @param Request $request
     */
    public function premiumUpgradeCallback(Request $request)
    {
        $responseTemplate = [
            'success' => false,
            'message' => null,
            'subscription' => null,
        ];

        $status = $request->get('status');
        $wantsJson = filter_var($request->get('json', 0), FILTER_VALIDATE_BOOLEAN);
        $subscription = PaymentSubscription::query()->findOrFail($request->get('subscription_id'));
        // Check if the subscription not expecting payment approval
        if ($subscription->status !== PaymentSubscription::STATUS_WAITING_PAYMENT_APPROVAL) {
            return $wantsJson
                ? response(array_merge($responseTemplate, [
                    'success' => false,
                    'message' => 'Unauthorized.',
                ]), 401)
                : response('Unauthorized.', 401);
        }

        try {
            if ($status === 'cancelled') {
                $subscription->status = PaymentSubscription::STATUS_CANCELLED;
                $subscription->system_message = 'User cancelled payment.';
                $subscription->save();

                return $wantsJson
                    ? array_merge($responseTemplate, [
                        'success' => true,
                        'message' => 'User cancelled payment.',
                        'subscription' => new SubscriptionResource($subscription),
                    ])
                    : response('Payment cancelled.');
            }

            if ($status === 'success') {
                /** @var SubscriptionResponseInterface $response */
                $response = SubscriptionFacade::completePaymentApproval(
                    $subscription,
                    $subscription->gateway,
                    $request->all()
                );

                if(!$response->isSuccessful()) {
                    throw new \Exception($response->getGatewayResponse()->getMessage());
                }

                SubscriptionFacade::initialSubscriptionActivation($subscription);
            }

            return $wantsJson
                ? response()->json(array_merge($responseTemplate, [
                    'success' => true,
                    'message' => 'Payment completed.',
                    'subscription' => new SubscriptionResource($subscription),
                ]))
                : response('Payment completed.');
        } catch (Throwable $exception) {
            // mark subscription as payment failed
            SubscriptionFacade::setSubscriptionToPaymentFailed($subscription, $exception->getMessage());
            Log::critical('Subscription payment failed or cancelled. ' . $exception->getMessage());
            return $wantsJson
                ? response()->json(array_merge($responseTemplate, [
                    'success' => false,
                    'message' => $exception->getMessage(),
                    'subscription' => new SubscriptionResource($subscription),
                ]), 400)
                : response($exception->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function activateSubscription(Request $request)
    {
        $user = $request->user('api');

        $subscription = $user->getActiveSubscription();
        if($subscription){
            $subscription->load('plan');
        }

        return response()->json([
            'success' => true,
            'has_active_subscription' => $user->hasActiveSubscription(),
            'data' => $subscription ? new SubscriptionResource($subscription) : null,
        ]);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function subscriptionHistory(Request $request)
    {
        $user = $request->user('api');

        $subscriptions = $user->subscriptions->map->load('plan');

        return SubscriptionResourceCollection::make($subscriptions)
            ->additional([
                'success' => true,
            ]);
    }

    /**
     * Cancel active subsctiption
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelActiveSubscription(Request $request)
    {
        $responseTemplate = [
            'success' => false,
            'message' => null,
            'data' => null,
        ];

        /** @var User $user */
        $user = $request->user('api');

        try {
            $subscription = $user->getActiveSubscription();
            if(is_null($subscription)) {
                throw new \Exception('You don\'t have active subscription.');
            }

            SubscriptionFacade::unsubscribe($subscription);

            return response()->json(array_merge($responseTemplate, [
                'success' => true,
                'message' => 'Subscription cancelled. Your account set to Standard.',
                'data' => new SubscriptionResource($subscription),
            ]));
        }catch (\Throwable $exception) {
            return response()->json(array_merge($responseTemplate, [
                'success' => false,
                'message' => $exception->getMessage(),
            ]), 400);
        }
    }

    public function removeUserByEmail($email) {
        User::where('email', $email)->delete();
    }

    /**
     * Add user device id
     * @author Angelito Tan
     */
    public function addUserDeviceId(UserDeviceRequest $request) {
        try{
           UserDevice::firstOrCreate([
                'user_id' => authUser()->id,
                'device_id' => $request->device_id
           ]);
            return $this->successResponse(null);
        }catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Remove user device Id
     * @author Angelito Tan
     */
    public function removeUserDeviceId(UserDeviceRequest $request){
        try{
            UserDevice::where('user_id', authUser()->id)
                ->where('device_id', $request->device_id)
                ->delete();
            return $this->successResponse(null);
        }catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Remove all user device id
     * @author Angelito Tan
     */
    public function removeUserAllDeviceId(){
        try{
            UserDevice::where('user_id', authUser()->id)
                ->delete();
            return $this->successResponse(null);
        }catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function getUserVideos(User $user)
    {
        $wallAttachmentIds = $user->walls->pluck('id');

        $userWallAttachments = $this->userRepository->getUserWallVideos($wallAttachmentIds);

        $eventWallAttachments = $this->userRepository->getEventWallVideos();

        $groupWallAttachments = $this->userRepository->getGroupWallVideos();


        $userVideos = [
            'user_wall' => UserVideoResource::collection($userWallAttachments),
            'user_event_wall' => UserVideoResource::collection($eventWallAttachments),
            'user_group_wall' => UserVideoResource::collection($groupWallAttachments),
        ];

        return $this->successResponse($userVideos);
    }
}
