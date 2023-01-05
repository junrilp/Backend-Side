<?php

namespace App\Repository\Group;

use App\Enums\AccountType;
use App\Enums\GroupMemberInviteStatus;
use App\Events\UserJoinedGroup;
use App\Events\UserLeftGroup;
use App\Jobs\CalculateGroupTotalMembers;
use App\Models\Category;
use App\Models\Group;
use App\Models\Conversation;
use App\Models\GroupMemberInvite;
use App\Models\Tag;
use App\Models\Type;
use App\Models\User;
use App\Notifications\GroupNewMemberNotification;
use App\Repository\Discussions\DiscussionRepository;
use App\Repository\Media\MediaRepository;
use App\Repository\Role\RoleRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use App\Enums\TableLookUp;
use Illuminate\Support\Facades\DB;

/**
 * All business logic intended for groups and user-groups
 * will be place inside this repository.
 */
class GroupRepository implements GroupInterface
{

    /**
     * @inheritDoc
     */
    public function query(): Builder
    {
        return Group::query();
    }

    /**
     * Update group details
     *
     * @param $modelOrId
     * @param array $requestData
     * @param int|null $userId
     * @return Group $group
     */
    public function update($modelOrId, array $requestData, int $userId = null): Group
    {
        $group = $this->query()->findOrFail($modelOrId instanceof Model ? $modelOrId->getKey() : $modelOrId);

        $group->fill($requestData);

        if ($userId) {
            $group->user_id = $userId;
        }

        if (Arr::has($requestData, 'interests')) {
            $interests = Arr::get($requestData, 'interests');
            $group->interests()->sync($interests);
        }

        // Attach group image
        $image = Arr::get($requestData, 'image');
        if ($image) {
            $this->attachMedia($group, $image)->save();
        }

        // Attach group video
        $videoId = Arr::get($requestData, 'video_id');
        if ($videoId) {
            $group->video_id = $videoId;
        }

        // Update tags
        $tags = Arr::get($requestData, 'tags');
        if (!empty($tags)) {
            $tagArray = array_map('trim', is_array($tags) ? $tags : explode(',', $tags));
            $this->addTags($group, $tagArray);
        }

        // Update admins
        if (Arr::has($requestData, 'admins')) {
            // $admins = Arr::get($requestData, 'admins');
            // RoleRepository::assignUserRole($request['roles'], $group, authUser()->id);
        }

        $group->save();

        return $group;
    }

    public function updateAdmins(array $request, Group $group, int $userId)
    {
        RoleRepository::assignUserRole($request['admins'], $group, $userId);
        return $group;
    }

