<?php

/*
 * Script to loop through hex values and generate data!
 *
 * Sorry no documentation yet, but basically, this script is designed to loop
 *  through rgb values from a given starting point.
 *      php -f looper.php -- --red=0 --green=0 --blue=0
 *      php -f looper.php -- --red=0 --green=0 --blue=1
 *      php -f looper.php -- --red=0 --green=0 --blue=0
 *      php -f looper.php -- --red=0 --green=0 --blue=0 --red-max=2
 *
 * red-max indicates that the script should stop when the red loop gets to this value.
 *
 * In theory this script could be adapted to run in a map reduce kinda fashion,
 *  but that hasn't happened yet.
 *
 * I watch the log files with a command like this:
 *      watch --interval=1 --no-title tail -n2 looper*.log
 */
define('MORIARTY_DIR', '/var/www/lib/moriarty/');
define('MORIARTY_ARC_DIR', '/var/www/lib/ARC/');
define('CP_DEBUG', false);

require_once 'colourphonrdf.class.php';

echo strftime("%d/%m/%Y:%H:%M:%S",time())." : Script start\n";

/**
 * parseArgs Command Line Interface (CLI) utility function.
 * @usage               $args = parseArgs($_SERVER['argv']);
 * @author              Patrick Fisher <patrick@pwfisher.com>
 * @source              https://github.com/pwfisher/CommandLine.php
 */
function parseArgs($argv){
    array_shift($argv);
    $out = array();
    foreach ($argv as $arg){
        if (substr($arg,0,2) == '--'){
            $eqPos = strpos($arg,'=');
            if ($eqPos === false){
                $key = substr($arg,2);
                $out[$key] = isset($out[$key]) ? $out[$key] : true;
            } else {
                $key = substr($arg,2,$eqPos-2);
                $out[$key] = substr($arg,$eqPos+1);
            }
        } else if (substr($arg,0,1) == '-'){
            if (substr($arg,2,1) == '='){
                $key = substr($arg,1,1);
                $out[$key] = substr($arg,3);
            } else {
                $chars = str_split(substr($arg,1));
                foreach ($chars as $char){
                    $key = $char;
                    $out[$key] = isset($out[$key]) ? $out[$key] : true;
                }
            }
        } else {
            $out[] = $arg;
        }
    }
    return $out;
}

$step = 1;

$args = parseArgs($argv);
var_dump($args);

if( !isset($args['red']) || !isset($args['red-max']) || !isset($args['green']) || !isset($args['blue'])){
    echo "args not valid\n";
    exit;
}

$file = $args['file'];
$fh = fopen($file, 'a');

//3,134,26
//6,203,186
//32,241,219
//65,253,237
$startRGB[0] = $args['red'];
$startRGB[1] = $args['green'];
$startRGB[2] = $args['blue'];

for ($r = $startRGB[0]; $r <= $args['red-max']; $r+=$step) {

    for ($g = $startRGB[1]; $g <= 255; $g+=$step) {

        for ($b = $startRGB[2]; $b <= 255; $b+=$step) {
            $rgb[0] = $r;
            $rgb[1] = $g;
            $rgb[2] = $b;
            
            if (!isset ($c)) $c = new ColourphonRdf(true) ;

            //echo strftime("%d/%m/%Y:%H:%M:%S",time())." : Processing \n";

            $c->set_rgb($rgb);
            echo strftime("%d/%m/%Y:%H:%M:%S",time())." : Processing ".$c->get_rgb_as_string()." ".$c->get_hex()."\n";

            $uri = "/doc/colour/".ltrim($c->get_hex(),"#");

            $c->parse_path($uri);

            echo strftime("%d/%m/%Y:%H:%M:%S",time())." : Storing ".$c->get_hex()."\n";
            //$c->store_data();
            fwrite($fh, $c->get_ntriples());
            //unset ($c);
        }
    }
}
fclose($fh);
echo strftime("%d/%m/%Y:%H:%M:%S",time())." : Script end\n";

?>
