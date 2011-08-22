<?php

/* Script to get RDF about a colour URI
 *  supported path elements are
 *      http://...com/colour/00ff00
 */



require_once MORIARTY_DIR . 'moriarty.inc.php';
require_once MORIARTY_DIR . 'store.class.php';
require_once MORIARTY_DIR . 'credentials.class.php';

require_once 'http_response.class.php';
require_once 'colours.php';
require_once 'colourProfile.php';

//$store = new Store('');
//$ss = $store->get_sparql_service();
//
//$query = 'describe ?s where { ?s ?p ?o . } ';
//
//$response = $ss->query( $query, 'turtle');
//if ( $response->is_success() ) {
//   $results = $response->body;
//   // do something with results array...
//   debug_print($results, 'results');
//}else{
//    debug_print($response, 'error');
//}

/*
 * Pseudo Code
 *
 * recieve request
 * match /colour/{hex}
 * if not in store
 *  generate data
 *      colourProfile
 *      HSL
 *  write turtle
 *  post to store
 * else
 * return from store
 *
 */
class ColourphonRdf {

    private $hex;
    // string
    private $thing_uri;
    // string
    private $rgb;
    // array
    private $hsl;
    // array
    private $suppress;
    private $base_uri = "http://data.colourphon.co.uk";
    private $store_uri = "http://api.talis.com/stores/storename";
    private $store_user = 'storeuser';
    private $store_pass = 'storepass';
    private $cs_prefix = "http://data.colourphon.co.uk/def/colour-ontology#";
    private $path;
    //private $extension;
    //private $id;
    private $allColours;
    private $c;
    // to hold our Colour Profile object;
    //complementary
    private $complementary_uri;
    //triadic
    private $triadic;
    private $triadic_uri;
    // split complementary
    private $split_complementary_degrees = 10;
    private $split_complementary;
    private $split_complementary_uri;
    //analpgous
    private $analogous;
    private $analogous_degrees = 25;
    private $analogous_uri;

    public  $g;

    public function __construct($suppress = FALSE) {
        $this->suppress = $suppress;
        if (!$this->suppress)
            $this->parse_path();
        if (!$this->suppress)
            $this->build_response();
        //return $this;
    }

    /*
     * Parse Path
     * Look for one of:
     *      /id|doc/colour/{identifier}
     *      /id|doc/namedcolour/{identifier}
     *      /id|doc/colourscheme/{type}/{identifier}
     * where {type} is a type of colourscheme
     * where {identifier} is one of rgb/hsl/hex
     *
     * we know what sort of path we have based on this
     * $this->path['is_id']
     * $this->path['is_doc']
     * $this->path['is_colour']
     * $this->path['is_namedcolour']
     * $this->path['is_colourscheme']
     *
     */

