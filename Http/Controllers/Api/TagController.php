<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\Tag;
use App\Traits\ApiResponser;
use Illuminate\Http\Response;
use App\Http\Requests\TagRequest;
use App\Http\Resources\TagResource;
use App\Http\Controllers\Controller;
use App\Repository\Tag\TagRepository;

class TagController extends Controller
{
    use ApiResponser;

    private $tag;

    public function __construct(TagRepository $tag)
    {
        $this->tag = $tag;
    }

    /**
     * This will return all the tag
     * @return Array
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function index()
    {
        try {
            return $this->successResponse(TagResource::collection(Tag::all()));
        } catch (Exception $e) {
            return $this->errorResponse('Unable to retrieve tags.', Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Submit tag request to our repository tag
     * @param TagRequest $request
     * @return Object $data
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function store(TagRequest $request)
    {

        $data = $this->tag->postTag($request->label);

        if (!$data) {
            return $this->errorResponse('Tag already exist.', Response::HTTP_CONFLICT);
        }

        return $this->successResponse(new TagResource($data), null, Response::HTTP_CREATED);
    }

    /**
     * Submit tag request to our repository tag for updating tags request
     * @param TagRequest $request
     * @return Array $data
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function update(TagRequest $request, $id)
    {
        
        $data = $this->tag->updateTag($request->label, $id);

        if (!$data) {
            return $this->errorResponse('Tag already exist.', Response::HTTP_CONFLICT);
        }

        return $this->successResponse(new TagResource($data));
    }

    /**
     * Delete tags
     * @param mixed $id
     * @return Array $data
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function destroy($id)
    {
        $data = $this->tag->removeTag($id);

        if (!$data) {
            return $this->errorResponse('No record found.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(new TagResource($data));
    }
}
