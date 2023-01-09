<?php

namespace App\Repository\Group;

use App\Models\Group;
use App\Models\GroupMemberInvite;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

interface GroupInterface
{
    /**
     * @return Builder
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function query(): Builder;

    /**
     * @param string|null $keywords
     * @param array $options
     * @return LengthAwarePaginator
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function search(string $keywords = null, array $options = []): LengthAwarePaginator;

    /**
     * @param array $requestData
     * @param int $userId
     * @return Group
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function create(array $requestData, int $userId): Group;

    /**
     * @param int $userId
     * @param string|null $keywords
     * @param array $options
     * @return LengthAwarePaginator
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function searchUserGroups(int $userId, string $keywords = null, array $options = []): LengthAwarePaginator;

    /**
     * @param Group $group
     * @param int $perPage
     * @return LengthAwarePaginator
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function getRelatedGroups(Group $group, int $perPage = 6): LengthAwarePaginator;

    /**
     * Published or Unpublished group
     * @param Group $group
     * @param string $action
     * @return Group
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function changePublishStatusGroup(Group $group, string $action = 'toggle'): Group;

    /**
     * Find group members
     * @param Group $group
     * @param $keywords
     * @param array $options
     * @return LengthAwarePaginator
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function searchGroupMembers(Group $group, string $keywords = null, array $options = []): LengthAwarePaginator;

    /**
     * @param Group $group
     * @param string|null $keywords
     * @param array $options
     * @return mixed
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function getNonMemberUsers(Group $group, string $keywords = null, array $options = []);

    /**
     * @param Group $group
     * @param string|null $keywords
     * @param array $options
     * @return LengthAwarePaginator
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function getMemberInvites(Group $group, string $keywords = null, array $options = []): LengthAwarePaginator;

    /**
     * @param int $groupMemberInviteId
     * @param bool $deleteInvite
     * @return GroupMemberInvite
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function userAcceptGroupInvite(int $groupMemberInviteId, bool $deleteInvite = false): GroupMemberInvite;

    /**
     * @param int $groupMemberInviteId
     * @param bool $deleteInvite
     * @return GroupMemberInvite
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function userRejectGroupInvite(int $groupMemberInviteId, bool $deleteInvite = false): GroupMemberInvite;

    /**
     * @param int $userId
     * @return LengthAwarePaginator
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function getUserGroupPendingInvites(int $userId): LengthAwarePaginator;

    /**
     * Get user's groups statistics
     * - Recently created are categories within 24hrs range
     * - Last updated are also categories within 24hrs range 
     * - New members are also categories within 24hrs range when they joined the group
     * 
     * Note: For simplicity, query has been use instead of computed, avoid calling this method frequently.
     * @param int $userId
     * @return array
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function getUserGroupStatistics(int $userId): array;
}
