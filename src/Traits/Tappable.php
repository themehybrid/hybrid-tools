<?php

namespace Hybrid\Tools\Traits;

use function Hybrid\Tools\tap;

trait Tappable {

    /**
     * Call the given Closure with this instance then return the instance.
     *
     * @param (callable( $this): mixed)|null $callback
     * @return ($callback is null ? \Hybrid\Tools\HigherOrderTapProxy : $this)
     */
    public function tap( $callback = null ) {
        return tap( $this, $callback );
    }

}