    /**
     * @param Group $group
     * @param $image
     *
     * @return Group
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    protected function attachMedia(Group $group, $image): Group
    {
        if (is_numeric($image)) {
            $group->image_id = $image;
        } elseif (is_file($image) && $media = MediaRepository::checkAndSaveImage($image)) {
            $group->media()->associate($media);
        } elseif (!empty($image) && $media = MediaRepository::addMediaFromBase64($image)) {
            $group->media()->associate($media);
        }

        return $group;
    }

    /**
     * Attach or detach tags to a group.
     *
     * @param Group $group
     * @param array $tags
     * @return Group
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    protected function addTags(Group $group, array $tags): Group
    {
        $tagModels = collect();
        foreach ($tags as $tag) {
            if (!empty($tag)) {

                // Check if tag already exist
                if (is_numeric($tag)) {
                    // Tag ID was passed
                    $tagModel = Tag::query()->find($tag);
                } else {
                    $tagModel = Tag::query()->firstOrCreate(['label' => $tag]);
                    $tagModel->status = 1;
                    $tagModel->save();
                }

                $tagModels->when($tagModel, function ($collection, $tagModel) {
                    $collection->push($tagModel);
                });
            }
        }

        $group->tags()->sync($tagModels->pluck('id')->toArray());

        return $group;
    }

    /**
     * Delete group and its relationships
     *
     * @param $modelOrId
     * @return Group
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function delete($modelOrId): Group
    {
        return DB::transaction(function () use ($modelOrId) {
            $groupModel = $this->query()->findOrFail($modelOrId instanceof Model ? $modelOrId->getKey() : $modelOrId);

            $groupModel->load([
                'media',
            ]);

            if ($groupModel->image) {
                MediaRepository::unlinkMedia($groupModel->media);
            }

            GroupMemberInvite::where('group_id', $groupModel->id)->delete();

            $groupModel->delete();

            return $groupModel;
        });
    }

    /**
     * @inheritDoc
     */
    public function create(array $requestData, int $userId): Group
    {
        /** @var Group $group */
        $group = $this->query()
            ->create([
                'user_id' => $userId,
                'name' => Arr::get($requestData, 'name'),
                'description' => Arr::get($requestData, 'description'),
                'type_id' => Arr::get($requestData, 'type_id'),
                'category_id' => Arr::get($requestData, 'category_id'),
                'live_chat_enabled' => Arr::get($requestData, 'live_chat_enabled') ?? false,
                'video_id' => Arr::get($requestData, 'video_id') ?? NULL,
            ]);

        // Attach group image
        $image = Arr::get($requestData, 'image');
        if ($image) {
            $this->attachMedia($group, $image)->save();
        }

        $tags = Arr::get($requestData, 'tags');
        if (!empty($tags)) {
            $tagArray = array_map('trim', is_array($tags) ? $tags : explode(',', $tags));
            $this->addTags($group, $tagArray);
        }

        //interests
        if (Arr::has($requestData, 'interests')) {
            $interests = Arr::get($requestData, 'interests');
            $group->interests()->sync($interests);
        }

        $this->joinUserToGroup($userId, $group);

        return $group;
    }

    /**
     * @inheritDoc
     */
    public function searchUserGroups(int $userId, string $keywords = null, array $options = []): LengthAwarePaginator
    {
        $filterType = Arr::get($options, 'type') ?: 'all';

        $options['custom_query'] = function (Builder $builder) use ($filterType, $userId) {
            if ($filterType === 'my-groups') {
                $builder->byUser($userId);
                // ->published();
            } else if ($filterType === 'joined') {
                $builder->memberUser($userId)->published();
            } else {
                $builder->where(function (Builder $builder) use ($userId) {
                    $builder
                        ->where(function (Builder $builder) use ($userId) {
                            $builder->byUser($userId);
                        })
                        ->orWhere(function (Builder $builder) use ($userId) {
                            $builder->memberUser($userId);
                        });
                })->published();
            }
        };

        #$options['include_unpublished'] = true;

        return $this->search($keywords, $options);
    }

    /**
     * @inheritDoc
     */
    public function search(string $keywords = null, array $options = []): LengthAwarePaginator
    {
        $query = $this->query();
        $perPage = $this->getSearchParameter('perPage', $options);

        $query
            ->scopes([
                'search' => $keywords,
                'category' => Arr::get($options, 'category_id'),
                'type' => Arr::get($options, 'type_id'),
            ]);

        $trimmedKeyword = trim($keywords);
        // Display first the exact group name match
        $query->orderByRaw("IF(name = '{$trimmedKeyword}', 1, 0) DESC");

        if (Arr::has($options, 'created_at')) {
            $query->whereDate("created_at", ">=", Arr::get($options, 'created_at'));
        }

        $excludeByOwnerIds = $this->getSearchParameter('exclude_by_owner', $options);
        if ($excludeByOwnerIds) {
            $query->whereNotIn('user_id', Arr::wrap($excludeByOwnerIds));
        }

        $this->applyCommonSearchParameters($query, $options);

        if (Arr::has($options, 'include_unpublished')) {
            if(!Arr::get($options, 'include_unpublished')) {
                $query->published();
            }
        }else{
            $query->published();
        }

        return $query->paginate($perPage);
    }

