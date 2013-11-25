<?php
/**
 * Gmaps
 *
 * Display a Google Map and adds some markers and layers - API Google Maps V3.
 *
 * @category    Third Party Component
 * @version     1.0.2
 * @license     http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 *
 * @author      Coroico - www.revo.wangba.fr
 * @date        03/12/2011
 *
 * -----------------------------------------------------------------------------
 */

class Gmaps{

    protected $modx;
    protected $config = array();

    protected $chunks = array();      // collection of preprocessed chunk values

    function __construct(modX & $modx, array $properties = array()) {

    	$this->modx =& $modx;

        // path and url
        $corePath = $this->modx->getOption('gmaps.core_path',null,$this->modx->getOption('core_path').'components/gmaps/');
        $assetsUrl = $this->modx->getOption('gmaps.assets_url',null,$this->modx->getOption('assets_url').'components/gmaps/');
        $this->config = array_merge(array(
            'corePath' => $corePath,
            'chunksPath' => $corePath.'elements/chunks/',
        ),$properties);

        if ($this->modx->getOption('debug',$this->config,0)) {
            error_reporting(E_ALL & ~E_NOTICE); ini_set('display_errors',true);
            $this->modx->setLogTarget('HTML');
            $this->modx->setLogLevel(modX::LOG_LEVEL_DEBUG);
        }

        // load default lexicon
        $this->modx->lexicon->load('gmaps:default');
    }

    public function output() {

        $this->checkConfig();
        $markers = $this->getMarkers();
        $imageMarkers = $this->getImageMarkers($markers);
        $output = $this->displayMap($markers, $imageMarkers);

        return $output;
    }

