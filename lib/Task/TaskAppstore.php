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
require_once dirname(__FILE__) . '/TaskAbstract.php';
require_once dirname(__FILE__) . '/../Appstore.php';
class Marketplace_TaskAppstore extends Marketplace_TaskAbstract
{
    /**
     * Name of the Task 
     * @LOCA:PluginMarketplace_Taskappstore
     * @var string
     */
    protected $name = 'appstore';
    protected $webid = null;
    protected $appstoreInfo = array();

    public function execute()
    {
        $app = new PluginMarketplace_Appstore();
        if (!($pluginfo = $app->getPluginInfo($this->webid))) {
            throw new RuntimeException('can not parse appstore  info');
        }
        $this->appstoreInfo = $pluginfo;
        return $this;
    }

    public function setUp()
    {
        $this->webid = $this->container->getConfig('webid');
        if (empty($this->webid)) {
            throw new RuntimeException('no webid set to download plugin');
        }
        return $this;
    }

    public function tearDown()
    {
        if (empty($this->appstoreInfo)) {
            throw new RuntimeException('cannot get info from appstore');
        }
        
        $pluginName = $this->appstoreInfo['name'];
        $this->container->setName($pluginName)
            ->setConfig('appstore', $this->appstoreInfo)
            ->setConfig('download_url', $this->appstoreInfo['download_url'])
            ->setConfig('pluginname', $pluginName);
        if (!empty($this->appstoreInfo['md5signature'])) {
            $this->container->setConfig('md5signature', $this->appstoreInfo['md5signature']);
        }
        $this->rearangeTaskSet();
        return $this;
    }

    protected function rearangeTaskSet()
    {
        if ('{plugin.UN}' == $this->container->getConfig('pluginname')) {
            // skip deactivate, if selfupdate
            $this->container->removeTaskByTaskName('deactivate');
        }
        if (!empty($this->appstoreInfo['skipdeactivate'])) {
            $this->container->removeTaskByTaskName('deactivate');
        }
        if (!empty($this->appstoreInfo['skipactivate'])) {
            $this->container->removeTaskByTaskName('activate');
        }
        if (!empty($this->appstoreInfo['skipinstall'])) {
            $this->container->removeTaskByTaskName('activate')
                ->removeTaskByTaskName('install')
                ->removeTaskByTaskName('update');
        }
        /* @deprecated since appstore version 2
        if (!empty($this->appstoreInfo['ismajorupdate'])) {
            $this->container->removeTaskByTaskName('activate')
                ->removeTaskByTaskName('update');
        }
        */
        return $this;
    }
}
