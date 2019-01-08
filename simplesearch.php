<?php

namespace herbie\plugin\simplesearch;

use Herbie\Config;
use Herbie\Environment;
use Herbie\Menu\MenuItem;
use Herbie\Menu\MenuList;
use herbie\plugin\shortcode\classes\Shortcode;
use Herbie\PluginInterface;
use Herbie\Repository\PageRepositoryInterface;
use Herbie\TwigRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\EventManager\EventInterface;
use Zend\EventManager\EventManagerInterface;

class SimplesearchPlugin implements PluginInterface, MiddlewareInterface
{
    private $config;
    private $environment;
    private $menuList;
    private $pageRepository;
    private $twigRenderer;
    private $events;
    private $request;

    /**
     * SimplesearchPlugin constructor.
     * @param Config $config
     * @param Environment $environment
     * @param MenuList $menuList
     * @param PageRepositoryInterface $pageRepository
     * @param TwigRenderer $twigRenderer
     */
    public function __construct(
        Config $config,
        Environment $environment,
        MenuList $menuList,
        PageRepositoryInterface $pageRepository,
        TwigRenderer $twigRenderer
    ) {
        $this->config = $config;
        $this->environment = $environment;
        $this->pageRepository = $pageRepository;
        $this->twigRenderer = $twigRenderer;
        $this->menuList = $menuList;
    }
    
    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->request = $request;
        return $handler->handle($request);
    }

    /**
     * @return array
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->events = $events;
        if ((bool)$this->config->get('plugins.config.simplesearch.twig', false)) {
            $events->attach('onTwigInitialized', [$this, 'onTwigInitialized'], $priority);
        }
        if ((bool)$this->config->get('plugins.config.simplesearch.shortcode', true)) {
            $events->attach('onShortcodeInitialized', [$this, 'onShortcodeInitialized'], $priority);
        }
        $events->attach('onPluginsInitialized', [$this, 'onPluginsInitialized'], $priority);
    }

    /**
     * @param EventInterface $event
     */
    public function onTwigInitialized(EventInterface $event)
    {
        /** @var TwigRenderer $twig */
        $twig = $event->getTarget();
        $twig->addFunction(
            new \Twig_SimpleFunction('simplesearch_results', [$this, 'results'], ['is_safe' => ['html']])
        );
        $twig->addFunction(
            new \Twig_SimpleFunction('simplesearch_form', [$this, 'form'], ['is_safe' => ['html']])
        );
    }

    /**
     * @param EventInterface $event
     */
    public function onShortcodeInitialized(EventInterface $event)
    {
        /** @var Shortcode $shortcode */
        $shortcode = $event->getTarget();
        $shortcode->add('simplesearch_form', [$this, 'form']);
        $shortcode->add('simplesearch_results', [$this, 'results']);
    }

    /**
     * @param EventInterface $event
     */
    public function onPluginsInitialized(EventInterface $event)
    {
        if ($this->config->isEmpty('plugins.config.simplesearch.no_page')) {
            $this->config->push('pages.extra_paths', '@plugin/simplesearch/pages');
        }
    }

    /**
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function form(): string
    {
        $name = $this->config->get(
            'plugins.config.simplesearch.template.form',
            '@plugin/simplesearch/templates/form.twig'
        );
        $action = $this->environment->getPathInfo();
        $queryParams = $this->request->getQueryParams();
        return $this->twigRenderer->renderTemplate($name, [
            'action' => $action,
            'query' => $queryParams['query'] ?? '',
        ]);
    }

    /**
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function results(): string
    {
        $queryParams = $this->request->getQueryParams();
        $query = $queryParams['query'] ?? '';
        $results = $this->search($query);
        $name = $this->config->get(
            'plugins.config.simplesearch.template.results',
            '@plugin/simplesearch/templates/results.twig'
        );
        return $this->twigRenderer->renderTemplate($name, [
            'query' => $query,
            'results' => $results,
            'submitted' => isset($query)
        ]);
    }

    /**
     * @param MenuItem $item
     * @param bool $usePageCache
     * @return array
     */
    private function loadPageData(MenuItem $item, bool $usePageCache): array
    {
        if (!$usePageCache) {
            $page = $this->pageRepository->find($item->path);
            $title = $page->getTitle();
            $content = implode('', $page->getSegments());
            return [$title, $content];
        }

        // @see Herbie\Application::renderPage()
        $cacheId = 'page-' . $item->route;
        $content = $this->pageCache->get($cacheId);
        if ($content !== null) {
            return [strip_tags($content)];
        }

        return [];
    }

    /**
     * @param string $query
     * @return array
     */
    private function search(string $query): array
    {
        if (empty($query)) {
            return [];
        }

        $i = 1;
        $max = 100;
        $results = [];

        $usePageCache = $this->config->get('cache.page.enable', false);
        $usePageCache &= $this->config->get('plugins.config.simplesearch.use_page_cache', false);

        $appendIterator = new \AppendIterator();
        $appendIterator->append($this->menuList->getIterator());

        foreach ($appendIterator as $item) {
            if ($i>$max || empty($item->title) || !empty($item->no_search)) {
                continue;
            }
            $data = $this->loadPageData($item, $usePageCache);
            if ($this->match($query, $data)) {
                $results[] = $item;
                $i++;
            }
        }

        return $results;
    }

    /**
     * @param string $query
     * @param array $data
     * @return bool
     */
    private function match(string $query, array $data): bool
    {
        foreach ($data as $part) {
            if (empty($part)) {
                continue;
            }
            if (stripos($part, $query) !== false) {
                return true;
            }
        }
        return false;
    }
}
