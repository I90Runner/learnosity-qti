<?php

namespace Learnosity\Processors\QtiV2\Out;

use Learnosity\Services\LogService;
use Learnosity\Utils\QtiMarshallerUtil;
use Learnosity\Utils\SimpleHtmlDom\SimpleHtmlDom;
use Learnosity\Utils\StringUtil;
use qtism\data\content\BlockCollection;
use qtism\data\content\FlowCollection;
use qtism\data\content\ItemBody;
use qtism\data\content\xhtml\text\Div;
use qtism\data\content\xhtml\text\Span;
use qtism\data\QtiComponentCollection;

class ItemBodyBuilder
{
    public function buildItemBody(array $interactions, $content = '')
    {
        // Try to build the <itemBody> according to items` content if exists
        if (empty($content)) {
            return $this->buildItemBodySimple($interactions);
        }
        try {
            return $this->buildItemBodyWithItemContent($interactions, $content);
            // If anything fails, <itemBody> can't be mapped due to whatever reasons
            // Probably simply due to its being wrapped in a tag which only accept inline content
            // Simply build it without considering items` content and put the content on the top
        } catch (\Exception $e) {
            $itemBody = $this->buildItemBodySimple($interactions);
            $itemBodyContent = new BlockCollection();

            // Build the div bundle that contains all the item`s content
            // minus those questions and features`span`
            $html = new SimpleHtmlDom();
            $html->load($content);
            foreach ($html->find('span.learnosity-response, span.learnosity-feature') as &$span) {
                $span->outertext = '';
            }
            $div = new Div();
            $contentCollection = QtiMarshallerUtil::unmarshallElement($html->save());
            $div->setContent(ContentCollectionBuilder::buildFlowCollectionContent($contentCollection));

            $itemBodyContent->attach($div);
            $itemBodyContent->merge($itemBody->getComponents());
            $itemBody->setContent($itemBodyContent);
            LogService::log(
                'Interactions are failed to be mapped with `item` content: ' . $e->getMessage()
                . '. Thus, interactions are separated from its actual `item` content and appended in the bottom'
            );
            return $itemBody;
        }
    }

    private function buildItemBodyWithItemContent(array $interactions, $content)
    {
        // Map <itemBody>
        // TODO: Wrap these `content` stuff in a div
        // TODO: to avoid QtiComponentIterator bug ignoring 2nd element with empty content
        $contentCollection = QtiMarshallerUtil::unmarshallElement($content);
        $wrapperCollection = new FlowCollection();
        foreach ($contentCollection as $component) {
            $wrapperCollection->attach($component);
        }
        $divWrapper = new Div();
        $divWrapper->setContent($wrapperCollection);

        // Iterate through these elements and try to replace every single question `span` with its interaction equivalent
        $iterator = $divWrapper->getIterator();
        foreach ($iterator as $component) {
            if ($component instanceof Span && StringUtil::contains($component->getClass(), 'learnosity-response')) {
                $currentContainer = $iterator->getCurrentContainer();
                $questionReference = trim(str_replace('learnosity-response', '', $component->getClass()));
                $questionReference = trim(str_replace('question-', '', $questionReference));

                $replacement = ContentCollectionBuilder::buildContent($currentContainer, $interactions[$questionReference])->current();
                $currentContainer->getComponents()->replace($component, $replacement);
            }
        }

        // Extract the actual content from the div wrapper and add that to our <itemBody>
        $componentsWithinDiv = $divWrapper->getComponents();
        $itemBody = new ItemBody();
        $itemBody->setContent(ContentCollectionBuilder::buildBlockCollectionContent($componentsWithinDiv));

        return $itemBody;
    }

    private function buildItemBodySimple(array $interactions)
    {
        $interactionCollection = new QtiComponentCollection(array_values($interactions));
        $itemBody = new ItemBody();
        $itemBody->setContent(ContentCollectionBuilder::buildBlockCollectionContent($interactionCollection));

        return $itemBody;
    }
}