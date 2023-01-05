<?php

namespace App\Http\Controllers\Web\Page;

use App\Http\Controllers\Web\PerfectFriendController;
use App\Http\Resources\ProfileResource;
use Inertia\Inertia;
use App\Models\User;
use App\Repository\Profile\ProfileRepository;
use Illuminate\Http\Request;
use Inertia\Response;

class VisitorController extends PerfectFriendController
{
    private $profileRepository;

    public function __construct(Request $request, ProfileRepository $profileRepository)
    {
        parent::__construct();
        $this->profileRepository = $profileRepository;
        $this->navLinkBaseUrl = '/' . $request->username;
        $this->setWallNavLink('/wall');
        $this->setProfileNavLink('/');
        $this->setEventsNavLink('/events');
    }

    /**
     * @param Request $request
     *
     * @return \Inertia\Response
     * @author Richmond De Silva <richmond.ds@ragingrivetict.com>
     */

    public function wall()
    {
        return Inertia::render('Dashboard');
    }

    /**
     * @param Request $request
     *
     * @return \Inertia\Response
     * @author Richmond De Silva <richmond.ds@ragingrivetict.com>
     */

    public function profile(string $username)
    {
        $user = User::where('user_name', '=', $username)
            ->firstOrFail();
        $userProfile = $this->profileRepository->getProfile($user);

        return Inertia::render(
            'Username',
            $this->baseResponseData->merge([
                'profile' => new ProfileResource($userProfile)
            ])
        );
    }

    /**
     * Users events page
     *
     * @return Response
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function events(): Response
    {
        return Inertia::render('Dashboard/Events/MyEvents', $this->baseResponseData);
    }

    /**
     * Users attending events page
     *
     * @return Response
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function attendingEvents(): Response
    {
        return Inertia::render('Dashboard/Events/AttendingEvents', $this->baseResponseData);
    }
}