    public function parse_path($uri='') {
        if (!$this->suppress) {
            $uri = $_SERVER['REQUEST_URI'];
        }
        // DEAL WITH SCRIPT INJECTTION ???
        $this->debug_print($uri, "Request URI");

        $parts = explode("/", $uri);
        $this->debug_print($parts, "Parts");

        if ($parts[1] == 'id') {
            $this->path['is_id'] = true;

            $last = array_pop($parts);
            if (preg_match("[.]", $last)) {
                $i = explode(".", $last);
                $this->path['key'] = $i[0];
                $this->path['extension'] = $i[1];
                $this->path['is_id'] = true;
                array_push($parts, $i[0]);
                $this->path['thing_uri'] = implode("/", $parts);
                $parts[1] = 'doc';
                $this->path['doc_uri'] = implode("/", $parts);
                if ($i[1] != '')
                    $this->path['doc_uri'] . "." . $this->path['extension'];
            }else {
                $this->path['thing_uri'] = implode("/", $parts);
            }
        } else if ($parts[1] == 'doc') {
            $this->path['is_doc'] = true;
            $this->path['doc_uri'] = implode("/", $parts);

            // defaults
            $this->path['is_namedcolour'] = false;
            $this->path['is_colourscheme'] = false;

            if ($parts[2] == 'colour') {
                $this->path['is_colour'] = true;
            }
            // how do we know what to resolve a named colour too?
            // only can support this if there is data in a store.
            else if ($parts[2] == 'namedcolour') {
                $this->path['is_namedcolour'] = true;
            } else if ($parts[2] == 'colourscheme') {
                $this->path['is_colourscheme'] = true;
                $this->path['colourscheme'] = $parts[3];
            }

            $last = array_pop($parts);
            
            if (preg_match("/[.]/",$last)) {
                $i = explode(".", $last);
                $this->path['key'] = $i[0];
                $this->path['extension'] = $i[1];
                array_push($parts, $i[0]);                
            }else{
                $this->path['key'] = $last;
                $this->path['extension'] = '';
                array_push($parts, $last); 
            }

            $parts[1] = 'id';
            $this->path['thing_uri'] = implode("/", $parts);
        } else {
            if (!$this->suppress)
                $res = new http_response('404', '', '');
        }

        if ($this->path['is_namedcolour'] === false) {
            if ($this->validate($this->path['key'], 'hex')) {
                $this->path['is_HEX'] = true;
            }
        }

        $this->thing_uri = $this->base_uri . $this->path['thing_uri'];
        // actually cannot diffrentiate between a RGB or HSL value so only supporting HEX.
        //if(preg_match("/[0-9]{1,3}\,[0-9]{1,3}\,[0-9]{1,3}/", $this->path['key'])) $this->path['is_RGB'] = true ;

        $this->debug_print($this->path, "Path");
    }

    public function build_response() {
        if ($this->path['is_id']) {
            //redirect id to doc with a 303
            if (!$this->suppress)
                $res = new http_response("303", '', '', $this->path['doc_uri']);
        }else if ($this->path['is_doc']) {
            // serve some data

            
            $this->debug_print($this->thing_uri, "thing_uri");

            // check if we allready have this in the store?
            $store = new Store($this->store_uri);
            $ss = $store->get_sparql_service();

            $response = $ss->describe($this->thing_uri, 'slcbd');
            if ($response->is_success()) {
                $this->debug_print($response, "Response from sparql describe for " . $this->thing_uri);

                $graph = new SimpleGraph();
                $graph->from_rdfxml($response->body);

                if ($graph->is_empty()) {
                    // generate new set of data
                    $this->debug_print($this->thing_uri, 'Graph is empty');
                    $data = $this->get_rdf();
                    $this->debug_print($data, "the graph");
                    if (!$this->suppress) {
                        $res = new http_response("200", $this->get_content_type(), $data);
                    }
                    //TODO store the new data ???
                    $this->store_data();
                } else {
                    // do something with graph...
                    $this->debug_print($response, "Response from store");
                    if (!$this->suppress)
                        $res = new http_response("200", $this->get_content_type(), $this->serialiseGraph($graph));
                }
            } else {
                // TODO else there was no useful response, so we generate some...
                // $this->debug_print($response,"Sparql Error");

                if (!$this->suppress)
                    $res = new http_response("200", $this->get_content_type(), $this->get_rdf());
            }
        }
    }

    private function get_content_type() {
        switch ($this->path['extension']) {
            case 'html':
                return 'text/html';
                break;
            case 'json':
                return 'text/json';
                break;
            case 'rdf' :
                return 'application/rdf+xml';
                break;
            case 'ttl' :
                return 'text/turtle';
                break;
            case 'nt' :
                return 'text/ntriples';
                break;
            default:
                return 'text/html';
                break;
        }
    }

    private function serialiseGraph($g) {
        $this->debug_print($g, "graph passed to serialiseGraph");
        switch ($this->path['extension']) {
            case 'html':
                return $g->to_html();
                break;
            case 'json':
                return $g->to_json();
                break;
            case 'rdf' :
                return $g->to_rdfxml();
                break;
            case 'ttl' :
                return $g->to_turtle();
                break;
            case 'nt' :
                return $g->to_ntriples();
                break;
            default:
                return $g->to_html();
                break;
        }
    }

    public function store_data() {
        $this->debug_print('', "in store_data()");

        $cred = new Credentials($this->store_user, $this->store_pass);
        $store = new Store($this->store_uri, $cred);
        $mb = $store->get_metabox();

        if (!isset($this->g)) {
            $this->get_rdf();
        }
        $this->debug_print($this->g, "Our graph");

        $mb->submit_turtle($this->g->to_turtle());
    }

