<?php

namespace Shah\Guardian\Prevention\Protectors;

use DOMDocument;
use Illuminate\Support\Str;
use Shah\Guardian\Prevention\Utilities\DomUtility;

class HoneytokenInjector
{
    /**
     * DOM utility instance.
     *
     * @var \Shah\Guardian\Prevention\Utilities\DomUtility
     */
    protected $domUtility;

    /**
     * Create a new honeytoken injector instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->domUtility = new DomUtility;
    }

    /**
     * Add honeytokens to the DOM.
     */
    public function inject(DOMDocument $dom): void
    {
        // Generate unique honeypot links
        $honeypots = [
            [
                'text' => 'Full Content Access',
                'path' => '/access/content/'.Str::random(8),
            ],
            [
                'text' => 'View Complete Article',
                'path' => '/article/full/'.Str::random(8),
            ],
            [
                'text' => 'Download Data',
                'path' => '/data/export/'.Str::random(8),
            ],
        ];

        $body = $this->domUtility->ensureBody($dom);
        if (! $body) {
            return;
        }

        // Add each honeypot
        foreach ($honeypots as $pot) {
            $div = $dom->createElement('div');
            $div->setAttribute('class', 'gdn-ht-'.$this->domUtility->generateUniqueClassName());
            $div->setAttribute('style', 'position:absolute; opacity:0; pointer-events:none;');

            $link = $dom->createElement('a');
            $link->setAttribute('href', $pot['path']);
            $link->setAttribute('class', 'guardian-honeypot');
            $link->appendChild($dom->createTextNode($pot['text']));

            $div->appendChild($link);
            $body->appendChild($div);
        }

        // Add hidden content honeypot
        $honeypotDiv = $dom->createElement('div');
        $honeypotDiv->setAttribute('class', 'hidden-content');
        $honeypotDiv->setAttribute('style', 'display:none;');

        $honeypotHeading = $dom->createElement('h2');
        $honeypotHeading->appendChild($dom->createTextNode('Restricted Content'));
        $honeypotDiv->appendChild($honeypotHeading);

        $honeypotText = $dom->createElement('p');
        $honeypotText->appendChild($dom->createTextNode('This content is only accessible to registered users. Please sign in to view this protected content.'));
        $honeypotDiv->appendChild($honeypotText);

        $body->appendChild($honeypotDiv);
    }
}
