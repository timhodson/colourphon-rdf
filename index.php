<?php

/* Script to get RDF about a colour URI
 *  supported path elements are
 *      http://...com/id/colour/00ff00
 * and so on.
 * see http://data.colourphon.co.uk/id/colour/7b3f47 as an example
 */
define('MORIARTY_DIR', '/var/www/lib/moriarty/');
define('MORIARTY_ARC_DIR', '/var/www/lib/ARC/');
define('CP_DEBUG', false);

require_once 'colourphonrdf.class.php';

$c = new ColourphonRdf();

// Debug junk here follows....
//
//  print "From Hex: ";
//  $testhex = "#C5003E";
//  $testhex = "#0039F5";
//  $testhex = "#FFED47";
//  print $testhex;
//
//  echo "<pre>";
//  $c->set_hex($testhex);
//
//  $rgb = $c->get_rgb();
//  $hex = $c->get_hex();
//  $hsl = $c->get_hsl();
//
//  print_r($rgb);
//  print_r($hex);
//  print_r($hsl);
//  echo "</pre>";
//
//  print "<hr><br />From RGB: <pre> ";
//  $c->set_rgb($rgb);
//  $rgb = $c->get_rgb();
//  $hex = $c->get_hex();
//  $hsl = $c->get_hsl();
//  print_r($rgb);
//  print_r($hex);
//  print_r($hsl);
//  echo "</pre>";
//
//  print "<hr><br />From HSL: <pre> ";
//  $c->set_hsl($hsl);
//  $rgb = $c->get_rgb();
//  $hex = $c->get_hex();
//  $hsl = $c->get_hsl();
//  print_r($rgb);
//  print_r($hex);
//  print_r($hsl);
//  echo "</pre>";



?>