    private function get_rdf() {

        //TODO make other identifiers act as access points??
        $this->debug_print($this->path['key'], "key");
        $this->set_hex($this->path['key']);

        $this->new_graph();

        if ($this->path['is_colour']) {
            $this->debug_print('', "in is_colour");
            $this->get_hex_description_rdf();
            $this->get_named_colour_rdf();
            $this->get_parent_rdf();
        }

        if ($this->path['is_namedcolour']) { // don't think we can do this...
            $this->get_named_colour_rdf();
        }

        if ($this->path['is_colour'] || ($this->path['is_colourscheme'] && $this->path['colourscheme'] == 'complementary')) {
            $this->debug_print('', "in complementary");
            $this->get_complementary_rdf();
        }
        if ($this->path['is_colour'] || ($this->path['is_colourscheme'] && $this->path['colourscheme'] == 'triadic')) {
            $this->debug_print('', "in triadic");
            $this->get_triadic_rdf();
        }
        if ($this->path['is_colour'] || ($this->path['is_colourscheme'] && $this->path['colourscheme'] == 'analogous')) {
            $this->debug_print('', "in analogous");
            $this->get_analogous_rdf();
        }
        if ($this->path['is_colour'] || ($this->path['is_colourscheme'] && $this->path['colourscheme'] == 'splitcomplementary')) {
            $this->debug_print('', "in split_complementary");
            $this->get_split_complementary_rdf();
        }


        return $this->serialiseGraph($this->g);
    }

    private function new_graph() {
        $this->g = new SimpleGraph();
        $this->g->set_namespace_mapping("colour", $this->cs_prefix);
    }

    private function get_hex_description_rdf() {
        // describe the hex uri
        $this->g->add_resource_triple($this->thing_uri, RDF_TYPE, "{$this->cs_prefix}Colour");
        $this->g->add_literal_triple($this->thing_uri, RDFS_LABEL, $this->get_c_shade());
        $this->g->add_literal_triple($this->thing_uri, "{$this->cs_prefix}hex", $this->hex);
        $this->g->add_literal_triple($this->thing_uri, "{$this->cs_prefix}rgb", $this->get_rgb_as_string());
        $this->g->add_literal_triple($this->thing_uri, "{$this->cs_prefix}red", $this->get_red());
        $this->g->add_literal_triple($this->thing_uri, "{$this->cs_prefix}green", $this->get_green());
        $this->g->add_literal_triple($this->thing_uri, "{$this->cs_prefix}blue", $this->get_blue());
        $this->g->add_literal_triple($this->thing_uri, "{$this->cs_prefix}hsl", $this->get_hsl_as_string());
        $this->g->add_literal_triple($this->thing_uri, "{$this->cs_prefix}hue", $this->get_hue());
        $this->g->add_literal_triple($this->thing_uri, "{$this->cs_prefix}saturation", $this->get_saturation());
        $this->g->add_literal_triple($this->thing_uri, "{$this->cs_prefix}lightness", $this->get_lightness());
    }

    private function get_complementary_rdf() {
        // complementary colour
        if ($this->path['is_colour']) {
            $this->g->add_resource_triple($this->thing_uri, "{$this->cs_prefix}colourScheme", $this->complementary_uri);
        }
        if ($this->path['is_colourscheme']) {
            $this->complementary_uri = $this->thing_uri;
        }
        $this->g->add_resource_triple($this->complementary_uri, "{$this->cs_prefix}colour", $this->get_complementary_as_resource());
        $this->g->add_resource_triple($this->complementary_uri, RDF_TYPE, "{$this->cs_prefix}ComplementaryColourScheme");
        $this->g->add_literal_triple($this->complementary_uri, RDFS_LABEL, "complementary colour to " . $this->get_c_shade() . " ({$this->hex})");
    }

