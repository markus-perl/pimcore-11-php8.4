<?php
/**
 * PHP Script to Fix Implicitly Nullable Parameter Deprecation Warnings
 * Searches recursively for PHP files and fixes "Implicitly marking parameter $object as nullable is deprecated" issues
 */

function scanAndFixFiles($directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    $count = 0;

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $filePath = $file->getPathname();
            $count++;
            if ($count % 100 == 0) {
                echo "Processed $count files...\n";
            }

            $content = file_get_contents($filePath);
            $originalContent = $content;

            // Fix patterns for method parameters
            $content = fixNullableParameters($content);

            if ($content !== $originalContent) {
                file_put_contents($filePath, $content);
                echo "Fixed: $filePath\n";
            }
        }
    }
    echo "Total files processed: $count\n";
}

function fixNullableParameters($content) {
    // Pattern 1: Single type parameter in function/method signatures (after , or ()
    // Matches: , ?Type $param = null or ( ?Type $param = null
    // Replaces with: , ?Type $param = null (only if not already nullable)
    $content = preg_replace_callback(
        '/([,\\(])\s*(\?[A-Za-z_\\\\][A-Za-z0-9_\\\\]*|[A-Za-z_\\\\][A-Za-z0-9_\\\\]*)\s+\$(\w+)\s*=\s*null/',
        function($matches) {
            $prefix = $matches[1];
            $type = $matches[2];
            $param = $matches[3];
            // Skip if already nullable or special types or union types
            if (strpos($type, '?') === 0 || strpos($type, 'null') !== false || strpos($type, '|') !== false || in_array($type, ['mixed', 'array', 'callable', 'iterable'])) {
                return $matches[0];
            }
            // For namespaced types, add |null; for simple types, add ?
            if (strpos($type, '\\') !== false) {
                $result = $prefix . ' ' . $type . '|null $' . $param . ' = null';
            } else {
                $result = $prefix . ' ?' . $type . ' $' . $param . ' = null';
            }
            // Additional check: if result has ??, skip
            if (strpos($result, '??') !== false) {
                return $matches[0];
            }
            return $result;
        },
        $content
    );

    // Pattern 2: Union type parameter in function/method signatures (contains |)
    // Matches: , Type1|Type2|null $param = null
    // Replaces with: , Type1|Type2|null $param = null (only if not already containing null)
    $content = preg_replace_callback(
        '/([,\\(])\s*([A-Za-z_\\\\][A-Za-z0-9_\\\\]*(?:\??[A-Za-z_\\\\][A-Za-z0-9_\\\\]*(?:\|[A-Za-z_\\\\][A-Za-z0-9_\\\\]*)+))\s+\$(\w+)\s*=\s*null/',
        function($matches) {
            $prefix = $matches[1];
            $type = $matches[2];
            $param = $matches[3];
            // Skip if already contains null
            if (strpos($type, 'null') !== false) {
                return $matches[0];
            }
            return $prefix . ' ' . $type . '|null $' . $param . ' = null';
        },
        $content
    );

    return $content;
}

// Run the script from the current directory
$currentDir = __DIR__;
echo "Starting scan from: $currentDir\n";
scanAndFixFiles($currentDir);
echo "Scan complete.\n";
?>
