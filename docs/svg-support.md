# SVG Support

`Hybrid\Tools\WordPress\SvgSupport` adds SVG files to the WordPress media library:
upload permission, sanitization, and the media-library fixes a vector format needs.

It ships no sanitizer. The base `sanitize()` returns `null`, which rejects every
upload — so SVGs stay disabled until you subclass and supply one.

## Setup

Install a sanitizer. [enshrined/svg-sanitize][1] is the established choice:

```
composer require enshrined/svg-sanitize
```

Subclass and implement `sanitize()`:

```php
namespace App\Media;

use enshrined\svgSanitize\Sanitizer;
use Hybrid\Tools\WordPress\SvgSupport as BaseSvgSupport;

class SvgSupport extends BaseSvgSupport {

    protected function sanitize( string $markup ): ?string {
        if ( ! class_exists( Sanitizer::class ) ) {
            return null;
        }

        $sanitizer = new Sanitizer();

        // Optional. Omit to use the library's own allowlist. Custom classes
        // must implement enshrined's TagInterface and AttributeInterface.
        $sanitizer->setAllowedTags( new SvgTags() );
        $sanitizer->setAllowedAttrs( new SvgAttributes() );

        $sanitizer->minify( true );

        // Allowing huge files removes libxml's guard against billion-laughs
        // entity expansion. Leave off unless you trust your uploaders.
        $sanitizer->setAllowHugeFiles( false );

        $clean = $sanitizer->sanitize( $markup );

        // False means the document couldn't be parsed at all.
        return false === $clean ? null : $clean;
    }
}
```

Register and boot it in a service provider:

```php
use Hybrid\Core\ServiceProvider;
use Hybrid\Tools\WordPress\SvgSupport as BaseSvgSupport;

class MediaServiceProvider extends ServiceProvider {

    public function register(): void {
        // Bind the base to your subclass, so anything type-hinting the base
        // gets the instance that can actually sanitize. Resolving the base
        // directly hands back the fail-closed default and rejects everything.
        $this->app->singleton( BaseSvgSupport::class, SvgSupport::class );
    }

    public function boot(): void {
        $this->app->resolve( BaseSvgSupport::class )->boot();
    }
}
```

Outside a hybrid-core project, `( new SvgSupport() )->boot()` on `after_setup_theme`
works too — `boot()` needs to run before the admin screen load actions fire.

## Sanitizing is not rejecting

`enshrined/svg-sanitize` is a scrubber. Given a file containing `<script>`, it removes
the block and returns the cleaned document; it returns `false` only when libxml can't
parse the input. A deliberately malicious SVG will therefore **upload successfully**,
with the payload stripped. That's the intended outcome — the file on disk is safe.

For reject-on-suspicion, scan the raw markup yourself before sanitizing and return
`null` on a match. That's upload policy, not a security control; the sanitizer is
still the actual defense.

## Permissions

`currentUserCanUploadSvg()` defaults to `upload_files`, which Authors hold. For a
format that can carry script, administrators-only is a reasonable tightening:

```php
protected function currentUserCanUploadSvg(): bool {
    return current_user_can( 'manage_options' );
}
```

The check runs before the file is read.

Upload permission itself is scoped narrowly: `upload_mimes` is opened only on the
media, post, and site editor screens plus the duration of an upload, then closed on
`pre_move_uploaded_file`. (Teardown is deferred because `wp_check_filetype_and_ext()`
runs several times per upload; removing the filters earlier makes a later pass reject
the file just approved.) The global `mime_types` registration grants nothing — it only
teaches WordPress what a `.svg` is.

## Upload behaviour

Intercepted on `wp_handle_upload_prefilter` and `wp_handle_sideload_prefilter`, while
the file is still in the temp directory. Rejected with `$file['error']` set when:

- the user lacks permission
- the file can't be read
- a `.svgz` fails to decompress
- `sanitize()` returns `null`

Otherwise the sanitized markup is written back over the temp file.

`.svgz` is decompressed before sanitizing and re-encoded after, so `sanitize()` always
receives and returns plain markup — compression is handled for you.

## Media library fixes

WordPress's image pipeline assumes raster files, so `SvgSupport` also patches
dimensions (`wp_get_attachment_image_src`), the admin preview, thumbnail regeneration
(skipped), srcset (disabled), and the MIME icon.

Dimensions resolve in order: stored metadata → root `viewBox` → root `width`/`height`
if numeric → 100×100 fallback. `viewBox` is preferred because it's the true coordinate
space; `width`/`height` are often percentages or unit-suffixed. Parsing needs
`simplexml_load_string()`.

[1]: https://github.com/darylldoyle/svg-sanitizer
