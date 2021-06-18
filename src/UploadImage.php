<?php

namespace Fronty\ResponsiveImages;

use Nette\Utils\Html;
use Nette\Utils\Strings;
use enshrined\svgSanitize\Sanitizer;
use PHPHtmlParser\Dom;
use LogicException;

/**
 * Object to manipulate with uploaded images.
 *	Get images from Cloudinary, Fly Dynamic Image Resizer plugin or by WP native functions.
 *  Big up for Adam Laita: https://naswp.cz/mistrovska-optimalizace-obrazku-nejen-pro-wordpress/#cloudinary
 *
 * @see https://github.com/junaidbhura/auto-cloudinary/wiki/Best-Practices
 */
class UploadImage extends BaseImage
{
	/** @var int */
	private $ID;

	/** @var Sanitizer */
	private $sanitizer;

	/** @var array Named breakpoints for self::getResponsiveImgTag(), desktop-first, ie. [xs => 575, sm => 767] */
	private $breakpoints;

	/** @var array Without width, height and crop (set individually) */
	private $defaultCloudinaryTransform = [
		'quality' => 'auto:eco',
		'fetch_format' => 'auto'
	];

	/**
	 * Get object for image uploaded in admin by WP attachment ID.
	 * @param int $ID
	 */
	public function __construct(int $ID)
	{
		$this->ID = $ID;
	}

	/**
	 * Set breakpoints for image.
	 * @param array $breakpoints
	 * @return static
	 */
	public function setBreakpoints(array $breakpoints): static
	{
		$this->breakpoints = $breakpoints;
		return $this;
	}

	/**
	 * Get URL of the original image stored in WP uploads folder.
	 * @inheritDoc
	 */
	public function getUrl(): string
	{
		$src = wp_get_attachment_image_src($this->ID, 'full');
		return $src[0];
	}

	/**
	 * Get path of the original SVG stored in WP uploads folder.
	 * @inheritDoc
	 */
	public function getPath(): string
	{
		return get_attached_file($this->ID);
	}

	/**
	 * Get image URL for given size and crop.
	 * 	Crop is not performed if neither Auto Cloudinary and Fly Images plugins are available.
	 *  For Cloudinary crop values, see https://cloudinary.com/documentation/resizing_and_cropping
	 * @param int $width
	 * @param int|null $height Null = proportional height.
	 * @param string|bool $crop (true = 'fill', false = 'fit')
	 * @param array $cloudinaryTransform
	 * @return array [0 => URL, 1 => image width, 2 => image height]
	 */
	public function getSizedSrc(int $width, int $height = null, $crop = 'fit', array $cloudinaryTransform = []): array
	{
		if ($this->isSvg()) {
			$src = wp_get_attachment_image_src($this->ID, [$width, $height]);
			$src[1] = $width;
			$src[2] = $height;
			return $src;
		}

		if ($crop === '1') $crop = 'fill';
		if (!$crop) $crop = 'fit';
		if (function_exists('cloudinary_url')) {
			$cloudinaryTransform = array_merge(
				$this->defaultCloudinaryTransform,
				$cloudinaryTransform,
				[
					'width' => $width,
					'height' => $height,
					'crop' => $crop
				]
			);
			return [cloudinary_url($this->ID, ['transform' => $cloudinaryTransform]), $width, $height];
		}
		if (function_exists('fly_get_attachment_image_src')) {
			$doCrop = in_array($crop, ['crop', 'fill', 'lfill', 'fill_pad', 'thumb']);
			return array_values(fly_get_attachment_image_src($this->ID, [$width, $height], $doCrop));
		}
		return wp_get_attachment_image_src($this->ID, [$width, $height]);
	}

	/**
	 * Create link to image of given size.
	 * 	Useful when creating links to open image in lightbox.
	 * @param array $attrs Array of link HTML attributes.
	 * @param int|null $width If null given, original image size will be used and other arguments will be ignored.
	 * @param int|null $height Null = proportional height.
	 * @param string|bool $crop (true = 'fill', false = 'fit')
	 * @param array $cloudinaryTransform
	 * @return Html
	 *
	 * @see static::getSizedSrc()
	 */
	public function getImgLink(array $attrs = [], int $width = null, int $height = null, $crop = 'fit', array $cloudinaryTransform = []): Html
	{
		$attrs['href'] = ($width === null) ? $this->getUrl() : $this->getSizedSrc($width, $height, $crop, $cloudinaryTransform)[0];
		return Html::el('a', $attrs);
	}

