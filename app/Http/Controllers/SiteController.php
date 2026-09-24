<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\SiteLifecycle;
use Illuminate\Http\RedirectResponse;

class SiteController extends Controller
{
    public function destroy(Site $site, SiteLifecycle $lifecycle): RedirectResponse
    {
        $domain = $site->domain;
        $lifecycle->deprovision($site);

        return redirect()
            ->route('sites.index')
            ->with('success', "{$domain} deprovisioned.");
    }
}
