<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EventBuilder
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $route = basename($request->path());

        // Check if name and location is present, before proceeding to description route
        if ($route === 'description' && ($request->event->setting === 1 && !$request->event->hasValidNameLocation)) {
            return redirect("/events/{$request->event->slug}/edit/name-location");
        }

        // Check if there's a description present, before proceeding to media route
        if ($route === 'media' && !$request->event->hasValidDescription) {
            return redirect("/events/{$request->event->slug}/edit/description");
        }

        // Check if there's a media uploaded, before proceeding to admin route or schedule
        if (collect(['admin','schedule'])->contains($route) && !$request->event->hasValidMedia) {
            return redirect("/events/{$request->event->slug}/edit/media");
        }

        // Check if there's a date and time before proceeding to review-and-publish route
        if ($route === 'review-and-publish' && !$request->event->hasValidSchedule) {
            return redirect("/events/{$request->event->slug}/edit/schedule");
        }

        return $next($request);
    }
}
