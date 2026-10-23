<?php

namespace Fronty\ResponsiveImages;

use DOMDocument;
use DOMElement;
use Fronty\ResponsiveImages\Sizes\ImageSize;
use Fronty\ResponsiveImages\Sizes\ImageSizeList;
use Nette\Utils\Html;
use Nette\Utils\Strings;
use Nette\Utils\ArrayHash;
use enshrined\svgSanitize\Sanitizer;

/**
 * Object to manipulate with uploaded images.
 *	Get images from Cloudinary, Fly Dynamic Image Resizer plugin or by WP native functions.
 *  Big up for Adam Laita: https://naswp.cz/mistrovska-optimalizace-obrazku-nejen-pro-wordpress/#cloudinary
 *
 * @see https://github.com/junaidbhura/auto-cloudinary/wiki/Best-Practices
 */
class UploadImage extends BaseImage
{
	/** @var float Maximal relative difference between the required and the returned aspect ratio. */
	private const RATIO_TOLERANCE = 0.02;

	/** @var int */
	private $ID;

	/** @var Sanitizer */
	private $sanitizer;

	/**
	 * Get object for image uploaded in admin by WP attachment ID.
	 * @param int $ID
	 */
	public function __construct(int $ID)
	{
		$this->ID = $ID;
	}

	/**
	 * Get attachment ID.
	 * @return int
	 */
	public function getImageId(): int
	{
		return $this->ID;
	}

	/**
	 * Get focal point set by Better Image sizes plugin.
	 * @param int $precision
	 * @return array [left%, top%]
	 */
	public function getFocalpoint(int $precision = 2): array
	{
		$focal = get_post_meta($this->ID, 'focal_point', true);
		if (function_exists('sanitize_focal_point')) {
			$focal = sanitize_focal_point($focal);
		}
		if (empty($focal)) $focal = [0.5, 0.5];
		return array_map(fn(float $val): float => round($val * 100, $precision), $focal);
	}

	/**
	 * @inheritDoc
	 */
	public function getUrl(): string
	{
		return strval(wp_get_attachment_url($this->ID));
	}

	/**
	 * @inheritDoc
	 */
	public function getPath(): string
	{
		return get_attached_file($this->ID);
	}

	/**
	 * Check if image is still uploaded in media library.
	 * @return bool
	 */
	public function hasImage(): bool
	{
		return (wp_get_attachment_image_src($this->ID) !== false);
	}

	/**
	 * Get width and height of full image from DB.
	 * @return array|null [0 => int width, 1 => int height], null if not available.
	 */
	public function getOriginalSize(): ?array
	{
		$data = wp_get_attachment_image_src($this->ID, 'full');
		if ($data === false) return parent::getOriginalSize();
		return [intval($data[1]), intval($data[2])];
	}

	/**
	 * Get image URL, width and height for given ImageSize.
	 * 	Crop is not performed if neither Auto Cloudinary and Fly Images plugins are available.
	 *  For Cloudinary crop values, see https://cloudinary.com/documentation/resizing_and_cropping
 	 * @return array [0 => URL, 1 => image width, 2 => image height]
	 */
	public function getSizedSrc(ImageSize $size): array
	{
		if (!$this->hasImage()) return ['', 0, 0];

		$width = $size->getWidth();
		$height = $size->getHeight();

		if ($this->isSvg()) {
			$src = wp_get_attachment_image_src($this->ID, [$width, $height]);
			$src[1] = $width;
			$src[2] = $height;
			return $src;
		}

		if (function_exists('cloudinary_url')) {
			return [cloudinary_url($this->ID, ['transform' => $size->getCloudinaryTransform()]), $width, $height];
		}
		if (function_exists('bis_get_attachment_image_src')) {
			$src = bis_get_attachment_image_src($this->ID, [$width, $height], $size->getCropOrPosition());
			return is_array($src) ? array_values($src) : ['', 0, 0];
		}
		return wp_get_attachment_image_src($this->ID, [$width, $height]);
	}

	/**
	 * Create link to image of given size.
	 * 	Useful when creating links to open image in lightbox.
	 * @param array $attrs Array of link HTML attributes.
	 * @param ImageSize $size Size to which to resize the targeted image. Null - full image will be targeted.
	 * @return Html
	 *
	 * @see static::getSizedSrc()
	 */
	public function getImgLink(array $attrs = [], ?ImageSize $size = null): Html
	{
		$attrs['href'] = ($size === null) ? $this->getUrl() : $this->getSizedSrc($size)[0];
		return Html::el('a', $attrs);
	}

