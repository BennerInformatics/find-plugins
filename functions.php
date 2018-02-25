<?php

namespace BennerInformatics;

function findPlugins(array $options = []) {
    $PLUGIN_CACHE = 'plugin-cache.json';
    $defaultOptions = [
        'keyword' => null,
        'vendorPath' => path_join(__DIR__, '../../'),
        'cache' => null,
        'sort' => true
    ];
    $ignoredFolders = ['bin'];
    $options = array_merge($defaultOptions, $options);

    if ($options['cache'] != null) {
        $cacheFile = path_resolve($options['cache'], $PLUGIN_CACHE);

        if (file_exists($cacheFile)) {
            $cachedPlugins = json_decode(file_get_contents($cacheFile), true);

            if ($cachedPlugins != null) {
                return $cachedPlugins;
            }
        }
    }

    if ($options['keyword'] == null) {
        $composer = json_decode(file_get_contents(path_join($options['vendorPath'], '..', 'composer.json')), true);
        $options['keyword'] = $composer['name'];
    }

    if (!file_exists($options['vendorPath'])) {
        throw new \InvalidArgumentException('The vendorPath directory does not exist');
    }

    $plugins = [];
    $vendor = new \DirectoryIterator($options['vendorPath']);

    foreach ($vendor as $org) {
        if ($org->isDot() || $org->isFile() ||
            ($org->isDir() && in_array($org->getBasename(), $ignoredFolders))) {
            continue;
        }

        $libraries = new \DirectoryIterator($org->getPathname());

        foreach ($libraries as $library) {
            if ($library->isDot() || $library->isFile()) {
                continue;
            }

            $libraryComposerFile = path_join($library->getPathname(), 'composer.json');

            if (!file_exists($libraryComposerFile)) {
                continue;
            }

            $composer = json_decode(file_get_contents($libraryComposerFile), true);

            if ($composer == null || !in_array($options['keyword'], $composer['keywords'] ?? [])) {
                continue;
            }

            $plugins[$composer['name']] = ['path' => $library->getPathname(), 'composer' => $composer];
        }
    }

    if ($options['sort']) {
        $sorter = new \MJS\TopSort\Implementations\StringSort();

        foreach ($plugins as $pluginName => $plugin) {
            $extra = $plugin['composer']['extra'] ?? [];
            $sorter->add($pluginName, $extra['after'] ?? []);
        }

        $sortedPlugins = $sorter->sort();
        $plugins = array_map(function ($name) use ($plugins) {
            return $plugins[$name];
        }, $sortedPlugins);
    } else {
        $plugins = array_values($plugins);
    }

    if ($options['cache'] != null) {
        if (!file_exists($options['cache'])) {
            mkdir($options['cache'], 0777, true);
        }

        $cacheFile = path_resolve($options['cache'], $PLUGIN_CACHE);
        file_put_contents($cacheFile, json_encode($plugins));
    }

    return $plugins;
}
