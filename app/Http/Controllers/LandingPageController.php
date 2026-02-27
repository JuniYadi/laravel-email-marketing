<?php

namespace App\Http\Controllers;

use App\Models\LandingPage;
use App\Support\LandingPages\LandingPageRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class LandingPageController extends Controller
{
    public function show(string $slug, LandingPageRenderer $renderer): View
    {
        $landingPage = LandingPage::query()
            ->where('slug', $slug)
            ->where('status', LandingPage::STATUS_PUBLISHED)
            ->firstOrFail();

        return $this->renderLandingPage($landingPage, $renderer);
    }

    public function showForHost(Request $request, LandingPageRenderer $renderer): View
    {
        $landingPage = LandingPage::query()
            ->where('custom_domain', strtolower($request->getHost()))
            ->where('status', LandingPage::STATUS_PUBLISHED)
            ->firstOrFail();

        return $this->renderLandingPage($landingPage, $renderer);
    }

    public function showForSubdomain(Request $request, string $subdomain, LandingPageRenderer $renderer): View
    {
        $landingPage = LandingPage::query()
            ->where('custom_domain', strtolower($request->getHost()))
            ->where('status', LandingPage::STATUS_PUBLISHED)
            ->firstOrFail();

        return $this->renderLandingPage($landingPage, $renderer);
    }

    protected function renderLandingPage(LandingPage $landingPage, LandingPageRenderer $renderer): View
    {
        $meta = array_replace([
            'title' => $landingPage->title,
            'description' => '',
            'og_title' => $landingPage->title,
            'og_description' => '',
            'og_image' => '',
            'noindex' => false,
        ], is_array($landingPage->meta) ? $landingPage->meta : []);

        $html = $renderer->render(
            is_array($landingPage->template_snapshot) ? $landingPage->template_snapshot : [],
            is_array($landingPage->form_data) ? $landingPage->form_data : [],
        );

        return view('landing-pages.public', [
            'landingPage' => $landingPage,
            'meta' => $meta,
            'html' => $html,
        ]);
    }
}
