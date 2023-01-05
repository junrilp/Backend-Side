<?php

namespace App\Http\Controllers\Web\Page;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class AccountController extends Controller
{
    /**
     * Account premium upgrade page
     * @return \Inertia\Response
     */
    public function premiumUpgrade()
    {
        return Inertia::render('Account/PremiumUpgrade', ['title' => '- Premium Upgrade']);
    }
}