    /*
    *  check some parameters
    */
    function checkConfig() {

        $cfg =& $this->config;

        if ($cfg['debug'])
            $this->modx->log(modX::LOG_LEVEL_INFO, "Config before checking: " . print_r($cfg,true));

        // map id [ string | 'Gmaps' ]
        // default : 'Gmaps'
        $mapId = str_replace(' ', '', $this->modx->getOption('mapId',$cfg,'Gmaps'));
        $cfg['mapId'] = (!empty($mapId)) ? $mapId : 'Gmaps';

        // map latitude & longitude : ['float,float']
        // Default : '46.538362, 2.427753' (center of France !)
        $cfg['mapLatLng'] = $this->modx->getOption('mapLatLng',$cfg,'46.538362, 2.427753');
        $latLng = array_map("trim",explode(',',$cfg['mapLatLng']));
        // set latitude and longitude
        $lat = (float) $latLng[0];
        $cfg['lat'] = (($lat >= -90.) && ($lat <= 90.)) ? $lat : '46.538362';
        $lng = (float) (isset($latLng[1]) ? $latLng[1] : '2.427753');
        $cfg['lng'] = (($lng >= -180.) && ($lng <= 180.)) ? $lng : '2.427753';

        // zoom : [0 < int < 20 ]
        // default : 5
        $zoom = intval($this->modx->getOption('zoom',$cfg,5));
        $cfg['zoom'] = (($zoom > 0) && ($zoom < 20)) ? $zoom : 5;

        // map type : ['SATELLITE' | 'HYBRID' | 'TERRAIN' | 'ROADMAP']
        // default : 'ROADMAP'
        $mapType = $this->modx->getOption('mapType',$cfg,'ROADMAP');
        $mt = strtoupper(trim($mapType));
		$cfg['mapType'] = (($mt != 'ROADMAP') && ($mt != 'SATELLITE') && ($mt != 'HYBRID') && ($mt == 'TERRAIN ')) ? 'ROADMAP' : $mt; 
		
        // map width : [npx | x%] in pixels or in purcent
        // default : 100%
        // maximum in pixels : 1920px
        $mapWidth =  $this->modx->getOption('mapWidth',$cfg,'100%');
        if (preg_match_all("#(^[0-1]?[0-9]?[0-9])%$#",$mapWidth,$matches)) {
            $cfg['mapWidth'] = ($matches[1][0] > 0 ) ? $mapWidth : '100%';
        }
        elseif (preg_match_all("#(^[0-1]?[0-9]?[0-9]?[0-9])px$#",$mapWidth,$matches)) {
            $cfg['mapWidth'] = ($matches[1][0] > 0 && $matches[1][0] <= 1920 ) ? $mapWidth : '100%';
        }
        else $cfg['mapWidth'] = '100%';

        // map height : [npx | x%] in pixels or in purcent
        // default : 500px
        // maximum height in pixels : 1200px
        $mapHeight =  $this->modx->getOption('mapHeight',$cfg,'500px');
        if (preg_match_all("#(^[0-1]?[0-9]?[0-9])%$#",$mapHeight,$matches)) {
            $cfg['mapHeight'] = ($matches[1][0] > 0 ) ? $mapHeight : '500px';
        }
        elseif (preg_match_all("#(^[0-1]?[0-9]?[0-9]?[0-9])px$#",$mapHeight,$matches)) {
            $cfg['mapHeight'] = ($matches[1][0] > 0 && $matches[1][0] <= 1200 ) ? $mapHeight : '500px';
        }
        else $cfg['mapHeight'] = '500px';

        // scalable : ['yes' | 'no']
        // default : 'no'
        $scalable = $this->modx->getOption('scalable',$cfg,'no');
        $scalable = strtolower(trim($scalable));
        $cfg['scalable'] = (($scalable == 'yes') || ($scalable == 'no')) ? $scalable : 'no';

        // apiVersion : ['3.7' | '']
        // default : '' => nightly version
		// see "Choosing an API version" on the google API javascript V3 documentation
        $cfg['apiVersion'] = $this->modx->getOption('apiVersion',$cfg,'');

        // markers : [ comma separated list of id ]
        // default: '' (empty list) - GetIds snippet for complex list
        $cfg['markers'] = $this->modx->getOption('markers',$cfg,'');
        if (!$cfg['markers']) {
            $mkrs = array_map("trim",explode(',',$cfg['markers']));
            $markers = array();
            foreach ($markers as $mkr) if (is_int($mkr)) $markers[] = $mkr;
            $cfg['markers'] = implode(',',array_unique($markers));
        }

        // kml : csv list of KML or GeoRSS files.
        // files should be in assets/files/kml folder
        // e.g &kml=`trip1.kml , trip2.kml, trip3.kml`
        // By default : ''
        $kml = $this->modx->getOption('kml',$cfg,'');
        $kmlArray = array_unique(array_map("trim",explode(',',$kml)));
        $cfg['kml'] = implode(',',$kmlArray);

        // mkrLatLngTv : [ tv name | 'mkrLatLng']
        // name of tv where is stored the latLng value of the marker. Tv should should contains: lat,lng values
        // By default : 'mkrLatLng'
		// or two tv names separated by a comma. The first tv should be the mkrLatTv with the lat value 
		// and the second tv the mkrLngTv with the lng value
        $mkrTvs = array_map("trim",explode(',',$this->modx->getOption('mkrLatLngTv',$cfg,'mkrLatLng')));
		if (count($mkrTvs) == 1) $cfg['mkrLatLngTv'] = $mkrTvs[0];
		else {
			$cfg['mkrLatTv'] = $mkrTvs[0];
			$cfg['mkrLngTv'] = $mkrTvs[1];
			unset($cfg['mkrLatLngTv']);
		}
		
        // mkrClick : [ 'info' | 'link' | 'none' ]
        // When the map marker is clicked, should the info box open on that marker or follow the link or do nothing?
        // By default : 'link' - follow the link
        $mkrClick = $this->modx->getOption('mkrClick',$cfg,'link');
        $mkrClick = strtolower(trim($mkrClick));
        $cfg['mkrClick'] = (($mkrClick == 'info') || ($mkrClick == 'link') || ($mkrClick == 'none')) ? $mkrClick : 'link';

        // mkrOver : [ 'info' | 'link' | 'none' ]
        // When mouse cursor is moved over the map marker, should the info box open on that marker or follow the link or do nothing?
        // By default : 'none' - do nothing
        $mkrOver = $this->modx->getOption('mkrOver',$cfg,'none');
        $mkrOver = strtolower(trim($mkrOver));
        $cfg['mkrOver'] = (($mkrOver == 'info') || ($mkrOver == 'link') || ($mkrOver == 'none')) ? $mkrOver : 'none';

        // mkrIconTv : [ tv name | '']
        // name of tv where is stored the chunk name which contains information about the icon to use
        // A chunk should be defined for each icon and contain: image,[shadow,[shape]]
        // image: image_name, width, height, originX, originY, anchorX, anchorY,
        // shadow: width, height, originX, originY, anchorX, anchorY
        // shape: comma separated list of shape coordinates
        // By default : '' => default marker
        $cfg['mkrIconTv'] = trim($this->modx->getOption('mkrIconTv',$cfg,''));

        // mkrLinkTv : [ tv name | '']
        // name of tv where is stored the page url to go with 'link'
        // By default : '' => marker page id
        $cfg['mkrLinkTv'] = trim($this->modx->getOption('mkrLinkTv',$cfg,''));

        // iconDir : [ path name | 'assets/images/gmaps/']
        // path to the directory where are stored icons for google maps
        // By default : 'assets/images/gmaps/'
        $cfg['iconDir'] = trim($this->modx->getOption('iconDir',$cfg,'assets/images/gmaps/'));

        // method : [ 'GET' | 'POST']
        // method used for the ajax infobox
        // By default : 'POST'
        $method = strtoupper(trim($this->modx->getOption('method',$cfg,'POST')));
        $cfg['method'] = (($method == 'POST') || ($method == 'GET')) ? $method : 'POST';

        // infoboxAjaxId : [ id | 0 ]
        // Ajax response page for the infobox content.
        // should be a blank template page with the snippet call [!GMapsInfobox!]
        $infoboxAjaxId = $this->modx->getOption('infoboxAjaxId',$cfg,0);
        $cfg['infoboxAjaxId'] = intval($infoboxAjaxId) ?  $infoboxAjaxId : 0;
        if (!$cfg['infoboxAjaxId']) {
            if ($cfg['mkrClick'] == 'info') $cfg['mkrClick'] = 'none';
            if ($cfg['mkrOver'] == 'info') $cfg['mkrOver'] = 'none';
        }
		else {
			$cfg['infoboxAjaxUrl'] = $this->modx->makeUrl($this->config['infoboxAjaxId'], '', array(), $this->config['urlScheme']);
		}

        if ($this->config['debug'])
            $this->modx->log(modX::LOG_LEVEL_INFO, "Config after checking: " . print_r($cfg,true));
    }

