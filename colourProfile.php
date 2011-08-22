<?php

/*
 * Created on 21 Jan 2008
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */

class ColourProfile {

    private $accuracy = -1;
    private $accuracyPercentage = 0;
    private $colourType = "Unknown";
    private $shade = "Unknown";
    private $databaseId = -1;
    private $shadeRed = -1;
    private $shadeGreen = -1;
    private $shadeBlue = -1;
    //performance tweak
    private $accuracyThreshold = 10;
    private $testRed;
    private $testGreen;
    private $testBlue;
    private $allColours;
    private $ADJUSTMENT = 75;

    function calculateAccuracyPercentage() {
        $acc = $this->accuracy;
        $total = 100;
        $percentage = ($acc / $total) * 100;
        $this->accuracyPercentage = $percentage;
    }

    function ColourProfile($testRed, $testGreen, $testBlue, $allColours) {
        $this->testRed = $testRed;
        $this->testGreen = $testGreen;
        $this->testBlue = $testBlue;
        $this->allColours = $allColours;
        $this->analyseTestColours();
        $this->calculateAccuracyPercentage();
    }

    function analyseTestColours() {
        $allColours = $this->allColours;
        $red = $this->testRed;
        $green = $this->testGreen;
        $blue = $this->testBlue;

        $size = sizeof($allColours);
        $previousOffset = 255;

        if ($red == $green && $green == $blue && $red > 15 && $red < 239) {
            //Performance tweak
            //This will be a grey

            $this->accuracy = 0;
            $this->shade = $red;
            $this->colourType = "Grey";
            //$this->databaseId = $colourIndex;
            // testing finds that this section will mean that a databaseId of -1 (default value) is returned. TH
//			echo "This will be a grey\n";
//			echo var_dump($this);
        } else {
//				echo "$size";
            for ($i = 0; $i < $size; $i++) {
//					echo "Here";
                $test = $allColours[$i]; //Final adjustment is used for now - will be different in final code  (I.e dynamic value from DB)
                $STORED_RANGE = $this->workOutRange($test[0], $test[1], $test[2], $this->ADJUSTMENT);
                $isStored = $this->colourTest($STORED_RANGE);
//					echo var_dump($STORED_RANGE);
                if ($isStored) {
                    $colourIndex = $i;
                    $colourName = $test[3];
                    $colourSubType = $test[4];
                    $offsetRed = $test[0] - $red;
                    $offsetGreen = $test[1] - $green;
                    $offsetBlue = $test[2] - $blue;

                    $offsetRed = SQRT($offsetRed * $offsetRed);
                    $offsetGreen = SQRT($offsetGreen * $offsetGreen);
                    $offsetBlue = SQRT($offsetBlue * $offsetBlue);

                    $newAccuracy = $offsetRed + $offsetGreen + $offsetBlue;
                    //$this->accuracy = $offsetRed + $offsetGreen + $offsetBlue;
                    //$fullListOfMatches[$totalMatches] = $colourIndex;
                    //$totalMatches++;

                    if ($newAccuracy < $previousOffset) {
                        $previousOffset = $newAccuracy;
                        $this->accuracy = $newAccuracy;
                        $this->shade = $colourName;
                        $this->colourType = $colourSubType;
                        $this->databaseId = $colourIndex;
                        //this is a performance tweak and might need to be adjusted if we are seriously missing accuracy
                        if ($newAccuracy <= 10) {
                            $i = $size;
                        }
                    }
                }
            }
        }
    }

    function colourTest($testcriteria) {
        $iscolour = false;
        $r = $this->testRed;
        $g = $this->testGreen;
        $b = $this->testBlue;

        if ($r >= $testcriteria[0] and $r <= $testcriteria[1] and $g >= $testcriteria[2] and $g <= $testcriteria[3] and $b >= $testcriteria[4] and $b <= $testcriteria[5]) {
            $iscolour = true;
        }
        return $iscolour;
    }

    function workOutRange($red, $green, $blue, $adjustment) {
        $low = 0;
        $high = 255;
        //fix for grey - eventually should be stored
        //this should never happen now
//		if ($red == $green && $green == $blue) {
//			//the overall range for grey must be controlled to stop too many matches, as we get more colours the overall adjustment will be shrunk (50 is uesed by default)
//			echo "all values equal";
//			//$adjustment = 5;
//		}
        //$adjustment = 50;

        $redLow = $this->workOutLow($red, $adjustment);
        $redHigh = $this->workOutHigh($red, $adjustment);
        $greenLow = $this->workOutLow($green, $adjustment);
        $greenHigh = $this->workOutHigh($green, $adjustment);
        $blueLow = $this->workOutLow($blue, $adjustment);
        $blueHigh = $this->workOutHigh($blue, $adjustment);

        $testArray = array(
            $redLow,
            $redHigh,
            $greenLow,
            $greenHigh,
            $blueLow,
            $blueHigh
        );

        return $testArray;
    }

    function getShadeRed() {
        return $this->shadeRed;
    }

    function getShadeGreen() {
        return $this->shadeGreen;
    }

    function getShadeBlue() {
        return $this->shadeBlue;
    }

    function getAccuracyPercentage() {
        return $this->accuracyPercentage;
    }

    function setShadeRed($shadeRed) {
        $this->shadeRed = $shadeRed;
    }

    function setShadeGreen($shadeGreen) {
        $this->shadeGreen = $shadeGreen;
    }

    function setShadeBlue($shadeBlue) {
        $this->shadeBlue = $shadeBlue;
    }

    function setDatabaseId($databaseId) {
        $this->databaseId = $databaseId;
    }

    function getDatabaseId() {
        return $this->databaseId;
    }

    function setAccuracy($accuracy) {
        $this->accuracy = $accuracy;
    }

    function getAccuracy() {
        return $this->accuracy;
    }

    function setColourType($colourType) {
        $this->colourType = $colourType;
    }

    function getColourType() {
        return $this->colourType;
    }

    function setShade($shade) {
        $this->shade = $shade;
    }

    function getShade() {
        return $this->shade;
    }

    function workOutLow($colour, $percentage) {
        $value = 50;
        $value = $percentage;

        $colour = $colour - $value;
//		echo __CLASS__."::".__FUNCTION__."Colour Low: {$colour}";
        if ($colour < 0) {
            $colour = 0;
        }
        return $colour;
    }

    function workOutHigh($colour, $percentage) {
        $value = 50;
        $value = $percentage;

        $colour = $colour + $value;
//		echo __CLASS__."::".__FUNCTION__."Colour High: {$colour}";
        if ($colour > 255) {
            $colour = 255;
        }

        return $colour;
    }

}
?>
