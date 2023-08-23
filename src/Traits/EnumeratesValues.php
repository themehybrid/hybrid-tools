<?php

namespace Hybrid\Tools\Traits;

use CachingIterator;
use Closure;
use Hybrid\Contracts\Arrayable;
use Hybrid\Contracts\Jsonable;
use Hybrid\Tools\Arr;
use Hybrid\Tools\Collection;
use Hybrid\Tools\Enumerable;
use Hybrid\Tools\HigherOrderCollectionProxy;
use JsonSerializable;
use Symfony\Component\VarDumper\VarDumper;
use Traversable;
use UnitEnum;
use function Hybrid\Tools\data_get;
use function Hybrid\Tools\value;

/**
 * @template TKey of array-key
 * @template TValue
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $average
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $avg
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $contains
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $doesntContain
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $each
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $every
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $filter
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $first
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $flatMap
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $groupBy
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $keyBy
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $map
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $max
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $min
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $partition
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $reject
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $skipUntil
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $skipWhile
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $some
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $sortBy
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $sortByDesc
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $sum
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $takeUntil
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $takeWhile
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $unique
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $unless
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $until
 * @property-read \Hybrid\Tools\HigherOrderCollectionProxy $when
 */
trait EnumeratesValues {

    use Conditionable;

    /**
     * Indicates that the object's string representation should be escaped when __toString is invoked.
     *
     * @var bool
     */
    protected $escapeWhenCastingToString = false;

    /**
     * The methods that can be proxied.
     *
     * @var array<int, string>
     */
    protected static $proxies = [
        'average',
        'avg',
        'contains',
        'doesntContain',
        'each',
        'every',
        'filter',
        'first',
        'flatMap',
        'groupBy',
        'keyBy',
        'map',
        'max',
        'min',
        'partition',
        'reject',
        'skipUntil',
        'skipWhile',
        'some',
        'sortBy',
        'sortByDesc',
        'sum',
        'takeUntil',
        'takeWhile',
        'unique',
        'unless',
        'until',
        'when',
    ];

    /**
     * Create a new collection instance if the value isn't one already.
     *
     * @param  \Hybrid\Contracts\Arrayable<TMakeKey, TMakeValue>|iterable<TMakeKey, TMakeValue>|null $items
     * @return static<TMakeKey, TMakeValue>
     *
     * @template TMakeKey of array-key
     * @template TMakeValue
     */
    public static function make( $items = [] ) {
        return new static( $items );
    }

    /**
     * Wrap the given value in a collection if applicable.
     *
     * @param  iterable<TWrapKey, TWrapValue> $value
     * @return static<TWrapKey, TWrapValue>
     *
     * @template TWrapKey of array-key
     * @template TWrapValue
     */
    public static function wrap( $value ) {
        return $value instanceof Enumerable
            ? new static( $value )
            : new static( Arr::wrap( $value ) );
    }

    /**
     * Get the underlying items from the given collection if applicable.
     *
     * @param  array<TUnwrapKey, TUnwrapValue>|static<TUnwrapKey, TUnwrapValue> $value
     * @return array<TUnwrapKey, TUnwrapValue>
     *
     * @template TUnwrapKey of array-key
     * @template TUnwrapValue
     */
    public static function unwrap( $value ) {
        return $value instanceof Enumerable ? $value->all() : $value;
    }

    /**
     * Create a new instance with no items.
     *
     * @return static
     */
    public static function empty() {
        return new static( [] );
    }

    /**
     * Create a new collection by invoking the callback a given amount of times.
     *
     * @param  int                               $number
     * @param  (callable(int): TTimesValue)|null $callback
     * @return static<int, TTimesValue>
     *
     * @template TTimesValue
     */
    public static function times( $number, ?callable $callback = null ) {
        if ( $number < 1 ) {
            return new static();
        }

        return static::range( 1, $number )
            ->unless( $callback === null )
            ->map( $callback );
    }

    /**
     * Alias for the "avg" method.
     *
     * @param  (callable(TValue): float|int)|string|null $callback
     * @return float|int|null
     */
    public function average( $callback = null ) {
        return $this->avg( $callback );
    }

    /**
     * Alias for the "contains" method.
     *
     * @param  (callable(TValue, TKey): bool)|TValue|string $key
     * @param  mixed                                        $operator
     * @param  mixed                                        $value
     * @return bool
     */
    public function some( $key, $operator = null, $value = null ) {
        return $this->contains( ...func_get_args() );
    }

