<?php

namespace herbie\plugins\simplesearch;

use herbie\Configuration;
use herbie\Environment;
use herbie\Event;
use herbie\PageItem;
use herbie\Plugin;
use herbie\PageRepositoryInterface;
use herbie\TwigRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SimplesearchPlugin extends Plugin implements MiddlewareInterface
{
    private $config;
    private $environment;
    private $pageRepository;
    private $twigRenderer;
    private $request;

    /**
     * SimplesearchPlugin constructor.
     * @param Configuration $config
     * @param Environment $environment
     * @param PageRepositoryInterface $pageRepository
     * @param TwigRenderer $twigRenderer
     */
    public function __construct(
        Configuration $config,
        Environment $environment,
        PageRepositoryInterface $pageRepository,
        TwigRenderer $twigRenderer
    ) {
        $this->config = $config;
        $this->environment = $environment;
        $this->pageRepository = $pageRepository;
        $this->twigRenderer = $twigRenderer;
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

    public function events(): array
    {
        return [
            ['onTwigInitialized', [$this, 'onTwigInitialized']]
        ];
    }

    /**
     * @param Event $event
     */
    public function onTwigInitialized(Event $event)
    {
        /** @var TwigRenderer $twig */
        $twig = $event->getTarget();
        $twig->addFunction(
            new \TwigFunction('simplesearch_results', [$this, 'results'], ['is_safe' => ['html']])
        );
        $twig->addFunction(
            new \TwigFunction('simplesearch_form', [$this, 'form'], ['is_safe' => ['html']])
        );
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
            'plugins.config.simplesearch.formTemplate',
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
            'plugins.config.simplesearch.resultsTemplate',
            '@plugin/simplesearch/templates/results.twig'
        );
        return $this->twigRenderer->renderTemplate($name, [
            'query' => $query,
            'results' => $results,
            'submitted' => isset($query)
        ]);
    }

    /**
     * @param PageItem $item
     * @param bool $usePageCache
     * @return array
     */
    private function loadPageData(PageItem $item, bool $usePageCache): array
    {
        if (!$usePageCache) {
            $page = $this->pageRepository->find($item->path);
            $title = $page->getTitle();
            $content = implode('', $page->getSegments());
            return [$title, $content];
        }

        // @see herbie\Application::renderPage()
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

        $usePageCache = $this->config->get('plugins.config.simplesearch.usePageCache', false);

        $appendIterator = new \AppendIterator();
        $appendIterator->append($this->pageRepository->findAll()->getIterator());

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