    /*
    *  get markers from database
    */
    function getMarkers() {

        $markers = array();

        // Read the database to get the marker infos (pagetitle, mkrLatLng value, mkrText value , mkrIcon value

        $mkrsid = array_map("trim",(explode(',',$this->config['markers'])));
        $lstMkrsid = implode(',',$mkrsid);  // list of markers id

        if ($lstMkrsid) {

            $c = $this->modx->newQuery('modResource');
            $c->select(array('id', 'pagetitle'));
            $c->where(array("(id IN (" . $lstMkrsid . "))"));

            if ($this->config['debug']) {
                $c->prepare();
                $this->modx->log(modX::LOG_LEVEL_INFO, $c->toSQL());
            }
            $collection = $this->modx->getCollection('modResource', $c);

            $iconTvs = array();
            // Append TV rendered output values to selected markers
            foreach ($collection as $resource) {
                $id = $resource->get('id');
                $pagetitle = $resource->get('pagetitle');
                $tvValues = $this->getdocTvs($id);
                if ($this->validMarker($tvValues)) $markers[] = array_merge( array("id" => $id, "pagetitle" => $pagetitle), (array) $tvValues ) ;
            }
        }

        if ($this->config['debug'])
            foreach ($markers as $marker) $this->modx->log(modX::LOG_LEVEL_INFO, "marker = " . print_r($marker,true));

        return $markers;
    }

