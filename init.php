<?php

if (!function_exists('getFullSlug')) {

    function getFullSlug($item)
    {
        if (!isset($item->slug)) {
            return null;
        }
        $parts = [$item->slug];
        while (($part = $item->parent) != null && $part->slug) {
            $parts[] = $part->slug;
            $item = $part;
        }
        return implode('/', array_reverse($parts));
    }
}