    private function get_triadic_rdf() {
        //build triadic colours for the source colour
        if ($this->path['is_colour']) {
            $this->g->add_resource_triple($this->thing_uri, "{$this->cs_prefix}colourScheme", $this->triadic_uri);
        }
        if ($this->path['is_colourscheme']) {
            $this->triadic_uri = $this->thing_uri;
        }
        foreach ($this->get_triadic_as_resource() as $uri) {
            $this->debug_print($uri);
            $this->g->add_resource_triple($this->triadic_uri, "{$this->cs_prefix}colour", $uri);
        }
        $this->g->add_resource_triple($this->triadic_uri, RDF_TYPE, "{$this->cs_prefix}TriadicColourScheme");
        $this->g->add_literal_triple($this->triadic_uri, RDFS_LABEL, "triadic colours to complement " . $this->get_c_shade() . " ({$this->hex})");
    }

    private function get_split_complementary_rdf() {

        //build split complementary colours for the source colour
        if ($this->path['is_colour']) {
            $this->g->add_resource_triple($this->thing_uri, "{$this->cs_prefix}colourScheme", $this->split_complementary_uri);
        }
        if ($this->path['is_colourscheme']) {
            $this->split_complementary_uri = $this->thing_uri;
        }
        foreach ($this->get_analogous_as_resource() as $uri) {
            $this->debug_print($uri);
            $this->g->add_resource_triple($this->split_complementary_uri, "{$this->cs_prefix}colour", $uri);
        }
        $this->g->add_literal_triple($this->split_complementary_uri, "{$this->cs_prefix}degrees", $this->split_complementary_degrees);
        $this->g->add_literal_triple($this->split_complementary_uri, RDFS_LABEL, "split complementary colours to complement " . $this->get_c_shade() . " ({$this->hex})");
        $this->g->add_resource_triple($this->split_complementary_uri, RDF_TYPE, "{$this->cs_prefix}SplitcomplementaryColourScheme");
    }

    private function get_analogous_rdf() {

        //build split complementary colours for the source colour
        if ($this->path['is_colour']) {
            $this->g->add_resource_triple($this->thing_uri, "{$this->cs_prefix}colourScheme", $this->analogous_uri);
        }
        if ($this->path['is_colourscheme']) {
            $this->analogous_uri = $this->thing_uri;
        }
        foreach ($this->get_analogous_as_resource() as $uri) {
            $this->debug_print($uri);
            $this->g->add_resource_triple($this->analogous_uri, "{$this->cs_prefix}colour", $uri);
        }
        $this->g->add_literal_triple($this->analogous_uri, "{$this->cs_prefix}degrees", $this->analogous_degrees);
        $this->g->add_literal_triple($this->analogous_uri, RDFS_LABEL, "analogous colours to complement " . $this->get_c_shade() . " ({$this->hex})");
        $this->g->add_resource_triple($this->analogous_uri, RDF_TYPE, "{$this->cs_prefix}AnalogousColourScheme");
    }

    private function get_named_colour_rdf() {
        // descibe the named colour
        $shade_uri = "{$this->base_uri}/id/namedcolour/" . $this->uri_safe($this->get_c_shade());
        $this->g->add_resource_triple($this->thing_uri, "{$this->cs_prefix}describedAs", $shade_uri);
        $this->g->add_resource_triple($shade_uri, RDF_TYPE, "{$this->cs_prefix}NamedColour");
        $this->g->add_literal_triple($shade_uri, RDFS_LABEL, $this->get_c_shade());
        $this->g->add_literal_triple($shade_uri, FOAF_NAME, $this->get_c_shade());
    }

    private function get_parent_rdf() {
        // describe the parent colour
        $colourtype_uri = "{$this->base_uri}/id/namedcolour/" . $this->uri_safe($this->get_c_colour_type());
        $shade_uri = "{$this->base_uri}/id/namedcolour/" . $this->uri_safe($this->get_c_shade());
        $this->g->add_resource_triple($colourtype_uri, RDF_TYPE, "{$this->cs_prefix}NamedColour");
        $this->g->add_literal_triple($colourtype_uri, RDFS_LABEL, $this->get_c_colour_type());
        $this->g->add_literal_triple($colourtype_uri, FOAF_NAME, $this->get_c_colour_type());
        $this->g->add_resource_triple($shade_uri, "{$this->cs_prefix}shadeOf", $colourtype_uri); //???
    }