	/**
	 * @inheritDoc
	 */
	public function getImgTag(ImageSize $size, array $attrs = []): Html
	{
		$src = $this->getSizedSrc($size);
		if ($src) {
			$attrs['src'] = esc_url_raw($src[0]);
			if (!isset($attrs['width'])) $attrs['width'] = $src[1];
			if (!isset($attrs['height'])) $attrs['height'] = $src[2];
		}
		if (!isset($attrs['alt'])) $attrs['alt'] = $this->getDefaultAlt();
		$attrs['alt'] = $this->sanitizeAlt(strval($attrs['alt']));
		$el = Html::el('img', $attrs);
		apply_filters('fri_upload_img_tag', $el, $size, $this);
		return $el;
	}

	/**
	 * Get <picture> tag with the <img> of a single size and <source> elements of alternative
	 * 	image formats, eg. AVIF or WebP.
	 * 	Handy for images which need one size only - a logo, an avatar or a fixed teaser - where
	 * 	a responsive srcset has nothing to offer, but the alternative formats still have.
	 *
	 * 	The <img> inside is the very same tag getImgTag() returns, including the
	 * 	`fri_upload_img_tag` filter, and the whole <picture> collapses to it when no format is
	 * 	confirmed, so switching a template from getImgTag() to this method changes the markup
	 * 	only where the alternative files really exist.
	 * @param ImageSize $size
	 * @param array $attrs Attributes of the <img> tag.
	 * @param array $pictureAttrs Attributes of the <picture> tag.
	 * @param array|null $formats Image MIME types in order of preference, eg. ['image/avif'].
	 * 	Null (default) asks the `fri_upload_formats` filter for the project default, an empty
	 * 	array switches the formats off for this call.
	 * @return Html The <picture> tag, or the <img> alone when no format is confirmed.
	 *
	 * @see static::getImgTag()
	 * @see static::getResponsivePictureTag() For an image which needs more sizes.
	 */
	public function getPictureTag(ImageSize $size, array $attrs = [], array $pictureAttrs = [], ?array $formats = null): Html
	{
		$img = $this->getImgTag($size, $attrs);

		// A single candidate with no `sizes` attribute needs no `w` descriptor at all: there is
		// nothing for the browser to choose from. The URL is read back from the <img>, so the
		// sources offer exactly what it ended up with, and the resizer is asked just once.
		$src = strval($img->src);
		$candidates = ($src !== '') ? [[$src, 0]] : [];

		$sources = $this->createPictureSources($size, $candidates, '', $formats);
		return $this->wrapInPicture($img, $sources, $size, $pictureAttrs);
	}

	/**
	 * Output <picture> tag of a single size.
	 * @param ImageSize $size
	 * @param array $attrs Attributes of the <img> tag.
	 * @param array $pictureAttrs Attributes of the <picture> tag.
	 * @param array|null $formats Image MIME types, null asks the `fri_upload_formats` filter.
	 *
	 * @see static::getPictureTag()
	 */
	public function pictureTag(ImageSize $size, array $attrs = [], array $pictureAttrs = [], ?array $formats = null)
	{
		echo $this->getPictureTag($size, $attrs, $pictureAttrs, $formats);
	}

	/**
	 * Get <img> tag with responsive sizes and srcset according to given ImageSizeList.
	 * @param ImageSizeList $sizes
	 * @param array $attrs
	 * @return Html
	 *
	 * @see static::getResponsivePictureTag() To offer the image in alternative formats as well.
	 */
	public function getResponsiveImgTag(ImageSizeList $sizes, array $attrs = []): Html
	{
		if ($this->isSvg()) {
			$widest = $sizes->getWidest();
			$width = $widest->getWidth();
			$height = $widest->getHeight();
			return $this->getImgTag(new ImageSize($width, $height ?? $width), $attrs);
		}

		$original = $this->getOriginalSize();
		return $this->createResponsiveImgTag($sizes, $attrs, $this->createSrcsetSizes($sizes, $original), $original);
	}

	/**
	 * Create <img> tag from already created srcset and sizes attributes.
	 * @param ImageSizeList $sizes
	 * @param array $attrs
	 * @param ArrayHash $sets Srcset candidates and sizes slots.
	 * @param array|null $original Size of the original image, null when it is unknown.
	 * @return Html
	 *
	 * @see static::createSrcsetSizes()
	 */
	private function createResponsiveImgTag(ImageSizeList $sizes, array $attrs, ArrayHash $sets, ?array $original): Html
	{
		// The src attribute is the 1x candidate of the widest slot, ie. the fallback
		// for browsers which ignore srcset, clamped the same way as the candidates
		$widestSrc = $this->getDensitySrc($sizes->getWidest(), 1.0, $original);

		$setsSizes = apply_filters('fri_upload_responsive_img_sizes', $sets->sizes, $this);
		$setsSrcsets = apply_filters('fri_upload_responsive_img_srcset', $sets->srcsets, $this);

		if ($widestSrc !== null) {
			$attrs = array_merge($attrs, [
				'src' => esc_url_raw($widestSrc[0]),
				'sizes' => implode(', ', $setsSizes),
				'srcset' => implode(', ', $setsSrcsets)
			]);
			if (!isset($attrs['width'])) $attrs['width'] = $widestSrc[1];
			if (!isset($attrs['height'])) $attrs['height'] = $widestSrc[2];
		}

		if (!isset($attrs['alt'])) $attrs['alt'] = $this->getDefaultAlt();
		$attrs['alt'] = $this->sanitizeAlt(strval($attrs['alt']));
		$el = Html::el('img', $attrs);
		apply_filters('fri_upload_responsive_img_tag', $el, $sizes, $this);
		return $el;
	}

