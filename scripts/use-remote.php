<?php

declare(strict_types=1);

$composerFile = __DIR__.'/../composer.json';
$json = json_decode(file_get_contents($composerFile), true);

// Delete local path repository if exists
if (! empty($json['repositories'])) {
    $json['repositories'] = array_filter($json['repositories'], function ($repo) {
        return ! ($repo['type'] === 'path' && $repo['url'] === '/Users/franky/Projects/packages/telegram-notifications');
    });
}

// Change the version constraint to use the published version
$json['require']['softok2/telegram-notifications'] = '^1.0';

// Save the updated composer.json
file_put_contents($composerFile, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "✅ Composer configured to use packagist version.\n";
echo "🔄 Running composer update...\n";

// Run composer update to apply changes
passthru('composer update softok2/telegram-notifications');