    /*
    *  Return the user defined tvs of a document
    */
    function getDocTvs($docid) {

        $docTvs = array();

    	$c = $this->modx->newQuery('modTemplateVar');
        $c->query['distinct'] = 'DISTINCT';
        $c->select('modTemplateVar.id AS id, name, value');
        $c->innerJoin('modTemplateVarTemplate','tvtpl', array("modTemplateVar.id = tvtpl.tmplvarid"));
        $cond = "tvc.tmplvarid = modTemplateVar.id AND tvc.contentid = '" . $docid . "'";
        $c->leftJoin('modTemplateVarResource','tvc', array($cond));
        $c->where(array('tvc.contentid' => $docid));

        if ($this->config['debug']) {
            $c->prepare();
            $this->modx->log(modX::LOG_LEVEL_INFO, $c->toSQL());
        }

        $collection = $this->modx->getCollection('modTemplateVar', $c);
        foreach ($collection as $resource) {
            $tvValue = $resource->get('value');
            $tvName = $resource->get('name');
            $chunk = $this->modx->newObject('modChunk');
            $chunk->setContent($tvValue);
            $chunk->setCacheable(false);
            $docTvs[$tvName] = $chunk->process();
        }

        if ($this->config['debug']) {
            $this->modx->log(modX::LOG_LEVEL_INFO, "docTvs[$docid]:" . print_r($docTvs,true));
        }

        return $docTvs;
    }
    /*
    *  check the valididy of the marker
    */
    function validMarker($tvArray) {

        if (isset($this->config['mkrLatLngTv']) && isset($tvArray[$this->config['mkrLatLngTv']]) && ($tvArray[$this->config['mkrLatLngTv']])) {
            $latLng = array_map("trim",explode(',',$tvArray[$this->config['mkrLatLngTv']]));
            // check latitude and longitude
            $lat = (float) $latLng[0];
            if (($lat < -90.) || ($lat > 90.)) return false;
            $lng = (float) (isset($latLng[1]) ? $latLng[1] : '0.');
            if (($lng < -180.) || ($lng > 180.)) return false;
        }
        elseif (isset($this->config['mkrLatTv']) && isset($tvArray[$this->config['mkrLatTv']]) && ($tvArray[$this->config['mkrLatTv']])
		         && isset($this->config['mkrLngTv']) && isset($tvArray[$this->config['mkrLngTv']]) && ($tvArray[$this->config['mkrLngTv']])) {
            $latLng = array_map("trim",explode(',',$tvArray[$this->config['mkrLatLngTv']]));
            // check latitude and longitude
            $lat = (float) $tvArray[$this->config['mkrLatTv']];
            if (($lat < -90.) || ($lat > 90.)) return false;
            $lng = (float) $tvArray[$this->config['mkrLngTv']];
            if (($lng < -180.) || ($lng > 180.)) return false;
        }
        else return false;  // tv mkrLatLngTv not defined or empty

        return true;
    }
    /*
    *  get image markers used by markers
    */
    function getImageMarkers($markers) {
        $imageMarkers = array();
        $iconTvs = array();

        $nbMarkers = count($markers);
        $mkrIconTv = $this->config['mkrIconTv'];
        if ($mkrIconTv) {
                foreach($markers as $marker) {
                    if (isset($marker[$mkrIconTv])) $iconTvs[] = $marker[$mkrIconTv];
                }

            // set up the list of imageMarkers
            $iconTvs = array_values(array_unique($iconTvs));
            $nbImageMarkers = count($iconTvs);
            if ($nbImageMarkers) {
                for($i=0;$i<$nbImageMarkers;$i++){
                    $imageMarkers[$iconTvs[$i]] = $this->getImageMarker($iconTvs[$i]);
                }
            }
        }
        return $imageMarkers;
    }

    function getImageMarker($tvValue) {
        $imageMarker = array();

        $chunk = trim($this->modx->getChunk($tvValue));
        if ($chunk) {
            $imgMkr = array_map('trim',explode(':', $chunk));
            if (isset($imgMkr[0])) {
                // image
                $imageMarker['image'] = array_map('trim',explode(',', $imgMkr[0]));
            }
            if (isset($imgMkr[1])) {
                // shadow
                $imageMarker['shadow'] = array_map('trim',explode(',', $imgMkr[1]));
            }
            if (isset($imgMkr[2])) {
                // shape
                $imageMarker['shape'] = array_map('trim',explode(',', $imgMkr[2]));
            }
        }
        else {
            $imageMarker['image'] = $chunk;  // name of image
        }
        return $imageMarker;
    }

