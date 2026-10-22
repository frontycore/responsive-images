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
			return array_values(bis_get_attachment_image_src($this->ID, [$width, $height], $size->getCropOrPosition(), ['retina' => true]));
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
	 * Get <img> tag with responsive sizes and srcset according to given ImageSizeList.
	 * @param ImageSizeList $sizes
	 * @param array $attrs
	 * @return Html
	 */
	public function getResponsiveImgTag(ImageSizeList $sizes, array $attrs = []): Html
	{
		$widest = $sizes->getWidest();
		$maxWidth = $widest->getWidth();
		$maxHeight = $widest->getHeight();

		if ($this->isSvg()) {
			return $this->getImgTag(new ImageSize($maxWidth, $maxHeight ?? $maxWidth), $attrs);
		}

		$sets = $this->createSrcsetSizes($sizes);
		$widestSrc = $this->getSizedSrc($widest);

		$setsSizes = apply_filters('fri_upload_responsive_img_sizes', $sets->sizes, $this);
		$setsSrcsets = apply_filters('fri_upload_responsive_img_srcset', $sets->srcsets, $this);

		if (isset($widestSrc[0])) {
			$attrs = array_merge($attrs, [
				'src' => $widestSrc[0],
				'sizes' => implode(', ', $setsSizes),
				'srcset' => implode(', ', $setsSrcsets)
			]);
		}

		if (!isset($attrs['width']) && $widestSrc) {
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
	 * Output preload link of the image with given responsive sizes.
	 * 	Put this to <head> section for soon-to-render images to optimize CLS.
	 * @param ImageSizeList $sizes
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

		$ratio = $height / $width * 100;
		$el = Html::el('figure', $wrapAttrs)
			->addClass('ratio')
			->setStyle('--bs-aspect-ratio:' . $ratio . '%')
			->addHtml($img);
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
	 * @inheritDoc
	 */
	public function getInlineSvg(): string
	{
		return $this->getSanitizer()->sanitize(parent::getInlineSvg());
	}

	/**
	 * Create arrays of `srcset` and `sizes` HTML attributes for img tag.
	 * 	While `sizes` describes the slot width declared for each breakpoint, `srcset` is built
	 * 	independently of it: every declared width is offered in all pixel densities set in the
	 * 	ImageSizeList, so even the widest breakpoint has a dense enough candidate on retina
	 * 	displays. Candidates are deduplicated by the really returned width.
	 * @param ImageSizeList $imageSizes
	 * @return ArrayHash
	 *
	 * @see ImageSizeList::setDensities()
	 */
	private function createSrcsetSizes(ImageSizeList $imageSizes): ArrayHash
	{
		$sizes = $srcsets = [];
		$original = $this->getOriginalSize();

		foreach ($imageSizes as $viewport => $size) {
			$src = $this->getSizedSrc($size);
			if (!$src) continue;

			if ($imageSizes->isLast()) {
				$sizes[] = $src[1] . 'px';
			} else {
				$media = ($imageSizes->isMobileFirst()) ? 'min-width' : 'max-width';
				$sizes[] = sprintf('(%s: %dpx) %dpx', $media, $viewport, $src[1]);
			}

			foreach ($imageSizes->getDensities() as $density) {
				// The 1x candidate is the declared size, already resolved for the `sizes` attribute
				$candidate = ($density === 1.0) ? $src : $this->getDensitySrc($size, $density, $original);
				if (!$candidate || !isset($candidate[1])) continue;

				// Keep the first candidate of each width, eg. densities clamped to the same original width
				$width = intval($candidate[1]);
				if ($width <= 0 || isset($srcsets[$width])) continue;
				$srcsets[$width] = esc_url_raw($candidate[0]) . ' ' . $width . 'w';
			}
		}

		ksort($srcsets);
		return ArrayHash::from([
			'sizes' => $sizes,
			'srcsets' => array_values($srcsets)
		], false);
	}

	/**
	 * Get image URL, width and height for given ImageSize multiplied by pixel density.
	 * 	The required size is clamped to the original image size before the resizer is asked,
	 * 	so nothing is upscaled: asked for a size larger than the original,
	 * 	bis_get_attachment_image_src() returns the untouched original, ie. an image of
	 * 	a different aspect ratio than the required crop.
	 * @param ImageSize $size Size declared for a breakpoint, ie. in 1x density.
	 * @param float $density Pixel density (DPR) to multiply the size with.
	 * @param array|null $original Size of the original image, null when it is unknown.
	 * @return array|null [0 => URL, 1 => image width, 2 => image height],
	 * 	null when the resizer didn't return the required aspect ratio.
	 *
	 * @see static::getSizedSrc()
	 */
	private function getDensitySrc(ImageSize $size, float $density, ?array $original): ?array
	{
		$required = $size->getScaled($this->getDensityScale($size, $density, $original));
		$src = $this->getSizedSrc($required);
		if (!$src || !isset($src[1], $src[2])) return null;
		if (!$this->hasRequiredRatio($required, intval($src[1]), intval($src[2]))) return null;
		return $src;
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
	 * @return Sanitizer
	 */
	private function getSanitizer(): Sanitizer
	{
		if (!$this->sanitizer) {
			$this->sanitizer = new Sanitizer();
			$this->sanitizer->minify(true);
		}
		return $this->sanitizer;
	}

	/**
	 * Replace core/image Gutenberg block with responsiveImageTag.
	 * @param string $blockContent HTML content of block.
	 * @param ImageSizeList $sizes
	 * @param bool $withRatio Whether to add aspect-ratio box.
	 * @param bool $withMaxWidth Whether to add max-width to aspect-ratio box.
	 * @return string
	 * @see https://naswp.cz/mistrovska-optimalizace-obrazku-nejen-pro-wordpress/#nove-workflow-dodatek-obrazky-v-gutenbergu
	 */
	public function replaceImageBlock(string $blockContent, ImageSizeList $sizes, bool $withRatio = false, bool $withMaxWidth = true): string
	{
		$dom = new DOMDocument();
		$dom->loadHTML('<?xml encoding="utf-8" ?>' . $blockContent, LIBXML_NOERROR);

		$img = $dom->getElementsByTagName('img')->item(0);
		assert($img instanceof DOMElement);
		if (Strings::lower(pathinfo(strval($img?->getAttribute('src')), PATHINFO_EXTENSION)) === 'svg') return $blockContent;

		$div = $dom->getElementsByTagName('div')->item(0);
		$figure = $dom->getElementsByTagName('figure')->item(0);
		$link = $dom->getElementsByTagName('a')->item(0);
		$caption = $dom->getElementsByTagName('figcaption')->item(0);

		assert($figure instanceof DOMElement);
		$class = strval($figure?->getAttribute('class'));
		if ($div instanceof DOMElement) $class .= ' ' . $div->getAttribute('class');

		$imgStyle = strval($img->getAttribute('style'));

		$fig = Html::el('figure', [
			'class' => $class,
			'id' => $figure->getAttribute('id')
		]);
		if ($link instanceof DOMElement) {
			$imgParent = Html::el('a', [
				'class' => $link->getAttribute('class'),
				'href' => $link->getAttribute('href'),
				'target' => $link->getAttribute('target'),
				'rel' => $link->getAttribute('rel')
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

		$imgTag = $this->getResponsiveImgTag($sizes, [
			'class' => $img->getAttribute('class'),
			'id' => $img->getAttribute('id'),
			'alt' => $img->getAttribute('alt'),
			'title' => $img->getAttribute('title')
		]);
		if ($imgStyle) $imgTag->style($imgStyle);
		$imgParent->addHtml($imgTag);

		if ($caption) {
			$captionText = implode(array_map([$caption->ownerDocument, 'saveHTML'], iterator_to_array($caption->childNodes)));
			$fig->addHtml(Html::el('figcaption')->setHtml($captionText));
		}

		return (string)$fig;
	}
}