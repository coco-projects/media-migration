<?php

    namespace Coco\mediaMigration;

    class Processor
    {
        public function __construct(public $rule, public $processor)
        {

        }

        /**
         * @return callable
         */
        public function getProcessor(): callable
        {
            return $this->processor;
        }

        /**
         * @return callable
         */
        public function getRule(): callable
        {
            return $this->rule;
        }

    }