	/**
	 * Output responsive image tag.
	 * @param ImageSizeList $sizes
	 * @param array $attrs
	 *
	 * @see static::getResponsiveImgTag()
	 */
	public function responsiveImgTag(ImageSizeList $sizes, array $attrs = [])
	{
		echo $this->getResponsiveImgTag($sizes, $attrs);
	}

	/**
	 * Get <picture> tag with the responsive <img> and <source> elements of alternative image formats.
	 * 	Sources are rendered for the formats set by ImageSizeList::setFormats() or by the
	 * 	`fri_upload_formats` filter, and only when the `fri_upload_format_src` filter confirms
	 * 	the file of every single candidate. When no format is confirmed, the method returns the
	 * 	very same <img> getResponsiveImgTag() would return, without the <picture> wrapper.
	 *
	 * 	Beware of lazyloaders: inside <picture> the `srcset` of a <source> wins over the <img>,
	 * 	so a lazyloader which rewrites attributes of the <img> only would be silently bypassed
	 * 	and the browser would load every source immediately. Rewrite the sources as well
	 * 	through the `fri_upload_picture_sources` filter.
	 * @param ImageSizeList $sizes
	 * @param array $attrs Attributes of the <img> tag.
	 * @param array $pictureAttrs Attributes of the <picture> tag.
	 * @return Html
	 *
	 * @see ImageSizeList::setFormats()
	 * @see static::getResponsiveImgTag()
	 */
	public function getResponsivePictureTag(ImageSizeList $sizes, array $attrs = [], array $pictureAttrs = []): Html
	{
		if ($this->isSvg()) {
			$img = $this->getResponsiveImgTag($sizes, $attrs);
			$candidates = [];
		} else {
			$original = $this->getOriginalSize();
			$sets = $this->createSrcsetSizes($sizes, $original);
			$img = $this->createResponsiveImgTag($sizes, $attrs, $sets, $original);
			$candidates = $sets->candidates;
		}

		$sources = $this->createPictureSources($sizes, $candidates, strval($img->sizes));
		return $this->wrapInPicture($img, $sources, $sizes, $pictureAttrs);
	}

	/**
	 * Wrap given <img> in a <picture> tag with given sources.
	 * 	The wrapper is rendered only when there is at least one <source> to offer. A <picture>
	 * 	with nothing to choose from would just add a level to the DOM, and a template would have
	 * 	to style two different structures for one slot - a logo uploaded as SVG has no
	 * 	alternative formats, the same logo uploaded as PNG has.
	 * 	The sources filter runs before that decision, so a project can add a source of its own
	 * 	and still get the wrapper.
	 * @param Html $img
	 * @param array|Html[] $sources
	 * @param ImageSize|ImageSizeList $sizes The size or the list given to the calling method.
	 * @param array $pictureAttrs Attributes of the <picture> tag.
	 * @return Html The <picture> tag, or the given <img> when no source is offered.
	 */
	private function wrapInPicture(Html $img, array $sources, ImageSize|ImageSizeList $sizes, array $pictureAttrs): Html
	{
		$sources = apply_filters('fri_upload_picture_sources', $sources, $sizes, $this);
		if (count($sources) <= 0) return $img;

		$el = Html::el('picture', $pictureAttrs);
		foreach ($sources as $source) {
			$el->addHtml($source);
		}
		$el->addHtml($img);
		apply_filters('fri_upload_picture_tag', $el, $sizes, $this);
		return $el;
	}

	/**
	 * Output responsive picture tag.
	 * @param ImageSizeList $sizes
	 * @param array $attrs Attributes of the <img> tag.
	 * @param array $pictureAttrs Attributes of the <picture> tag.
	 *
	 * @see static::getResponsivePictureTag()
	 */
	public function responsivePictureTag(ImageSizeList $sizes, array $attrs = [], array $pictureAttrs = [])
	{
		echo $this->getResponsivePictureTag($sizes, $attrs, $pictureAttrs);
	}

