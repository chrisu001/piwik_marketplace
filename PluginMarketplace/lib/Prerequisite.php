<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://plugin.suenkel.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @author Christian Suenkel <christian@suenkel.de>
 *
 * @category Piwik_Plugins
 * @package  Piwik_PluginMarketplace
 */

/**
 * Include the ochestra
 */
require_once __DIR__ . '/Appstore.php';
require_once __DIR__ . '/Downloader.php';
require_once __DIR__ . '/Installer.php';
require_once __DIR__ . '/Process.php';

/**
 * Library: Prerequisite
 * check all requirements which has to be fullfilled, the the autoplugin can do its work
 *
 * @package Piwik_PluginMarketplace
 * @subpackage lib
 */
 class PluginMarketplace_Prerequisite 
 {
    
    /**
     * 
     * @var PluginMarketplace_Prerequisite
     */
    protected static $instance = null;

    /**
     * Singleton
     * @return PluginMarketplace_Prerequisite
     */
    static public function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    
    /**
     * perform all checks
     */
    public function all() {
        $this->filessytem();
    }
    
    /**
     * check, if filesstem is writeable
     * 
     */
    public function filessytem() {
        Piwik::checkDirectoriesWritableOrDie();
        Piwik::checkDirectoriesWritableOrDie( array('/plugins') );
    }
    
}