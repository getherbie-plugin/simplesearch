<?php

require_once 'plugin.php';

return [
    'apiVersion' => 2,
    'pluginName' => 'simplesearch',
    'pluginClass' => SimplesearchPlugin::class,
    'pluginPath' => __DIR__,
    'formTemplate' => null,
    'resultsTemplate' => null,
    'usePageCache' => false
];
