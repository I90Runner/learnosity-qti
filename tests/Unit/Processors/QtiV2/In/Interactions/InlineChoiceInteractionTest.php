<?php

namespace LearnosityQti\Tests\Unit\Processors\QtiV2\In\Interactions;

use LearnosityQti\Processors\QtiV2\In\Interactions\InlineChoiceInteractionMapper;
use LearnosityQti\Processors\QtiV2\In\ResponseProcessingTemplate;
use LearnosityQti\Services\LogService;
use LearnosityQti\Tests\Unit\Processors\QtiV2\In\Fixtures\InlineChoiceInteractionBuilder;
use LearnosityQti\Tests\Unit\Processors\QtiV2\In\Fixtures\ResponseDeclarationBuilder;

class InlineChoiceInteractionTest extends AbstractInteractionTest
{
    public function testSimpleCaseWithNoValidation()
    {
        $interaction = InlineChoiceInteractionBuilder::buildSimple('testIdentifier', [
            'sydney' => 'Sydney',
            'melbourne' => 'Melbourne',
            'canberra' => 'Canberra',
        ]);
        $interaction->setShuffle(true);
        $interaction->setRequired(true);
        $mapper = new InlineChoiceInteractionMapper($interaction);
        $question = $mapper->getQuestionType();

        // Should map question correctly with no `validation` object
        $this->assertNotNull($question);
        $this->assertEquals('clozedropdown', $question->get_type());
        $validation = $question->get_validation();
        $this->assertNull($validation);
    }

    public function testSimpleCaseWithInvalidValidation()
    {
        $interaction = InlineChoiceInteractionBuilder::buildSimple('testIdentifier', [
            'doesntmatter' => 'Doesntmatter'
        ]);
        $mapper = new InlineChoiceInteractionMapper($interaction, null, ResponseProcessingTemplate::mapResponsePoint());
        $question = $mapper->getQuestionType();

        // Should map question correctly with no `validation` object
        $this->assertNotNull($question);
        $this->assertEquals('clozedropdown', $question->get_type());
        $validation = $question->get_validation();
        $this->assertNull($validation);

        $this->assertTrue(count(LogService::read()) === 1);
    }

    public function testSimpleCaseWithMatchCorrectValidation()
    {
        $interaction = InlineChoiceInteractionBuilder::buildSimple('testIdentifier', [
            'sydney' => 'Sydney',
            'melbourne' => 'Melbourne',
            'canberra' => 'Canberra',
        ]);
        $responseDeclaration = ResponseDeclarationBuilder::buildWithCorrectResponse('testIdentifier', [
            'sydney',
            'melbourne'
        ]);
        $mapper = new InlineChoiceInteractionMapper($interaction, $responseDeclaration, ResponseProcessingTemplate::matchCorrect());
        $question = $mapper->getQuestionType();

        $this->assertNotNull($question);
        $this->assertEquals('clozedropdown', $question->get_type());

        // Should populate possible responses
        $this->assertCount(1, $question->get_possible_responses());
        $this->assertCount(3, $question->get_possible_responses()[0]);
        $this->assertContains('Sydney', $question->get_possible_responses()[0]);
        $this->assertContains('Melbourne', $question->get_possible_responses()[0]);
        $this->assertContains('Canberra', $question->get_possible_responses()[0]);

        // Should have validation object
        $validation = $question->get_validation();
        $this->assertNotNull($validation);
        $this->assertInstanceOf('LearnosityQti\Entities\QuestionTypes\clozedropdown_validation', $validation);
        $this->assertEquals('exactMatch', $validation->get_scoring_type());

        // Should set both `valid_response` and `alt_responses` for multiple correct values
        $validResponse = $validation->get_valid_response();
        $this->assertNotNull($validResponse);
        $this->assertEquals(1, $validResponse->get_score());
        $this->assertEquals(["Sydney"], $validResponse->get_value());

        $altResponses = $validation->get_alt_responses();
        $this->assertNotNull($altResponses);
        $this->assertCount(1, $altResponses);
        $this->assertEquals(1, $altResponses[0]->get_score());
        $this->assertEquals(["Melbourne"], $altResponses[0]->get_value());
    }

    public function testSimpleCaseWithMapResponseValidation()
    {
        $interaction = InlineChoiceInteractionBuilder::buildSimple('testIdentifier', [
            'sydney' => 'Sydney',
            'melbourne' => 'Melbourne',
            'canberra' => 'Canberra',
        ]);
        $responseDeclaration = ResponseDeclarationBuilder::buildWithMapping('testIdentifier', [
            'sydney' => ['0.5', false],
            'melbourne' => ['1', true]
        ]);

        $mapper = new InlineChoiceInteractionMapper($interaction, $responseDeclaration, ResponseProcessingTemplate::mapResponse());
        $question = $mapper->getQuestionType();

        $this->assertNotNull($question);
        $this->assertEquals('clozedropdown', $question->get_type());


        // Should populate possible responses
        $this->assertCount(1, $question->get_possible_responses());
        $this->assertCount(3, $question->get_possible_responses()[0]);
        $this->assertContains('Sydney', $question->get_possible_responses()[0]);
        $this->assertContains('Melbourne', $question->get_possible_responses()[0]);
        $this->assertContains('Canberra', $question->get_possible_responses()[0]);

        // Should have validation object
        $validation = $question->get_validation();
        $this->assertNotNull($validation);
        $this->assertInstanceOf('LearnosityQti\Entities\QuestionTypes\clozedropdown_validation', $validation);
        $this->assertEquals('exactMatch', $validation->get_scoring_type());

        // Should set both `valid_response` and `alt_responses` for multiple correct values
        // Also, the highest value should be put as `valid_response`
        $validResponse = $validation->get_valid_response();
        $this->assertNotNull($validResponse);
        $this->assertEquals(1, $validResponse->get_score());
        $this->assertEquals(["Melbourne"], $validResponse->get_value());

        $altResponses = $validation->get_alt_responses();
        $this->assertNotNull($altResponses);
        $this->assertCount(1, $altResponses);
        $this->assertEquals(0.5, $altResponses[0]->get_score());
        $this->assertEquals(["Sydney"], $altResponses[0]->get_value());
    }
}
