<?php

namespace App\Http\Controllers;

use App\Models\InfraTemplate;
use App\Models\ServiceCatalogItem;
use App\Services\CatalogDeployer;
use Illuminate\Http\RedirectResponse;

class CatalogController extends Controller
{
    public function deployService(ServiceCatalogItem $service, CatalogDeployer $deployer): RedirectResponse
    {
        $deployer->deployCatalog($service);

        return back()->with('success', $this->message($service->name, $service->fresh()->meta['dokploy_ref'] ?? null));
    }

    public function deployTemplate(InfraTemplate $template, CatalogDeployer $deployer): RedirectResponse
    {
        $deployer->deployTemplate($template);

        return back()->with('success', $this->message($template->name, $template->fresh()->dokploy_ref));
    }

    private function message(string $name, mixed $ref): string
    {
        if (is_string($ref) && str_starts_with($ref, 'local_')) {
            return "{$name} was recorded locally. Connect Dokploy to create it there.";
        }

        return "{$name} was sent to Dokploy.";
    }
}
