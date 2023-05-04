<?php namespace Visiosoft\AdvsModule\Http\Middleware;

use Anomaly\Streams\Platform\View\ViewTemplate;
use Closure;
use Illuminate\Http\Request;
use Anomaly\PagesModule\Page\Contract\PageRepositoryInterface;

/**
 * Class Pages
 */
class Pages
{
    protected $pages;
    protected $template;
    public function __construct(
        PageRepositoryInterface $pages,
        ViewTemplate $template
    )
    {
        $this->pages = $pages;
        $this->template = $template;
    }

    public function handle(Request $request, Closure $next)
    {
            $page = $this->pages->findByPath($request->getPathInfo());
            if (is_null($page)){
                return $next($request);
            }
            $type    = $page->getType();
            $handler = $type->getHandler();

            $this->template->set('page', $page);

            $handler->make($page);
            return $page->getResponse();
    }
}
