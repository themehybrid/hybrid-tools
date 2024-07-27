<?php

namespace Hybrid\Tools;

use Carbon\Carbon as BaseCarbon;
use Carbon\CarbonImmutable as BaseCarbonImmutable;
use Hybrid\Tools\Traits\Conditionable;
use Hybrid\Tools\Traits\Dumpable;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Uid\Ulid;

class Carbon extends BaseCarbon {

    use Conditionable;
    use Dumpable;

    /**
     * {@inheritDoc}
     */
    public static function setTestNow( mixed $testNow = null ): void {
        BaseCarbon::setTestNow( $testNow );
        BaseCarbonImmutable::setTestNow( $testNow );
    }

    /**
     * Create a Carbon instance from a given ordered UUID or ULID.
     */
    public static function createFromId( Uuid|Ulid|string $id ): static {
        if ( is_string( $id ) ) {
            $id = Ulid::isValid( $id ) ? Ulid::fromString( $id ) : Uuid::fromString( $id );
        }

        return static::createFromInterface( $id->getDateTime() );
    }

}