    /**
     * Get search parameter.
     *
     * @param string $key
     * @param array $options
     * @param null $default
     * @return mixed
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    protected function getSearchParameter(string $key, array $options = [], $default = null)
    {
        switch ($key) {
            case 'perPage':
                return (int)($options[$key] ?? ($default ?: 20));
            case 'relations':
                return $options[$key] ?? ($default ?: []);
            default:
                return Arr::get($options, $key, $default);
        }
    }

    /**
     * @param Builder|QueryBuilder $query
     * @param array $options
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function applyCommonSearchParameters($query, array $options = [])
    {
        $relations = $this->getSearchParameter('relations', $options);
        $query->with($relations);

        // Sorting
        if ($sortBy = Arr::get($options, 'sortBy')) {
            $sort = Arr::get($options, 'sort', 'ASC');
            $query->orderBy($sortBy, $sort);
        } else {
            $query->latest('updated_at');
        }

        // apply custom query
        if (is_callable($callable = (Arr::get($options, 'custom_query')))) {
            $callable($query);
        }
    }

    /**
     * @return Collection
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function getTypeOptions(): Collection
    {
        return Type::query()
            ->select([
                'id',
                'name',
            ])
            ->where('group_enable', 1)
            ->orderBy('name')
            ->enabled()
            ->get();
    }

    /**
     * @return Collection
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function getCategoryOptions(): Collection
    {
        return Category::query()
            ->select([
                'id',
                'name',
            ])
            ->where('group_enable', 1)
            ->orderBy('name')
            ->enabled()
            ->get();
    }

    /**
     * @inheritDoc
     */
    public function getRelatedGroups(Group $group, int $perPage = 6): LengthAwarePaginator
    {
        $query = $this->query();

        $query
            ->where('id', '!=', $group->id) // exclude self
            ->where(function (Builder $builder) use ($group) {
                $builder
                    ->where('type_id', $group->type_id)
                    ->orWhere('category_id', $group->category_id)
                    ->orWhereHas('tags', function (Builder $builder) use ($group) {
                        $builder->where('tags.id', $group->getRelationValue('tags')->pluck('id')->toArray());
                    });
            });

        // If above doesn't have results, we'll fall back to recently updated groups.
        if ($query->count() === 0) {
            $query
                ->newQuery()
                ->latest('updated_at');
        } else {
            $query->latest('updated_at');
        }

        $query->withAll();

        return $query->paginate($perPage);
    }

    /**
     * @inheritDoc
     */
    public function changePublishStatusGroup(Group $group, string $action = 'toggle'): Group
    {
        if ($action === 'publish' && empty($group->published_at)) {
            $group->published_at = now();
        } elseif ($action === 'unpublish' && $group->published_at) {
            $group->published_at = null;
        } elseif ($action === 'toggle') {
            $group->published_at = empty($group->published_at) ? now() : null;
        }
        $group->save();

        return $group;
    }

    /**
     * @inheritDoc
     */
    public function searchGroupMembers(Group $group, string $keywords = null, array $options = []): LengthAwarePaginator
    {
        $perPage = $this->getSearchParameter('perPage', $options);

        $query = $group->members()
            ->searchText(Arr::wrap($keywords))
            ->searchSmoking($this->getSearchParameter('smoking', $options))
            ->searchHasChildren($this->getSearchParameter('children', $options))
            ->searchInfluencer($this->getSearchParameter('influencer', $options))
            ->orWhereIn('users.id', $group->admins()->select('users.id'))
            ->addSelect(DB::raw("{$group->id} AS group_id"))
            ->groupBy('users.id');

        if ($value = $this->getSearchParameter('gender', $options)) {
            $query->searchGender($value);
        }
        if ($value = $this->getSearchParameter('drinking', $options)) {
            $query->searchDrinking($value);
        }
        if ($value = $this->getSearchParameter('income_level', $options)) {
            $query->searchIncomeLevel($value);
        }
        if ($value = $this->getSearchParameter('ethnicity', $options)) {
            $query->searchEthnicity($value);
        }
        if ($value = $this->getSearchParameter('zodiac_sign', $options)) {
            $query->searchZodiacSign($value);
        }
        if ($value = $this->getSearchParameter('body_type', $options)) {
            $query->searchBodyType($value);
        }
        if ($value = $this->getSearchParameter('education_level', $options)) {
            $query->searchRelationshipStatus($value);
        }
        if (
            ($fromValue = $this->getSearchParameter('height_from', $options))
            && ($toValue = $this->getSearchParameter('height_to', $options))
        ) {
            $query->searchHeight($fromValue, $toValue);
        }
        if (
            ($fromValue = $this->getSearchParameter('age_from', $options))
            && ($toValue = $this->getSearchParameter('age_to', $options))
        ) {
            $query->searchAge($fromValue, $toValue);
        }
        if (
            ($distance = $this->getSearchParameter('distance', $options))
            && ($latitude = $this->getSearchParameter('lat', $options))
            && ($longitude = $this->getSearchParameter('lng', $options))
        ) {
            $query->searchDistance($latitude, $longitude, $distance);
        }
        if ($value = $this->getSearchParameter('influencer', $options)) {
            $accountType = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? AccountType::PREMIUM : AccountType::NO_SELECTION;
            $query->searchInfluencer($accountType);
        }
        if (
            ($birthMonth = $this->getSearchParameter('month', $options))
            || ($birthDate = $this->getSearchParameter('day', $options))
        ) {
            $query->searchBirthday($birthMonth, $birthDate ?? null);
        }

        return $query->paginate($perPage);
    }

