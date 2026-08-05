<?php

/**
 * SVG support.
 *
 * Adds WordPress media library support for SVG files.
 *
 * WordPress refuses SVG uploads by default, and for good reason: an SVG is an
 * XML document that can carry `<script>`, event handlers, and external entity
 * references, so an uploaded SVG is a stored-XSS vector served from your own
 * origin. Enabling uploads is therefore only half the job — the other half is
 * making sure only trusted roles can do it, and that the markup is sanitized
 * before it ever lands in the uploads directory.
 *
 * Beyond security, WordPress's image pipeline assumes raster files. It reads
 * dimensions with getimagesize(), which returns nothing useful for a vector,
 * so SVGs surface as 1x1 pixels in the media grid, generate broken srcset
 * attributes, and get queued for thumbnail regeneration that can never
 * succeed. The filters below patch each of those assumptions.
 *
 * Sanitization itself is deliberately left abstract — see sanitize().
 */

namespace Hybrid\Tools\WordPress;

class SvgSupport {
    /**
     * MIME type WordPress uses for both .svg and .svgz files.
     */
    protected const MIME = 'image/svg+xml';

    /**
     * Fallback dimensions, in pixels, for an SVG whose real size can't be
     * determined. Something sensible is better than zero here: a 0x0 image
     * collapses in the media grid and in the block editor.
     */
    protected const FALLBACK_DIMENSION = 100;

    /**
     * Boot.
     */
    public function boot(): void {
        // Registering the MIME type globally is safe — this only teaches
        // WordPress what a .svg *is*, it doesn't grant permission to upload one.
        add_filter( 'mime_types', $this->addSvgMimeTypes( ...) );

        // Upload permission, by contrast, is granted narrowly. Rather than
        // opening `upload_mimes` for every request, it's opened only on the
        // screens that need it and for the duration of an actual upload, then
        // closed again. A permanently-open upload_mimes filter means every
        // plugin, REST endpoint, and sideload path in the site inherits SVG
        // upload rights whether it wanted them or not.
        add_action( 'load-upload.php', $this->allowSvgUploads( ...) );
        add_action( 'load-post.php', $this->allowSvgUploads( ...) );
        add_action( 'load-post-new.php', $this->allowSvgUploads( ...) );
        add_action( 'load-site-editor.php', $this->allowSvgUploads( ...) );

        // Sanitize (or reject) the file itself while it's still in the temp
        // directory, before WordPress moves it into wp-content/uploads.
        add_filter( 'wp_handle_upload_prefilter', $this->checkForSvg( ...) );
        add_filter( 'wp_handle_sideload_prefilter', $this->checkForSvg( ...) );

        // Teach the rest of the media pipeline how to treat a vector.
        add_filter( 'wp_get_attachment_image_src', $this->addSvgDimensions( ...), 10, 4 );
        add_filter( 'wp_prepare_attachment_for_js', $this->fixAdminPreview( ...), 10, 2 );
        add_filter( 'wp_generate_attachment_metadata', $this->skipSvgRegeneration( ...), 10, 2 );
        add_filter( 'wp_calculate_image_srcset_meta', $this->disableSrcset( ...), 10, 4 );
        add_filter( 'wp_mime_type_icon', $this->addSvgIconMimeType( ...), 10, 3 );
    }

    /**
     * Whether the current user may upload SVGs.
     *
     * Split out as its own method so a consuming theme or plugin can subclass
     * and tighten this — restricting SVG uploads to administrators is a common
     * and reasonable policy, since `upload_files` is held by Authors and above.
     */
    protected function currentUserCanUploadSvg(): bool {
        return current_user_can( 'upload_files' );
    }

    /**
     * Sanitize raw SVG markup, returning the cleaned markup, or null when the
     * file can't be made safe and should be rejected.
     *
     * Intentionally unimplemented here. hybrid-tools has no XML-sanitizing
     * dependency, and a half-measure (a regex that strips `<script>`, say) is
     * worse than none, because it invites the false confidence that the file
     * is now safe. Real sanitization has to handle entity expansion, namespaced
     * event handlers, `javascript:` and `data:` URLs, `<foreignObject>`, and
     * xlink references — that's a library's job, not a method's.
     *
     * The default therefore fails closed: no sanitizer, no upload. Subclass and
     * implement this with your sanitizer of choice to enable SVG uploads.
     *
     * @param string $markup Raw file contents.
     *
     * @return string|null Sanitized markup, or null to reject the upload.
     */
    protected function sanitize( string $markup ): ?string {
        return null;
    }

