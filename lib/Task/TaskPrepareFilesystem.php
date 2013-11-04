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
class Marketplace_TaskPrepareFilesystem extends Marketplace_TaskAbstract
{
    /**
     * Name of the Task
     * @LOCA:PluginMarketplace_Taskpreparefilesystem
     * 
     * @var string
     */
    protected $name = 'preparefilesystem';
    
    /**
     * default relative path (below PIWIK_USER_PATH) to the download cache
     *
     * @var string
     */
    const PATH_TO_DOWNLOAD = '/tmp/download/';
    
    /**
     * Relative path (below PIWIK_USER_PATH), where the downloadfile should be stored
     *
     * @var string
     */
    protected $workspace = null;
    protected $realworkspace = null;

    public function execute()
    {
        Piwik::checkDirectoriesWritableOrDie(array($this->workspace));
        $this->realworkspace = realpath(PIWIK_USER_PATH . DIRECTORY_SEPARATOR . $this->workspace) .
                 DIRECTORY_SEPARATOR;
    }

    public function setUp()
    {
        $ws = $this->container->getConfig('workspace');
        $this->workspace = $ws ? $ws : (self::PATH_TO_DOWNLOAD . uniqId('appstore'));
        return $this;
    }

    public function tearDown()
    {
        if (!$this->realworkspace) {
            throw new RuntimeException('could not create tmp-path:' . $this->workspace);
        }
        $this->container->setConfig('tmpPath', $this->realworkspace);
        return $this;
    }
}
