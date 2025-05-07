# Herbie Simplesearch Plugin

Simplesearch is a [Herbie](http://github.com/getherbie) plugin that allows you to provide your website with a simple but useful search engine.

## Installation

The plugin is installed with Composer.

	$ composer require getherbie/plugin-simplesearch

After that, the plugin can be activated in the configuration file.

## Configuration

The following options are available in the configuration file.

~~~php
return [
    // a comma-separated list of plugins to be activated
    'enabledPlugins' => 'simplesearch',
    'plugins' => [        
        'simplesearch' => [
            'config' => [
                // path to twig form template
                'formTemplate' => '@plugin/simplesearch/templates/form.twig',
                // path to twig results template
                'resultsTemplate' => '@plugin/simplesearch/templates/results.twig',
                // use page cahe if global caching is enabled
                'usePageCache' => false,
            ],
        ],
    ],
];
~~~

Please note that the above configuration is only part of the overall configuration.

## Page properties

With the page property `no_search` individual pages can be excluded from the search.
The page properties for such a page look like this:

    ---
    title: My page
    no_search: true
    ---

A complete search page looks like this:

    ---
    title: Search
    cached: false
    hidden: true
    ---
    
    # Search
    
    {{ simplesearch_form() }}
    
    {{ simplesearch_results() }}

## Demo

A live demo can be viewed at <https://herbie.tebe.ch/search>
