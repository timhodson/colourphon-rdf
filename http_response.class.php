<?php
/**
 * Colourphon
 *
 * Class providing basic but repetative header content designation.
 * Could also add content-type headers in here.
 * Perhaps this becomes a header factory?
 *
 * tim@timhodson.com
 */

class http_response extends ColourphonRdf {

	private $content;
	private $content_type;
	private $status;
        private $new_location ;
        private $extensions = array('.rdf', '.ttl', '.ntriples', '.html');

        public function __construct($status='', $content_type='', $content='', $new_location=''){

		$this->content_type = $content_type;
		$this->status = $status;
                $this->new_location = $new_location;
                $this->send_response($content);
	}
	public function set_content_type($content_type){
		if(isset($content_type)){
			$this->content_type = $content_type;
		}
	}
	public function set_content($content){
		if(isset($content)){
			$this->content = $content;
		}
	}
	public function set_status($status){
		if(isset($status)){
			$this->status = $status;
		}
	}
	public function send_headers(){

		if(isset($this->status)){
			switch ( $this->status ) {
				case "200":
					header("HTTP/1.0 200 OK", false);
					header("Status: 200 OK", false);
				break;
				case "201" :
					header("HTTP/1.0 201 Created", false);
					header("Status: 201 Created", false);
				break;
				case "202" :
					header("HTTP/1.0 202 Accepted",false);
					header("Status: 202 Accepted", false);
				break;
				case "302" :
					header("HTTP/1.0 302 Found", false);
					header("Status: 302 Found", false);
				break;
                                case "303" :

                                        $ext = substr($_SERVER['REQUEST_URI'], (strrpos($_SERVER['REQUEST_URI'],'.')));
                                        // ??? Check to see that we don't come back in and loop.
                                        // Note: this is handled by parsing the REQUEST_URI in ColourphonRdf
                                        //if (in_array($ext, $this->extensions)) $this->do_404();

                                        //  Inspect accept headers
                                        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/rdf+xml') !== false) {
                                                header('HTTP/1.1 303 See Other');
                                                header('Location: http://' . $_SERVER['SERVER_NAME'] . $this->new_location . '.rdf', 303);
                                                ob_end_flush();
                                                die;

                                        } else if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/turtle') !== false) {
                                                header('HTTP/1.1 303 See Other');
                                                header('Location: http://' . $_SERVER['SERVER_NAME'] . $this->new_location . '.ttl', 303);
                                                ob_end_flush();
                                                die;

                                        } else if (isset($_SERVER['HTTP_ACCEPT']) && (
                                                            strpos($_SERVER['HTTP_ACCEPT'], 'text/n3') !== false
                                                         || strpos($_SERVER['HTTP_ACCEPT'], 'text/ntriples') !== false
                                                        )
                                                  ){
                                                header('HTTP/1.1 303 See Other');
                                                header('Location: http://' . $_SERVER['SERVER_NAME'] . $this->new_location . '.ntriples', 303);
                                                ob_end_flush();
                                                die;

                                        } else {
                                                //  otherwise redirect to human-readable representation
                                                // Check we are not looking for a non-existent html file.
                                                header('HTTP/1.1 303 See Other');
                                                header('Location: http://' . $_SERVER['SERVER_NAME'] . $this->new_location . '.html', 303);
                                                ob_end_flush();
                                                die;
                                        }
				break;
				case "400" :
					header("HTTP/1.0 400 Bad Request", false);
					header("Status: 400 Bad Request", false);
				break;
				case "404" :
					header("HTTP/1.0 404 Not Found", false);
					header("Status: 404 Not Found", false);
				break;
				default:
					header("HTTP/1.0 404 Not Found");
					header("Status: 404 Not Found", false);
					break;
			}
		}

		if(isset($this->content_type)){
			header("Content-type: {$this->content_type}");
		}
	}

	public function send_response($content=""){

		if(isset($content)){
			$this->content = $content;
		}

		ob_start();
		$this->send_headers();
		echo $this->content;
		ob_end_flush();

	}
        public function do_404(){
            header('HTTP/1.1 404 Not Found');
            echo '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN"> <html><head> <title>404 Not Found</title> </head><body> <h1>Not Found</h1> <p>The requested URL ';
            echo $_SERVER['REQUEST_URI'];
            echo " was not found on this server.</p></body></html>\n";
            ob_end_flush();
            die;
        }
}
?>