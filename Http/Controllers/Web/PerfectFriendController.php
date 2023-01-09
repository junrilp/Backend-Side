<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

/**
 * Parent Controller for both Authenticated User and Other User (Visitor)
 */
class PerfectFriendController extends Controller
{
    protected $baseResponseData = [];

    public function __construct()
    {
        $this->baseResponseData = collect([
            'navLinkBase' => '/',
            'navLinks' => collect([
                'wall' => null,
                'profile' => null,
                'events' => null,
                'events_myEvents' => null,
                'events_attendingTo' => null,
                'favorites' => null,
                'favorites_myFavorites' => null,
                'favorites_favoritedMe' => null,
                'friends' => null,
                'friends_myFriends' => null,
                'friends_friendRequests' => null,
                'friends_sentRequests' => null,
                'groups' => null,
                'groups_myGroups' => null,
                'groups_myAllGroups' => null,
                'groups_myGroupsPendingInvites' => null,
            ]),
        ]);
    }

    protected function setNavLinkBase($link)
    {
        $this->baseResponseData['navLinkBase'] = $link;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Support\Collection $baseResponseData
     * @author Junril Pateño <junril090693@gmail.com>
     */
    protected function setWallNavLink($link)
    {
        $this->baseResponseData['navLinks']['wall'] = $this->baseResponseData['navLinkBase'] . $link;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Support\Collection $baseResponseData
     * @author Junril Pateño <junril090693@gmail.com>
     */
    protected function setProfileNavLink($link)
    {
        $this->baseResponseData['navLinks']['profile'] = $this->baseResponseData['navLinkBase'] . $link;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Support\Collection $baseResponseData
     * @author Junril Pateño <junril090693@gmail.com>
     */
    protected function setEventsNavLink($link)
    {
        $this->baseResponseData['navLinks']['events'] = $this->baseResponseData['navLinkBase'] . $link;
    }

    protected function setEventsMyEventsNavLink($link)
    {
        $this->baseResponseData['navLinks']['events_myEvents'] = $this->baseResponseData['navLinkBase'] . $link;
    }

    protected function setEventsAttendingToNavLink($link)
    {
        $this->baseResponseData['navLinks']['events_attendingTo'] = $this->baseResponseData['navLinkBase'] . $link;
    }

    protected function setEventsPastEventsNavLink($link)
    {
        $this->baseResponseData['navLinks']['events_pastEvents'] = $this->baseResponseData['navLinkBase'] . $link;
    }

    protected function setEventsAdministratorRolesNavLink($link)
    {
        $this->baseResponseData['navLinks']['events_administratorRoles'] = $this->baseResponseData['navLinkBase'] . $link;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Support\Collection $baseResponseData
     * @author Junril Pateño <junril090693@gmail.com>
     */
    protected function setFavoritesNavLink($link)
    {
        $this->baseResponseData['navLinks']['favorites'] = $this->baseResponseData['navLinkBase'] . $link;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Support\Collection $baseResponseData
     * @author Junril Pateño <junril090693@gmail.com>
     */
    protected function setFavoritesMyFavoritesNavLink($link)
    {
        $this->baseResponseData['navLinks']['favorites_myFavorites'] = $this->baseResponseData['navLinkBase'] . $link;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Support\Collection $baseResponseData
     * @author Junril Pateño <junril090693@gmail.com>
     */
    protected function setFavoritesFavoritedMeNavLink($link)
    {
        $this->baseResponseData['navLinks']['favorites_favoritedMe'] = $this->baseResponseData['navLinkBase'] . $link;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Support\Collection $baseResponseData
     * @author Junril Pateño <junril090693@gmail.com>
     */
    protected function setFriendsNavLink($link)
    {
        $this->baseResponseData['navLinks']['friends'] = $this->baseResponseData['navLinkBase'] . $link;
    }

    protected function setFriendsMyFriendsNavLink($link)
    {
        $this->baseResponseData['navLinks']['friends_myFriends'] = $this->baseResponseData['navLinkBase'] . $link;
    }

    protected function setFriendsFriendRequestsNavLink($link)
    {
        $this->baseResponseData['navLinks']['friends_friendRequests'] = $this->baseResponseData['navLinkBase'] . $link;
    }

    protected function setFriendsSentRequestsNavLink($link)
    {
        $this->baseResponseData['navLinks']['friends_sentRequests'] = $this->baseResponseData['navLinkBase'] . $link;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Support\Collection $baseResponseData
     * @author Junril Pateño <junril090693@gmail.com>
     */
    protected function setGroupsNavLink($link)
    {
        $this->baseResponseData['navLinks']['groups'] = $this->baseResponseData['navLinkBase'] . $link;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Support\Collection $baseResponseData
     * @author Junril Pateño <junril090693@gmail.com>
     */
    protected function setGroupsMyGroupsNavLink($link){
        $this->baseResponseData['navLinks']['groups_myGroups'] = $this->baseResponseData['navLinkBase'] . $link;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Support\Collection $baseResponseData
     * @author Junril Pateño <junril090693@gmail.com>
     */
    protected function setGroupsMyAllGroupsNavLink($link){
        $this->baseResponseData['navLinks']['groups_myAllGroups'] = $this->baseResponseData['navLinkBase'] . $link;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Support\Collection $baseResponseData
     * @author Junril Pateño <junril090693@gmail.com>
     */
    protected function setGroupsMyGroupsPendingInvintesNavLink($link){
        $this->baseResponseData['navLinks']['groups_myGroupsPendingInvites'] = $this->baseResponseData['navLinkBase'] . $link;
    }

}
