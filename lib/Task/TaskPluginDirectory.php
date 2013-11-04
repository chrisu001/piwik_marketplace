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
require_once dirname(__FILE__) . '/TaskPluginAbstract.php';
class Marketplace_TaskPluginDirectory extends Marketplace_TaskPluginAbstract
{
    /**
     * Name of the Task
     * @LOCA:PluginMarketplace_Taskcheckintegrity
     * 
     * @var string
     */
    protected $name = 'checkintegrity';
    protected $pluginSrcPath = null;

    public function execute()
    {
        $this->forceGlobalPluginsDir();
        $this->forcePluginDir();
        $this->checkDirStructure();
        return $this;
    }

    public function tearDown()
    {
        $pluginSrc = $this->workspace . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR .
                 $this->pluginname . DIRECTORY_SEPARATOR;
        
        $this->container->setConfig('pluginname', $this->pluginname)
            ->setConfig('pluginsrc', $pluginSrc)
            ->setName($this->pluginname);
        return $this;
    }

    protected function forceGlobalPluginsDir()
    {
        // check "$workspace/plugins/" dir existances and uniqness
        $content = glob($this->workspace . '*');
        if (count($content) == 1 && basename($content[0]) == 'plugins' && is_dir($content[0])) {
            return;
        }
        // assume $workspace/Myplugin
        $targetPath = $this->workspace . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR;
        Piwik_Common::mkdir($targetPath);
        
        foreach ($content as $toMove) {
            if (!rename($toMove, $targetPath . basename($toMove))) {
                $msg = sprintf('cannot rename %s to %s ', $toMove, $targetPath . basename($toMove));
                throw new RuntimeException($msg);
            }
        }
        return $this;
    }

    protected function forcePluginDir()
    {
        $targetPath = $this->workspace . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR;
        $content = glob($targetPath . '*');
        // "$workspace/plugins/MyPlugin/MyPlugin.php" ?
        if (count($content) == 1 && is_dir($content[0]) &&
                 $this->checkPluginClassFileExists($content[0], basename($content[0]))) {
            $this->pluginname = basename($content[0]);
            return;
        }
        // "$workspace/plugins/Myplugin.php" ?
        $pluginName = $this->findPluginNamefromDir($targetPath);
        if (!$pluginName) {
            throw new RuntimeException('cannot determine PluginName');
        }
        $destPath = $targetPath . $pluginName . DIRECTORY_SEPARATOR;
        Piwik_Common::mkdir($destPath);
        
        foreach ($content as $toMove) {
            if (!rename($toMove, $destPath . basename($toMove))) {
                $msg = sprintf('cannot rename %s to %s ', $toMove, $destPath . basename($toMove));
                throw new RuntimeException($msg);
            }
        }
        return $this;
    }

    protected function checkPluginClassFileExists($path, $pluginName)
    {
        return file_exists($path . DIRECTORY_SEPARATOR . $pluginName . '.php');
    }

    protected function findPluginNamefromDir($path)
    {
        $content = glob($path . '*.php');
        $pluginName = null;
        $wellKnownFiles = array('Controller.php', 'Archiver.php', 'Archive.php', 'Tracker.php');
        foreach ($content as $candidate) {
            $candidate = basename($candidate);
            if (in_array($candidate, $wellKnownFiles)) {
                continue;
            }
            $candidate = preg_replace('/.php$/', $candidate);
            if ($candidate == $this->pluginname) {
                return $this->pluginname;
            }
        }
        return $pluginName;
    }

    protected function checkDirStructure()
    {
        
        // "$workspace/plugins/" ?
        $content = glob($this->workspace . '*');
        if (count($content) != 1 || basename($content[0]) != 'plugins' || !is_dir($content[0])) {
            throw new RuntimeException('filestructure not starts with /plugin ');
        }
        
        $targetPath = $this->workspace . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR;
        $content = glob($targetPath . '*');
        // "$workspace/plugins/MyPlugin/" ?
        if (count($content) != 1 || !is_dir($content[0])) {
            throw new RuntimeException('filestructure not starts with /plugin/MyPlugin ');
        }
        $this->pluginname = basename($content[0]);
        
        // "$workspace/plugins/MyPlugin/Myplugin.php" ?
        if (!$this->checkPluginClassFileExists($content[0], $this->pluginname)) {
            throw new RuntimeException(
                    'filestructure classfile does not exists /plugin/MyPlugin/MyPlugin.php');
        }
        return $this;
    }
}