    public function get_ntriples() {
        if (!isset($this->g)) {
            $this->get_rdf();
        }
        return $this->g->to_ntriples();
    }
    /*
     * Public setters - automatically initiates conversion.
     */

    public function set_hex($hex) {
        $this->debug_print($hex, "set_hex().in");
        if ($this->validate($hex, 'hex')) {
            $this->hex = $hex;
            $this->rgb = $this->hex2rgb($this->hex);
            $this->hsl = $this->rgb2hsl($this->rgb);

            $this->profileColour($this->rgb);
            $this->calc_triadic($this->hsl);
            $this->calc_split_complementary($this->hsl);
            $this->calc_analogous($this->hsl);
        }
    }

    public function set_rgb($rgb) {
        $this->debug_print($rgb, "set_rgb().in");
        if ($this->validate($rgb, 'rgb')) {
            $this->rgb = $this->denormalise_rgb($rgb);
            $this->hex = $this->rgb2hex($this->rgb);
            $this->hsl = $this->rgb2hsl($this->rgb);

            $this->profileColour($this->rgb);
            $this->calc_triadic($this->hsl);
            $this->calc_split_complementary($this->hsl);
            $this->calc_analogous($this->hsl);
        }
    }

    public function set_hsl($hsl) {
        $this->debug_print($hsl, "set_hsl().in");
        if ($this->validate($hsl, 'hsl')) {
            $this->hsl = $this->denormalise_hsl($hsl);
            $this->rgb = $this->hsl2rgb($this->hsl);
            $this->hex = $this->rgb2hex($this->rgb);

            $this->profileColour($this->rgb);
            $this->calc_triadic($this->hsl);
            $this->calc_split_complementary($this->hsl);
            $this->calc_analogous($this->hsl);
        }
    }

    public function set_split($split) {
        $this->split_complementary_degrees = $split;
    }

    /*
     * public getters
     */

    public function get_base_uri() {
        return $this->base_uri;
    }

    public function get_split() {
        return $this->split_complementary_degrees;
    }

    public function get_hsl() {
        return $this->normalise_hsl($this->hsl);
    }

    public function get_hsl_as_string() {
        return implode(",", $this->normalise_hsl($this->hsl));
    }

    public function get_hue() {
        return array_shift($this->normalise_hsl($this->hsl));
    }

    public function get_saturation() {
        $hsl = $this->normalise_hsl($this->hsl);
        return $hsl[1];
    }

    public function get_lightness() {
        $hsl = $this->normalise_hsl($this->hsl);
        return $hsl[2];
    }

    public function get_hex() {
        return $this->hex;
    }

    public function get_rgb() {
        return $this->normalise_rgb($this->rgb);
    }

    public function get_rgb_as_string() {
        return implode(",", $this->normalise_rgb($this->rgb));
    }

    public function get_red() {
        $v = $this->normalise_rgb($this->rgb);
        return $v[0];
    }

    public function get_green() {
        $v = $this->normalise_rgb($this->rgb);
        return $v[1];
    }

    public function get_blue() {
        $v = $this->normalise_rgb($this->rgb);
        return $v[2];
    }

    public function get_c_accuracy_percentage() {
        return $this->c->getAccuracyPercentage();
    }

    public function get_c_colour_type() {
        return ucwords($this->c->getColourType());
    }

    public function get_c_shade() {
        return ucwords($this->c->getShade());
    }

    public function get_complementary_as_resource() {

        $hsl = $this->calc_complementary($this->hsl);
        $hex = $this->rgb2hex($this->hsl2rgb($hsl));

        return $this->make_hex_uri($hex);
    }

    public function get_triadic_as_resource() {
        $out = array();

        foreach ($this->triadic as $k => $v) {
            $hex = $this->rgb2hex($this->hsl2rgb($v));
            $out[$k] = $this->make_hex_uri($hex);
        }
        return $out;
    }

    public function get_split_complementary_as_resource() {
        $out = array();
        foreach ($this->split_complementary as $k => $v) {
            $hex = $this->rgb2hex($this->hsl2rgb($v));
            $out[$k] = $this->make_hex_uri($hex);
        }
        return $out;
    }

