<?php

namespace Hybrid\Tools;

interface DeferringDisplayableValue {

    /**
     * Resolve the displayable value that the class is deferring.
     *
     * @return \Hybrid\Contracts\Htmlable|string
     */
    public function resolveDisplayableValue();

}