    /**
     * Open SVG uploads for the current request.
     */
    protected function allowSvgUploads(): void {
        add_filter( 'upload_mimes', $this->addSvgUploadMimeTypes( ...) );
        add_filter( 'wp_check_filetype_and_ext', $this->fixSvgFileType( ...), 75, 4 );
    }

    /**
     * Close SVG uploads again.
     *
     * Hooked on `pre_move_uploaded_file` rather than immediately after the
     * check, because `wp_check_filetype_and_ext()` runs several times across a
     * single upload; removing the filters any earlier makes a later pass reject
     * the file we just approved.
     *
     * @param mixed $moveNewFile Passed through untouched.
     */
    protected function disallowSvgUploads( $moveNewFile ) {
        remove_filter( 'upload_mimes', $this->addSvgUploadMimeTypes( ...) );
        remove_filter( 'wp_check_filetype_and_ext', $this->fixSvgFileType( ...), 75 );

        return $moveNewFile;
    }

    /**
     * Register the SVG mime type extensions.
     *
     * This filter is the site-wide extension-to-MIME map, used for reading
     * (serving, listing, filtering the media library). It does not by itself
     * permit uploads — that's `upload_mimes`, below.
     *
     * @param array $mimeTypes Mime types keyed by the file extension regex
     *                         corresponding to those types.
     */
    protected function addSvgMimeTypes( $mimeTypes ) {
        $mimeTypes['svg']  = self::MIME;
        $mimeTypes['svgz'] = self::MIME;

        return $mimeTypes;
    }

    /**
     * Permit SVG uploads, for users allowed to make them.
     *
     * @param array $mimes Mime types keyed by the file extension regex
     *                     corresponding to those types.
     */
    protected function addSvgUploadMimeTypes( $mimes ) {
        if ( $this->currentUserCanUploadSvg() ) {
            $mimes['svg']  = self::MIME;
            $mimes['svgz'] = self::MIME;
        }

        return $mimes;
    }

    /**
     * Correct WordPress's filetype detection for SVG uploads.
     *
     * WordPress verifies an upload by sniffing its real contents and comparing
     * that to the extension. SVG is plain XML with no magic bytes, so the sniff
     * comes back as text/plain (or empty) and the upload is refused as a
     * mismatch. This restores the correct type — but *only* for .svg/.svgz.
     *
     * The narrowing matters: rewriting `$data` for every extension would
     * substitute filename-based guessing for real content verification across
     * the whole site, which is precisely the check that stops someone uploading
     * a PHP file named `harmless.jpg`.
     *
     * @param array  $data Values for the extension, mime type, and corrected filename.
     * @param string $file Full path to the file.
     * @param string $filename The name of the file (may differ from $file, as $file
     *                         may be in a tmp directory).
     * @param array  $mimes Key is the file extension with value as the mime type.
     */
    protected function fixSvgFileType( $data = null, $file = null, $filename = null, $mimes = null ) {
        $extension = isset( $data['ext'] ) ? (string) $data['ext'] : '';

        // WordPress couldn't determine an extension, so fall back to the name.
        if ( '' === $extension ) {
            $extension = strtolower( (string) pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
        }

        if ( in_array( $extension, [ 'svg', 'svgz' ], true ) ) {
            $data['ext']  = $extension;
            $data['type'] = self::MIME;
        }

        return $data;
    }

    /**
     * Intercept an SVG upload to authorize and sanitize it.
     *
     * Runs on the prefilter hooks, so the file is still in the temp directory:
     * rejecting here means nothing untrusted is ever written to uploads.
     *
     * @param array $file An array of data for a single file.
     */
    protected function checkForSvg( $file ) {
        if ( ! isset( $file['tmp_name'] ) ) {
            return $file;
        }

        // Temporarily allow SVGs so wp_check_filetype_and_ext() can identify
        // this file as one, then schedule the permission to be revoked once the
        // upload completes.
        $this->allowSvgUploads();
        add_filter( 'pre_move_uploaded_file', $this->disallowSvgUploads( ...) );

        $filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] ?? '' );

        if ( self::MIME !== ( $filetype['type'] ?? '' ) ) {
            return $file;
        }

        if ( ! $this->currentUserCanUploadSvg() ) {
            $file['error'] = __( 'Sorry, you are not allowed to upload SVG files.' );

            return $file;
        }

        $markup = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

        if ( false === $markup ) {
            $file['error'] = __( 'Sorry, this file could not be read.' );

            return $file;
        }