    public function get_analogous_as_resource() {
        $out = array();
        foreach ($this->analogous as $k => $v) {
            $hex = $this->rgb2hex($this->hsl2rgb($v));
            $out[$k] = $this->make_hex_uri($hex);
        }
        return $out;
    }

    private function uri_safe($val) {
        $val = $this->sanitize($val, true, true);
        return $val;
    }

    private function validate($val, $type) {
        $flag = true;
        switch ($type) {
            case "hex":
                $this->debug_print($val, "HEX");
                $val = ltrim($val, "#");
                if (!ctype_xdigit($val)) {
                    $flag = false;
                }

                break;
            case "hsl":
                $this->debug_print($val, "HSL");
                $v = explode(",", $val);
                if ($v[0] < 0 || $v[0] > 360) {
                    $flag = false;
                }
                if ($v[1] < 0 || $v[0] > 100) {
                    $flag = false;
                }
                if ($v[2] < 0 || $v[0] > 100) {
                    $flag = false;
                }
                break;
            case "rgb":
                $this->debug_print($val, "RGB");
                if (!is_array($val)) {
                    $v = explode(",", $val);
                } else {
                    $v = $val;
                }
                foreach ($v as $h) {
                    if ($h < 0 || $h > 255) {
                        $flag = false;
                    }
                }
                break;
            default:
                $flag = false;
                break;
        }
        if ($flag) {
            return true;
        } else {
            if (!$this->suppress)
                $res = new http_response("404", "text/text", "Not a valid Colour");
            //exit;
        }
    }

