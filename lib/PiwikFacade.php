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
 *
 * Facade to wrap Piwik-PluginManager
 *
 * this is neccesary because
 * - original pluginsmanager does not provide the full list of api
 * - original pluginsmanager is not "reentrant" save (aka assumes, that it is only a "webserver script"
 * - original pluginsmanager has crude access rules (mostly private, so no derivation...)
 * - original pluginsmanager insists to be a singleton
 * - original pluginsmanager has bugs
 * - original pluginsmanager might be changed
 *
 * At the end: a facade to
 *  - install/activate/deactivate plugins
 *  - new  uninstall
 *  - new remove plugin
 *
 * @category Piwik_Plugins
 * @package  PluginMarketplace
 */
class PluginMarketplace_PiwikFacade
{
    protected static $wrapinstance = null;
    /**
     * original Piwik_PluginsManager
     *
     * @var Piwik_PluginsManager
     */
    protected static $pm = null;

    /**
     * generate Instance of PiwikFacade
     *
     * @return PluginMarketplace_PiwikFacade
     */
    static public function getInstance()
    {
        if (self::$wrapinstance == null) {
            self::$wrapinstance = new self();
        }
        
        return self::$wrapinstance;
    }

    /**
     * Connstructor
     * inititialize Piwik_PluginsManager
     */
    public function __construct()
    {
        if (self::$pm == null) {
            self::$pm = Piwik_PluginsManager::getInstance();
        }
        $this->connectDB();
    }

    /**
     * activate a plugin (might be a implicite install!)
     *
     * @param string $pluginName
     *            - Name of the Plugin (without "Piwik_")
     * @return PluginMarketplace_PiwikFacade
     */
    public function activatePlugin($pluginName, $force = false)
    {
        if (!$force && self::$pm->isPluginActivated($pluginName)) {
            // not neccessary to deactivate
            return $this;
        }
        self::$pm->activatePlugin($pluginName);
        $this->resetPluginManager();
        return $this;
    }

    /**
     * deactivate a Plugin
     *
     * @param string $pluginName
     *            - Name of the Plugin (without "Piwik_")
     * @return PluginMarketplace_PiwikFacade
     */
    public function deactivatePlugin($pluginName, $force = false)
    {
        if (!$force && !self::$pm->isPluginActivated($pluginName)) {
            // not neccessary to deactivate
            return $this;
        }
        self::$pm->deactivatePlugin($pluginName);
        $this->resetPluginManager();
        return $this;
    }

    /**
     * remove (delete) a Plugin entirely
     *
     * @param string $pluginName
     *            - Name of the Plugin (without "Piwik_")
     * @return PluginMarketplace_PiwikFacade
     */
    public function removePlugin($pluginName)
    {
        if (empty($pluginName)) {
            throw new RuntimeException('empty pluginanme to remove');
        }
        $this->uninstallPlugin($pluginName);
        $rp = PIWIK_INCLUDE_PATH . '/plugins/' . $pluginName;
        if (file_exists($rp)) {
            Piwik::unlinkRecursive($rp, true);
        }
        $this->removeFromConfig($pluginName);
        return $this;
    }

    /**
     * Install a plugin,
     * but do not activate the plugin
     *
     * @param string $pluginName
     *            - Name of the Plugin (without "Piwik_")
     * @return PluginMarketplace_PiwikFacade
     */
    public function installPlugin($pluginName)
    {
        $this->loadPlugin($pluginName);
        self::$pm->installLoadedPlugins();
        return $this;
    }

    /**
     * Load a Plugin
     * but do not activate or install the plugin
     *
     * @param string $pluginName
     *            - Name of the Plugin (without "Piwik_")
     * @return PluginMarketplace_PiwikFacade
     */
    public function loadPlugin($pluginName)
    {
        self::$pm->loadPlugin($pluginName);
        return $this;
    }

    /**
     * Unload and comlete unistall a Plugin
     *
     * @param string $pluginName
     *            - Name of the Plugin (without "Piwik_")
     * @return PluginMarketplace_PiwikFacade
     */
    public function uninstallPlugin($pluginName)
    {
        if (self::$pm->isPluginActivated($pluginName)) {
            $this->deactivatePlugin($pluginName);
        }
        if (!in_array($pluginName, self::$pm->getInstalledPluginsName())) {
            $this->removeFromConfig($pluginName);
            return $this;
        }
        $this->removeFromConfig($pluginName);
        
        $emessage = array();
        $e = null;
        // Step I load Plugin to "uninstall"
        $oPlugin = null;
        try {
            $oPlugin = self::$pm->loadPlugin($pluginName);
        } catch (Exception $tmpE) {
            $emessage['load'] = 'calling load failed : ' . $tmpE->getMessage();
            $e = $tmpE;
        }
        // STEP II : Call the Uninstall routine of the Plugin
        if ($oPlugin !== null && method_exists($oPlugin, 'uninstall')) {
            try {
                call_user_func(array($oPlugin, 'uninstall'));
            } catch (Exception $tmpE) {
                $emessage['uninstallmethod'] = 'calling unistall mehtod failed: ' .
                         $tmpE->getMessage();
                $e = $tmpE;
            }
        }
        // STEP III: deregister Plugin-hooks
        try {
            $oPlugin !== null && Piwik_PluginsManager::getInstance()->unloadPlugin($oPlugin);
        } catch (Exception $tmpE) {
            // Piwik cannot unload deactivated plugins with registered hooks
            // so ignore all this (and all other errors)
        }
        unset($oPlugin);
        
        // remove version number and settings from option
        try {
            Piwik_Option::getInstance()->delete('version_' . $pluginName);
            // Piwik_Option::getInstance()->deleteLike('%'.$plugin.'%'); // SMELL might be not enough
        } catch (Exception $tmpE) {
            $emessage['optiondelete'] = 'option delete failed:' . $tmpE->getMessage();
            $e = $tmpE;
        }
        if ($e !== null) {
            $msg = sprintf(
                    "WARNING !!!!\n---------------\ncould not complete uninstall Plugin %s\n Errors: %s\n", 
                    $pluginName, print_r($emessage, true));
            throw new RuntimeException($msg, NULL, $e);
        }
        return $this->resetPluginManager();
    }

    /**
     * Get the Description of all plugins located in /plugins/*
     */
    public function getPluginsDescription()
    {
        $plugins = array();
        $listPlugins = self::$pm->readPluginsDirectory();
        
        foreach ($listPlugins as $pluginName) {
            $oPlugin = $this->loadPlugin($pluginName);
        }
        self::$pm->loadPluginTranslations();
        
        $loadedPlugins = self::$pm->getLoadedPlugins();
        foreach ($loadedPlugins as $oPlugin) {
            $pluginName = $oPlugin->getPluginName();
            $plugins[$pluginName] = array('active' => self::$pm->isPluginActivated($pluginName), 
                    'alwaysActive' => self::$pm->isPluginAlwaysActivated($pluginName), 
                    'name' => $pluginName, 
                    'info' => array('description' => 'unset', 
                            'author_homepage' => '', 
                            'author' => 'unset', 
                            'license' => '', 
                            'license_homepage' => '', 
                            'version' => 0));
            $plugins[$pluginName]['info'] = array_merge($plugins[$pluginName]['info'], 
                    $oPlugin->getInformation());
        }
        return $plugins;
    }

    /**
     * remove a plugin entirely from /config/config.ini.php
     *
     * @param string $pluginName
     *            - Name of the Plugin (without "Piwik_")
     * @return PluginMarketplace_PiwikFacade
     */
    protected function removeFromConfig($pluginName)
    {
        $config = Piwik_Config::getInstance();
        $config->init();
        // STEP III: remove Plugin from config Tracker
        $section = $config->Plugins_Tracker;
        if (!empty($section['Plugins_Tracker']) && in_array($pluginName, 
                $section['Plugins_Tracker'])) {
            $config->Plugins_Tracker = array_diff($section['Plugins_Tracker'], array($pluginName));
        }
        // STEP IV: remove from config installed:
        $section = $config->PluginsInstalled;
        if (!empty($section['PluginsInstalled']) &&
                 in_array($pluginName, $section['PluginsInstalled'])) {
            $config->PluginsInstalled = array_diff($section['PluginsInstalled'], array($pluginName));
        }
        // STEP V: remove from config Plugins
        $section = $config->Plugins;
        if (!empty($section['Plugins']) && in_array($pluginName, $section['Plugins'])) {
            $config->Plugins = array_diff($section['Plugins'], array($pluginName));
        }
        $config->forceSave();
        $config->clear();
        $config->init();
        Piwik::deleteAllCacheOnUpdate();
        return $this;
    }

    /**
     * Reset the Pluginsmanagager as "far as possible"
     *
     * @return PluginMarketplace_PiwikFacade
     */
    protected function resetPluginManager()
    {
        return $this->unloadPlugins()
            ->loadPlugins();
    }

    /**
     * Load activated Plugins defined in configfile
     *
     * @param string $pluginName
     *            - Name of the Plugin (without "Piwik_")
     * @return PluginMarketplace_PiwikFacade
     */
    public function loadPlugins()
    {
        // load activated Plugins
        $config = Piwik_Config::getInstance();
        $config->clear();
        $pluginNames = $config->Plugins['Plugins'];
        self::$pm->loadPlugins($pluginNames);
        return $this;
    }

    /**
     * Unload plugins
     * BUG in Piwik: cant unload deactivated Plugins with registered hooks
     *
     * @return PluginMarketplace_PiwikFacade
     */
    protected function unloadPlugins()
    {
        try {
            self::$pm->unloadPlugins();
        } catch (Exception $e) {
            $plugins = self::$pm->getLoadedPlugins();
            foreach ($plugins as $oPlugin) {
                unset($oPlugin);
            }
        }
        self::$pm->loadPlugins(array());
        return $this;
    }

    /**
     * Run Updates of Plugins
     * this is very odd, but neccessary for plugins with /Update/XX_XX.php updatefiles
     *
     * @return PluginMarketplace_PiwikFacade
     */
    public function updatePlugins()
    {
        Piwik::createAccessObject(); // buggy initialisation of PluginsManager...
                                     // force Upgrade of the Plugin
        $updater = new Piwik_Updater();
        
        $componentsWithUpdateFile = Piwik_CoreUpdater::getComponentUpdates($updater);
        if (!is_array($componentsWithUpdateFile)) {
            return $this;
        }
        foreach ($componentsWithUpdateFile as $name => $filenames) {
            $updater->update($name);
        }
        return $this;
    }

    /**
     * Disable Maintenance mode , reeinstall tracking
     *
     * @see : Piwik_Updates::disableMaintenance();
     *     
     *      we cannot use Piwik_Updates::disableMaintenance(), because is reinititializes Piwik_Config and so it overwrites
     *      our previously made changes to the config
     *      @TODO: refactor Piwik_Updates to chose "overwrite" or not prev mad config-changes
     * @return PluginMarketplace_InstallerCore
     */
    public function disableMaintenance()
    {
        $config = Piwik_Config::getInstance();
        
        $tracker = $config->Tracker;
        $tracker['record_statistics'] = $this->trackerModeOld === null ? 1 : $this->trackerModeOld;
        $config->Tracker = $tracker;
        
        $general = $config->General;
        $general['maintenance_mode'] = $this->generalModeOld === null ? 0 : $this->generalModeOld;
        $config->General = $general;
        
        $config->forceSave();
        return $this;
    }

    /**
     * Enable MaintenanceMode
     *
     * @see : Piwik_Updates::disableMaintenance();
     *     
     *      we cannot use Piwik_Updates::enableMaintenance(), because is reinititializes Piwik_Config and so it overwrites
     *      our previously changes to the config
     *      @TODO: refactor Piwik_Updates to chose "overwrite" or not prev mad config-changes
     *      // SMELL: config->init() assumed before?
     * @return PluginMarketplace_InstallerCore
     */
    public function enableMaintenance()
    {
        $config = Piwik_Config::getInstance();
        
        $tracker = $config->Tracker;
        $this->trackerModeOld = $tracker['record_statistics'];
        $tracker['record_statistics'] = 0;
        $config->Tracker = $tracker;
        
        $general = $config->General;
        $this->generalModeOld = $general['maintenance_mode'];
        $general['maintenance_mode'] = 1;
        $config->General = $general;
        
        $config->forceSave();
        return $this;
    }

    public function readPluginsDirectory()
    {
        return self::$pm->readPluginsDirectory();
    }

    /**
     * Create database if needed
     * Pluginmagaer does not implement this check
     *
     * @return PluginMarketplace_PiwikFacade
     */
    protected function connectDB()
    {
        if (!Zend_Registry::isRegistered('db')) {
            Piwik::createDatabaseObject();
        }
        return $this;
    }
}

/**
 * Exeptions to be thrown
 *
 * @author chris
 *        
 */
class PluginMarketplace_PiwikFacade_Exception extends RuntimeException
{}
