<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\InterestController;
use App\Http\Controllers\Web\Page\PerfectFriendMeController;
use App\Http\Resources\UserSearchResource2 as UserSearchResource;
use App\Models\Page;
use App\Repository\Connection\ConnectionRepository;
use App\Repository\Friend\FriendRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware([
                'account-not-verified',
                'account-verified'
            ])->only('index');
    }

    /**
     * @param Request $request
     * @param FriendRepository $friendRepository
     * @param InterestController $interestController
     *
     * @return Response
     *
     * @author Richmond De Silva <richmond.ds@ragingriverict.com>
     */
    public function index(Request $request, FriendRepository $friendRepository, ConnectionRepository $connectionRepository, InterestController $interestController): Response
    {
        if (Auth::check()) {
            return app(PerfectFriendMeController::class)->wall($friendRepository);
        }

        $birthdayCelebrants = $connectionRepository->getBirthdayCelebrants([
            'date' => date('Y-m-d')
        ]);

        $featuredInterests = json_decode($interestController->getFeaturedInterests()
            ->getContent())
            ->data;

        return Inertia::render('Home', [
            'title' => '- find new friends or become an influencer to create custom experiences for others at an optional fee you set',
            'birthdayCelebrants' => UserSearchResource::collection($birthdayCelebrants),
            'featuredInterests' => $featuredInterests,
            'currentDate' => Carbon::now()->format('Y-m-d'),
        ]);
    }

    /**
     * Show About Us Page
     *
     * @return  \Inertia\Response
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function aboutUs(){


        return Inertia::render('AboutUs', [
                'title' => '- About Us',
                'aboutUs' => Page::where('url', '/about-us')->get()->pluck('content')->first()
            ]
        );


    }

    /**
     * Show Go Premium Page
     *
     * @return  \Inertia\Response
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function goPremium(){
        return Inertia::render('GoPremium', ['title' => '- Go Premium']);
    }

    /**
     * Show FAQs page
     *
     * @return  \Inertia\Response
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function faqs(){
        return Inertia::render('Faqs', ['title' => '- FAQs']);
    }

    /**
     * Show Conctact Us Page
     *
     * @return  \Inertia\Response
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function contactUs(){
        return Inertia::render('ContactUs', ['title' => '- Contact Us']);
    }

    /**
     * Show Terms Page
     *
     * @return  \Inertia\Response
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function terms(){
        return Inertia::render('Terms', ['title' => '- Terms and Conditions']);
    }

    /**
     * Show Privacy Policy Page
     *
     * @return  \Inertia\Response
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function privacyPolicy(){
        return Inertia::render('PrivacyPolicy', ['title' => '- Privacy Policy']);
    }

    /**
     * Show the account suspended page
     * Suspension text message
     * @return \Inertia\Response
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function accountSuspended(){
        return Inertia::render('AccountSuspended', ['title' => '- Account Suspended']);
    }
}
