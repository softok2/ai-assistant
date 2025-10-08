<?php

declare(strict_types=1);

$composerFile = __DIR__.'/../composer.json';
$json = json_decode(file_get_contents($composerFile), true);

// Check if 'repositories' key exists, if not, create it
$json['repositories'] = $json['repositories'] ?? [];

// Check if the path repository already exists
$exists = false;
foreach ($json['repositories'] as $repo) {
    if (($repo['type'] ?? '') === 'path' && ($repo['url'] ?? '') === '/Users/franky/Projects/packages/telegram-notifications') {
        $exists = true;
        break;
    }
}

if (! $exists) {
    array_unshift($json['repositories'], [
        'type' => 'path',
        'url' => '/Users/franky/Projects/packages/telegram-notifications',
        'options' => ['symlink' => true],
    ]);
}

// Change the version constraint to use the local package
$json['require']['softok2/telegram-notifications'] = '@dev';

// Save the modified composer.json
file_put_contents($composerFile, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "✅ Composer configured to use local packages.\n";
echo "🔄 Updating composer...\n";

// Run composer update to apply changes
passthru('composer update softok2/telegram-notifications');
