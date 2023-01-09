<?php

namespace App\Repository\Admin;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Media;
use App\Forms\MediaForm;
use App\Enums\MediaTypes;
use App\Enums\UserStatus;
use App\Forms\InterestForm;
use App\Models\AdminRoleUser;
use App\Models\EventAlbumItem;
use App\Models\GroupAlbumItem;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use App\Repository\Users\UserInterface;
use App\Models\UserDiscussionAttachment;

class AdminUserRepository implements UserInterface
{

    /**
     * @param mixed $userId
     *
     * @return [type]
     */
    public static function getUsers($userId)
    {
        $whereRaw = ($userId ? 'id != ' . $userId : '');
        $user = User::when($whereRaw, function ($q) use ($whereRaw) {
            $q->whereRaw($whereRaw);
        })
            ->with([
                'info',
                'interest',
            ])
            ->get();

        $data = [];
        foreach ($user as $user) {
            $interest = [];
            if (!empty($user->interests)) {
                foreach ($user->interests as $interest) {
                    array_push($interest, InterestForm::show($interest->interest_id));
                }
            }
            $data[] = [
                "id" => $user->id,
                "user_name" => $user->user_name,
                "first_name" => $user->first_name,
                "last_name" => $user->last_name,
                "email" => $user->email,
                "uploaded_image" => $user->image ? MediaForm::getImageURLById($user->image) : '',
                "gender" => $user->gender,
                "birth_date" => $user->birth_date,
                "account_type" => $user->account_type,
                "status" => $user->status,
                "info" => $user->info,
                "interests" => $interest,
                "photos" => MediaForm::getAdditionalPhotosByUserId($user->id)
            ];
        }

        return $data;
    }

    /**
     * Custom Login method for Admin account
     * @param string $userName
     * @param string $password
     * @param int|null $remember
     *
     * @return array | Boolean
     * @author Robert Edward Hughes Jr <robert.h@ragingriverict.com>
     */
    public static function loginAccount(
        string $userName,
        string $password,
        bool $remember
    ) {
        $findByUserName = User::where(filter_var($userName, FILTER_VALIDATE_EMAIL) ? 'email' : 'user_name', $userName)->first();

        if($findByUserName) {

            $adminPassword = AdminRoleUser::whereUserId($findByUserName->id)->first();

            if(Hash::check($password, $adminPassword->password)) {
                $getUserInfo = User::find($findByUserName->id);
                $token = $getUserInfo->createToken('adminAuthToken')->accessToken;

                $getUserInfo->last_login_at = Carbon::now()->toDateTimeString();
                $getUserInfo->save();

                return ['token' => $token, 'user' => $getUserInfo];
            }
        }

        return false;
    }

    /**
     * @param mixed $id
     *
     * @return [type]
     */
    public static function getMedia($id)
    {
        $data = Media::whereId($id)
            ->first();
        return $data;
    }

    /**
     * @param mixed $userId
     *
     * @return [type]
     */
    public static function getUserById(int $userId)
    {
        $whereRaw = ($userId ? 'id = ' . $userId : '');

        $user = User::when($whereRaw, function ($q) use ($whereRaw) {
            $q->whereRaw($whereRaw);
        })
            ->with([
                'profile',
                'interests',
            ])
            ->first();

        return new UserResource($user);
    }

    /**
     * This method will handle the token validation sent from email
     * if the token is correct then it will update user and send the User
     * info back to controller to send back to FE
     *
     * @param string $token
     * @param int $status
     *
     * @return object
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function validateAccount(
        string $token,
        int $status,
        string $emailVerifiedAt = null
    ) {
        $checkVerifiedAt = User::where('validate_token', '=', $token)
            ->exists();
        if (!$checkVerifiedAt) {
            //abort(404, 'This token has already been activated or is invalid.');
            return null;
        }

        $checkToken = User::where('validate_token', $token);
        if ($checkToken->first()) {
            return $checkToken->first();
        }
    }

    public static function getUserByEmail(string $email)
    {
        $user = User::where('email', strtolower($email))
            ->first();

        if(is_null($user)) {
            return null;
        }

        return new UserResource($user);
    }

    /**
     * @param string $updateUserEmailToken
     *
     * @return User|null
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public static function getUserByChangeEmailToken(string $updateUserEmailToken):? User
    {
        if(!Cache::has($updateUserEmailToken)) {
            return null;
        }
        $tokenData = Cache::get($updateUserEmailToken);
        return User::query()->find($tokenData['id']);
    }

    /**
     * @param User $user
     * @param string $email
     * @return User
     *
     * @throws \Exception
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function updateUserEmail(User $user, string $email): User
    {
        if(User::query()->where('id', '!=', $user->id)->where('email', $email)->exists()) {
            throw new \Exception('Email already in exists.');
        }

        $user->email = $email;
        $user->save();

        return $user;
    }
    /**
     * @return integer
     */
    public static function getActiveUsersCount()
    {
        return User::where('status', UserStatus::PUBLISHED)
            ->count();
    }


    public static function getUserWallVideos($wallAttachmentIds)
    {
        $attachments = UserDiscussionAttachment::whereIn('user_discussion_id', $wallAttachmentIds)
            ->get()
            ->pluck('attachment');

        return $attachments->where('media_type_id', MediaTypes::VIDEOS);
    }

    public static function getEventWallVideos()
    {
        $eventWallAttachmentIds = EventAlbumItem::select('media_id')
            ->where('user_id', authUser()->id)
            ->get();

        $attachments = Media::whereIn('id', $eventWallAttachmentIds)
            ->get();

        return $attachments->where('media_type_id', MediaTypes::VIDEOS);
    }

    public static function getGroupWallVideos()
    {
        $groupWallAttachmentIds = GroupAlbumItem::select('media_id')
            ->where('user_id', authUser()->id)
            ->get();

        $attachments = Media::whereIn('id', $groupWallAttachmentIds)
            ->get();

        return $attachments->where('media_type_id', MediaTypes::VIDEOS);
    }
}
