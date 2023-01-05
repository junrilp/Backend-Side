<?php

namespace App\Http\Middleware;

use App\Helpers\RandomContentReplacer;
use App\Traits\ConcealUserMessage;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ConcealMessageContents
{
    use ConcealUserMessage;
    
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        /** @var JsonResponse $response */
        $response = $next($request);
        
        if(!$this->hasAuthorized() && $response->getStatusCode() / 10 * 10 === 200) {
            $response->setData($this->concealMessageData($response->getData(true)));
        }
        
        return $response;
    }

    /**
     * @return bool
     */
    private function hasAuthorized(): bool
    {
        return auth()->check() && auth()->user()->canAccessMessaging();        
    }
}
