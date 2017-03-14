<?php

namespace LearnosityQti\Processors\QtiV2\In;

use DOMElement;
use DOMDocument;
use LearnosityQti\Entities\Question;
use LearnosityQti\Entities\QuestionTypes\sharedpassage;
use LearnosityQti\Utils\UuidUtil;
use qtism\data\content\RubricBlock;
use qtism\data\content\xhtml\Object;
use qtism\data\QtiComponentCollection;
use SplFileInfo;
use LearnosityQti\Services\LogService;
use LearnosityQti\Exceptions\MappingException;

class SharedPassageMapper
{
    private $sourceDirectoryPath;

    public function __construct($sourceDirectoryPath = null)
    {
        $this->sourceDirectoryPath = $sourceDirectoryPath;
    }

    public function parse($xmlString)
    {
        return $this->parseXml($xmlString);
    }

    public function parseXml($xmlString)
    {
        $results = [
            'features' => [],
        ];

        $passageContent = $this->parsePassageContentFromXml($xmlString);

        $widget = $this->buildSharedPassageWithContent($passageContent);

        $results['features'][$widget->get_reference()] = $widget;

        return $results;
    }

    public function parseHtml($htmlString)
    {
        $results = [
            'features' => [],
        ];

        $passageContent = $this->parsePassageContentFromHtml($htmlString);

        $widget = $this->buildSharedPassageWithContent($passageContent);

        $results['features'][$widget->get_reference()] = $widget;

        return $results;
    }

    public function parseFile(SplFileInfo $file, $contentType = 'application/xml')
    {
        $passageContentString = file_get_contents($file->getRealPath());
        switch ($contentType) {
            case 'text/html':
                $result = $this->parseHtml($passageContentString);
                break;
            case 'application/xml':
                # Falls through
            default:
                $result = $this->parseXml($passageContentString);
                break;
        }

        return $result;
    }

    public function parseWithRubricBlockComponent(RubricBlock $rubricBlock)
    {
        // TODO: Handle HTML content inside rubricBlock that wraps the object element
        $result = [];
        $messages = [];

        // Extract the object element (if any)
        /** @var QtiComponentCollection $objects */
        $objects = $rubricBlock->getComponentsByClassName('object', true);
        if ($objects->count()) {
            if ($objects->count() > 1) {
                LogService::log('<rubricBlock use="context"> - multiple <object> elements found, will use the first only');
            }
            $objects->rewind();
            $contentRelativePath = $objects->current()->getData();
            $contentType = $objects->current()->getType();
            $file = new SplFileInfo($this->sourceDirectoryPath.'/'.$contentRelativePath);
            if (!$file->isFile()) {
                throw new MappingException("Could not process <rubricBlock> - resource file at {$contentRelativePath} not found in directory: '{$this->sourceDirectoryPath}'");
            }

            $result = $this->parseFile($file, $contentType);
        }

        // TODO: Fix flush issue with LogService
        // $messages = array_merge($messages, array_values(array_unique(LogService::flush())));

        return $result;
    }

    protected function buildSharedPassageWithContent($passageContent)
    {
        return new Question('sharedpassage', UuidUtil::generate(), new sharedpassage('sharedpassage', $passageContent));
    }

    protected function parsePassageContentFromHtml($htmlString)
    {
        $htmlDom = new DOMDocument();
        $htmlDom->loadHTML($htmlString);

        /** @var \DOMNodeList $body */
        $body = $htmlDom->getElementsByTagName('body');
        if ($body->item(0)) {
            $oldHtmlDom = $htmlDom;

            // Parse to get only the HTML body content.
            $htmlDom = new DOMDocument();
            foreach ($body->item(0)->childNodes as $childNode) {
                $htmlDom->appendChild($htmlDom->importNode($childNode, true));
            }

            // Check for any stripped elements (e.g. <link> stylesheets) that needs to be logged.
            foreach ($oldHtmlDom->getElementsByTagName('link') as $linkElement) {
                /** @var DOMElement $linkElement */
                $rel = $linkElement->getAttribute('rel');
                $href = $linkElement->getAttribute('href');
                LogService::log("Could not import <link> element in passage content with rel: {$rel} href: {$href}");
            }
        }

        return $this->parsePassageContentFromDom($htmlDom);
    }

    protected function parsePassageContentFromXml($xmlString)
    {
        return $this->parsePassageContentFromDom($this->loadXmlAsHtmlDocument($xmlString));
    }

    private function loadXmlAsHtmlDocument($xmlString)
    {
        // HACK: Load as XML in one DOM and transfer it to another DOM as HTML for modification
        $xmlDom = new DOMDocument();
        $xmlDom->loadXML($xmlString);

        $htmlDom = new DOMDocument();
        $htmlDom->loadHTML('<body></body>');
        $xmlDom = $htmlDom->importNode($xmlDom->documentElement, true);
        $htmlDom->replaceChild($xmlDom, $htmlDom->documentElement);

        return $htmlDom;
    }

    private function parsePassageContentFromDom(DOMDocument $htmlDom)
    {
        // Strip all <rubricBlock> elements (keep inner HTML)
        foreach ($htmlDom->getElementsByTagName('rubricBlock') as $rubricBlockElement) {
            /** @var DOMElement $rubricBlockElement */
            $replacementNode = $htmlDom->createDocumentFragment();
            while ($rubricBlockElement->childNodes->length > 0) {
                /** @var DOMNode $childNode */
                $replacementNode->appendChild($rubricBlockElement->childNodes->item(0));
            }
            $rubricBlockElement->parentNode->replaceChild($replacementNode, $rubricBlockElement);
        }

        // Strip all <apipAccessibility> elements
        foreach ($htmlDom->getElementsByTagName('apipAccessibility') as $apipElement) {
            /** @var DOMElement $apipElement */
            $apipElement->parentNode->removeChild($apipElement);
        }

        // Process all <object> elements
        $xpath = new \DOMXPath($htmlDom);
        foreach ($xpath->query('//object') as $objectElement) {
            $this->handleObjectElementInDocument($objectElement, $htmlDom);
        }

        return $htmlDom->saveHTML($htmlDom->documentElement);
    }

    private function handleObjectElementInDocument(DOMElement $objectElement, DOMDocument $context)
    {
        // If <object> has `image/*` MIME type:
        if (strpos((string)$objectElement->getAttribute('type'), 'image') !== false) {
            /** @var DOMElement $replacementElement */
            $replacementElement = $context->createElement('img');
            $replacementElement->setAttribute('src', $objectElement->getAttribute('data'));

            // TODO: support `alt` text (using the inner text content of the <object> element)
            if ($objectElement->hasAttribute('height')) {
                $replacementElement->setAttribute('height', $objectElement->getAttribute('height'));
            }
            if ($objectElement->hasAttribute('width')) {
                $replacementElement->setAttribute('width', $objectElement->getAttribute('width'));
            }
            $objectElement->parentNode->replaceChild($replacementElement, $objectElement);
        }
    }
}