<?php
/*
		pBarcode128 - class to create barcodes (128B)

		Version     : 2.1.3
		Made by     : Jean-Damien POGOLOTTI
		Last Update : 09/09/11

		This file can be distributed under the license you can find at :

											http://www.pchart.net/license

		You can find the whole class documentation on the pChart web site.
*/

/* pData class definition */

class pBarcode128 {
	var $Codes;
	var $Reverse;
	var $Result;
	var $pChartObject;
	var $CRC;

	/* Class creator */
	function __construct($BasePath = ""){
		$this->Codes   = [];
		$this->Reverse = [];

		$FileHandle = @fopen($BasePath . "data/128B.db", "r");

		if (!$FileHandle){
			die("Cannot find barcode database (" . $BasePath . "128B.db).");
		}

		while (!feof($FileHandle)){
			$Buffer = fgets($FileHandle, 4096);
			$Buffer = str_replace(chr(10), "", $Buffer);
			$Buffer = str_replace(chr(13), "", $Buffer);
			$Values = preg_split("/;/", $Buffer);

			$this->Codes[$Values[1]]["ID"]     = $Values[0];
			$this->Codes[$Values[1]]["Code"]   = $Values[2];
			$this->Reverse[$Values[0]]["Code"] = $Values[2];
			$this->Reverse[$Values[0]]["Asc"]  = $Values[1];
		}
		fclose($FileHandle);
	}

	/* Return the projected size of a barcode */
	function getSize($TextString, $Format = []){
		$Angle        = $Format["Angle"] ?? 0;
		$ShowLegend   = $Format["ShowLegend"] ?? false;
		$LegendOffset = $Format["LegendOffset"] ?? 5;
		$DrawArea     = $Format["DrawArea"] ?? false;
		$FontSize     = $Format["FontSize"] ?? 12;
		$Height       = $Format["Height"] ?? 30;

		$TextString    = $this->encode128($TextString);
		$BarcodeLength = strlen($this->Result);

		if ($DrawArea){
			$WOffset = 20;
		}else{
			$WOffset = 0;
		}
		if ($ShowLegend){
			$HOffset = $FontSize + $LegendOffset + $WOffset;
		}else{
			$HOffset = 0;
		}

		$X1 = cos($Angle * PI / 180) * ($WOffset + $BarcodeLength);
		$Y1 = sin($Angle * PI / 180) * ($WOffset + $BarcodeLength);

		$X2 = $X1 + cos(($Angle + 90) * PI / 180) * ($HOffset + $Height);
		$Y2 = $Y1 + sin(($Angle + 90) * PI / 180) * ($HOffset + $Height);


		$AreaWidth  = max(abs($X1), abs($X2));
		$AreaHeight = max(abs($Y1), abs($Y2));

		return (["Width" => $AreaWidth, "Height" => $AreaHeight]);
	}

	function encode128($Value, $Format = []){
		$this->Result = "11010010000";
		$this->CRC    = 104;
		$TextString   = "";

		for ($i = 1;$i <= strlen($Value);$i++){
			$CharCode = ord($this->mid($Value, $i, 1));
			if (isset($this->Codes[$CharCode])){
				$this->Result = $this->Result . $this->Codes[$CharCode]["Code"];
				$this->CRC    = $this->CRC + $i * $this->Codes[$CharCode]["ID"];
				$TextString   = $TextString . chr($CharCode);
			}
		}
		$this->CRC = $this->CRC - floor($this->CRC / 103) * 103;

		$this->Result = $this->Result . $this->Reverse[$this->CRC]["Code"];
		$this->Result = $this->Result . "1100011101011";

		return ($TextString);
	}