	/**
	 * Output preload link of the image with given responsive sizes.
	 * 	Put this to <head> section for soon-to-render images to optimize CLS.
	 *
	 * 	The link preloads the <img> candidates, ie. the original image format. Don't combine it
	 * 	with image formats of a <picture> tag: a preload link per format would be downloaded by
	 * 	every browser which supports more than one of them, as nothing makes the browser choose
	 * 	just one of several preloads. Preload scanners read <picture> on their own anyway.
	 * @param ImageSizeList $sizes
	 * @param array $attrs
	 *
	 * @see static::getResponsivePictureTag()
	 */
	public function responsivePreloadLink(ImageSizeList $sizes, array $attrs = [])
	{
		$img = $this->getResponsiveImgTag($sizes);
		$link = Html::el('link', [
			'rel' => 'preload',
			'as' => 'image',
			'href' => $img->getSrc(),
			'imagesrcset' => $img->getSrcset(),
			'imagesizes' => $img->getSizes()
		]);
		echo $link . PHP_EOL;
	}

	/**
	 * Get image tag wrapped in Bootstrap 5 figure.ratio, which ensures it's aspect ratio.
	 * 	Useful with lazyloaded images to prevent Core Web Vitals CLS.
	 * 	To use with responsive img tag, set $sizes argument.
	 * @param int $width Image width from which count ratio.
	 * @param int $height Image height from which to count ratio.
	 * @param ImageSizeList|null $sizes If given, <img> tag will be generated using getResponsiveImgTag().
	 * @param array $wrapAttrs
	 * @param array $imgAttrs
	 * @return Html
	 *
	 * @see https://getbootstrap.com/docs/5.0/helpers/ratio/#custom-ratios
	 * @see https://web.dev/cls/
	 */
	public function getAspectImgTag(int $width, int $height, ?ImageSizeList $sizes = null, array $wrapAttrs = [], array $imgAttrs = []): Html
	{
		if ($sizes === null) $img = $this->getImgTag(new ImageSize($width, $height), $imgAttrs);
		else $img = $this->getResponsiveImgTag($sizes, $imgAttrs);
		return $this->wrapInRatio($img, $width, $height, $wrapAttrs, $sizes);
	}

	/**
	 * Get picture tag wrapped in Bootstrap 5 figure.ratio, which ensures it's aspect ratio.
	 * 	The <picture> element is the direct child of the figure, so CSS which stretches the
	 * 	image has to target `figure.ratio > picture > img` - and `figure.ratio > img` as well,
	 * 	because the wrapper is left out when no image format is confirmed.
	 * @param int $width Image width from which count ratio.
	 * @param int $height Image height from which to count ratio.
	 * @param ImageSizeList $sizes
	 * @param array $wrapAttrs
	 * @param array $imgAttrs Attributes of the <img> tag.
	 * @param array $pictureAttrs Attributes of the <picture> tag.
	 * @return Html
	 *
	 * @see static::getResponsivePictureTag()
	 */
	public function getAspectPictureTag(int $width, int $height, ImageSizeList $sizes, array $wrapAttrs = [], array $imgAttrs = [], array $pictureAttrs = []): Html
	{
		$picture = $this->getResponsivePictureTag($sizes, $imgAttrs, $pictureAttrs);
		return $this->wrapInRatio($picture, $width, $height, $wrapAttrs, $sizes);
	}

	/**
	 * Output picture tag wrapped in fixed ratio figure.
	 * @param int $width
	 * @param int $height
	 * @param ImageSizeList $sizes
	 * @param array $wrapAttrs
	 * @param array $imgAttrs Attributes of the <img> tag.
	 * @param array $pictureAttrs Attributes of the <picture> tag.
	 *
	 * @see static::getAspectPictureTag()
	 */
	public function aspectPictureTag(int $width, int $height, ImageSizeList $sizes, array $wrapAttrs = [], array $imgAttrs = [], array $pictureAttrs = [])
	{
		echo $this->getAspectPictureTag($width, $height, $sizes, $wrapAttrs, $imgAttrs, $pictureAttrs);
	}

	/**
	 * Wrap given image tag in Bootstrap 5 figure.ratio of given aspect ratio.
	 * @param Html $inner Tag to wrap, ie. <img> or <picture>.
	 * @param int $width Image width from which count ratio.
	 * @param int $height Image height from which to count ratio.
	 * @param array $wrapAttrs
	 * @param ImageSizeList|null $sizes Passed to the filter as a context only.
	 * @return Html
	 */
	private function wrapInRatio(Html $inner, int $width, int $height, array $wrapAttrs, ?ImageSizeList $sizes): Html
	{
		$ratio = $height / $width * 100;
		$el = Html::el('figure', $wrapAttrs)
			->addClass('ratio')
			->setStyle('--bs-aspect-ratio:' . $ratio . '%')
			->addHtml($inner);
		apply_filters('fri_upload_aspect_img_tag', $el, $width, $height, $ratio, $sizes, $this);
		return $el;
	}