	/**
	 * @inheritDoc
	 * @param string|bool $crop Cloudinary crop transformation, true (= crop fill) or false (= crop fit).
	 */
	public function getImgTag(int $width, int $height = null, array $attrs = [], $crop = 'fit'): Html
	{
		$src = $this->getSizedSrc($width, $height, $crop);
		$attrs['src'] = esc_url($src[0]);
		$attrs['width'] = $src[1];
		$attrs['height'] = $src[2];
		if (!isset($attrs['alt'])) $attrs['alt'] = $this->getDefaultAlt();
		$attrs['alt'] = esc_attr(strip_tags($attrs['alt']));
		$el = Html::el('img', $attrs);
		apply_filters('fri_upload_img_tag', $el);
		return $el;
	}

	/**
	 * @inheritDoc
	 */
	public function imgTag(int $width, int $height = null, array $attrs = [], $crop = 'fit')
	{
		echo $this->getImgTag($width, $height, $attrs, $crop);
	}

	/**
	 * Get <img> tag with srcset.
	 * @param array $sizeArgs
	 * 	Array of sizes arguments.
	 * 	Each array item represents arguments for self::getSizedSrc().
	 * 	Keys represents either named Bootstrap breakpoint (ie. 'md', 'lg', ...) or maximal viewport width in px, for which the size should apply.
	 * 	[
	 * 		'md' => [360, null, 'fill'],
	 * 		'lg' => [900, 600],
	 * 		1600 => [1440]
	 * 	]
	 * @param array $attrs
	 * @return Html
	 *
	 * @see self::setBreakpoints()
	 * @see self::getSizedSrc()
	 */
	public function getResponsiveImgTag(array $sizeArgs, array $attrs = []): Html
	{
		$sizeArgs = $this->translateBreakpoints($sizeArgs);
		if ($this->isSvg()) {
			$args = end($sizeArgs);
			$width = $args[0];
			$height = (!isset($args[1]) || $args[1] === null) ? $args[0] : $args[1];
			return $this->getImgTag(intval($width), intval($height), $attrs);
		}

		$defaultSrc = call_user_func_array([$this, 'getSizedSrc'], $sizeArgs[key($sizeArgs)]);
		if (isset($sizeArgs[0])) unset($sizeArgs[0]);

		$sizes = $srcsets = [];
		$i = 1;
		foreach ($sizeArgs as $viewport => $args) {
			$isLast = ($i === count($sizeArgs));
			$src = call_user_func_array([$this, 'getSizedSrc'], $args);
			if ($isLast) {
				$sizes[] = $src[1] . 'px';
			} else {
				$sizes[] = '(max-width: ' . $viewport . 'px) ' . $src[1] . 'px';
			}
			$srcsets[] = esc_url($src[0]) . ' ' . $src[1] . 'w';
			$i ++;
		}

		$attrs['src'] = esc_url($defaultSrc[0]);
		$attrs['sizes'] = implode(', ', $sizes);
		$attrs['srcset'] = implode(', ', $srcsets);
		if (!isset($attrs['width'])) $attrs['width'] = $src[1];
		if (!isset($attrs['height'])) $attrs['height'] = $src[2];
		if (!isset($attrs['alt'])) $attrs['alt'] = $this->getDefaultAlt();
		$attrs['alt'] = esc_attr(strip_tags($attrs['alt']));
		$el = Html::el('img', $attrs);
		apply_filters('fri_upload_responsive_img_tag', $el);
		return $el;
	}

	/**
	 * Output responsive image tag.
	 * @param array $sizeArgs
	 * @param array $attrs
	 *
	 * @see self::getResponsiveImgTag
	 */
	public function responsiveImgTag(array $sizeArgs, array $attrs = [])
	{
		echo $this->getResponsiveImgTag($sizeArgs, $attrs);
	}

