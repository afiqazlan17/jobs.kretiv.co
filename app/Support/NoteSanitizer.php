<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

// A note's HTML comes from Quill (resources/js/app.js) running in the
// browser — never trust it as-is. Quill's own DOM sanitization only
// covers what its own toolbar produces; a user editing the hidden input
// via devtools before submit can send anything. Server-side allowlist
// purification is the actual security boundary, not the editor.
class NoteSanitizer
{
    public static function clean(string $html): string
    {
        $config = HTMLPurifier_Config::createDefault();
        // No <img>: inline images aren't supported by the composer — an
        // image is just a file attachment (see JobController::addNote()),
        // rendered from our own trusted attachment markup, never from
        // user-supplied HTML. Keeps URI.AllowedSchemes to http/https only.
        $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,s,ul,ol,li,a[href],span[style]');
        $config->set('CSS.AllowedProperties', 'color,background-color');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true]);
        $config->set('Cache.DefinitionImpl', null);

        return (new HTMLPurifier($config))->purify($html);
    }
}
