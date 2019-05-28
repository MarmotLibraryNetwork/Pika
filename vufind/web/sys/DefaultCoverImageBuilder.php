<?php
/**
 * Creates a default image for a cover based on a default background.
 * Overlays with title and author
 * Based on work done by Juan Gimenez at Douglas County Libraries
 *
 * @category Pika
 * @author   Mark Noble <mark@marmot.org>
 * Date: 10/30/13
 * Time: 5:17 PM
 */

class DefaultCoverImageBuilder {

	private $imageWidth               = 280; //Pixels
	private $imageHeight              = 400; // Pixels
	private $imagePrintableAreaWidth  = 254; //Area printable in Pixels (includes 13px margin on both sides)
	private $imagePrintableAreaHeight = 380; //Area printable in Pixels (includes 10px margin on both sides)
	private $colorText                = array("red" => 1, "green" => 1, "blue" => 1);
	private $titleFont;
	private $authorFont;

	/**
	 * DefaultCoverImageBuilder constructor.
	 * Sets the font files
	 */
	public function __construct(){
		// ROOT_DIR may not be defined when the class is first included.
		$this->titleFont  = ROOT_DIR . '/fonts/DejaVuSansCondensed-Bold.ttf';
		$this->authorFont = ROOT_DIR . '/fonts/DejaVuSansCondensed-BoldOblique.ttf';
	}

	/**
	 * Main method which generates the default cover with title & author if possible on the image
	 *
	 * @param string $title
	 * @param string $author
	 * @param string $format
	 * @param string $format_category
	 * @param string $filename
	 */
	public function getCover($title, $author, $format, $format_category, $filename){
		$coverName = strtolower(preg_replace('/\W/', '', $format));
		if (!file_exists(ROOT_DIR . '/images/blankCovers/' . $coverName . '.jpg')){
			$coverName = strtolower(preg_replace('/\W/', '', $format_category));

			if (!file_exists(ROOT_DIR . '/images/blankCovers/' . $coverName . '.jpg')){
				$coverName = 'books';
			}
		}

		//Create the background image
		$blankCover        = imagecreatefromjpeg(ROOT_DIR . '/images/blankCovers/' . $coverName . '.jpg');
		$this->imageWidth  = imagesx($blankCover);
		$this->imageHeight = imagesy($blankCover);

		$colorText = imagecolorallocate($blankCover, $this->colorText['red'], $this->colorText['green'], $this->colorText['blue']);

		//Add the title to the background image
		$textYPos = $this->addWrappedTextToImage($blankCover, $this->titleFont, $title, 25, 5, 10, $colorText);
		if (strlen($author) > 0){
			//Add the author to the background image
			$this->addWrappedTextToImage($blankCover, $this->authorFont, $author, 18, 10, $textYPos + 6, $colorText);
		}

		imagepng($blankCover, $filename);
		imagedestroy($blankCover);
	}

	/**
	 * Add text to an image, wrapping based on number of characters.
	 *
	 * @param resource $imageHandle The image resource to use
	 * @param string   $font        The font file to use to generate the text
	 * @param string   $text        The text to write
	 * @param int      $fontSize    The pixel size of the font to use
	 * @param int      $lineSpacing The number of pixels between lines of text
	 * @param int      $startY      The vertical pixel position for the text
	 * @param int      $color       The color identifier
	 * @return float|int  The starting vertical position for the line of text. Use to set where the next line of text should start at
	 */
	private function addWrappedTextToImage($imageHandle, $font, $text, $fontSize, $lineSpacing, $startY, $color){
		$textBox           = imageftbbox($fontSize, 0, $font, $text);
		$totalTextWidth    = abs($textBox[4] - $textBox[6]); //Get the total string length
		$numLines          = ceil((float)$totalTextWidth / (float)$this->imagePrintableAreaWidth); //Determine how many lines we will need to break the text into
		$charactersPerLine = floor(strlen($text) / $numLines);
		$lines             = explode("\n", wordwrap($text, $charactersPerLine, "\n", true)); //Wrap based on the number of lines
		foreach ($lines as $line){
			$lineBox    = imageftbbox($fontSize, 0, $font, $line);
			$lineWidth  = abs($lineBox[4] - $lineBox[6]); //Get the width of this line
			$lineHeight = abs($lineBox[3] - $lineBox[5]); //Get the height of this line
			$x          = ($this->imageWidth - $lineWidth) / 2; //Get the starting position for the text
			if ($this->imagePrintableAreaHeight > $startY + $lineHeight){
				$startY += $lineHeight;
				imagefttext($imageHandle, $fontSize, 0, $x, $startY, $color, $font, $line); //Write the text to the image
				$startY += $lineSpacing;
			}else{
				break; // Don't write text outside of the printable area
			}
		}
		return $startY;
	}

	/**
	 *  Checks that a default cover image exists. The default images are based on the format
	 *
	 * @param string $format          The format of the title to look for
	 * @param string $format_category The format category of the title to look for
	 * @return bool                   Whether or not the file exists
	 */
	public function blankCoverExists($format, $format_category){
		$coverName = strtolower(preg_replace('/\W/', '', $format));
		if (!file_exists(ROOT_DIR . '/images/blankCovers/' . $coverName . '.jpg')){
			$coverName = strtolower(preg_replace('/\W/', '', $format_category));

			if (!file_exists(ROOT_DIR . '/images/blankCovers/' . $coverName . '.jpg')){
				return false;
			}
		}
		return true;
	}

}