	/* Create the encoded string */
	function draw($Object, $Value, $X, $Y, $Format = []){
		$this->pChartObject = $Object;

		$R            = $Format["R"] ?? 0;
		$G            = $Format["G"] ?? 0;
		$B            = $Format["B"] ?? 0;
		$Alpha        = $Format["Alpha"] ?? 100;
		$Height       = $Format["Height"] ?? 30;
		$Angle        = $Format["Angle"] ?? 0;
		$ShowLegend   = $Format["ShowLegend"] ?? false;
		$LegendOffset = $Format["LegendOffset"] ?? 5;
		$DrawArea     = $Format["DrawArea"] ?? false;
		$AreaR        = $Format["AreaR"] ?? 255;
		$AreaG        = $Format["AreaG"] ?? 255;
		$AreaB        = $Format["AreaB"] ?? 255;
		$AreaBorderR  = $Format["AreaBorderR"] ?? $AreaR;
		$AreaBorderG  = $Format["AreaBorderG"] ?? $AreaG;
		$AreaBorderB  = $Format["AreaBorderB"] ?? $AreaB;

		$TextString = $this->encode128($Value);

		if ($DrawArea){
			$X1 = $X + cos(($Angle - 135) * PI / 180) * 10;
			$Y1 = $Y + sin(($Angle - 135) * PI / 180) * 10;

			$X2 = $X1 + cos($Angle * PI / 180) * (strlen($this->Result) + 20);
			$Y2 = $Y1 + sin($Angle * PI / 180) * (strlen($this->Result) + 20);

			if ($ShowLegend){
				$X3 = $X2 + cos(($Angle + 90) * PI / 180) * ($Height + $LegendOffset + $this->pChartObject->FontSize + 10);
				$Y3 = $Y2 + sin(($Angle + 90) * PI / 180) * ($Height + $LegendOffset + $this->pChartObject->FontSize + 10);
			}else{
				$X3 = $X2 + cos(($Angle + 90) * PI / 180) * ($Height + 20);
				$Y3 = $Y2 + sin(($Angle + 90) * PI / 180) * ($Height + 20);
			}

			$X4 = $X3 + cos(($Angle + 180) * PI / 180) * (strlen($this->Result) + 20);
			$Y4 = $Y3 + sin(($Angle + 180) * PI / 180) * (strlen($this->Result) + 20);

			$Polygon  = [$X1, $Y1, $X2, $Y2, $X3, $Y3, $X4, $Y4];
			$Settings = ["R" => $AreaR, "G" => $AreaG, "B" => $AreaB, "BorderR" => $AreaBorderR, "BorderG" => $AreaBorderG, "BorderB" => $AreaBorderB];
			$this->pChartObject->drawPolygon($Polygon, $Settings);
		}

		for ($i = 1;$i <= strlen($this->Result);$i++){
			if ($this->mid($this->Result, $i, 1) == 1){
				$X1 = $X + cos($Angle * PI / 180) * $i;
				$Y1 = $Y + sin($Angle * PI / 180) * $i;
				$X2 = $X1 + cos(($Angle + 90) * PI / 180) * $Height;
				$Y2 = $Y1 + sin(($Angle + 90) * PI / 180) * $Height;

				$Settings = ["R" => $R, "G" => $G, "B" => $B, "Alpha" => $Alpha];
				$this->pChartObject->drawLine($X1, $Y1, $X2, $Y2, $Settings);
			}
		}

		if ($ShowLegend){
			$X1 = $X + cos($Angle * PI / 180) * (strlen($this->Result) / 2);
			$Y1 = $Y + sin($Angle * PI / 180) * (strlen($this->Result) / 2);

			$LegendX = $X1 + cos(($Angle + 90) * PI / 180) * ($Height + $LegendOffset);
			$LegendY = $Y1 + sin(($Angle + 90) * PI / 180) * ($Height + $LegendOffset);

			$Settings = ["R" => $R, "G" => $G, "B" => $B, "Alpha" => $Alpha, "Angle" => -$Angle, "Align" => TEXT_ALIGN_TOPMIDDLE];
			$this->pChartObject->drawText($LegendX, $LegendY, $TextString, $Settings);
		}
	}

	function left($value, $NbChar){
		return substr($value, 0, $NbChar);
	}

	function right($value, $NbChar){
		return substr($value, strlen($value) - $NbChar, $NbChar);
	}

	function mid($value, $Depart, $NbChar){
		return substr($value, $Depart - 1, $NbChar);
	}
}

?>