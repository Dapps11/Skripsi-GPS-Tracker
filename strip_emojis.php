<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources/views'));
foreach ($files as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') continue;
    
    $content = file_get_contents($file->getPathname());
    // Regex for typical emojis
    $newContent = preg_replace('/[\x{1F300}-\x{1F5FF}\x{1F600}-\x{1F64F}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $content);
    
    if ($content !== $newContent) {
        file_put_contents($file->getPathname(), $newContent);
        echo "Stripped emojis from: " . $file->getPathname() . "\n";
    }
}
