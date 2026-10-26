<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitizes rich-text topic content produced by the editor before it is stored.
 */
class HtmlCleaner
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowElement('input', ['type', 'checked', 'disabled'])
            ->allowElement('pre', ['class'])
            ->allowElement('code', ['class'])
            ->allowElement('li', ['data-checked', 'data-type'])
            ->allowElement('ul', ['data-type'])
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height'])
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowMediaSchemes(['http', 'https'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow');

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function clean(?string $html): string
    {
        return $this->sanitizer->sanitize((string) $html);
    }
}
