<?php

use JohannSchopplich\LockedPages\Firewall;
use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\FileVersion;
use Kirby\Filesystem\Asset;

return [
    'file::url' => fn (App $kirby, File $file): string => Firewall::url($file),
    'file::version' => fn (App $kirby, File|Asset $file, array $options = []): File|Asset|FileVersion => Firewall::version($kirby, $file, $options)
];