    /**
     * @inheritDoc
     */
    public function getNonMemberUsers(Group $group, string $keyword = null, array $options = [])
    {
        $perPage = $this->getSearchParameter('perPage', $options);
        $excludeUsers = $this->getSearchParameter('exclude_users', $options, []);

        $query = User::query()
            ->whereDoesntHave('groups', function (Builder $builder) use ($group) {
                // exclude already members
                $builder->where('groups.id', $group->id);
            })
            ->whereNotIn('id', function ($query) use ($group) {
                // exclude invited users
                $query
                    ->select('user_id')
                    ->from('group_member_invites')
                    ->where('group_id', $group->id);
            })
            ->orderBy('first_name')
            ->searchByPartialName($keyword);

        if (!empty($excludeUsers)) {
            $query->whereNotIn('id', $excludeUsers);
        }

        return $perPage === -1 ? $query->get() : $query->paginate($perPage);
    }

    /**
     * @param Group $group
     * @param array $userIds
     * @return object
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function generateGroupMemberInvites(Group $group, array $userIds): object
    {
        $groupInvites = [
            'new' => [],
            'existing' => [],
            'members' => [],
        ];

        $alreadyMembers = $group->members()->whereIn('user_id', $userIds)->get();
        $existingInviteModels = $group->memberInvites()->whereIn('user_id', $userIds)->get();
        $newUserInvites = collect($userIds)
            ->filter(function (int $userId) use ($alreadyMembers, $existingInviteModels) {
                return !$existingInviteModels->pluck('user_id')->contains($userId)
                    && !$alreadyMembers->pluck('id')->contains($userId);
            });

        $groupInvites['new'] = $newUserInvites->map(function (int $userId) use ($group) {
            return $this->inviteMemberToGroup($group, $userId);
        })->values();
        $groupInvites['existing'] = $existingInviteModels->values();
        $groupInvites['members'] = $alreadyMembers->values();

        return (object)$groupInvites;
    }

    /**
     * @param Group $group
     * @param int $userId
     * @return GroupMemberInvite
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function inviteMemberToGroup(Group $group, int $userId): GroupMemberInvite
    {
        return GroupMemberInvite::create([
            'user_id' => $userId,
            'group_id' => $group->id,
            'status' => GroupMemberInviteStatus::PENDING,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getMemberInvites(Group $group, string $keywords = null, array $options = []): LengthAwarePaginator
    {
        $perPage = $this->getSearchParameter('perPage', $options);
        $query = $group->memberInvites();

        $this->applyCommonSearchParameters($query, $options);

        return $query->paginate($perPage);
    }

    /**
     * @param int $userId
     * @param Group $group
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function leaveUserFromGroup(int $userId, Group $group)
    {
        if ($group->members()->where('users.id', $userId)->exists()) {
            $group->members()->detach($userId);
            $group->total_members = $group->total_members - 1;
            $group->save();

            $member = User::query()->find($userId);

            // remove from receiving a message to all members
            Conversation::where('receiver_id', $userId)
                ->where('table_id', $group->id)
                ->where('table_lookup', TableLookUp::PERSONAL_MESSAGE_GROUPS)
                ->delete();

            event(new UserLeftGroup($group, $member));
        }

        dispatch(new CalculateGroupTotalMembers($group));
    }

    /**
     * @inheritDoc
     */
    public function userAcceptGroupInvite(int $groupMemberInviteId, bool $deleteInvite = false): GroupMemberInvite
    {
        $groupMemberInvite = GroupMemberInvite::query()->findOrFail($groupMemberInviteId);
        $this->joinUserToGroup($groupMemberInvite->user_id, $groupMemberInvite->group);

        if ($deleteInvite) {
            $groupMemberInvite->delete();
        } else {
            $groupMemberInvite->update([
                'status' => GroupMemberInviteStatus::ACCEPTED,
            ]);
        }

        return $groupMemberInvite;
    }

