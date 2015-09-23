<?php

namespace Learnosity\Processors\QtiV2\Out\Validation;

use Learnosity\Entities\QuestionTypes\clozeassociation_validation;
use Learnosity\Exceptions\MappingException;
use Learnosity\Processors\QtiV2\Out\QuestionTypes\ClozeassociationMapper;
use qtism\common\datatypes\DirectedPair;
use qtism\common\enums\BaseType;
use qtism\common\enums\Cardinality;
use qtism\data\state\CorrectResponse;
use qtism\data\state\ResponseDeclaration;
use qtism\data\state\Value;
use qtism\data\state\ValueCollection;

class ClozeassociationValidationBuilder extends AbstractQuestionValidationBuilder
{
    private $possibleResponsesMap;

    public function __construct(array $possibleResponses)
    {
        $this->possibleResponsesMap = array_flip($possibleResponses);
    }

    protected function buildResponseDeclaration($responseIdentifier, $validation)
    {
        $responseDeclaration = new ResponseDeclaration($responseIdentifier);
        $responseDeclaration->setCardinality(Cardinality::MULTIPLE);
        $responseDeclaration->setBaseType(BaseType::DIRECTED_PAIR);

        /** @var clozeassociation_validation $validation */
        $validationValues = $validation->get_valid_response()->get_value();
        $validationScore = $validation->get_valid_response()->get_score();

        // Build correct response
        // Try to handle `null` values in `valid_response` `value`s
        $values = new ValueCollection();
        foreach ($validationValues as $index => $validResponse) {
            if (!isset($this->possibleResponsesMap[$validResponse])) {
                throw new MappingException('Invalid or missing missing valid response `' . $validResponse . '``');
            }
            if (!empty($validResponse)) {
                $first = ClozeassociationMapper::GAP_IDENTIFIER_PREFIX . $index;
                $second = ClozeassociationMapper::GAPCHOICE_IDENTIFIER_PREFIX . $this->possibleResponsesMap[$validResponse];
                $values->attach(new Value(new DirectedPair($first, $second)));
            }
        }
        if ($values->count() > 0) {
            $correctResponse = new CorrectResponse($values);
            $responseDeclaration->setCorrectResponse($correctResponse);
        }
        return $responseDeclaration;
    }
}
