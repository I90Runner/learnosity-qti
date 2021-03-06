<?php

namespace LearnosityQti\Entities\QuestionTypes;

use LearnosityQti\Entities\BaseQuestionTypeAttribute;

/**
* This class is auto-generated based on Schemas API and you should not modify its content
* Metadata: {"responses":"v2.108.0","feedback":"v2.71.0","features":"v2.107.0"}
*/
class numberlineplot_validation_valid_response_value_item extends BaseQuestionTypeAttribute {
    protected $type;
    
    public function __construct(
            )
    {
            }

    /**
    * Get Keypad type \
    * Type of the tool \
    * @return string $type \
    */
    public function get_type() {
        return $this->type;
    }

    /**
    * Set Keypad type \
    * Type of the tool \
    * @param string $type \
    */
    public function set_type ($type) {
        $this->type = $type;
    }

    
}