	/**
	 * Output image tag wrapped in fixed ratio div.
	 * @param int $width
	 * @param int $height
	 * @param ImageSizeList $sizes
	 * @param array $wrapAttrs
	 * @param array $imgAttrs
	 *
	 * @see static::getAspectImgTag()
	 */
	public function aspectImgTag(int $width, int $height, ?ImageSizeList $sizes = null, array $wrapAttrs = [], array $imgAttrs = [])
	{
		echo $this->getAspectImgTag($width, $height, $sizes, $wrapAttrs, $imgAttrs);
	}

	/**
	 * Add inline SVG sanitize.
	 *  The sanitizer returns false for input it can't process safely, eg. an SVG whose root
	 *  element is not <svg>. Such SVG is left out of the output and the issue is reported.
	 * @inheritDoc
	 *
	 * @see static::reportIssue()
	 */
	public function getInlineSvg(): string
	{
		$svg = parent::getInlineSvg();
		if ($svg === '') return '';

		$sanitizer = $this->getSanitizer();
		$sanitized = $sanitizer->sanitize($svg);
		if ($sanitized === false) {
			$this->reportIssue(sprintf(
				"SVG '%s' was rejected by the sanitizer: %s",
				$this->getUrl(),
				$this->getSanitizerIssues($sanitizer)
			));
			return '';
		}
		return $sanitized;
	}

	/**
	 * Get issues reported by the SVG sanitizer as a single line.
	 * @param Sanitizer $sanitizer
	 * @return string
	 */
	private function getSanitizerIssues(Sanitizer $sanitizer): string
	{
		$issues = array_filter(array_map(
			fn($issue): string => strval(is_array($issue) ? ($issue['message'] ?? '') : $issue),
			$sanitizer->getXmlIssues()
		));
		return (count($issues) > 0) ? implode('; ', $issues) : 'no details given';
	}

	/**
	 * Create arrays of `srcset` and `sizes` HTML attributes for img tag.
	 * 	The `sizes` attribute describes how wide the image slot is in CSS pixels, so it is
	 * 	built from the widths declared for the breakpoints, never from the widths the resizer
	 * 	returned - a smaller file doesn't make the slot narrower.
	 * 	`srcset` is built independently of it: every declared width is offered in all pixel
	 * 	densities set in the ImageSizeList, so even the widest breakpoint has a dense enough
	 * 	candidate on retina displays.
	 * 	Neighbouring slots of the same width are collapsed into one, which says the very same
	 * 	thing in fewer bytes - and `sizes` is repeated on every <source> of a <picture> tag.
	 * @param ImageSizeList $imageSizes
	 * @param array|null $original Size of the original image, null when it is unknown.
	 * @return ArrayHash sizes - slots of the `sizes` attribute, srcsets - candidates of the
	 * 	`srcset` attribute, candidates - the same candidates as [0 => URL, 1 => width] pairs.
	 *
	 * @see ImageSizeList::setDensities()
	 */
	private function createSrcsetSizes(ImageSizeList $imageSizes, ?array $original): ArrayHash
	{
		$sizes = $slots = $candidates = [];

		$previousWidth = null;
		foreach ($imageSizes as $viewport => $size) {
			$slots[] = $size;
			$width = $size->getWidth();
			if ($imageSizes->isLast()) {
				$slot = $width . 'px';
			} else {
				$media = ($imageSizes->isMobileFirst()) ? 'min-width' : 'max-width';
				$slot = sprintf('(%s: %dpx) %dpx', $media, $viewport, $width);
			}

			// A slot as wide as its predecessor only repeats what the previous condition
			// already said, so a series of equal widths is collapsed into its last member.
			// The browser takes the first matching slot and the last member of the series is
			// the most permissive one - the lowest `min-width` when mobile first, the highest
			// `max-width` otherwise - so it covers the whole range the series covered.
			if ($width === $previousWidth) array_pop($sizes);
			$sizes[] = $slot;
			$previousWidth = $width;
		}

		// A flat srcset can't hold two files of the same `w` descriptor, so candidates are
		// deduplicated by the really returned width. Slots are asked from the widest one, so
		// the kept candidate of a colliding width is always the one cropped for the widest
		// slot, ie. for the viewport which asks for such a wide file, whatever the list order is.
		usort($slots, fn(ImageSize $a, ImageSize $b): int => $b->getWidth() <=> $a->getWidth());

		foreach ($slots as $size) {
			foreach ($imageSizes->getDensities() as $index => $density) {
				$candidate = $this->getDensitySrc($size, $density, $original);
				if ($candidate === null) continue;

				// The lowest density is the fallback candidate of the slot, kept even when the
				// resizer returned another aspect ratio, so the srcset is never left empty,
				// eg. on a site without any resizer plugin
				$width = intval($candidate[1]);
				if ($index > 0 && !$this->hasRequiredRatio($size, $width, intval($candidate[2]))) continue;
				if ($width <= 0 || isset($candidates[$width])) continue;

				$candidates[$width] = [$candidate[0], $width];
			}
		}

		ksort($candidates);
		$candidates = array_values($candidates);
		return ArrayHash::from([
			'sizes' => $sizes,
			'srcsets' => array_map(fn(array $candidate): string => esc_url_raw($candidate[0]) . ' ' . $candidate[1] . 'w', $candidates),
			'candidates' => $candidates
		], false);
	}

