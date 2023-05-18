<?php namespace Visiosoft\AdvsModule\Http\Middleware;

use Anomaly\Streams\Platform\Application\Application;
use Anomaly\Streams\Platform\View\ViewTemplate;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Anomaly\PagesModule\Page\Contract\PageRepositoryInterface;
use Illuminate\Support\Facades\App;

/**
 * Class Pages
 */
class Pages
{
    protected $pages;
    protected $template;

    public function __construct(
        PageRepositoryInterface            $pages,
        \Illuminate\Foundation\Application $application,
        Repository                         $config,
        ViewTemplate                       $template
    )
    {
        $this->app = $application;
        $this->pages = $pages;
        $this->config = $config;
        $this->template = $template;
    }

    public function handle(Request $request, Closure $next)
    {
        if ($locale = $request->session()->get('_locale')) {
            $this->config->set('app.locale', $locale);
            $this->app->setLocale($locale);
        }

        $page = $this->pages->findByPath($request->getPathInfo());
        if (is_null($page)) {
            return $next($request);
        }
        $type = $page->getType();
        $handler = $type->getHandler();

        $this->template->set('page', $page);

        $handler->make($page);
        return $page->getResponse();
    }
}
