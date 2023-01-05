<?php

namespace App\Repository\Users;

use App\Enums\MediaTypes;
use App\Enums\UserStatus;
use App\Forms\InterestForm;
use App\Forms\MediaForm;
use App\Http\Resources\UserResource;
use App\Models\EventAlbumItem;
use App\Models\GroupAlbumItem;
use App\Models\Media;
use App\Models\User;
use App\Models\UserDiscussionAttachment;
use App\Repository\Media\MediaRepository;
use App\Repository\Steps\StepsRepository;
use App\Repository\Users\UserInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Enums\LoginStatus;

class UserRepository implements UserInterface
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
     * This will be the login logic for the application
     * @param string $userName
     * @param string $password
     * @param int|null $remember
     *
     * @return array | Response
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function loginAccount(
        string $userName,
        string $password,
        bool $remember
    ) {
        $fieldType = filter_var($userName, FILTER_VALIDATE_EMAIL) ? 'email' : 'user_name';
        if (auth()->attempt(array($fieldType => $userName, 'password' => $password), $remember)) {
            $user = Auth::user();
            $user->update([
                'updated_at' => Carbon::now(),
            ]);
            $token = $user->createToken('Laravel Password Grant Client')->accessToken;

            $getRedirection = StepsRepository::getStepRedirection($user->id, $user->status);

            Auth::guard('web')->login($user, $remember);

            $response = ['token' => $token, 'user' => self::getUserById($user->id), 'step' => $getRedirection ?? ''];

            return $response;
        } else {
            return false;
        }
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

        if (is_null($user)) {
            return null;
        }

        return new UserResource($user);
    }

    public static function getUserByEmailOrUserName(string $emailOrUserName)
    {
        $user = User::where('email', strtolower($emailOrUserName))
            ->orWhere('user_name', strtolower($emailOrUserName))
            ->first();

        if (is_null($user)) {
            return null;
        }

        return new UserResource($user);
    }

    /**
     * @param string $updateUserEmailToken
     *
     * @return User|null
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public static function getUserByChangeEmailToken(string $updateUserEmailToken): ?User
    {
        if (!Cache::has($updateUserEmailToken)) {
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
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function updateUserEmail(User $user, string $email): User
    {
        if (User::query()->where('id', '!=', $user->id)->where('email', $email)->exists()) {
            throw new \Exception('Email already in exists.');
        }

        $user->email = $email;
        $user->save();

        return $user;
    }

    /**
     * @param User $user
     * @param array $data
     * 
     * @return User
     */
    public function updateRegistrationDetails(User $user, array $data): User
    {
        if ($user->status !== UserStatus::NOT_VERIFIED) {
            throw new \Exception('You are not allowed to update the registration data of this user anymore.');
        }

        $user->email = $data['emailAddress'];
        $user->first_name = $data['firstName'];
        $user->last_name = $data['lastName'];
        $user->birth_date = $data['birthdate'];
        $user->zodiac_sign = $data['zodiacSign'];
        $user->image = $data['photoId'];
        $user->account_type = $data['accountType'];
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

    /**
     * @param mixed $emailAddress
     * @param mixed $username
     * 
     * @return int
     * 
     * @author Richmond De Silva <richmond.ds@ragingriverict.com>
     */
    public static function verifyEmailUsername($emailUsername): int
    {
        $user = User::where('email', $emailUsername)
            ->orWhere('user_name', $emailUsername)
            ->first();

        if ($user) {
            return $user->status === UserStatus::NOT_VERIFIED ? LoginStatus::NOT_VERIFIED : LoginStatus::VERIFIED;
        }
        
        return LoginStatus::NOT_REGISTERED;
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
