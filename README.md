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

Du kannst einzelne URL's von der Suche ausschließen.

Ergänze dazu die Konfigurationsdatei:

    config:
        simplesearch:
            excluded_urls: ['@plugin/adminpanel/pages/adminpanel.html', '@plugin/simplesearch/pages/suche.html' ]

## Demo

<http://getherbie.org/suche?query=herbie>