	/**
	 * Create <source> elements of alternative image formats for the <picture> tag.
	 * 	A source is rendered only when every single candidate is confirmed in the given format,
	 * 	so the browser never gets a sparser ladder in the format it prefers. Formats are skipped
	 * 	altogether for SVG and with Auto Cloudinary, which negotiates the format itself.
	 * @param ImageSize|ImageSizeList $imageSizes The size or the list given to the calling method.
	 * @param array $candidates Srcset candidates as [0 => URL, 1 => width] pairs. A width of 0
	 * 	renders the candidate without any descriptor, for a single candidate with no `sizes`.
	 * @param string $sizesAttr The `sizes` attribute of the <img> tag.
	 * @param array|null $formats Formats required by the calling method, null to resolve them.
	 * @return array|Html[]
	 *
	 * @see ImageSizeList::setFormats()
	 * @see static::getFormatSrc()
	 */
	private function createPictureSources(ImageSize|ImageSizeList $imageSizes, array $candidates, string $sizesAttr, ?array $formats = null): array
	{
		if ($this->isSvg() || function_exists('cloudinary_url')) return [];

		$sources = [];
		foreach ($this->resolveFormats($imageSizes, $formats) as $format) {
			$srcsets = [];
			foreach ($candidates as $candidate) {
				$src = $this->getFormatSrc(strval($candidate[0]), $format);
				// An incomplete ladder would be worse than none, so the whole format is left out
				if ($src === null) continue 2;
				$width = intval($candidate[1]);
				$srcsets[] = esc_url_raw($src) . (($width > 0) ? " {$width}w" : '');
			}
			if (count($srcsets) <= 0) continue;

			$attrs = ['type' => $format, 'srcset' => implode(', ', $srcsets)];
			if ($sizesAttr !== '') $attrs['sizes'] = $sizesAttr;
			$sources[] = Html::el('source', $attrs);
		}
		return $sources;
	}

	/**
	 * Resolve the image formats to offer as <picture> sources.
	 * 	Formats set explicitly win, no matter whether by the argument of the calling method or
	 * 	by ImageSizeList::setFormats(), so an empty array switches them off for a single call.
	 * 	When nothing is set, the `fri_upload_formats` filter supplies the project default - at
	 * 	render time and with the image at hand, so it can decide per image.
	 * 	Unlike ImageSizeList::setFormats(), the filtered value is not validated: a MIME type
	 * 	the browser doesn't know only means it skips that source.
	 * @param ImageSize|ImageSizeList $sizes The size or the list given to the calling method.
	 * @param array|null $formats Formats required by the calling method, null to resolve them.
	 * @return array|string[] Image MIME types in order of preference.
	 *
	 * @see ImageSizeList::setFormats()
	 */
	private function resolveFormats(ImageSize|ImageSizeList $sizes, ?array $formats): array
	{
		if ($formats !== null) return $formats;
		if ($sizes instanceof ImageSizeList && $sizes->hasFormats()) return $sizes->getFormats();
		return apply_filters('fri_upload_formats', [], $this, $sizes);
	}

	/**
	 * Get URL of the image file in given format.
	 * 	This library converts nothing, it only offers files which a project confirms through
	 * 	the `fri_upload_format_src` filter. Conversion plugins store derivatives in layouts too
	 * 	different to guess - in place or in a mirrored directory tree, with the original
	 * 	extension replaced or kept - so there is no default: without the filter no <source>
	 * 	is rendered at all.
	 * @param string $url URL of the candidate in the original format.
	 * @param string $format Required image MIME type, eg. 'image/avif'.
	 * @return string|null URL of the file in the required format, null when there is none.
	 */
	private function getFormatSrc(string $url, string $format): ?string
	{
		$src = apply_filters('fri_upload_format_src', null, $url, $this->getUploadPath($url), $format, $this);
		if (!is_string($src) || trim($src) === '') return null;
		return $src;
	}

