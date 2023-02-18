<?php

namespace Hybrid\Tools\Traits;

trait Tappable {

    /**
     * Call the given Closure with this instance then return the instance.
     *
     * @param  callable|null $callback
     * @return $this|\Hybrid\Tools\HigherOrderTapProxy
     */
    public function tap( $callback = null ) {
        return tap( $this, $callback );
    }

}
