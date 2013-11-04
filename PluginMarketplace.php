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
 * @package  Piwik_PluginMarketplace
 */

/**
 * This plugins provides a conection to the alternative marketplace of plugins http://plugin.suenkel.org
 * - remote update of Piwik-Plugins
 * - new tab Settings->Pluginstore
 * - upload own zip-filed plugins
 *
 * @package Piwik_PluginMarketplace
 */
class Piwik_PluginMarketplace extends Piwik_Plugin
{

    /**
     * Standardinformation about this plugin
     * (non-PHPdoc)
     *
     * @see Piwik_Plugin::getInformation()
     */
    public function getInformation()
    {
        return array('description' => Piwik_Translate('APUA_PluginDescription'), 
                'author' => 'Christian Suenkel', 
                'author_homepage' => 'http://www.suenkel.de/', 
                'homepage' => 'http://plugin.suenkel.org/', 
                'version' => '1.0', 
                'license' => 'The BSD 3-Clause License', 
                'license_homepage' => 'http://www.opensource.org/licenses/BSD-3-Clause', 
                'translationAvailable' => true);
    }

    /**
     * Register the callback-hooks, where the plugin wants to be called
     * - add a tab in the settings-menue
     * - register additional css and js files
     * - hijack the Plugin-Managers index page (disabled atm)
     *
     * @see Piwik_Plugin::getListHooksRegistered()
     */
    public function getListHooksRegistered()
    {
        return array('AdminMenu.add' => 'addMenu', 
                'AssetManager.getCssFiles' => 'getCssFiles', 
                'WidgetsList.add' => 'addWidgets');
    }

    /**
     * Add Admin-Menu "Appstore"
     */
    public function addMenu()
    {
        if (!function_exists('Piwik_AddAdminSubMenu')) {
            Piwik_AddAdminMenu('APUA_AdminMenu', 
                    array('module' => 'PluginMarketplace', 'action' => 'index'), 
                    Piwik::isUserIsSuperUser(), 8);
        } else {
            Piwik_AddAdminSubMenu('CorePluginsAdmin_MenuPlugins', 'APUA_AdminMenu', 
                    array('module' => 'PluginMarketplace', 'action' => 'index'), 
                    Piwik::isUserIsSuperUser(), $order = 3);
        }
    }

    /**
     * Add the RSS-Feed Widget
     */
    public function addWidgets()
    {
        // first param, catgeroy, untranslated....
        // second param is locakey....
        Piwik_AddWidget('Marketplace', 'APUA_Feed_Widget_Title', 'PluginMarketplace', 'rss');
    }

    /**
     * Additional css-assets
     *
     * @param Piwik_Event_Notification $notification
     *            - notification object
     */
    public function getCssFiles($notification)
    {
        $cssFiles = &$notification->getNotificationObject();
        $cssFiles[] = 'plugins/PluginMarketplace/stylesheets/styles.css';
    }
}