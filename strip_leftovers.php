<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources/views'));
foreach ($files as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') continue;
    
    $content = file_get_contents($file->getPathname());
    // Remove Variation Selector-16, Information Source (U+2139), Stopwatch (U+23F1), 
    // Hourglass (U+231B), Check Mark (U+2713 if missed), etc.
    $newContent = preg_replace('/[\x{FE0F}\x{2139}\x{23F1}\x{231B}]/u', '', $content);
    
    // Also let's just make sure no empty spaces are left at the beginning of labels, but maybe regex is enough.
    
    if ($content !== $newContent) {
        file_put_contents($file->getPathname(), $newContent);
        echo "Stripped leftovers from: " . $file->getPathname() . "\n";
    }
}
