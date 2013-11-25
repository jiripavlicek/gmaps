<?php
/**
 * GmapsInfobox
 *
 * Returns the info window content requested by Gmaps thru an ajax request
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

global $modx;

if (isset($_REQUEST['mkrid'])) {
    $mkrid = intval(strip_tags($modx->sanitizeString($_REQUEST['mkrid'])));

    // mkrTextTv : [ tv name | 'mkrText']
    // name of tv where is stored the text value of the marker. Tv output should be as text: Text of the marker
    // By default : 'mkrText'
    $mkrTextTv = trim($modx->getOption('mkrTextTv',$scriptProperties,'mkrText'));

    $debug = $modx->getOption('debug',$scriptProperties,false);

    if ($mkrid && $mkrTextTv) {

    	$c = $modx->newQuery('modTemplateVar');
        $c->select('modTemplateVar.id AS id, value');
        $c->query['distinct'] = 'DISTINCT';
        $c->innerJoin('modTemplateVarTemplate','tvtpl', array("modTemplateVar.id = tvtpl.tmplvarid"));
        $cond = "tvc.tmplvarid = modTemplateVar.id AND tvc.contentid = '" . $mkrid . "'";
        $c->leftJoin('modTemplateVarResource','tvc', array($cond));
        $c->where(array('tvc.contentid' => $mkrid, 'modTemplateVar.name' => $mkrTextTv ));

        if ($debug) {
            $modx->setLogTarget('HTML');
            $modx->setLogLevel(modX::LOG_LEVEL_DEBUG);
            $c->prepare();
            $modx->log(modX::LOG_LEVEL_INFO, $c->toSQL());
        }
        $collection = $modx->getCollection('modTemplateVar', $c);

        if (count($collection)) {
            $tvValue = $collection[0]->get("value");
            $tvValue = preg_replace('#\[\~\[\[\*(.*?)\]\]\~\]#', $modx->makeUrl($mkrid), $tvValue); //replace [~[[*id]]~] by index.php?id=id
            $chunk = $modx->newObject('modChunk');
            $chunk->setContent($tvValue);
            $chunk->setCacheable(false);
            $tvParsed = $chunk->process();
            return $tvParsed;
        }
        else return "ERROR: infowindow text not found";
    }
    else {
        return "ERROR: infowindow rejected";
    }
}