    /**
     * Determine if an item exists, using strict comparison.
     *
     * @param  (callable(TValue): bool)|TValue|array-key $key
     * @param  TValue|null                               $value
     * @return bool
     */
    public function containsStrict( $key, $value = null ) {
        if ( func_num_args() === 2 ) {
            return $this->contains( static fn( $item ) => data_get( $item, $key ) === $value );
        }

        if ( $this->useAsCallable( $key ) ) {
            return ! is_null( $this->first( $key ) );
        }

        foreach ( $this as $item ) {
            if ( $item === $key ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dump the items and end the script.
     *
     * @param  mixed ...$args
     * @return \never
     */
    public function dd( ...$args ) {
        $this->dump( ...$args );

        exit( 1 );
    }

    /**
     * Dump the items.
     *
     * @return $this
     */
    public function dump() {
        ( new Collection( func_get_args() ) )
            ->push( $this->all() )
            ->each( static function ( $item ) {
                VarDumper::dump( $item );
            });

        return $this;
    }

    /**
     * Execute a callback over each item.
     *
     * @param  callable(TValue, TKey): mixed $callback
     * @return $this
     */
    public function each( callable $callback ) {
        foreach ( $this as $key => $item ) {
            if ( $callback( $item, $key ) === false ) {
                break;
            }
        }

        return $this;
    }

    /**
     * Execute a callback over each nested chunk of items.
     *
     * @param  callable(...mixed): mixed  $callback
     * @return static
     */
    public function eachSpread( callable $callback ) {
        return $this->each( static function ( $chunk, $key ) use ( $callback ) {
            $chunk[] = $key;

            return $callback( ...$chunk );
        });
    }

    /**
     * Determine if all items pass the given truth test.
     *
     * @param  (callable(TValue, TKey): bool)|TValue|string $key
     * @param  mixed                                        $operator
     * @param  mixed                                        $value
     * @return bool
     */
    public function every( $key, $operator = null, $value = null ) {
        if ( func_num_args() === 1 ) {
            $callback = $this->valueRetriever( $key );

            foreach ( $this as $k => $v ) {
                if ( ! $callback( $v, $k ) ) {
                    return false;
                }
            }

            return true;
        }

        return $this->every( $this->operatorForWhere( ...func_get_args() ) );
    }

    /**
     * Get the first item by the given key value pair.
     *
     * @param  callable|string $key
     * @param  mixed           $operator
     * @param  mixed           $value
     * @return TValue|null
     */
    public function firstWhere( $key, $operator = null, $value = null ) {
        return $this->first( $this->operatorForWhere( ...func_get_args() ) );
    }

    /**
     * Get a single key's value from the first matching item in the collection.
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public function value( $key, $default = null ) {
        if ( $value = $this->firstWhere( $key ) ) {
            return data_get( $value, $key, $default );
        }

        return value( $default );
    }

    /**
     * Determine if the collection is not empty.
     *
     * @return bool
     */
    public function isNotEmpty() {
        return ! $this->isEmpty();
    }

    /**
     * Run a map over each nested chunk of items.
     *
     * @param  callable(mixed): TMapSpreadValue $callback
     * @return static<TKey, TMapSpreadValue>
     *
     * @template TMapSpreadValue
     */
    public function mapSpread( callable $callback ) {
        return $this->map( static function ( $chunk, $key ) use ( $callback ) {
            $chunk[] = $key;

            return $callback( ...$chunk );
        });
    }

    /**
     * Run a grouping map over the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @param  callable(TValue, TKey): array<TMapToGroupsKey, TMapToGroupsValue> $callback
     * @return static<TMapToGroupsKey, static<int, TMapToGroupsValue>>
     *
     * @template TMapToGroupsKey of array-key
     * @template TMapToGroupsValue
     */
    public function mapToGroups( callable $callback ) {
        $groups = $this->mapToDictionary( $callback );

        return $groups->map( [ $this, 'make' ] );
    }

    /**
     * Map a collection and flatten the result by a single level.
     *
     * @param  callable(TValue, TKey): mixed $callback
     * @return static<int, mixed>
     */
    public function flatMap( callable $callback ) {
        return $this->map( $callback )->collapse();
    }

    /**
     * Map the values into a new class.
     *
     * @param  class-string<TMapIntoValue> $class
     * @return static<TKey, TMapIntoValue>
     *
     * @template TMapIntoValue
     */
    public function mapInto( $class ) {
        return $this->map( static fn( $value, $key ) => new $class( $value, $key ) );
    }

    /**
     * Get the min value of a given key.
     *
     * @param  (callable(TValue):mixed)|string|null $callback
     * @return TValue
     */
    public function min( $callback = null ) {
        $callback = $this->valueRetriever( $callback );

        return $this->map( static fn( $value ) => $callback( $value ) )->filter( static fn( $value ) => ! is_null( $value ) )->reduce( static fn( $result, $value ) => is_null( $result ) || $value < $result ? $value : $result );
    }

    /**
     * Get the max value of a given key.
     *
     * @param  (callable(TValue):mixed)|string|null $callback
     * @return TValue
     */
    public function max( $callback = null ) {
        $callback = $this->valueRetriever( $callback );

        return $this->filter( static fn( $value ) => ! is_null( $value ) )->reduce(static function ( $result, $item ) use ( $callback ) {
            $value = $callback( $item );

            return is_null( $result ) || $value > $result ? $value : $result;
        });
    }

    /**
     * "Paginate" the collection by slicing it into a smaller collection.
     *
     * @param  int $page
     * @param  int $perPage
     * @return static
     */
    public function forPage( $page, $perPage ) {
        $offset = max( 0, ( $page - 1 ) * $perPage );

        return $this->slice( $offset, $perPage );
    }

    /**
     * Partition the collection into two arrays using the given callback or key.
     *
     * @param  (callable(TValue, TKey): bool)|TValue|string $key
     * @param  TValue|string|null                           $operator
     * @param  TValue|null                                  $value
     * @return static<int<0, 1>, static<TKey, TValue>>
     */
    public function partition( $key, $operator = null, $value = null ) {
        $passed = [];
        $failed = [];

        $callback = func_num_args() === 1
            ? $this->valueRetriever( $key )
            : $this->operatorForWhere( ...func_get_args() );

        foreach ( $this as $key => $item ) {
            if ( $callback( $item, $key ) ) {
                $passed[ $key ] = $item;
            } else {
                $failed[ $key ] = $item;
            }
        }

        return new static( [ new static( $passed ), new static( $failed ) ] );
    }

    /**
     * Get the sum of the given values.
     *
     * @param  (callable(TValue): mixed)|string|null $callback
     * @return mixed
     */
    public function sum( $callback = null ) {
        $callback = is_null( $callback )
            ? $this->identity()
            : $this->valueRetriever( $callback );

        return $this->reduce( static fn( $result, $item ) => $result + $callback( $item ), 0 );
    }

    /**
     * Apply the callback if the collection is empty.
     *
     * @param  (callable( $this): TWhenEmptyReturnType)  $callback
     * @param  (callable( $this): TWhenEmptyReturnType)|null  $default
     * @return $this|TWhenEmptyReturnType
     *
     * @template TWhenEmptyReturnType
     */
    public function whenEmpty( callable $callback, ?callable $default = null ) {
        return $this->when( $this->isEmpty(), $callback, $default );
    }

    /**
     * Apply the callback if the collection is not empty.
     *
     * @param  callable(  $this): TWhenNotEmptyReturnType  $callback
     * @param  (callable( $this): TWhenNotEmptyReturnType)|null  $default
     * @return $this|TWhenNotEmptyReturnType
     *
     * @template TWhenNotEmptyReturnType
     */
    public function whenNotEmpty( callable $callback, ?callable $default = null ) {
        return $this->when( $this->isNotEmpty(), $callback, $default );
    }

    /**
     * Apply the callback unless the collection is empty.
     *
     * @param  callable(  $this): TUnlessEmptyReturnType  $callback
     * @param  (callable( $this): TUnlessEmptyReturnType)|null  $default
     * @return $this|TUnlessEmptyReturnType
     *
     * @template TUnlessEmptyReturnType
     */
    public function unlessEmpty( callable $callback, ?callable $default = null ) {
        return $this->whenNotEmpty( $callback, $default );
    }

    /**
     * Apply the callback unless the collection is not empty.
     *
     * @param  callable(  $this): TUnlessNotEmptyReturnType  $callback
     * @param  (callable( $this): TUnlessNotEmptyReturnType)|null  $default
     * @return $this|TUnlessNotEmptyReturnType
     *
     * @template TUnlessNotEmptyReturnType
     */
    public function unlessNotEmpty( callable $callback, ?callable $default = null ) {
        return $this->whenEmpty( $callback, $default );
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param  callable|string $key
     * @param  mixed           $operator
     * @param  mixed           $value
     * @return static
     */
    public function where( $key, $operator = null, $value = null ) {
        return $this->filter( $this->operatorForWhere( ...func_get_args() ) );
    }

    /**
     * Filter items where the value for the given key is null.
     *
     * @param  string|null $key
     * @return static
     */
    public function whereNull( $key = null ) {
        return $this->whereStrict( $key, null );
    }

    /**
     * Filter items where the value for the given key is not null.
     *
     * @param  string|null $key
     * @return static
     */
    public function whereNotNull( $key = null ) {
        return $this->where( $key, '!==', null );
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return static
     */
    public function whereStrict( $key, $value ) {
        return $this->where( $key, '===', $value );
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param  string                               $key
     * @param  \Hybrid\Contracts\Arrayable|iterable $values
     * @param  bool                                 $strict
     * @return static
     */
    public function whereIn( $key, $values, $strict = false ) {
        $values = $this->getArrayableItems( $values );

        return $this->filter( static fn( $item ) => in_array( data_get( $item, $key ), $values, $strict ) );
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param  string                               $key
     * @param  \Hybrid\Contracts\Arrayable|iterable $values
     * @return static
     */
    public function whereInStrict( $key, $values ) {
        return $this->whereIn( $key, $values, true );
    }

    /**
     * Filter items such that the value of the given key is between the given values.
     *
     * @param  string                               $key
     * @param  \Hybrid\Contracts\Arrayable|iterable $values
     * @return static
     */
    public function whereBetween( $key, $values ) {
        return $this->where( $key, '>=', reset( $values ) )->where( $key, '<=', end( $values ) );
    }

    /**
     * Filter items such that the value of the given key is not between the given values.
     *
     * @param  string                               $key
     * @param  \Hybrid\Contracts\Arrayable|iterable $values
     * @return static
     */
    public function whereNotBetween( $key, $values ) {
        return $this->filter( static fn( $item ) => data_get( $item, $key ) < reset( $values ) || data_get( $item, $key ) > end( $values ) );
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param  string                               $key
     * @param  \Hybrid\Contracts\Arrayable|iterable $values
     * @param  bool                                 $strict
     * @return static
     */
    public function whereNotIn( $key, $values, $strict = false ) {
        $values = $this->getArrayableItems( $values );

        return $this->reject( static fn( $item ) => in_array( data_get( $item, $key ), $values, $strict ) );
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param  string                               $key
     * @param  \Hybrid\Contracts\Arrayable|iterable $values
     * @return static
     */
    public function whereNotInStrict( $key, $values ) {
        return $this->whereNotIn( $key, $values, true );
    }

    /**
     * Filter the items, removing any items that don't match the given type(s).
     *
     * @param  class-string<TWhereInstanceOf>|array<array-key, class-string<TWhereInstanceOf>> $type
     * @return static<TKey, TWhereInstanceOf>
     *
     * @template TWhereInstanceOf
     */
    public function whereInstanceOf( $type ) {
        return $this->filter( static function ( $value ) use ( $type ) {
            if ( is_array( $type ) ) {
                foreach ( $type as $classType ) {
                    if ( $value instanceof $classType ) {
                        return true;
                    }
                }

                return false;
            }

            return $value instanceof $type;
        });
    }

    /**
     * Pass the collection to the given callback and return the result.
     *
     * @param  callable( $this): TPipeReturnType  $callback
     * @return TPipeReturnType
     *
     * @template TPipeReturnType
     */
    public function pipe( callable $callback ) {
        return $callback( $this );
    }

    /**
     * Pass the collection into a new class.
     *
     * @param  class-string $class
     * @return mixed
     */
    public function pipeInto( $class ) {
        return new $class( $this );
    }

    /**
     * Pass the collection through a series of callable pipes and return the result.
     *
     * @param  array<callable> $callbacks
     * @return mixed
     */
    public function pipeThrough( $callbacks ) {
        return Collection::make( $callbacks )->reduce(
            static fn( $carry, $callback ) => $callback( $carry ),
            $this
        );
    }

    /**
     * Reduce the collection to a single value.
     *
     * @param  callable(TReduceInitial|TReduceReturnType, TValue, TKey): TReduceReturnType $callback
     * @param  TReduceInitial                                                              $initial
     * @return TReduceReturnType
     *
     * @template TReduceInitial
     * @template TReduceReturnType
     */
    public function reduce( callable $callback, $initial = null ) {
        $result = $initial;

        foreach ( $this as $key => $value ) {
            $result = $callback( $result, $value, $key );
        }

        return $result;
    }

    /**
     * Reduce the collection to multiple aggregate values.
     *
     * @param  mixed ...$initial
     * @return array
     * @throws \UnexpectedValueException
     */
    public function reduceSpread( callable $callback, ...$initial ) {
        $result = $initial;

        foreach ( $this as $key => $value ) {
            $result = call_user_func_array( $callback, array_merge( $result, [ $value, $key ] ) );

            if ( ! is_array( $result ) ) {
                throw new \UnexpectedValueException(sprintf(
                    "%s::reduceSpread expects reducer to return an array, but got a '%s' instead.",
                    class_basename( static::class ), gettype( $result )
                ));
            }
        }

        return $result;
    }

    /**
     * Create a collection of all elements that do not pass a given truth test.
     *
     * @param  (callable(TValue, TKey): bool)|bool $callback
     * @return static
     */
    public function reject( $callback = true ) {
        $useAsCallable = $this->useAsCallable( $callback );

        return $this->filter(static fn( $value, $key ) => $useAsCallable
                ? ! $callback( $value, $key )
        : $value !== $callback);
    }

    /**
     * Pass the collection to the given callback and then return it.
     *
     * @param  callable( $this): mixed  $callback
     * @return $this
     */
    public function tap( callable $callback ) {
        $callback( $this );

        return $this;
    }

    /**
     * Return only unique items from the collection array.
     *
     * @param  (callable(TValue, TKey): mixed)|string|null $key
     * @param  bool                                        $strict
     * @return static
     */
    public function unique( $key = null, $strict = false ) {
        $callback = $this->valueRetriever( $key );

        $exists = [];

        return $this->reject( static function ( $item, $key ) use ( $callback, $strict, &$exists ) {
            if ( in_array( $id = $callback( $item, $key ), $exists, $strict ) ) {
                return true;
            }

            $exists[] = $id;
        });
    }

    /**
     * Return only unique items from the collection array using strict comparison.
     *
     * @param  (callable(TValue, TKey): mixed)|string|null $key
     * @return static
     */
    public function uniqueStrict( $key = null ) {
        return $this->unique( $key, true );
    }

    /**
     * Collect the values into a collection.
     *
     * @return \Hybrid\Tools\Collection<TKey, TValue>
     */
    public function collect() {
        return new Collection( $this->all() );
    }

    /**
     * Get the collection of items as a plain array.
     *
     * @return array<TKey, mixed>
     */
    public function toArray() {
        return $this->map( static fn( $value ) => $value instanceof Arrayable ? $value->toArray() : $value )->all();
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array<TKey, mixed>
     */
    public function jsonSerialize(): array {
        return array_map(static function ( $value ) {
            if ( $value instanceof JsonSerializable ) {
                return $value->jsonSerialize();
            }

            if ( $value instanceof Jsonable ) {
                return json_decode( $value->toJson(), true );
            }

            if ( $value instanceof Arrayable ) {
                return $value->toArray();
            }

            return $value;
        }, $this->all());
    }

    /**
     * Get the collection of items as JSON.
     *
     * @param  int $options
     * @return string
     */
    public function toJson( $options = 0 ) {
        return json_encode( $this->jsonSerialize(), $options );
    }

    /**
     * Get a CachingIterator instance.
     *
     * @param  int $flags
     * @return \CachingIterator
     */
    public function getCachingIterator( $flags = CachingIterator::CALL_TOSTRING ) {
        return new CachingIterator( $this->getIterator(), $flags );
    }

    /**
     * Convert the collection to its string representation.
     *
     * @return string
     */
    public function __toString() {
        return $this->escapeWhenCastingToString
            ? e( $this->toJson() )
            : $this->toJson();
    }

    /**
     * Indicate that the model's string representation should be escaped when __toString is invoked.
     *
     * @param  bool $escape
     * @return $this
     */
    public function escapeWhenCastingToString( $escape = true ) {
        $this->escapeWhenCastingToString = $escape;

        return $this;
    }

    /**
     * Add a method to the list of proxied methods.
     *
     * @param  string $method
     * @return void
     */
    public static function proxy( $method ) {
        static::$proxies[] = $method;
    }

    /**
     * Dynamically access collection proxies.
     *
     * @param  string $key
     * @return mixed
     * @throws \Exception
     */
    public function __get( $key ) {
        if ( ! in_array( $key, static::$proxies ) ) {
            throw new \Exception( "Property [{$key}] does not exist on this collection instance." );
        }

        return new HigherOrderCollectionProxy( $this, $key );
    }

    /**
     * Results array of items from Collection or Arrayable.
     *
     * @param  mixed $items
     * @return array<TKey, TValue>
     */
    protected function getArrayableItems( $items ) {
        if ( is_array( $items ) ) {
            return $items;
        }

        if ( $items instanceof Enumerable ) {
            return $items->all();
        }

        if ( $items instanceof Arrayable ) {
            return $items->toArray();
        }

        if ( $items instanceof Jsonable ) {
            return json_decode( $items->toJson(), true );
        }

        if ( $items instanceof JsonSerializable ) {
            return (array) $items->jsonSerialize();
        }

        if ( $items instanceof Traversable ) {
            return iterator_to_array( $items );
        }

        if ( $items instanceof UnitEnum ) {
            return [ $items ];
        }

        return (array) $items;
    }

    /**
     * Get an operator checker callback.
     *
     * @param  callable|string $key
     * @param  string|null     $operator
     * @param  mixed           $value
     * @return \Closure
     */
    protected function operatorForWhere( $key, $operator = null, $value = null ) {
        if ( $this->useAsCallable( $key ) ) {
            return $key;
        }

        if ( func_num_args() === 1 ) {
            $value = true;

            $operator = '=';
        }

        if ( func_num_args() === 2 ) {
            $value = $operator;

            $operator = '=';
        }

        return static function ( $item ) use ( $key, $operator, $value ) {
            $retrieved = data_get( $item, $key );

            $strings = array_filter( [ $retrieved, $value ], static fn( $value ) => is_string( $value ) || ( is_object( $value ) && method_exists( $value, '__toString' ) ) );

            if ( count( $strings ) < 2 && count( array_filter( [ $retrieved, $value ], 'is_object' ) ) === 1 ) {
                return in_array( $operator, [ '!=', '<>', '!==' ] );
            }

            switch ( $operator ) {
                default:
                case '=':
                case '==':
                    return $retrieved === $value;
                case '!=':
                case '<>':
                    return $retrieved !== $value;
                case '<':
                    return $retrieved < $value;
                case '>':
                    return $retrieved > $value;
                case '<=':
                    return $retrieved <= $value;
                case '>=':
                    return $retrieved >= $value;
                case '===':
                    return $retrieved === $value;
                case '!==':
                    return $retrieved !== $value;
            }
        };
    }

    /**
     * Determine if the given value is callable, but not a string.
     *
     * @param  mixed $value
     * @return bool
     */
    protected function useAsCallable( $value ) {
        return ! is_string( $value ) && is_callable( $value );
    }

    /**
     * Get a value retrieving callback.
     *
     * @param  callable|string|null $value
     * @return callable
     */
    protected function valueRetriever( $value ) {
        if ( $this->useAsCallable( $value ) ) {
            return $value;
        }

        return static fn( $item ) => data_get( $item, $value );
    }

    /**
     * Make a function to check an item's equality.
     *
     * @param  mixed $value
     * @return \Closure(mixed): bool
     */
    protected function equality( $value ) {
        return static fn( $item ) => $item === $value;
    }

    /**
     * Make a function using another function, by negating its result.
     *
     * @return \Closure
     */
    protected function negate( Closure $callback ) {
        return static fn( ...$params ) => ! $callback( ...$params );
    }

    /**
     * Make a function that returns what's passed to it.
     *
     * @return \Closure(TValue): TValue
     */
    protected function identity() {
        return static fn( $value ) => $value;
    }

}