    /*
    *  display map with markers
    */
    function displayMap($markers, $imageMarkers) {

        $gmapsJs = $this->jsInit();
        $gmapsJs = $this->jsDisplayInfowindow($gmapsJs);
        $gmapsJs = $this->jsAddMarkerPlace($gmapsJs);
        $gmapsJs = $this->jsSetMarkers($markers, $imageMarkers, $gmapsJs);
        $gmapsJs = $this->jsInitialize($gmapsJs);
        $gmapsJs = $this->jsEnd($gmapsJs);

        $this->modx->regClientStartupScript($gmapsJs);

        $output = '
        <div id="' . $this->config['mapId'] .'" style="width:' .$this->config['mapWidth']. '; height:' .$this->config['mapHeight']. '"></div>
        <noscript>
            <b>JavaScript must be enabled in order for you to use Google Maps.</b>
            However, it seems JavaScript is either disabled or not supported by your browser.
            To view Google Maps, enable JavaScript by changing your browser options, and then
            try again.
        </noscript>';

        return $output;
    }

    function jsInit() {
		$version = '';
		if ($this->config['apiVersion']) $version = 'v=' . $this->config['apiVersion'] . '&';
        $js = '<meta name="viewport" content="initial-scale=1.0, user-scalable=' . $this->config['scalable'] . '"/>
<script type="text/javascript" src="http://maps.google.com/maps/api/js?' . $version . 'sensor=false&language=' . $this->config['language'] .'"></script>
<script type="text/javascript">
//<![CDATA[';
        return $js;
    }

    function jsInitPlaces($markers, $imageMarkers, $js='') {
        // set up localisation of markers
        $js .= "\n" . '    var places = new Array();' . "\n";
        $js .= '    places = [' . "\n";
        $nbMarkers = count($markers);
        if ($nbMarkers) {
			if (isset($this->config['mkrLatLngTv'])) {
				foreach($markers as $marker) {
					$placeJs = '["' . $this->slashAndStrip($marker['pagetitle']) . '",';
					$placeJs .= $this->slashAndStrip($marker[$this->config['mkrLatLngTv']]) . ',';
					$placeJs .= '"' . $this->slashAndStrip($marker['id']) . '",';
					$lnk = $this->config['mkrLinkTv'] ? $marker[$this->config['mkrLinkTv']] : $this->modx->makeUrl($marker['id']);
					$placeJs .= '"' . $this->slashAndStrip($lnk) . '",';
					$placeJs .= '"' . $this->slashAndStrip($marker[$this->config['mkrIconTv']]) . '"],';
					$js .= '        ' . $placeJs . "\n";
				}
				$js = substr($js,0,strlen($js)-2) . "\n" ;      // removing of the last comma
			}
			else {
				foreach($markers as $marker) {
					$placeJs = '["' . $this->slashAndStrip($marker['pagetitle']) . '",';
					$placeJs .= $this->slashAndStrip($marker[$this->config['mkrLatTv']]) . ',';
					$placeJs .= $this->slashAndStrip($marker[$this->config['mkrLngTv']]) . ',';
					$placeJs .= '"' . $this->slashAndStrip($marker['id']) . '",';
					$lnk = $this->config['mkrLinkTv'] ? $marker[$this->config['mkrLinkTv']] : $this->modx->makeUrl($marker['id']);
					$placeJs .= '"' . $this->slashAndStrip($lnk) . '",';
					$placeJs .= '"' . $this->slashAndStrip($marker[$this->config['mkrIconTv']]) . '"],';
					$js .= '        ' . $placeJs . "\n";
				}
				$js = substr($js,0,strlen($js)-2) . "\n" ;      // removing of the last comma
			}
        }
        $js .= '    ];' . "\n";

        // set up image markers
        $js .= '    var imgMkrs = new Array();' . "\n";
        foreach($imageMarkers as $key => $imageMarker) {
            $imgJs = 'imgMkrs["' . $key . '"] = new Array();' . "\n";
            if (isset($imageMarker['image'])) {
                if (!is_array($imageMarker['image'])) {
                    $imgJs .= '    imgMkrs["' . $key . '"]["image"] = ["' . $key . '"];'. "\n";
                }
                else {
                    $imgJs .= '    imgMkrs["' . $key . '"]["image"] = [' . implode(',',$imageMarker['image']) . '];'. "\n";
                    if (isset($imageMarker['shadow']))$imgJs .= '    imgMkrs["' . $key . '"]["shadow"] = [' . implode(',',$imageMarker['shadow']) . '];'. "\n";
                    if (isset($imageMarker['shape'])) $imgJs .= '    imgMkrs["' . $key . '"]["shape"] = [' . implode(',',$imageMarker['shape']) . '];' . "\n";
                }
            }
            $js .= '    ' . $imgJs;
        }
        return $js;
    }

