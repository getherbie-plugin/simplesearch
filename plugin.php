<?php

use herbie\Config;
use herbie\Environment;
use herbie\PageItem;
use herbie\Plugin;
use herbie\PageRepositoryInterface;
use herbie\TwigRenderer;
use Psr\Http\Message\ServerRequestInterface;
use Twig\TwigFunction;

class GetherbiePluginSimplesearch extends Plugin
{
    private Config $config;
    private Environment $environment;
    private PageRepositoryInterface $pageRepository;
    private ServerRequestInterface $request;
    private TwigRenderer $twigRenderer;

    public function __construct(
        Config $config,
        Environment $environment,
        PageRepositoryInterface $pageRepository,
        ServerRequestInterface $request,
        TwigRenderer $twigRenderer
    ) {
        $this->config = $config;
        $this->environment = $environment;
        $this->pageRepository = $pageRepository;
        $this->request = $request;
        $this->twigRenderer = $twigRenderer;
    }

    public function twigFunctions(): array
    {
        return [
            new TwigFunction('simplesearch_results', [$this, 'results'], ['is_safe' => ['html']]),
            new TwigFunction('simplesearch_form', [$this, 'form'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function form(): string
    {
        $name = $this->config->get('plugins.simplesearch.formTemplate', function () {
            return $this->getComposerOrLocalTemplatesPath('form.twig');
        });

        $action = $this->environment->getPathInfo();
        $queryParams = $this->request->getQueryParams();
        return $this->twigRenderer->renderTemplate($name, [
            'action' => $action,
            'query' => $queryParams['query'] ?? '',
        ]);
    }

    /**
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function results(): string
    {
        $queryParams = $this->request->getQueryParams();
        $query = $queryParams['query'] ?? '';
        $results = $this->search($query);

        $name = $this->config->get('plugins.simplesearch.resultsTemplate', function () {
            return $this->getComposerOrLocalTemplatesPath('results.twig');
        });

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

        // TODO load and return cached page content
        return [];
    }

    /**
     * @param string $query
     * @return array
     * @throws Exception
     */
    private function search(string $query): array
    {
        if (empty($query)) {
            return [];
        }

        $i = 1;
        $max = 100;
        $results = [];

        $usePageCache = $this->config->get('plugins.simplesearch.usePageCache', false);

        $appendIterator = new \AppendIterator();
        $appendIterator->append($this->pageRepository->findAll()->getIterator());

        foreach ($appendIterator as $item) {
            if (($i > $max) || empty($item->title) || !empty($item->no_search)) {
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

    private function getComposerOrLocalTemplatesPath(string $name): string
    {
        $composerPath = '@vendor/getherbie/plugin-simplesearch/templates/' . $name;
        $localPath = '@plugin/plugin-simplesearch/templates/' . $name;
        return $this->twigRenderer->getTwigEnvironment()->getLoader()->exists($composerPath)
            ? $composerPath
            : $localPath;
    }
}
