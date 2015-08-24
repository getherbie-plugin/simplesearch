# Herbie SimpleSearch Plugin

`SimpleSearch` ist ein [Herbie](http://github.com/getherbie/herbie) Plugin, mit dem du deine Website mit einer einfachen
aber leistungsvollen Suche ausstattest.

## Installation

Das Plugin installierst du via Composer.

	$ composer require getherbie/plugin-simplesearch

Danach aktivierst du das Plugin in der Konfigurationsdatei.

    plugins:
        enable:
            - simplesearch


## Konfiguration

Unter `plugins.config.simplesearch` stehen dir die folgenden Optionen zur Verfügung:

    # template paths to twig templates 
    template:
        form: @plugin/simplesearch/templates/form.twig
        results: @plugin/simplesearch/templates/results.twig

    # enable shortcode
    shortcode: true

    # enable twig function
    twig: false
    
    # if set no extra page will be included (you do it manually)
    no_page: false
    
    # use page cache (if cache.page.enable is set)
    use_page_cache: false
    

## Seiteneigenschaften

Mit der Seiteneigenschaft `no_search` kannst du einzelne Seiten von der Suche ausschliessen. Die Seiteneigenschaften
für eine solche Seite sieht wie folgt aus:

    ---
    title: Titel
    no_search: true
    ---


## Demo

<http://getherbie.org/suche?query=herbie>