	/**
	 * Get img tag wrapped in span ensuring aspect-ratio before image is lazyloaded.
	 * @param int $width Width used as img width attribute and to calculate ratio.
	 * @param int $height Height used as img height attribute and to calculate ratio.
	 * @param array $responsiveWidths
	 * 	Array of either int[] widths based on viewport width, or array[] of all self::getSizedSrc() arguments [width, height, crop].
	 * 	Keys represents either named Bootstrap breakpoint (ie. 'md', 'lg', ...) or maximal viewport width in px, for which the width should apply.
	 * 	[
	 * 		'md' => 360,
	 * 		'lg' => 900,
	 * 		1600 => [1440, 900, true]
	 * 	]
	 * @param array $wrapAttrs HTML arguments for ratio wrapper span.
	 * @param array $imgAttrs HTML arguments for img tag.
	 * @return Html
	 */
	public function getAspectImgTag(int $width, int $height, array $responsiveWidths, array $wrapAttrs = [], array $imgAttrs = []): Html
	{
		$sizeArgs = $responsiveWidths;
		$maxWidth = 0;
		foreach ($sizeArgs as &$sizeArg) {
			$sizeArg = (is_array($sizeArg)) ? $sizeArg : [intval($sizeArg), null, false];
			if ($sizeArg[0] > $maxWidth) $maxWidth = $sizeArg[0];
		}

		$img = $this->getResponsiveImgTag($sizeArgs, $imgAttrs);

		$ratio = $height / $width * 100;
		return Html::el('div', $wrapAttrs)
			->addClass('ratio')->setStyle('--bs-aspect-ratio:' . $ratio . '%; max-width:' . $maxWidth . 'px')
			->addHtml(Html::el('figure', ['class' => 'mb-0'])->addHtml($img));
	}

	/**
	 * Output img tag wrapped in aspect-ratio ensuring span.
	 * @param int $width
	 * @param int $height
	 * @param array $responsiveWidths
	 * @param array $wrapAttrs
	 * @param array $imgAttrs
	 *
	 * @see self::getAspectImgTag()
	 */
	public function aspectImgTag(int $width, int $height, array $responsiveWidths, array $wrapAttrs = [], array $imgAttrs = [])
	{
		echo $this->getAspectImgTag($width, $height, $responsiveWidths, $wrapAttrs, $imgAttrs);
	}

	/**
	 * @inheritDoc
	 */
	public function getInlineSvg(): string
	{
		if (!$this->isSvg()) throw new LogicException("Image '{$this->getUrl()}' is not SVG.");
		$svg = file_get_contents($this->getPath());
		$svg = $this->svgUniqueClasses($svg);
		return $this->getSanitizer()->sanitize($svg);
	}

	/**
	 * Get default image alt.
	 * @return string
	 */
	private function getDefaultAlt(): string
	{
		if ($alt = get_post_meta($this->ID, '_wp_attachment_image_alt', true)) return $alt;
		if ($alt = get_the_title($this->ID)) return $alt;
		return '';
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
	 * Translate named breakpoints in given array's keys to viewport max withs integers, sort array asc.
	 * @param array $sizes See self::getResponsiveImgTag()
	 * @return array
	 */
	private function translateBreakpoints(array $sizes): array
	{
		$translated = [];
		foreach ($sizes as $key => $args) {
			if (isset($this->breakpoints[$key])) $key = $this->breakpoints[$key];
			if (is_numeric($key)) $translated[$key] = $args;
		}
		ksort($translated);
		return $translated;
	}

	/**
	 * Replace core/image Gutenberg block for responsiveImageTag.
	 * @param string $blockContent
	 * @param array $alignSizeArgs Array of responsive image sizes based on image align class.
	 *	Keys may be following: aligncenter|alignleft|alignright|alignwide|alignfull.
	 * 	Values of array has to comply with structure for self::getResponsiveImgTag().
	 * 	If no class found in keys, aligncenter will be used.
	 * @return string
	 * @see https://naswp.cz/mistrovska-optimalizace-obrazku-nejen-pro-wordpress/#nove-workflow-dodatek-obrazky-v-gutenbergu
	 */
	public function replaceImageBlock(string $blockContent, array $alignSizeArgs): string
	{
		$dom = new DOM();
		$dom->loadStr($blockContent);

		$figure = $dom->find('figure', 0);
		$link = $dom->find('a', 0);
		$img = $dom->find('img', 0);
		if (Strings::lower(pathinfo($img->src, PATHINFO_EXTENSION)) === 'svg') return $blockContent;

		$classes = array_filter(array_map('trim', explode(' ', $figure->class)));
		$align = 'aligncenter';
		foreach ($classes as $class) {
			if (isset($alignSizeArgs[$class])) {
				$align = $class;
				break;
			}
		}
		$sizeArgs = $alignSizeArgs[$align];

		$fig = Html::el('figure', ['class' => $figure->class, 'id' => $figure->id]);
		if ($link) {
			$imgParent = Html::el('a', ['class' => $link->class, 'href' => $link->href, 'target' => $link->target, 'rel' => $link->rel]);
			$fig->addHtml($imgParent);
		} else {
			$imgParent = $fig;
		}

		$imgTag = $this->getResponsiveImgTag($sizeArgs, [
			'class' => $img->class,
			'id' => $img->id,
			'alt' => $img->alt,
			'title' => $img->title
		]);
		$imgParent->addHtml($imgTag);

		return (string)$fig;
	}
}