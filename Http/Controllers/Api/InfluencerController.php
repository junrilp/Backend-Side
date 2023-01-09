<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\UserSearchResource2 as UserSearchResource;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use App\Models\User;
use App\Enums\UserStatus;

class InfluencerController extends Controller
{
    use ApiResponser;

    private $influencer;
    private $request;
    private $limit;

    public function __construct(Request $request)
    {
        $this->influencer = User::query(); // Can be chained for new condition
        $this->request    = $request;
        $this->limit      = 20; // default value
    }

    /**
     * Get top influencer
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function getTopInfluencer() {

        $this->influencer
            ->searchInfluencer(UserStatus::PUBLISHED)
            ->newestMember()
            ->with('primaryPhoto')
            ->limit($this->limit)
            ->where('hp_influencer', 1);

        // Start the search condition
        $this->addSearchQueries();

        return $this->successResponse(UserSearchResource::collection($this->influencer->get()));
    }

    /**
     * Automatically call the function base on the url parameter name
     *
     * @return function
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function addSearchQueries(){
        foreach($this->request->query as $name => $value){
            if (method_exists($this, $name)){
                call_user_func_array([$this, $name], [$value]);
            }
        }
    }

    /**
     * Will check if default limit need to override
     *
     * @return collection
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function withLimit($value){
        $this->influencer->limit($value);
    }

    /**
     * Will check if 'withInterest' parameter is included in the request
     * Include the interests - interest eloquent relationship
     *
     * @return collection
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function withInterest(){
        $this->influencer->with('interests');
    }
}
