<?php
/**
 * Gmaps
 *
 * Display a Google Map and adds some markers - API Google Maps V3
 *
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

require_once $modx->getOption('gmaps.core_path',null,$modx->getOption('core_path').'components/gmaps/').'model/gmaps/gmaps.class.php';

$gm  = str_replace(' ', '', $modx->getOption('mapId',$scriptProperties,'Gmaps'));

$$gm = new Gmaps($modx,$scriptProperties);
if (!($$gm instanceof Gmaps)) {
    $this->modx->log(modX::LOG_LEVEL_ERROR,'[Gmaps] Gmaps class not found.');
    return false;
}

$output = $$gm->output();

return $output;