    /**
     * Function: sanitize (borrowed from Chyrp)
     * Returns a sanitized string, typically for URLs.
     *
     * Parameters:
     *     $string - The string to sanitize.
     *     $force_lowercase - Force the string to lowercase?
     *     $anal - If set to *true*, will remove all non-alphanumeric characters.
     */
    private function sanitize($string, $force_lowercase = true, $strict = false) {
        $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
            "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
            "â€”", "â€“", ",", "<", ".", ">", "/", "?");
        $clean = trim(str_replace($strip, "", strip_tags($string)));
        $clean = preg_replace('/\s+/', "_", $clean);
        $clean = ($strict) ? preg_replace("/[^a-zA-Z0-9\_]/", "", $clean) : $clean;
        return ($force_lowercase) ?
                (function_exists('mb_strtolower')) ?
                        mb_strtolower($clean, 'UTF-8') :
                        strtolower($clean) :
                $clean;
    }

    /*
     * Function to get the colour Profiling info (names etc) with colourProfile.php
     * This is an expensive function so we only want to do this once.
     * @param $rgb array internal rgb values
     */

    private function profileColour($rgb) {
        $rgb = $this->normalise_rgb($rgb);

        if (!isset($this->allColours)) {
            $this->allColours = getAllColours();
        }

        $this->c = new ColourProfile($rgb[0], $rgb[1], $rgb[2], $this->allColours);
        $this->debug_print($this->c, "Colour Profile");
    }

    /*
     * Internaly we use values that are fractions of 1.
     */

    private function normalise_rgb($rgb) {
        foreach ($rgb as $k => $v) {
            $rgb[$k] = round($v * 255);
        }
        return $rgb;
    }

    private function denormalise_rgb($rgb) {
        foreach ($rgb as $k => $v) {
            $rgb[$k] = $v / 255;
        }
        return $rgb;
    }

    private function normalise_hsl($hsl) {

        $hsl[0] = round(($hsl[0] * 360)); //degrees
        $hsl[1] = round(($hsl[1] * 100));
        $hsl[2] = round(($hsl[2] * 100));

        return $hsl;
    }

    private function denormalise_hsl($hsl) {

        $hsl[0] = ($hsl[0] / 360 ); //degrees
        $hsl[1] = ($hsl[1] / 100 );
        $hsl[2] = ($hsl[2] / 100 );

        return $hsl;
    }

    /*
     * Colour Scheme calculators
     */

    private function calc_complementary($hsl) {
        $this->complementary_uri = $this->make_hex_uri_complementary($this->hsl2hex($hsl));
        $hsl = $this->normalise_hsl($hsl);
        // if less than 180 add 180
        if ($hsl[0] <= 180) {
            $hsl[0] = $hsl[0] + 180;
        } else if ($hsl[0] > 180) {
            $hsl[0] = $hsl[0] - 180;
        }
        // if greater than 180, minus 180
        // return new hsl
        return $this->denormalise_hsl($hsl);
    }

    private function calc_triadic($hsl) {
        $this->triadic_uri = $this->make_hex_uri_triadic($this->hsl2hex($hsl));
        $hsl = $this->normalise_hsl($hsl);

        //$t[2] = $hsl[0] ; // keep original

        if ($hsl[0] <= 120) {
            $t[0] = $hsl[0] + 120;
            $t[1] = $hsl[0] + 240;
        } else if ($hsl[0] > 120 * 2) {
            $t[0] = $hsl[0] - 120;
            $t[1] = $hsl[0] - 240;
        } else {
            $t[0] = $hsl[0] - 120;
            $t[1] = $hsl[0] + 120;
        }

        foreach ($t as $k => $v) {
            $hsl[0] = $v;
            $this->triadic[$k] = $this->denormalise_hsl($hsl);
        }

        return $this->triadic;
    }

    private function calc_split_complementary($hsl) {
        $this->split_complementary_uri = $this->make_hex_uri_split_complementary($this->hsl2hex($hsl));
        //calc complementary,
        $hsl = $this->calc_complementary($hsl);
        $hsl = $this->normalise_hsl($hsl);

        //then add and minus $split from new complementary.
        $s[0] = $hsl[0] - $this->split_complementary_degrees;
        $s[1] = $hsl[0] + $this->split_complementary_degrees;
        // if new value is less than 0 add 360.
        foreach ($s as $k => $v) {
            if ($v < 0) {
                $v = $v + 360;
            } else if ($v > 360) {
                $v = $v - 360;
            }
            $hsl[0] = $v;
            $this->split_complementary[$k] = $this->denormalise_hsl($hsl);
            $this->debug_print($this->split_complementary);
        }
    }

    private function calc_analogous($hsl) {
        $this->analogous_uri = $this->make_hex_uri_analogous($this->hsl2hex($hsl));
        //calc complementary,

        $hsl = $this->normalise_hsl($hsl);

        //then add and minus $split .
        $s[0] = $hsl[0] - $this->analogous_degrees;
        $s[1] = $hsl[0] + $this->analogous_degrees;
        // if new value is less than 0 add 360.
        $this->debug_print($s, "s from calc_analogous");
        foreach ($s as $k => $v) {
            $this->debug_print($s, "v from calc_analogous");
            if ($v < 0) {
                $v = $v + 360;
            } else if ($v > 360) {
                $v = $v - 360;
            }
            $hsl[0] = $v;
            $this->debug_print($hsl, "hsl from calc_analogous");
            $this->analogous[$k] = $this->denormalise_hsl($hsl);
            $this->debug_print($this->analogous);
        }
    }

    private function make_hex_uri($hex) {
        $hex = substr($hex, 1);
        return $this->base_uri . "/id/colour/" . $hex;
    }

    private function make_hex_uri_triadic($hex) {
        $hex = substr($hex, 1);
        return $this->base_uri . "/id/colourscheme/triadic/" . $hex;
    }

    private function make_hex_uri_complementary($hex) {
        $hex = substr($hex, 1);
        return $this->base_uri . "/id/colourscheme/complementary/" . $hex;
    }

    private function make_hex_uri_split_complementary($hex) {
        $hex = substr($hex, 1);
        return $this->base_uri . "/id/colourscheme/splitcomplementary/" . $hex;
    }

    private function make_hex_uri_analogous($hex) {
        $hex = substr($hex, 1);
        return $this->base_uri . "/id/colourscheme/analogous/" . $hex;
    }

    /*
     * RGB to HSL conversion code is based on drupal code
     */

    private function rgb2hex($rgb) {

        $this->debug_print($rgb, "rgb2hex().in");
        $rgb = $this->normalise_rgb($rgb);
        $this->debug_print($rgb, "rgb2hex().afterNorm");

        $out = '';
        foreach ($rgb as $k => $v) {
            $this->debug_print($v, "rgb2hex().v");
            $out |= ( ($v * 1) << (16 - $k * 8));
        }

        $rval = '#' . str_pad(dechex($out), 6, 0, STR_PAD_LEFT);
        $this->debug_print($rval, "rgb2hex().out");
        return $rval;
    }

    private function hex2rgb($hex, $n = true) {
        $this->debug_print($hex, "hex2rgb().in");
        if (strlen($hex) == 4) {
            $hex = $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
        }
        $c = hexdec($hex);
        for ($i = 16; $i >= 0; $i -= 8) {
            $out[] = (($c >> $i) & 0xFF) / ($n ? 255 : 1);
        }
        $this->debug_print($out, "hex2rgb().out");
        return $out;
    }

    private function hsl2hex($hsl) {
        return $this->rgb2hex($this->hsl2rgb($hsl));
    }

    private function rgb2hsl($rgb) {
        $this->debug_print($rgb, "rgb2hsl().in");
        $r = $rgb[0];
        $g = $rgb[1];
        $b = $rgb[2];
        $min = min($r, min($g, $b));
        $max = max($r, max($g, $b));
        $delta = $max - $min;
        $l = ($min + $max) / 2;
        $s = 0;

        $this->debug_print($r, "rgb2hsl().r");
        $this->debug_print($g, "rgb2hsl().g");
        $this->debug_print($b, "rgb2hsl().b");
        $this->debug_print($min, "rgb2hsl().min");
        $this->debug_print($max, "rgb2hsl().max");
        $this->debug_print($delta, "rgb2hsl().delta");
        $this->debug_print($l, "rgb2hsl().l");
        $this->debug_print($s, "rgb2hsl().s");

        if ($l > 0 && $l < 1) {
            $s = $delta / ($l < 0.5 ? (2 * $l) : (2 - 2 * $l));
        }
        $h = 0;
        if ($delta > 0) {
            if ($max == $r && $max != $g)
                $h += ( $g - $b) / $delta;
            if ($max == $g && $max != $b)
                $h += ( 2 + ($b - $r) / $delta);
            if ($max == $b && $max != $r)
                $h += ( 4 + ($r - $g) / $delta);
            $h /= 6;
        }
        $c = array($h, $s, $l);
        $this->debug_print($c, "rgb2hsl().out");
        return $c;
    }

    /*
     * convert HSL to RGB
     */

    private function hsl2rgb($hsl) {
        $this->debug_print($hsl, "hsl2rgb().in");
        $h = $hsl[0];
        $s = $hsl[1];
        $l = $hsl[2];
        $m2 = ($l <= 0.5) ? $l * ($s + 1) : $l + $s - $l * $s;
        $m1 = $l * 2 - $m2;

        $this->debug_print($h, "hsl2rgb().h");
        $this->debug_print($s, "hsl2rgb().s");
        $this->debug_print($l, "hsl2rgb().l");
        $this->debug_print($m1, "hsl2rgb().m1");
        $this->debug_print($m2, "hsl2rgb().m2");

        $c = array($this->hue2rgb($m1, $m2, $h + 0.33333),
            $this->hue2rgb($m1, $m2, $h),
            $this->hue2rgb($m1, $m2, $h - 0.33333));

        $this->debug_print($c, "hsl2rgb().out");
        return $c;
    }

    /**
     * Helper function for hsl2rgb().
     */
    private function hue2rgb($m1, $m2, $h) {
        $h = ($h < 0) ? $h + 1 : (($h > 1) ? $h - 1 : $h);
        if ($h * 6 < 1)
            return $m1 + ($m2 - $m1) * $h * 6;
        if ($h * 2 < 1)
            return $m2;
        if ($h * 3 < 2)
            return $m1 + ($m2 - $m1) * (0.66666 - $h) * 6;
        return $m1;
    }

    private function debug_print($var, $title='', $flag = 0) {
        if (CP_DEBUG || $flag) {
            echo "<h2>" . $title . "</h2>";
            echo "<pre>";
            print_r($var);
            echo "</pre>";
        }
    }

}
?>