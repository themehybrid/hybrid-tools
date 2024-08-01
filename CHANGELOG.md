# Change Log

You can see the changes made via the [commit log](https://github.com/themehybrid/hybrid-tools/commits/master) for the latest release.

## [2.0.3] - 2024-08-01

### Changed

- sync with https://github.com/illuminate/support/releases/tag/v11.18.1
- sync with https://github.com/illuminate/collections/releases/tag/v11.18.1
- sync with https://github.com/illuminate/conditionable/releases/tag/v11.18.1
- sync with https://github.com/illuminate/config/releases/tag/v11.18.1
- sync with https://github.com/illuminate/macroable/releases/tag/v11.18.1
- Add composer minimum-stability to "dev"
- Add composer prefer-stable to true
- Add composer sort-packages configuration
- Update copyright date
- Update lint php

## [2.0.2] - 2024-06-11

### Changed

- fixed typo and performed PHP linting

## [2.0.1] - 2023-11-07

### Changed

- sync with https://github.com/illuminate/collections/releases/tag/v10.25.1
- sync with https://github.com/illuminate/support/releases/tag/v10.25.1
- add `with` helper function
- add `Benchmark` class
- add `AccessiblePrivateMethods` trait class for WordPress filters & actions use with private methods
- add `maybe_define_constant`, `get_child_theme_file_path`, `get_child_theme_file_uri` WordPress helper functions

## [2.0.0] - 2023-08-23

### Changed

- fix typo

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