	/**
	 * Get server path of a file inside the uploads directory by it's URL.
	 * 	Only the uploads base URL is translated, as resizers and converters keep their files in
	 * 	various subtrees of it - cutting WP_CONTENT_DIR out of a path string wouldn't find them.
	 * @param string $url
	 * @return string|null Null when the URL doesn't point into the uploads directory, eg. to a CDN.
	 */
	private function getUploadPath(string $url): ?string
	{
		$uploads = wp_get_upload_dir();
		$baseurl = $this->withoutScheme(strval($uploads['baseurl'] ?? ''));
		$basedir = rtrim(strval($uploads['basedir'] ?? ''), '/');
		if ($baseurl === '' || $basedir === '') return null;

		$url = $this->withoutScheme($url);
		if (!str_starts_with($url, $baseurl . '/')) return null;

		$path = strval(parse_url(substr($url, strlen($baseurl)), PHP_URL_PATH));
		if ($path === '') return null;
		return $basedir . '/' . ltrim(urldecode($path), '/');
	}

	/**
	 * Strip URL scheme, so that http, https and protocol-relative URLs are comparable.
	 * @param string $url
	 * @return string
	 */
	private function withoutScheme(string $url): string
	{
		return strval(preg_replace('#^(?:[a-z][a-z0-9+.-]*:)?//#i', '//', trim($url)));
	}

	/**
	 * Get image URL, width and height for given ImageSize multiplied by pixel density.
	 * 	The required size is clamped to the original image size before the resizer is asked,
	 * 	so nothing is upscaled. Asked for a size larger than the original,
	 * 	bis_get_attachment_image_src() returns the untouched original, ie. an image of
	 * 	a different aspect ratio than the required crop - which is why even the 1x density
	 * 	is resolved through this method. Clamping is not dropping: the clamped size keeps the
	 * 	required ratio, so a candidate is always returned.
	 * @param ImageSize $size Size declared for a breakpoint, ie. in 1x density.
	 * @param float $density Pixel density (DPR) to multiply the size with.
	 * @param array|null $original Size of the original image, null when it is unknown.
	 * @return array|null [0 => URL, 1 => image width, 2 => image height],
	 * 	null when the resizer returned nothing.
	 *
	 * @see static::getSizedSrc()
	 * @see static::hasRequiredRatio()
	 */
	private function getDensitySrc(ImageSize $size, float $density, ?array $original): ?array
	{
		$required = $size->getScaled($this->getDensityScale($size, $density, $original));
		$src = $this->getSizedSrc($required);
		if (!$src || !isset($src[0], $src[1], $src[2]) || $src[0] === '') return null;
		return array_values($src);
	}

	/**
	 * Get scale of the declared size, which doesn't exceed the original image size.
	 * @param ImageSize $size Size declared for a breakpoint, ie. in 1x density.
	 * @param float $density Required pixel density (DPR).
	 * @param array|null $original Size of the original image, null when it is unknown.
	 * @return float The density itself when the scaled size still fits into the original image.
	 */
	private function getDensityScale(ImageSize $size, float $density, ?array $original): float
	{
		$width = $size->getWidth();
		$height = $size->getHeight();
		if ($original === null || $width <= 0 || $original[0] <= 0 || $original[1] <= 0) return $density;

		$scale = min($density, $original[0] / $width);
		// Cropped sizes are limited by the original height as well to keep the required ratio
		if ($height !== null && $height > 0 && $size->hasCrop()) {
			$scale = min($scale, $original[1] / $height);
		}
		return $scale;
	}

	/**
	 * Check whether the resizer returned an image in the aspect ratio of the required size.
	 * 	Uncropped (fit) sizes always pass, as their ratio is given by the original image.
	 * @param ImageSize $size Required size.
	 * @param int $width Returned image width.
	 * @param int $height Returned image height.
	 * @return bool
	 */
	private function hasRequiredRatio(ImageSize $size, int $width, int $height): bool
	{
		$requiredHeight = $size->getHeight();
		if ($requiredHeight === null || !$size->hasCrop()) return true;
		if ($size->getWidth() <= 0 || $requiredHeight <= 0 || $width <= 0 || $height <= 0) return false;

		$required = $requiredHeight / $size->getWidth();
		return (abs($height / $width - $required) <= self::RATIO_TOLERANCE * $required);
	}

	/**
	 * Get "Alternative text" set in WP Media Library.
	 * 	Unlike the automatic alt of <img> tags, attachment title is not used, as it is usually just a file name.
	 * @param string $fallback Returned when the alternative text is empty, eg. a heading next to the image.
	 * @return string
	 */
	public function getAlt(string $fallback = ''): string
	{
		$alt = trim(strval(get_post_meta($this->ID, '_wp_attachment_image_alt', true)));
		return ($alt !== '') ? $alt : $fallback;
	}

	/**
	 * Get default image alt.
	 * @return string
	 */
	private function getDefaultAlt(): string
	{
		return $this->getAlt(strval(get_the_title($this->ID)));
	}

