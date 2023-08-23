# Change Log

You can see the changes made via the [commit log](https://github.com/themehybrid/hybrid-tools/commits/master) for the latest release.

## [2.0.0-beta.4] - 2023-08-03

- lint php files

## [2.0.0-beta.3] - 2023-06-13

### Changed

- composer ramsey/uuid make version generic, so could target specific version in root composer, using ^4 allows for specifying lower version in theme's composer.json for PHP v7.4 compatibility

## [2.0.0-beta.2] - 2023-06-08

### Changed

- composer move `vlucas/phpdotenv` to require packages

## [2.0.0-beta.1] - 2023-02-22

### Changed

- Add back deprecated second param to `Hybrid\Tools\Collection::add()` for legacy support
- Add `str` helper function

## [2.0.0.alpha.1] - 2023-02-01

### Added / Changed

- Collections: [v9.24.0](https://github.com/illuminate/collections/tree/v9.24.0)
  - Traits\EnumeratesValues.php
  - Arr
  - Collection
  - Enumerable
  - HigherOrderCollectionProxy
  - ItemNotFoundException
  - LazyCollection
  - MultipleItemsFoundException
- Conditionable: [v9.24.0](https://github.com/illuminate/conditionable/tree/v9.24.0)
  - Traits\Conditionable
  - HigherOrderWhenProxy
- Macroable: [v9.24.0](https://github.com/illuminate/macroable/tree/v9.24.0)
  - Traits\Macroable
- Support: [v9.24.0](https://github.com/illuminate/support/tree/v9.24.0)
  - Facades\Config
  - Facades\Date
  - Traits\ForwardsCalls
  - Traits\ReflectsClosures
  - Traits\Tappable
  - Carbon
  - DateFactory
  - Env
  - Fluent
  - HigherOrderTapProxy
  - HtmlString
  - InteractsWithTime
  - Reflector
  - Str
  - Stringable
- Config: [v9.24.0](https://github.com/illuminate/config/tree/v9.24.0)
  - Config\Repository

## [1.0.1] - 2023-08-03

### Changed

- lint php files
- lint composer.json
- replace references to urls from http to https
- bump php version req from 5.6 -> 7.4
- update copyright date
- update copyright author


## [1.0.0] - 2021-08-01

### Added

- Launch.  Everything's new!