    function slashAndStrip($output) {
        return addslashes(preg_replace("~([\n\r\t\s]+)~"," ",$output));
    }

    function jsDisplayInfowindow($js='') {
        $js .= '
var xmlHttp;
function GetXmlHttpObject(){
    var xmlHttp = null;
    try {xmlHttp = new XMLHttpRequest();}
    catch (e) {
        try {xmlHttp = new ActiveXObject("Msxml2.XMLHTTP");}
        catch (e){xmlHttp = new ActiveXObject("Microsoft.XMLHTTP");}
    }
    return xmlHttp;
}
function displayInfoWindow(map,iburl,mkr,infow){
    xmlHttp = GetXmlHttpObject();
    if(xmlHttp == null){
      alert ("Your browser does not support AJAX!");
      return;
    }
    xmlHttp.onreadystatechange = function(){
        if(xmlHttp.readyState == 4){
            var res = xmlHttp.responseText;
            err = res.split("ERROR:")[1];
            if (!err) {
                infow.setContent(res);
                infow.open(map,mkr);
            }
        }
    }
    mkrid = mkr.docid;
    if ( mkrid > 0){';

	if ($this->config['method'] == 'POST') {
		$js .= '
        var params = "mkrid="+mkrid;
        xmlHttp.open("POST",iburl,true);
        xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xmlHttp.setRequestHeader("Content-length", params.length);
        xmlHttp.setRequestHeader("Connection", "close");
        xmlHttp.send(params);';
	}
	else {
		$js .= '
		var nfurl = iburl.split("index.php?id=")[1];
		if (nfurl) var url = iburl+"&mkrid="+mkrid; 
		else var url = iburl+"?mkrid="+mkrid;
        xmlHttp.open("GET",url,true);
        xmlHttp.send(null);';
	}

	$js .= '
    }
}';
        return $js;
    }