	/**
	 * Get SVG sanitizer.
	 * 	The XML prolog is removed, as the sanitized code is meant to be inlined into HTML,
	 * 	where an HTML parser turns the prolog into a bogus comment.
	 * @return Sanitizer
	 */
	private function getSanitizer(): Sanitizer
	{
		if (!$this->sanitizer) {
			$this->sanitizer = new Sanitizer();
			$this->sanitizer->minify(true);
			$this->sanitizer->removeXMLTag(true);
		}
		return $this->sanitizer;
	}

	/**
	 * Replace core/image Gutenberg block with responsiveImageTag.
	 * @param string $blockContent HTML content of block.
	 * @param ImageSizeList $sizes
	 * @param bool $withRatio Whether to add aspect-ratio box.
	 * @param bool $withMaxWidth Whether to add max-width to aspect-ratio box.
	 * @param bool $withPicture Whether to wrap the <img> in a <picture> tag with the image formats
	 * 	set on the ImageSizeList. Beware that it changes the markup of the block, ie. CSS which
	 * 	targets a direct child of the figure or the link has to be updated.
	 * @return string The rebuilt block, or the given content when the block holds no image to
	 * 	replace - the issue is reported in that case.
	 *
	 * @see static::reportIssue()
	 * @see https://naswp.cz/mistrovska-optimalizace-obrazku-nejen-pro-wordpress/#nove-workflow-dodatek-obrazky-v-gutenbergu
	 * @see static::getResponsivePictureTag()
	 */
	public function replaceImageBlock(string $blockContent, ImageSizeList $sizes, bool $withRatio = false, bool $withMaxWidth = true, bool $withPicture = false): string
	{
		$dom = new DOMDocument();
		$dom->loadHTML('<?xml encoding="utf-8" ?>' . $blockContent, LIBXML_NOERROR);

		$img = $dom->getElementsByTagName('img')->item(0);
		if (!$img instanceof DOMElement) {
			// Eg. an image block whose author didn't choose any image yet
			$this->reportIssue('The core/image block has no <img> element to replace.');
			return $blockContent;
		}
		if (Strings::lower(pathinfo(strval($img->getAttribute('src')), PATHINFO_EXTENSION)) === 'svg') return $blockContent;

		$div = $dom->getElementsByTagName('div')->item(0);
		$figure = $dom->getElementsByTagName('figure')->item(0);
		$link = $dom->getElementsByTagName('a')->item(0);
		$caption = $dom->getElementsByTagName('figcaption')->item(0);

		if (!$figure instanceof DOMElement) {
			$this->reportIssue('The core/image block has no <figure> element to rebuild.');
			return $blockContent;
		}
		$class = strval($figure->getAttribute('class'));
		if ($div instanceof DOMElement) $class .= ' ' . $div->getAttribute('class');

		$imgStyle = strval($img->getAttribute('style'));

		// An attribute copied from the block is nulled when empty, because Html renders
		// an empty string as an empty attribute, eg. id="". The `alt` below is the exception:
		// an empty alt is the correct markup of a decorative image, leaving it out is not.
		$fig = Html::el('figure', [
			'class' => $class,
			'id' => $figure->getAttribute('id') ?: null
		]);
		if ($link instanceof DOMElement) {
			$imgParent = Html::el('a', [
				'class' => $link->getAttribute('class') ?: null,
				'href' => $link->getAttribute('href'),
				'target' => $link->getAttribute('target') ?: null,
				'rel' => $link->getAttribute('rel') ?: null
			]);
			$fig->addHtml($imgParent);
		} else {
			$imgParent = $fig;
		}

		if ($withRatio && $size = $this->getOriginalSize()) {
			$ratio = round(100 * $size[1] / $size[0], 2);
			$style = "--bs-aspect-ratio:$ratio%";
			if ($withMaxWidth) $style .= ";--img-max-width:$size[0]px";
			$imgParent
				->addClass('ratio')
				->addStyle($style);
		}

		$imgAttrs = [
			'class' => $img->getAttribute('class') ?: null,
			'id' => $img->getAttribute('id') ?: null,
			'alt' => $img->getAttribute('alt'),
			'title' => $img->getAttribute('title') ?: null
		];
		if ($imgStyle) $imgAttrs['style'] = $imgStyle;

		$imgTag = ($withPicture)
			? $this->getResponsivePictureTag($sizes, $imgAttrs)
			: $this->getResponsiveImgTag($sizes, $imgAttrs);
		$imgParent->addHtml($imgTag);

		if ($caption) {
			$captionText = implode(array_map([$caption->ownerDocument, 'saveHTML'], iterator_to_array($caption->childNodes)));
			$fig->addHtml(Html::el('figcaption')->setHtml($captionText));
		}

		return (string)$fig;
	}
}