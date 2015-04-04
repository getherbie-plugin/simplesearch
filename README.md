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

Du kannst einzelne Seiten mit der Seiteneigenschaft "no_search: 1" von der Suche ausschließen.

## Demo

<http://getherbie.org/suche?query=herbie>
