# Working with images

Library includes two main classes for work with images:
- [`Fronty\ResponsiveImages\UploadImage`](#uploadimage-object) for images uploaded by user in WP Media Gallery
- [`Fronty\ResponsiveImages\ThemeImage`](#themeimage-object) for images located inside your theme directory (logos, backgrounds, etc.)

Methods of both classes, which generates HTML output are always available in two variants:
- with `get` prefix returns [Html](https://doc.nette.org/en/3.0/html-elements) object for futher modification
- without `get` prefix outputs HTML directly

> All methods generating \<img\> tag sets `width` and `height` attributes properly from ImageSize, or from widest size found in ImageSizeList in case of responsive images. If height is not given explicitly, it will be calculated proprtionaly.

---

## Shared methods
Both UploadImage and ThemeImage class extends mutual abstract parent class `Fronty\ResponsiveImages\BaseImage`, which contains or forces following shared methods:

### `isSvg()`

Check if image is SVG.

- `@return bool`

### `getPathinfo()`

Returns image [pathinfo](https://www.php.net/manual/en/function.pathinfo.php) as the ArrayHash.

- `@return ArrayHash` [ArrayHash](https://doc.nette.org/en/3.0/arrays#toc-arrayhash) object with pathinfo data.

### `getUrl()` and `getPath()`
Returns fully qualified URL or absolute path of image original size. In context of `UploadImage`, this always returns url or path on your server (not from Cloudinary).

> These methos in ThemeImage object uses `fri_theme_img_url` and `fri_theme_img_path` filters, see [more about WP filters](./wp-filters.md).

- `@return string` Fully qualified URL or path of the image on local server.

### `getImgTag()` and `imgTag()`

Returns or output non-responsive \<img\> HTML tag. Useful also for SVG in \<img\> tags.

- `@param ImageSize $size` How to resize image. See [ImageSize docs](./image-sizes.md#imagesize-object).
- `@param array $attrs = []` Optional image tag HTML attributes.
- `@return Html` [Html object](https://doc.nette.org/en/3.0/html-elements) of \<img\>.

> Uses `fri_upload_img_tag` with UploadImage and `fri_theme_img_tag` in ThemeImage, see [more about WP filters](./wp-filters.md).

Example:
```php
use Fronty\ResponsiveImages\UploadImage;
use Fronty\ResponsiveImages\Sizes\ImageSize;

(new UploadImage($attachment_id))->imgTag(new ImageSize(400, 200), ['alt' => 'My uploaded image']);

(new ThemeImage('my-image.jpg'))->imgTag(new ImageSize(400, 200), ['alt' => 'My theme image']);

// With factory function
img_upload($attachment_id)->imgTag(img_size(400, 200), ['alt' => 'My uploaded image']);
img_theme('my-image.jpg')->imgTag(img_size(400, 200), ['alt' => 'My theme image']);
```

Output:
```html
<img
	src="https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_400,h_200,c_fill/your-project/2021/05/your-image.jpg"
	width="400" height="200"
	alt="My uploaded image">

<img
	src="https://your-project.com/wp-content/themes/your-theme/my-image.jpg"
	width="400" height="200"
	alt="My theme image">
```

### `getInlineSvg()` and `inlineSvg()`

Return or ouput inline \<svg\>. All classes in SVG are changed to unique using random hash. In UploadImage, the SVG is also sanitized using [darylldoyle/svg-sanitizer](https://github.com/darylldoyle/svg-sanitizer).

When the file can't be read (eg. a mistyped icon name) or the sanitizer rejects the SVG (eg. its root element is not \<svg\>), an empty string is returned and the reason is reported through the [`fri_image_issue` action](./wp-filters.md#shared-action), so it doesn't disappear from the output without a trace.

- `@return string` SVG code, empty string when the file can't be read or the sanitizer rejects it.
- `@throws LogicException` If the image is not of SVG type. Check image using `isSvg()` before.

---

## UploadImage object

> All methods generating \<img\> tag takes care of **`alt`** attribute. If you don't specify it explicitly in $attrs argument, it will be set automatically from "Alternative text" or "Title" field in WP Media Library. To avoid the title (usually just a file name), pass your own alt using [`getAlt()`](#getalt).
>
> Attributes are escaped by the [Html object](https://doc.nette.org/en/3.0/html-elements), so pass them unescaped. HTML tags are stripped from `alt` and entities (eg. `&nbsp;`) are decoded.

### `__construct()`

- `@param int $attachment_id` ID of WP attachment post.

Example:
```php
use Fronty\ResponsiveImages\UploadImage;

$img = new UploadImage($attachment_id);

// Or using suggested factory function:
$img = img_upload($attachment_id);
```

### `isSvg()`, `getPathinfo()`, `getUrl()`, `getPath()`, `getInlineSvg()`, `inlineSvg()`, `getImgTag()` and `imgTag()`

These methods is documented in [Shared methods above](#shared-methods).

### `getAlt()`

Returns "Alternative text" set in WP Media Library. Unlike the automatic `alt` of \<img\> tags, "Title" field is never used.

- `@param string $fallback = ''` Returned when the alternative text is empty, eg. a heading next to the image. Keep the default empty string for decorative images.
- `@return string`

Example:
```php
$img = img_upload($attachment_id);

// Content image: heading as a fallback
$img->imgTag(img_size(400, 250), ['alt' => $img->getAlt($heading)]);

// Decorative image: empty alt unless set in Media Library
$img->imgTag(img_size(1920), ['alt' => $img->getAlt()]);
```

### `getSizedSrc()`

Returns fully qualified URL, width and height of an image in required size. Returns URL from Cloudinary if Auto Cloudinary plugin is active, otherwise returns scaled image from local server.

- `@param ImageSize $size` Size to which to scale image. See [ImageSize docs](./image-sizes.md#imagesize-object).
- `@return array` [ 0 => string URL, 1 => int width, 2 => int height ]

### `getImgLink()`

Get [Html object](https://doc.nette.org/en/3.0/html-elements) of \<a\> tag, which href points to image scaled by given ImageSize. Useful when creating lightbox links.

- `@param array $attrs = []` Optional HTML attributes for \<a\> tag.
- `@param ImageSize $size = null` Size to which scale the linked image. Unscaled image will be returned if null given. See [ImageSize docs](./image-sizes.md#imagesize-object).
- `@return Html` [Html object](https://doc.nette.org/en/3.0/html-elements) of \<a\> link without any children.

Example:
```php
use Fronty\ResponsiveImages\UploadImage;
use Fronty\ResponsiveImages\Sizes\ImageSize;

$img = new UploadImage($attachment_id);

// Add any Html object inside the link object
echo $img->getImgLink(['class' => 'lightbox'], new ImageSize(1920))
	->addHtml(
		$img->getImgTag(new ImageSize(400, 250))
	);

// Same with suggested factory functions
$img = img_upload($attachment_id);
echo $img->getImgLink(['class' => 'lightbox'], img_size(1920))
	->addHtml($img->getImgTag(img_size(400, 250)));
```

### `getResponsiveImgTag()` and `responsiveImgTag()`

Get or output responsive \<img\> tag with `sizes` and `srcset` arguments calculated by given ImageSizeList.

The `sizes` attribute describes how wide the image slot is in CSS pixels, so it is built from the widths declared for the breakpoints - never from the widths the resizer returned, as a smaller file doesn't make the slot narrower. The `srcset` attribute is built independently of it: every declared width is offered in all [pixel densities](./image-sizes.md#pixel-densities) of the ImageSizeList (`[1, 2]` by default), so retina displays have a dense enough candidate even in the widest breakpoint. Candidates are limited by the size of the original image, so nothing is upscaled.

Attributes `src`, `width` and `height` are taken from the 1x candidate of the widest size found in ImageSizeList, ie. from the fallback for browsers which ignore `srcset`.

To offer the image in alternative formats as well, see [getResponsivePictureTag()](#getresponsivepicturetag-and-responsivepicturetag).

Attribute `alt` will be automatically filled with first non-empty field alt or title set in WP Media Library.

- `@param ImageSizeList $sizes` Sizes of the responsive image. See [ImageSizeList docs](./image-sizes.md#imagesizelist-object).
- `@param array $attrs = []` HTML attributes of the image, eg. ['class' => 'img-fluid', 'alt' => 'My image']
- `@return Html` [Html object](https://doc.nette.org/en/3.0/html-elements) of \<img\>.

> Uses `fri_upload_responsive_img_tag` filter, see [more about WP filters](./wp-filters.md).

Example:
```php
use Fronty\ResponsiveImages\UploadImage;
use Fronty\ResponsiveImages\Sizes\ImageSizeList;

$sizes = ImageSizeList::fromArray([
	0 => [400, 250],
	576 => 500,
	768 => 700,
	992 => 900,
	1200 => 1100
]);

(new UploadImage($attachment_id))
	->responsiveImgTag($sizes, [
		'class' => 'img-fluid',
		'alt' => 'My image'
	]);


// The same with suggested factory function
img_upload($attachment_id)
	->responsiveImgTag(
		img_sizes([
			0 => [400, 250],
			576 => 500,
			768 => 700,
			992 => 900,
			1200 => 1100
		]),
		[
			'class' => 'img-fluid',
			'alt' => 'My image'
		]
	);
```

Output:
```html
<img
	src="https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_1100,c_fit/your-project/2021/05/your-image.jpg"
	sizes="(min-width: 1200px) 1100px,
		(min-width: 992px) 900px,
		(min-width: 768px) 700px,
		(min-width: 576px) 500px,
		400px"
	srcset="https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_400,h_250,c_fill/your-project/2021/05/your-image.jpg 400w,
		https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_500,c_fit/your-project/2021/05/your-image.jpg 500w,
		https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_700,c_fit/your-project/2021/05/your-image.jpg 700w,
		https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_800,h_500,c_fill/your-project/2021/05/your-image.jpg 800w,
		https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_900,c_fit/your-project/2021/05/your-image.jpg 900w,
		https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_1000,c_fit/your-project/2021/05/your-image.jpg 1000w,
		https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_1100,c_fit/your-project/2021/05/your-image.jpg 1100w,
		https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_1400,c_fit/your-project/2021/05/your-image.jpg 1400w,
		https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_1800,c_fit/your-project/2021/05/your-image.jpg 1800w,
		https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_2200,c_fit/your-project/2021/05/your-image.jpg 2200w"
	width="1100" height="619"
	alt="My image"
	class="img-fluid">
```

### `responsivePreloadLink()`

Output `<link rel="preload" as="image">` of the responsive image, ie. of the very same candidates `getResponsiveImgTag()` would offer. Put it to the \<head\> section for an image which renders in the first viewport, typically a hero, to let the browser start loading it before it parses the markup.

- `@param ImageSizeList $sizes` Sizes of the responsive image.
- `@param array $attrs = []` Reserved for additional attributes of the \<link\> tag.

Output:
```html
<link rel="preload" as="image" href="…" imagesrcset="… 400w, … 1100w, … 2048w" imagesizes="(min-width: 1200px) 1100px, 400px">
```

> **Don't combine it with image [formats](./image-sizes.md#image-formats).** The link preloads the original format, so a browser which picks an AVIF source out of a \<picture\> downloads the image twice. One link per format isn't a solution either - nothing makes the browser pick just one of several preloads, so a browser which supports both AVIF and WebP downloads both. Preload scanners read \<picture\> on their own, so an image offered in several formats is better left to them.

### `getResponsivePictureTag()` and `responsivePictureTag()`

Get or output \<picture\> tag, which offers the image in the [formats](./image-sizes.md#image-formats) set on the ImageSizeList - typically AVIF and WebP - with the responsive \<img\> of `getResponsiveImgTag()` as the fallback.

**This library converts nothing.** Every \<source\> is built from files your project confirms through the `fri_upload_format_src` filter. A format is offered only when **all** candidates of the `srcset` are confirmed in it, so the browser never gets a sparser ladder in the format it prefers.

**When no format is confirmed, the method returns the very same \<img\> as `getResponsiveImgTag()`, with no \<picture\> wrapper around it.** A picture with nothing to choose from would only add a level to the DOM, and a template would have to style two structures for one slot: a logo uploaded as SVG has no alternative formats, the same logo uploaded as PNG has. Write the CSS for both depths, eg. `.ratio > img, .ratio > picture > img`.

- `@param ImageSizeList $sizes` Sizes of the responsive image. See [ImageSizeList docs](./image-sizes.md#imagesizelist-object).
- `@param array $attrs = []` HTML attributes of the \<img\> tag.
- `@param array $pictureAttrs = []` HTML attributes of the \<picture\> tag.
- `@return Html` [Html object](https://doc.nette.org/en/3.0/html-elements) of \<picture\> with \<source\> and \<img\> children, or of the \<img\> alone when no format is confirmed.

> Uses `fri_upload_formats`, `fri_upload_format_src`, `fri_upload_picture_sources` and `fri_upload_picture_tag` filters, besides all the filters of `getResponsiveImgTag()`. See [more about WP filters](./wp-filters.md).

Example:
```php
$sizes = img_sizes([
	0 => [400, 250],
	1200 => 1100
])->setFormats(['image/avif', 'image/webp']);

img_upload($attachment_id)->responsivePictureTag($sizes, ['class' => 'img-fluid', 'alt' => 'My image']);
```

Output:
```html
<picture>
	<source type="image/avif"
		srcset="https://domain.com/wp-content/uploads/bis-images/2026/10/image-400x250-c.jpg.avif 400w,
			https://domain.com/wp-content/uploads/bis-images/2026/10/image-1100x688-c.jpg.avif 1100w,
			https://domain.com/wp-content/uploads/bis-images/2026/10/image-2048x1281-c.jpg.avif 2048w"
		sizes="(min-width: 1200px) 1100px, 400px">
	<source type="image/webp" srcset="…" sizes="(min-width: 1200px) 1100px, 400px">
	<img
		src="https://domain.com/wp-content/uploads/bis-images/2026/10/image-1100x688-c.jpg"
		sizes="(min-width: 1200px) 1100px, 400px"
		srcset="…"
		width="1100" height="688"
		alt="My image"
		class="img-fluid">
</picture>
```

#### Where do the converted files come from?

Generating them is the project's job - a converter plugin, a CLI task or an action of your resizer. The library only needs to know the URL, which is what the `fri_upload_format_src` filter answers. Converters keep their files in layouts too different to guess, so there is no default. Two usual ones:

```php
// A) The derivative lies next to the original with the extension replaced:
//    …/image-1100x688-c.jpg -> …/image-1100x688-c.avif
add_filter('fri_upload_format_src', function (?string $src, string $url, ?string $path, string $format) {
	$extension = ($format === 'image/avif') ? 'avif' : 'webp';
	if ($path === null || !file_exists(preg_replace('#\.\w+$#', ".$extension", $path))) return null;
	return preg_replace('#\.\w+$#', ".$extension", $url);
}, 10, 4);

// B) Converter for Media layout: a mirrored tree with the extension appended:
//    …/uploads/image-1100x688-c.jpg -> …/uploads-webpc/uploads/image-1100x688-c.jpg.avif
add_filter('fri_upload_format_src', function (?string $src, string $url, ?string $path, string $format) {
	$extension = ($format === 'image/avif') ? 'avif' : 'webp';
	$uploads = wp_get_upload_dir();
	if ($path === null) return null;

	$relative = substr($path, strlen($uploads['basedir'])) . '.' . $extension;
	if (!file_exists(dirname($uploads['basedir']) . '/uploads-webpc/uploads' . $relative)) return null;
	return dirname($uploads['baseurl']) . '/uploads-webpc/uploads' . $relative;
}, 10, 4);
```

> **Lazyloaders have to rewrite the sources as well.** Inside \<picture\> the `srcset` of a \<source\> wins over the \<img\>, so a lazyloader which rewrites the \<img\> attributes only is silently bypassed and the browser loads every source immediately. Hook `fri_upload_picture_sources` for that.
> **Don't preload a \<picture\>.** A `<link rel="preload">` per format would be downloaded by every browser which supports more than one of them, as nothing makes the browser pick just one of several preloads. Preload scanners read \<picture\> on their own anyway, see [responsivePreloadLink()](#responsivepreloadlink).
> Formats are ignored for SVG and with [Auto Cloudinary](https://wordpress.org/plugins/auto-cloudinary/), which negotiates the format itself.

### `getPictureTag()` and `pictureTag()`

Get or output \<picture\> tag for an image which needs **one size only** - a logo, an avatar, a fixed teaser - where a responsive `srcset` has nothing to offer, but AVIF or WebP still have.

The \<img\> inside is the very same tag `getImgTag()` returns, including it's `fri_upload_img_tag` filter, and the whole \<picture\> collapses to it when no format is confirmed. Switching a template from `imgTag()` to `pictureTag()` therefore changes the markup only where the converted files really exist - an SVG logo, a hotlinked image or a site with the formats switched off keeps the markup it has today.

- `@param ImageSize $size` Size of the image. See [ImageSize docs](./image-sizes.md#imagesize-object).
- `@param array $attrs = []` HTML attributes of the \<img\> tag.
- `@param array $pictureAttrs = []` HTML attributes of the \<picture\> tag.
- `@param array|null $formats = null` Image MIME types in order of preference. `null` asks the `fri_upload_formats` filter for the project default, an empty array switches the formats off for this single call.
- `@return Html` [Html object](https://doc.nette.org/en/3.0/html-elements) of \<picture\>, or of the \<img\> alone when no format is confirmed.

> Uses `fri_upload_formats`, `fri_upload_format_src`, `fri_upload_picture_sources` and `fri_upload_picture_tag` filters, besides the `fri_upload_img_tag` filter of `getImgTag()`. See [more about WP filters](./wp-filters.md).

Example - formats come from the project default, so the call itself says nothing about them:
```php
// Once, in your setup file
add_filter('fri_upload_formats', fn(): array => ['image/avif', 'image/webp']);

// In a template
img_upload($logo_id)->pictureTag(img_size(240, 80), ['class' => 'navbar-logo', 'alt' => 'Logo']);
```

Output:
```html
<picture>
	<source type="image/avif" srcset="https://domain.com/wp-content/uploads/bis-images/2026/10/logo-240x80-c.jpg.avif">
	<source type="image/webp" srcset="https://domain.com/wp-content/uploads/bis-images/2026/10/logo-240x80-c.jpg.webp">
	<img
		src="https://domain.com/wp-content/uploads/bis-images/2026/10/logo-240x80-c.jpg"
		width="240" height="80"
		alt="Logo"
		class="navbar-logo">
</picture>
```

> **A single candidate carries no `w` descriptor and no `sizes` attribute.** There is nothing for the browser to choose from, so both would only add bytes. [Pixel densities](./image-sizes.md#pixel-densities) belong to the ImageSizeList, so they don't apply here: when you want a 2x image in a smaller box, declare the larger size and set `width` and `height` yourself.

> The converted files are the project's job here as well, see [where they come from](#where-do-the-converted-files-come-from).

---

### `getAspectImgTag()` and `aspectImgTag()`

Generate or outputs \<img\> tag wrapped in \<figure\> tag, which holds the image aspect-ratio before the image is loaded. It is useful with lazyloaded images to prevent [Cumulative Layout Shift](https://web.dev/cls/). Modern browsers starts to implement native ratio preserving images, so it might be redundant in the future.

- `@param int $width` Width for ratio calculation.
- `@param int $height` Height for ratio calculation.
- `@param ImageSizeList $sizes = null` Generate responsive image tag using `getResponsiveImgTag()` if given. If null, create non-responsive image using `getImgTag()` with size of given $width and $height.
- `@param array $wrapAttrs = []` HTML attributes for wrapping \<figure\> tag.
- `@param array $imgAttrs = []` HTML attributes for \<img\> tag.
- `@return Html` [Html object](https://doc.nette.org/en/3.0/html-elements) of \<figure\> with \<img\> child.

> Uses `fri_upload_aspect_img_tag` filter, see [more about WP filters](./wp-filters.md).

Method calculates the ratio from mandatory $width and $height arguments and uses [Bootstrap 5 Ratio helper](https://getbootstrap.com/docs/5.0/helpers/ratio/#custom-ratios).

If you use this method with ImageSizeList object, where you specify different ratio for each breakpoint, you need to change the `--bs-aspect-ratio` CSS variable in your CSS for all breakpoints with different ratio. The value of `--bs-aspect-ratio` should be given in percent, for example with 16:9 ratio: 9 / 16 * 100% = 56.25%.

If you don't use Bootstrap, add following CSS to your project:

```css
.ratio {
	position: relative;
	width: 100%;
}
.ratio::before {
	display: block;
    padding-top: var(--bs-aspect-ratio);
    content: "";
}
.ratio img {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
}
```

Example:
```php
use Fronty\ResponsiveImages\UploadImage;

(new UploadImage($attachment_id))
	->aspectImgTag(
		400, 250, null,
		['class' => 'my-figure'], ['class' => 'lazyloaded', 'alt' => 'My image']
	);

// Or the same with suggested factory function
img_upload($attachment_id)
	->aspectImgTag(
		400, 250, null,
		['class' => 'my-figure'], ['class' => 'lazyloaded', 'alt' => 'My image']
	);
```

Output:
```html
<figure class="my-figure ratio" style="--bs-aspect-ratio:62.5%">
	<img
		src="https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_400,h_250,c_fill/your-project/2021/05/your-image.jpg"
		width="400" height="250"
		alt="My image"
		class="lazyloaded">
</figure>
```


### `getAspectPictureTag()` and `aspectPictureTag()`

The same as `getAspectImgTag()`, but the ratio preserving \<figure\> wraps a \<picture\> tag of `getResponsivePictureTag()`.

- `@param int $width` Width for ratio calculation.
- `@param int $height` Height for ratio calculation.
- `@param ImageSizeList $sizes` Sizes of the responsive image including it's [formats](./image-sizes.md#image-formats).
- `@param array $wrapAttrs = []` HTML attributes for wrapping \<figure\> tag.
- `@param array $imgAttrs = []` HTML attributes for \<img\> tag.
- `@param array $pictureAttrs = []` HTML attributes for \<picture\> tag.
- `@return Html` [Html object](https://doc.nette.org/en/3.0/html-elements) of \<figure\> with \<picture\> child.

> Uses `fri_upload_aspect_img_tag` filter, the very same one as `getAspectImgTag()`, see [more about WP filters](./wp-filters.md).

Output:
```html
<figure class="my-figure ratio" style="--bs-aspect-ratio:62.5%">
	<picture>
		<source type="image/avif" srcset="…" sizes="…">
		<img src="…" srcset="…" sizes="…" width="400" height="250" alt="My image" class="lazyloaded">
	</picture>
</figure>
```

> **The \<picture\> is the direct child of the figure**, so CSS which stretches the image to the box has to target `figure.ratio > picture > img` - **and `figure.ratio > img` as well**, because the wrapper is left out when no format is confirmed. Bootstrap's own `.ratio` helper styles the first child, so it needs the same treatment.

---

## ThemeImage object

ThemeImage has similar methods as UploadImage, but it suppose you the images are already optimized by som task manager, so there is no sanitization with SVGs. Theme images can't be currently used with responsive attributes.

### `__construct()`

For `ThemeImage` to instantiate, you need relative location of the image within your theme directory. You can tweak source location of theme images using [wp filters](./wp-filters.md).

- `@param string $file`

Example:
```php
use Fronty\ResponsiveImages\ThemeImage;

$img = new ThemeImage('images/image.jpg');

// Or using suggested factory function
$img = img_theme('images/logo.svg');
```

### `isSvg()`, `getPathinfo()`, `getUrl()`, `getPath()`, `getInlineSvg()`, `inlineSvg()`, `getImgTag()` and `imgTag()`

These methods is documented in [Shared methods above](#shared-methods).

### `getAspectImgTag()` and `aspectImgTag()`

Unlike UploadImage, these methods can't be used with responsive attributes, so ImageSizeList argument is omitted. Besides this fact, these methods work exactly the same as the ones in [UploadImage object](#uploadimage-object).

- `@param int $width` Width for ratio calculation.
- `@param int $height` Height for ratio calculation.
- `@param array $wrapAttrs = []` HTML attributes for wrapping \<figure\> tag.
- `@param array $imgAttrs = []` HTML attributes for \<img\> tag.
- `@return Html` [Html object](https://doc.nette.org/en/3.0/html-elements) of \<figure\> with \<img\> child.

> Uses `fri_theme_aspect_img_tag` filter, see [more about WP filters](./wp-filters.md).