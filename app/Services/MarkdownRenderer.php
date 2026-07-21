<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

class MarkdownRenderer
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'min_heading_level' => 2,
                'max_heading_level' => 3,
                'insert' => 'none',
                'id_prefix' => 'section',
                'apply_id_to_heading' => true,
                'heading_class' => 'knowledge-heading',
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new HeadingPermalinkExtension);

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * @return array{html: string, headings: array<int, array{id: string, label: string, level: int}>}
     */
    public function render(string $markdown): array
    {
        $html = (string) $this->converter->convert($markdown);

        return [
            'html' => $html,
            'headings' => $this->headings($html),
        ];
    }

    /**
     * @return array<int, array{id: string, label: string, level: int}>
     */
    private function headings(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<?xml encoding="utf-8" ?><div id="knowledge-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);

        return collect($xpath->query('//*[@id="knowledge-root"]//h2 | //*[@id="knowledge-root"]//h3'))
            ->filter(fn ($node): bool => $node instanceof DOMElement && $node->hasAttribute('id'))
            ->map(fn (DOMElement $heading): array => [
                'id' => $heading->getAttribute('id'),
                'label' => trim($heading->textContent),
                'level' => (int) substr($heading->tagName, 1),
            ])
            ->values()
            ->all();
    }
}