    /**
     * @param int $userId
     * @param Group $group
     *
     * @return bool
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function joinUserToGroup(int $userId, Group $group): bool
    {
        if (!$group->members()->where('users.id', $userId)->exists()) {
            $group->members()->attach($userId);
            // Delete invite if exists
            $group->memberInvites()->where('user_id', $userId)->delete();
            $group->total_members = $group->total_members + 1;
            $group->save();

            $member = User::query()->find($userId);
            event(new UserJoinedGroup($group, $member));

            return true;
        }

        dispatch(new CalculateGroupTotalMembers($group));

        return false;
    }

    /**
     * @param Group $group
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function updateGroupMembers(Group $group)
    {
        $group->total_members = $group->members()->count();
        $group->save();
    }

    /**
     * @inheritDoc
     */
    public function userRejectGroupInvite(int $groupMemberInviteId, bool $deleteInvite = false): GroupMemberInvite
    {
        $groupMemberInvite = GroupMemberInvite::query()->findOrFail($groupMemberInviteId);

        if ($deleteInvite) {
            $groupMemberInvite->delete();
        } else {
            $groupMemberInvite->update([
                'status' => GroupMemberInviteStatus::DECLINED,
            ]);
        }

        return $groupMemberInvite;
    }

    /**
     * @inheritDoc
     */
    public function getUserGroupPendingInvites(int $userId, array $options = []): LengthAwarePaginator
    {
        $perPage = $this->getSearchParameter('perPage', $options);
        return GroupMemberInvite::query()
            ->where('user_id', $userId)
            ->has('group')
            ->with(['user', 'group.user'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @inheritDoc
     */
    public function getUserGroupStatistics(int $userId): array
    {
        $groups = $this->query()
            ->where('user_id', $userId)
            ->get();

        $membersPerGroup = $groups->map(function (Group $group) {
            $invites = $group->memberInvites;
            return [
                'id' => $group->id,
                'new_members' => $group->members()->wherePivot('created_at', '>=', now()->subDay())->count(),
                'total_members' => $group->total_members,
                'new_invites' => $invites->where('created_at', '>=', now()->subDay())->count(),
                'total_invites' => $invites->count(),
            ];
        })->toArray();

        return [
            'groups' => [
                'total' => $groups->count(),
                'recently_created' => $groups->where('create_at', '>=', now()->subDay())->count(),
                'last_updated' => $groups->where('updated_at', '>=', now()->subDay())->count(),
                'published' => $groups->where('published_at', '!=', null)->count(),
            ],
            'members' => [
                'total' => $groups->sum(function (Group $group) {
                    return $group->total_members;
                }),
                'membersPerGroup' => $membersPerGroup,
            ]
        ];
    }

    /**
     * @param int $userId
     * @return LengthAwarePaginator
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function getUserUnreadNotifications(int $userId): LengthAwarePaginator
    {
        return DatabaseNotification::query()
            ->whereType(GroupNewMemberNotification::class)
            ->unread()
            ->whereHas('notifiable', function (Builder $builder) use ($userId) {
                $builder->where('id', $userId);
            })
            ->paginate();
    }
}
