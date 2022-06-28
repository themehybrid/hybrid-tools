<?php

namespace Hybrid\Tools;

use Carbon\Carbon as BaseCarbon;
use Carbon\CarbonImmutable as BaseCarbonImmutable;
use Hybrid\Tools\Traits\Conditionable;

class Carbon extends BaseCarbon {

    use Conditionable;

    /**
     * {@inheritdoc}
     */
    public static function setTestNow( $testNow = null ) {
        BaseCarbon::setTestNow( $testNow );
        BaseCarbonImmutable::setTestNow( $testNow );
    }

}
