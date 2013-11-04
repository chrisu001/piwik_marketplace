<?php
/**
  *
 * PluginMarketplace
 *
 * Copyright (c) 2012-2013, Christian Suenkel <info@suenkel.org>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * * Redistributions of source code must retain the above copyright
 *   notice, this list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in
 *   the documentation and/or other materials provided with the
 *   distribution.
 *
 * * Neither the name of Christian Suenkel nor the names of his
 *   contributors may be used to endorse or promote products derived
 *   from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Christian Suenkel <christian@suenkel.de>
 * @link http://plugin.suenkel.org
 * @copyright 2012-2013 Christian Suenkel <info@suenkel.de>
 * @license http://www.opensource.org/licenses/BSD-3-Clause The BSD 3-Clause License
 * @category Piwik_Plugins
 * @package  PluginMarketplace
 */

/**
 * Include the ochestra
 */
require_once dirname(__FILE__) . '/Appstore.php';
require_once dirname(__FILE__) . '/PiwikFacade.php';
require_once dirname(__FILE__) . '/Cache.php';

/**
 * Library: Manager
 *
 *
 * this class handles
 * - conduct the install process
 * * get download url
 * * donwload
 * * extract
 * * deactivate
 * * deploy
 * * activate
 * - get lists of available plugins and their status
 *
 * @package  PluginMarketplace
 * @subpackage lib
 */
class PluginMarketplace_Manager
{
    CONST CACHE_ID_PLUGINLIST = 'Autopluginlist';
    
    /**
     * well known attributes of a install task
     * 
     * @var string
     */
    const ATTR_SKIPINSTALL = 'skipinstall';
    const ATTR_SKIPACTIVATE = 'skipactivate';
    const ATTR_SKIPDEACTIVATE = 'skipdeactivate';
    const ATTR_DEFERDEPLOY = 'deferredeploy';
    const ATTR_APPSTORE = 'isAppstoreAvail';
    const ATTR_ISACITVE = 'isActivated';
    const ATTR_ISALWAYSACT = 'isAlwaysActivated';
    const ATTR_ISINSTALLED = 'isInstalled';
    const ATTR_NAME = 'name';
    const ATTR_APPSTOREINFO = 'appstoreinfo';
    const ATTR_PIWIKINFO = 'info';
    
    /**
     * cache
     * 
     * @var PluginMarketplace_Cache
     */
    protected $cache = null;
    
    /**
     * List of local available Plugins
     * 
     * @var array
     */
    protected $localPlugins;
    
    /**
     * Appstore Object
     * 
     * @var PluginMarketplace_Appstore
     */
    protected $appstore;

    /**
     * Construtor
     * 
     * @param Piwik_CacheFile $cache
     *            - optional local cache
     */
    public function __construct(Piwik_CacheFile $cache = null)
    {
        $this->cache = ($cache !== null) ? $cache : PluginMarketplace_Cache::getInstance();
        $this->appstore = new PluginMarketplace_Appstore();
    }

    /**
     * Load local plugin configuration
     * 
     * @see Piwik_PluginsManager
     * @return array
     */
    public function getLocalPlugins()
    {
        if ($this->localPlugins) {
            // already scaned
            return $this->localPlugins;
        }
        
        $this->localPlugins = array();
        $listPlugins = PluginMarketplace_PiwikFacade::getInstance()->getPluginsDescription();
        
        foreach ($listPlugins as $pluginName => $descr) {
            $this->localPlugins[$pluginName] = array(
                    self::ATTR_ISACITVE => $descr['active'], 
                    self::ATTR_ISALWAYSACT => $descr['alwaysActive'], 
                    self::ATTR_ISINSTALLED => 1, 
                    self::ATTR_SKIPINSTALL => 0, 
                    self::ATTR_NAME => $pluginName, 
                    'isAppstoreAvail' => 0, 
                    self::ATTR_PIWIKINFO => $descr['info'], 
                    self::ATTR_APPSTOREINFO => array());
        }
        return $this->localPlugins;
    }

    /**
     * Set selected Release of Plugins to be load from appstore
     * 
     * @param string $release            
     * @return PluginMarketplace_Manager
     */
    public function setRelease($release = 'all')
    {
        $this->appstore->setRelease($release);
        return $this;
    }

    /**
     * Combine remote available (appstore)plugins with local installed plugins to a single list of plugins
     * 
     * @return mixed - array of Plugins
     */
    public function getCurrentPlugins()
    {
        $plugins = $this->getLocalPlugins();
        $remotePlugins = $this->appstore->listPlugins();
        
        // merge local and remoteplugins and extend the information with the appstore information
        foreach ($remotePlugins as $id => $aInfo) {
            $plugins[$aInfo['name']] = $this->translatePluginConfig($aInfo, $plugins);
        }
        
        // mark core-plugins and original Piwik-plugins
        foreach ($plugins as $pluginName => $pluginInfo) {
            $plugins[$pluginName]['isCore'] = (isset($pluginInfo['info']['author']) &&
                     $pluginInfo['info']['author'] == 'Piwik') ? true : false;
            $plugins[$pluginName]['isCore'] |= ($pluginName == 'MultiSites' ||
                     $pluginName == 'DevicesDetection');
        }
        ksort($plugins);
        return $plugins;
    }

    /**
     * Translate the configuration of a Plugin to the internal manager info-structure
     * 
     * @param array $appstoreCfg            
     * @param array $localPlugins            
     * @return array - merged infostructure
     */
    protected function translatePluginConfig($appstoreCfg, $localPlugins = null)
    {
        
        // default definitition
        $cfg = array(self::ATTR_ISACITVE => 0, 
                self::ATTR_ISALWAYSACT => 0, 
                self::ATTR_ISINSTALLED => 0, 
                self::ATTR_PIWIKINFO => array(
                        'description' => $this->is($appstoreCfg['description'], ''), 
                        'author_homepage' => $this->is($appstoreCfg['author_homepage'], ''), 
                        'author' => $this->is($appstoreCfg['author'], ''), 
                        'license' => $this->is($appstoreCfg['license'], ''), 
                        'license_homepage' => $this->is($appstoreCfg['license_homepage'], ''), 
                        'version' => $this->is($appstoreCfg['version'], ''), 
                        'appstore' => true));
        // overwite default by a loaded/defined - plugin
        if (is_array($localPlugins) && isset($localPlugins[$appstoreCfg['name']])) {
            $cfg = $localPlugins[$appstoreCfg['name']];
        }
        // extend the info with the appstore info
        $extension = array(
                // major update, so defer the deployment until the last process-step
                self::ATTR_DEFERDEPLOY => $this->is($appstoreCfg['ismajorupdate'], 0), 
                // no activation
                self::ATTR_SKIPACTIVATE => $this->is($appstoreCfg['skipactivation'], 0), 
                // no automated install available, download only
                self::ATTR_SKIPINSTALL => $this->is($appstoreCfg['skipinstall'], 0), 
                self::ATTR_APPSTORE => true, 
                self::ATTR_NAME => $appstoreCfg['name'], 
                self::ATTR_APPSTOREINFO => $appstoreCfg)

        ;
        return array_merge($cfg, $extension);
    }

    /**
     * convenience method to check if given value is set.
     * if so, value is return, otherwise the default
     * 
     * @param mixed $arg
     *            value to check
     * @param mixed $default
     *            value returned if $value is unset
     */
    protected function is(& $arg, $default = null)
    {
        if (isset($arg)) {
            return $arg;
        }
        return $default;
    }
}