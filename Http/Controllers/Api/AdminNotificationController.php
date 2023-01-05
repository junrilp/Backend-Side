<?php

namespace App\Http\Controllers\Api;

use Throwable;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminNotificationResource;
use App\Http\Requests\StoreAdminNotificationRequest;
use App\Http\Requests\DeleteAdminNotificationRequest;
use App\Http\Requests\UpdateAdminNotificationRequest;
use App\Repository\Notification\NotificationRepository;

class AdminNotificationController extends Controller
{
    use ApiResponser;

    /**
     * @param Request $request
     * 
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->perPage ?? 15;
            $notif = NotificationRepository::getAdminNotification(authUser()->id, $perPage);
            $result = AdminNotificationResource::collection($notif);

            return $this->successResponse($result, null, Response::HTTP_OK, true);
        
        } catch (Throwable $exception) {
            Log::error($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param StoreAdminNotificationRequest $request
     * 
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function store(StoreAdminNotificationRequest $request)
    {
        try {
            $notif = NotificationRepository::saveOrUpdateCommunityGuidelines($request->all(), authUser()->id);
            
            $notif->load('author');
            $result = new AdminNotificationResource($notif);

            return $this->successResponse($result, null, Response::HTTP_OK);
        
        } catch (Throwable $exception) {
            Log::error($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param UpdateAdminNotificationRequest $request
     * @param mixed $id
     * 
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function update(UpdateAdminNotificationRequest $request, $id)
    {
        try {
            $notif = NotificationRepository::saveOrUpdateCommunityGuidelines($request->all(), authUser()->id, $id);
            
            $notif->load('author');
            $result = new AdminNotificationResource($notif);
            
            return $this->successResponse($result, null, Response::HTTP_OK);
        
        } catch (Throwable $exception) {
            Log::error($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param DeleteAdminNotificationRequest $request
     * @param mixed $id
     * 
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function destroy(DeleteAdminNotificationRequest $request, $id)
    {
        try {
            NotificationRepository::removeAdminNotification(authUser()->id, $id);
            
            return $this->successResponse('Successfully Remove');
        
        } catch (Throwable $exception) {
            Log::error($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param Request $request
     * @param mixed $id
     * 
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function viewNotification(Request $request, $id)
    {
        try {
            NotificationRepository::viewNotification($request->all(), $id, authUser()->id);
            
            return $this->successResponse('Successfully Remove');
        
        } catch (Throwable $exception) {
            Log::error($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