    function jsAddMarkerPlace($js='') {
        $js .= '
function addMarkerPlace(ptPlace, place, map, imgMkrs, infobulle){
    mkrIcon = place[5];
    marker = { position: ptPlace, map: map, title: place[0], link: place[4] };';

        if (($this->config['mkrClick'] == 'info') || ($this->config['mkrOver'] == 'info')) {
            $js .= '
    marker.docid = place[3];';
        }

        $js .= '
    if (mkrIcon && imgMkrs[mkrIcon] && imgMkrs[mkrIcon]["image"]) {

        if (imgMkrs[mkrIcon]["image"][1])
            marker.icon = new google.maps.MarkerImage("' . $this->config['iconDir'] . '"+imgMkrs[mkrIcon]["image"][0],
                new google.maps.Size(imgMkrs[mkrIcon]["image"][1], imgMkrs[mkrIcon]["image"][2]),
                new google.maps.Point(imgMkrs[mkrIcon]["image"][3], imgMkrs[mkrIcon]["image"][4]),
                new google.maps.Point(imgMkrs[mkrIcon]["image"][5], imgMkrs[mkrIcon]["image"][6]));
        else marker.icon ="' . $this->config['iconDir'] . '"+imgMkrs[mkrIcon]["image"][0];
    }
    if (mkrIcon && imgMkrs[mkrIcon] && imgMkrs[mkrIcon]["shadow"]) {
        marker.shadow = new google.maps.MarkerImage("' . $this->config['iconDir'] . '"+imgMkrs[mkrIcon]["shadow"][0],
            new google.maps.Size(imgMkrs[mkrIcon]["shadow"][1], imgMkrs[mkrIcon]["shadow"][2]),
            new google.maps.Point(imgMkrs[mkrIcon]["shadow"][3], imgMkrs[mkrIcon]["shadow"][4]),
            new google.maps.Point(imgMkrs[mkrIcon]["shadow"][5], imgMkrs[mkrIcon]["shadow"][6]));
    }';

        if (($this->config['mkrClick'] != 'none') || ($this->config['mkrOver'] != 'none')) {
            $js .= '
    if (mkrIcon && imgMkrs[mkrIcon] && imgMkrs[mkrIcon]["shape"]) {
        marker.shape = { coord: imgMkrs[mkrIcon]["shape"], type: \'poly\'};
    }';
        }

        $js .= '
    var markerPlace = new google.maps.Marker(marker);';
        // link
        if ($this->config['mkrClick'] == 'link') {
            $js .= '
    google.maps.event.addListener(markerPlace, "click", function() {
            document.location.href=this.link;
        });';
        }
        else if ($this->config['mkrClick'] == 'info') {
            $js .= '
    google.maps.event.addListener(markerPlace, "click", function() {
            displayInfoWindow(map,\'' .$this->config['infoboxAjaxUrl'] . '\',this,infobulle);
        });';
        }
        // mouse over
        if ($this->config['mkrOver'] == 'link') {
            $js .= '
    google.maps.event.addListener(markerPlace, "mouseover", function() {
            document.location.href=this.link;
        });';
        }
        else if ($this->config['mkrOver'] == 'info') {
            $js .= '
    google.maps.event.addListener(markerPlace, "mouseover", function() {
            displayInfoWindow(map,\'' .$this->config['infoboxAjaxUrl'] . '\',this,infobulle);
        });';
        }
        $js .= '
    return markerPlace;
}';
        return $js;
    }

    function jsSetMarkers($markers, $imageMarkers, $js='') {
        $js .= '
function setMarkers(map){';

        $js = $this->jsInitPlaces($markers, $imageMarkers, $js);

        $nbMarkers = count($markers);
        if ($nbMarkers > 1 ) {
            $js .= '
    var infobulle = new google.maps.InfoWindow();
    var bounds = new google.maps.LatLngBounds();
    for (var i=0;i<places.length;i++) {
        ptPlc = new google.maps.LatLng(places[i][1], places[i][2]);
        bounds.extend(ptPlc);
        var markerPlace = addMarkerPlace(ptPlc, places[i], map, imgMkrs, infobulle);
    }
    map.fitBounds(bounds);';
        }
        else if ($nbMarkers == 1 ) {
            $js .= '
    var infobulle = new google.maps.InfoWindow();
    var ptPlc = new google.maps.LatLng(places[0][1], places[0][2]);
    var markerPlace = addMarkerPlace(ptPlc, places[0], map, imgMkrs, infobulle);
    map.center = ptPlc;';
        }

        $js .= '
}';
        return $js;
    }

    function jsInitialize($js='') {
        $js .= '
function initialize(){
    var centerMap = new google.maps.LatLng(' . $this->config['lat'] . ',' . $this->config['lng'] . ');
    var optionsMap = {
        zoom: ' . $this->config['zoom'] . ',
        center: centerMap,
        mapTypeId: google.maps.MapTypeId.' . $this->config['mapType'] . '
    }
    var map = new google.maps.Map(document.getElementById("'. $this->config['mapId'] .'"), optionsMap);
    setMarkers(map);';

    if ($this->config['kml']) {
        $kmlArray = explode(',',$this->config['kml']);
        $i = 0;
        foreach($kmlArray as $kml) {
            $i++;
            $kmlf = $this->modx->config['site_url']."assets/files/kml/" . $kml;
            $js .= '
    var kmlLayer'. $i .' = new google.maps.KmlLayer(\''. $kmlf . '\');
    kmlLayer'. $i .'.setMap(map);';
        }
    }

    $js .= '
}';
        return $js;
    }

    function jsEnd($js='') {
        $js .= '
google.maps.event.addDomListener(window, \'load\', initialize);
//]]>
</script>';
        return $js;
    }

}