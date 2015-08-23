<?php

use Herbie\DI;
use Herbie\Hook;
use Herbie\Menu;

class SimplesearchPlugin
{
    private $config;

    public function __construct()
    {
        $this->config = DI::get('Config');
    }

    /**
     * @return array
     */
    public function install()
    {
        if ((bool)$this->config->get('plugins.config.simplesearch.twig', false)) {
            Hook::attach('twigInitialized', [$this, 'addTwigFunctions']);
        }
        if ((bool)$this->config->get('plugins.config.simplesearch.shortcode', true)) {
            Hook::attach('shortcodeInitialized', [$this, 'addShortcodes']);
        }
        Hook::attach('pluginsInitialized', [$this, 'addPagePath']);
    }

    public function addTwigFunctions($twig)
    {
        $twig->addFunction(
            new \Twig_SimpleFunction('simplesearch_results', [$this, 'results'], ['is_safe' => ['html']])
        );
        $twig->addFunction(
            new \Twig_SimpleFunction('simplesearch_form', [$this, 'form'], ['is_safe' => ['html']])
        );
    }

    public function addShortcodes($shortcode)
    {
        $shortcode->add('simplesearch_form', [$this, 'form']);
        $shortcode->add('simplesearch_results', [$this, 'results']);
    }

    public function addPagePath()
    {
        if($this->config->isEmpty('plugins.config.simplesearch.no_page')) {
            $this->config->push('pages.extra_paths', '@plugin/simplesearch/pages');
        }
    }

    /**
     * @return string
     */
    public function form()
    {
        $template = $this->config->get(
            'plugins.config.simplesearch.template.form',
            '@plugin/simplesearch/templates/form.twig'
        );
        return DI::get('Twig')->render($template, [
            'action' => 'suche',
            'query' => DI::get('Request')->getQuery('query'),
        ]);
    }

    /**
     * @return string
     */
    public function results()
    {
        $query = DI::get('Request')->getQuery('query');
        $results = $this->search($query);
        $template = $this->config->get(
            'plugins.config.simplesearch.template.results',
            '@plugin/simplesearch/templates/results.twig'
        );
        return DI::get('Twig')->render($template, [
            'query' => $query,
            'results' => $results,
            'submitted' => isset($query)
        ]);
    }

    /**
     * @param Menu\ItemInterface $item
     * @param bool $usePageCache
     * @return array
     */
    protected function loadPageData(Menu\ItemInterface $item, $usePageCache)
    {
        if (!$usePageCache) {
            $page = DI::get('Loader\PageLoader')->load($item->path, false);
            $title = isset($page['data']['title']) ? $page['data']['title'] : '';
            $content = $page['segments'] ? implode('', $page['segments']) : '';
            return [$title, $content];
        }

        // @see Herbie\Application::renderPage()
        $cacheId = 'page-' . $item->route;
        $content = DI::get('Cache\PageCache')->get($cacheId);
        if ($content !== false) {
            return [strip_tags($content)];
        }

        return [];
    }

    /**
     * @param $query
     * @return array
     */
    protected function search($query)
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
        $appendIterator->append(DI::get('Menu\Page\Collection')->getIterator());
        $appendIterator->append(DI::get('Menu\Post\Collection')->getIterator());

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
    protected function match($query, array $data)
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

(new SimplesearchPlugin())->install();
