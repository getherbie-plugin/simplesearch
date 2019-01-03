<?php

namespace herbie\plugin\simplesearch;

use Herbie\Config;
use Herbie\Menu\MenuItem;
use herbie\plugin\twig\classes\Twig;
use Zend\EventManager\EventInterface;
use Zend\EventManager\EventManagerInterface;

class SimplesearchPlugin extends \Herbie\Plugin
{
    /** @var Config */
    private $config;

    /**
     * @return array
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->config = $this->herbie->getConfig();
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
        /** @var Twig $twig */
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
     */
    public function form(): string
    {
        $template = $this->config->get(
            'plugins.config.simplesearch.template.form',
            '@plugin/simplesearch/templates/form.twig'
        );
        $queryParams = $this->herbie->getRequest()->getQueryParams();
        return $this->herbie->getTwig()->render($template, [
            'action' => 'suche',
            'query' => $queryParams['query'] ?? '',
        ]);
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function results(): string
    {
        $queryParams = $this->herbie->getRequest()->getQueryParams();
        $query = $queryParams['query'] ?? '';
        $results = $this->search($query);
        $template = $this->config->get(
            'plugins.config.simplesearch.template.results',
            '@plugin/simplesearch/templates/results.twig'
        );
        return $this->herbie->getTwig()->render($template, [
            'query' => $query,
            'results' => $results,
            'submitted' => isset($query)
        ]);
    }

    /**
     * @param MenuItem $item
     * @param bool $usePageCache
     * @return array
     * @throws Exception
     * @throws \Exception
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function loadPageData(MenuItem $item, bool $usePageCache): array
    {
        if (!$usePageCache) {
            $page = $this->herbie->getPageRepository()->find($item->path);
            $title = $page->getTitle();
            $content = implode('', $page->getSegments());
            return [$title, $content];
        }

        // @see Herbie\Application::renderPage()
        $cacheId = 'page-' . $item->route;
        $content = $this->herbie->getPageCache()->get($cacheId);
        if ($content !== null) {
            return [strip_tags($content)];
        }

        return [];
    }

    /**
     * @param string $query
     * @return array
     * @throws Exception
     * @throws \Exception
     */
    protected function search(string $query): array
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
        $appendIterator->append($this->herbie->getMenuList()->getIterator());

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
    protected function match(string $query, array $data): bool
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
