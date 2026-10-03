<?php

namespace App\View\Components;

use Illuminate\View\Component;

/** Inline Lucide icon (ISC licence). <x-lucide name="shield-check" class="size-5" /> */
class Lucide extends Component
{
    private static array $cache = [];

    public function __construct(public string $name, public string $class = 'size-5', public ?string $label = null, public float $stroke = 2) {}

    public function render(): string
    {
        $file = resource_path('icons/'.basename($this->name).'.svg');
        $svg = self::$cache[$this->name] ??= (is_file($file) ? file_get_contents($file) : file_get_contents(resource_path('icons/circle.svg')));
        $svg = preg_replace('/<!--.*?-->/s', '', $svg);
        $aria = $this->label ? 'role="img" aria-label="'.e($this->label).'"' : 'aria-hidden="true" focusable="false"';
        $svg = preg_replace('/<svg\b[^>]*>/', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="'.$this->stroke.'" stroke-linecap="round" stroke-linejoin="round" class="'.e($this->class).' shrink-0" '.$aria.'>', $svg, 1);

        return trim($svg);
    }
}