        // .svgz is gzip-compressed SVG. Decompress before sanitizing, or the
        // sanitizer inspects compressed bytes, finds no markup to object to,
        // and waves through whatever the archive actually contains.
        $isCompressed = $this->isGzipped( $markup );

        if ( $isCompressed ) {
            $markup = gzdecode( $markup );

            if ( false === $markup ) {
                $file['error'] = __( 'Sorry, this file could not be decompressed.' );

                return $file;
            }
        }

        $sanitized = $this->sanitize( $markup );

        if ( null === $sanitized ) {
            $file['error'] = __( "Sorry, this file couldn't be sanitized so for security reasons wasn't uploaded." );

            return $file;
        }

        // Write the sanitized markup back, preserving the original encoding so
        // a .svgz stays a valid gzip file.
        file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
            $file['tmp_name'],
            $isCompressed ? gzencode( $sanitized ) : $sanitized
        );

        return $file;
    }

    /**
     * Whether the given contents are gzip-encoded, per the gzip magic number.
     *
     * @param string $contents Raw file contents.
     */
    protected function isGzipped( string $contents ): bool {
        return str_starts_with( $contents, "\x1f\x8b" );
    }

    /**
     * Supply real dimensions for SVG attachments.
     *
     * getimagesize() can't measure a vector, so WordPress records SVGs as 1x1
     * and every template that trusts those numbers renders a one-pixel image.
     *
     * @param array|false  $image Either array with src, width & height, icon src, or false.
     * @param int          $attachmentId Image attachment ID.
     * @param string|array $size Requested size, or array of width and height values.
     * @param bool         $icon Whether the image should be treated as an icon.
     *
     * @return array|false Either array with src, width & height, icon src, or false.
     */
    protected function addSvgDimensions( $image, $attachmentId = null, $size = 'thumbnail', $icon = false ) {
        // Identify SVGs by their recorded MIME type rather than by pattern
        // matching the URL: the src may carry a query string or point at a CDN
        // that rewrites the extension entirely.
        if ( ! is_array( $image ) || self::MIME !== get_post_mime_type( $attachmentId ) ) {
            return $image;
        }

        // An explicitly requested size wins — the caller has told us what it wants.
        if ( is_array( $size ) ) {
            $image[1] = $size[0];
            $image[2] = $size[1];

            return $image;
        }

        $dimensions = $this->svgDimensions( $attachmentId );

        $image[1] = $dimensions['width'];
        $image[2] = $dimensions['height'];

        return $image;
    }

    /**
     * Read an SVG's intrinsic dimensions.
     *
     * @param int $attachmentId Attachment ID.
     *
     * @return array{width: int, height: int}
     */
    protected function svgDimensions( $attachmentId ): array {
        $fallback = [
            'width'  => self::FALLBACK_DIMENSION,
            'height' => self::FALLBACK_DIMENSION,
        ];

        // Prefer stored metadata, so the file is parsed once on upload rather
        // than on every image_src call for the life of the attachment.
        $metadata = wp_get_attachment_metadata( $attachmentId );

        if ( ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) ) {
            return [
                'width'  => (int) $metadata['width'],
                'height' => (int) $metadata['height'],
            ];
        }

        // Read the file from disk via its attachment path. Never from the
        // attachment URL — that turns a template render into an outbound HTTP
        // request from the server to itself, on every uncached call.
        $file = get_attached_file( $attachmentId );

        if ( ! $file || ! is_readable( $file ) || ! function_exists( 'simplexml_load_string' ) ) {
            return $fallback;
        }

        $markup = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

        if ( false === $markup ) {
            return $fallback;
        }

        if ( $this->isGzipped( $markup ) ) {
            $markup = gzdecode( $markup );

            if ( false === $markup ) {
                return $fallback;
            }
        }

        $xml = @simplexml_load_string( $markup ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

        if ( false === $xml ) {
            return $fallback;
        }

        $attributes = $xml->attributes();

        // viewBox is preferred over width/height. It's the SVG's true coordinate
        // space and is almost always present, whereas width/height are often
        // omitted, or set to a percentage for responsive scaling — and "100%"
        // describes a ratio to the container, not a pixel size we can use.
        if ( isset( $attributes->viewBox ) ) {
            $viewbox = preg_split( '/[\s,]+/', trim( (string) $attributes->viewBox ) );

            if ( is_array( $viewbox ) && isset( $viewbox[2], $viewbox[3] ) ) {
                return [
                    'width'  => (int) round( (float) $viewbox[2] ),
                    'height' => (int) round( (float) $viewbox[3] ),
                ];
            }
        }

        if ( isset( $attributes->width, $attributes->height ) ) {
            $width  = (string) $attributes->width;
            $height = (string) $attributes->height;

            if ( is_numeric( $width ) && is_numeric( $height ) ) {
                return [
                    'width'  => (int) round( (float) $width ),
                    'height' => (int) round( (float) $height ),
                ];
            }
        }

        return $fallback;
    }

    /**
     * Store real dimensions on upload, and skip thumbnail generation.
     *
     * WordPress would otherwise try to resize the SVG into every registered
     * image size — work that can't succeed on a vector, and needn't: one file
     * scales to every size by definition. The registered sizes are still
     * described in the metadata, all pointing at the single original, so code
     * that asks for a 'thumbnail' gets a working URL back.
     *
     * @param array $metadata An array of attachment meta data.
     * @param int   $attachmentId Attachment ID.
     */
    protected function skipSvgRegeneration( $metadata, $attachmentId ) {
        if ( self::MIME !== get_post_mime_type( $attachmentId ) ) {
            return $metadata;
        }

        $file = get_attached_file( $attachmentId );

        if ( ! $file ) {
            return $metadata;
        }

        $uploadDirectory = wp_upload_dir();
        $dimensions      = $this->svgDimensions( $attachmentId );

        $metadata = [
            'width'  => $dimensions['width'],
            'height' => $dimensions['height'],
            'file'   => str_replace( trailingslashit( $uploadDirectory['basedir'] ), '', $file ),
            'sizes'  => [],
        ];

        foreach ( get_intermediate_image_sizes() as $size ) {
            $metadata['sizes'][ $size ] = [
                'width'     => $dimensions['width'],
                'height'    => $dimensions['height'],
                'crop'      => false,
                'file'      => basename( $file ),
                'mime-type' => self::MIME,
            ];
        }

        return $metadata;
    }

    /**
     * Describe SVGs correctly to the media library's JavaScript.
     *
     * Backbone media views read dimensions from this response to lay out the
     * grid and the attachment details pane; without it SVGs render as slivers.
     * Pointing `icon` at the file itself makes the grid show the actual artwork
     * instead of the generic document placeholder.
     *
     * @param array    $response Array of prepared attachment data.
     * @param \WP_Post $attachment Attachment object.
     */
    protected function fixAdminPreview( $response, $attachment ) {
        if ( self::MIME !== ( $response['mime'] ?? '' ) ) {
            return $response;
        }

        $dimensions = $this->svgDimensions( $attachment->ID );

        $response['width']       = $dimensions['width'];
        $response['height']      = $dimensions['height'];
        $response['orientation'] = $dimensions['width'] > $dimensions['height']
            ? 'landscape'
            : 'portrait';
        $response['icon']        = $response['url'];

        return $response;
    }

    /**
     * Prevent srcset generation for SVGs.
     *
     * Responsive srcset exists to offer the browser differently-sized copies of
     * a raster image. A vector has no such copies — the sizes recorded above are
     * all the same file — so emitting a srcset would advertise several widths
     * that resolve to one identical URL.
     *
     * @param array  $imageMeta The image meta data.
     * @param int[]  $sizeArray Requested width and height values.
     * @param string $imageSrc The 'src' of the image.
     * @param int    $attachmentId The image attachment ID.
     */
    protected function disableSrcset( $imageMeta, $sizeArray, $imageSrc, $attachmentId ) {
        if ( $attachmentId && is_array( $imageMeta ) && self::MIME === get_post_mime_type( $attachmentId ) ) {
            $imageMeta['sizes'] = [];
        }

        return $imageMeta;
    }

    /**
     * Use the SVG itself as its media-library icon.
     *
     * WordPress falls back to a generic document icon for any MIME it can't
     * render. An SVG is perfectly renderable in-browser, so show the real thing.
     *
     * @param string $icon Path to the mime type icon.
     * @param string $mime Mime type.
     * @param int    $postId Attachment ID, or 0 if the function was passed the mime type.
     */
    protected function addSvgIconMimeType( $icon, $mime, $postId ) {
        if ( ! in_array( $mime, [ self::MIME, 'image/x-icon' ], true ) || 0 >= $postId ) {
            return $icon;
        }

        $source = wp_get_attachment_image_src( $postId );

        return is_array( $source ) ? array_shift( $source ) : $icon;
    }
}
