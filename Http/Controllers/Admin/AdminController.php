<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use App\Repository\Profile\ProfileRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminController extends Controller
{

    private $profileRepository;

    public function __construct(ProfileRepository $profileRepository)
    {
        $this->profileRepository = $profileRepository;
    }

    /**
     * @param null $slug1
     * @param null $slug2
     * @param Request $request
     *
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function index($slug1 = null, $slug2 = null, Request $request)
    {

        $user = User::find(auth()->id());
        $status = '';
        $adminRole = '';

        if ($user->adminRoles()->exists()) {

            $adminRole = $user->adminRoles[0]->label;

            if ($user->adminRoles[0]->pivot->password == '') {

                $status = 'no-password';

            } else {

                if ($request->session()->has('is_admin')) {
                    $status = 'authenticated';
                } else {
                    $status = 'unauthenticated';
                }

            }

        } else {

            abort(404);

        }

        return view('admin', [
            'authenticationStatus' => $status,
            'adminRole' => $adminRole,
        ]);

    }


    public function getProfileInfo(int $userId) 
    {  
        $adminUser = User::find(authUser()->id);
        $adminRole = '';

        if ($adminUser->adminRoles()->exists()) {
            $adminRole = $adminUser->adminRoles[0]->label;
        }
            
        $user = User::find($userId);

        $userProfile = $this->profileRepository->getProfile($user);

        $str = $user->account_type === AccountType::PREMIUM ? "Influencer " : "";
        return Inertia::render(
            'PerfectFriend/Me/Profile',
            [
                'profile' => new ProfileResource($userProfile),
                'role' => $adminRole,
                'title'   => $str . $user->first_name . ' ' . $user->last_name
            ]
        );
    }
}
