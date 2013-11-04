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
require_once dirname(__FILE__) . '/TaskSet.php';
require_once dirname(__FILE__) . '/../Appstore.php';
require_once dirname(__FILE__) . '/TaskPrepareFilesystem.php';
require_once dirname(__FILE__) . '/TaskAppstore.php';
require_once dirname(__FILE__) . '/TaskDownload.php';
require_once dirname(__FILE__) . '/TaskUpload.php';
require_once dirname(__FILE__) . '/TaskCleanup.php';


require_once dirname(__FILE__) . '/TaskExtract.php';
require_once dirname(__FILE__) . '/TaskPluginDirectory.php';
require_once dirname(__FILE__) . '/TaskPluginVerify.php';
require_once dirname(__FILE__) . '/TaskPluginActivate.php';
require_once dirname(__FILE__) . '/TaskPluginDeactivate.php';
require_once dirname(__FILE__) . '/TaskPluginDeploy.php';
require_once dirname(__FILE__) . '/TaskPluginInstall.php';
require_once dirname(__FILE__) . '/TaskPluginUpdate.php';
require_once dirname(__FILE__) . '/TaskPluginRemove.php';
require_once dirname(__FILE__) . '/TaskPluginBackup.php';

require_once dirname(__FILE__) . '/Processor.php';
class Marketplace_TaskSetFactory
{

    public static function AppstoreInstall($webid, Marketplace_Processor $processor)
    {
        $config = array('webid' => $webid);
        $taskSet = new Marketplace_TaskSet('Appstore', $config, $processor);
        
        $taskSet
        // get Appstore info
        // set download_url, m5signature, pluginname (appstore)
        ->pushTaskByClassName('Marketplace_TaskAppstore')
        // create tmpPath
        ->pushTaskByClassName('Marketplace_TaskPrepareFilesystem')
        // download file to "filename"
        ->pushTaskByClassName('Marketplace_TaskDownload')
        // unzip "filename" to "stage"-path
        ->pushTaskByClassName('Marketplace_TaskExtract')
        // fix/normalize Directory Structure
        ->pushTaskByClassName('Marketplace_TaskPluginDirectory')
        // verify staged Plugin
        ->pushTaskByClassName('Marketplace_TaskPluginVerify')
        // backup old
        ->pushTaskByClassName('Marketplace_TaskPluginBackup')
        // deaktivate
        ->pushTaskByClassName('Marketplace_TaskPluginDeactivate')
        // deploy
        ->pushTaskByClassName('Marketplace_TaskPluginDeploy')
        // install
        ->pushTaskByClassName('Marketplace_TaskPluginInstall')
        // update Piwik
        ->pushTaskByClassName('Marketplace_TaskPluginUpdate')
        // activate
        ->pushTaskByClassName('Marketplace_TaskPluginActivate');
        
        $processor->pushTaskSet($taskSet);
        return $taskSet;
    }

    public static function Activate($pluginName, Marketplace_Processor $processor)
    {
        $config = array('pluginname' => $pluginName);
        $taskSet = new Marketplace_TaskSet($pluginName, $config, $processor);
        $taskSet
        // activate
        ->pushTaskByClassName('Marketplace_TaskPluginActivate');
        
        $processor->pushTaskSet($taskSet);
        return $taskSet;
    }
    
    public static function Upload($tmpFilename, Marketplace_Processor $processor)
    {
        $config = array('tmpfilename' => $tmpFilename);
        $taskSet = new Marketplace_TaskSet('Upload', $config, $processor);
        $taskSet
        // create tmpPath
        ->pushTaskByClassName('Marketplace_TaskPrepareFilesystem')
        // copy  file to "filename"
        ->pushTaskByClassName('Marketplace_TaskUpload')
        // unzip "filename" to "stage"-path
        ->pushTaskByClassName('Marketplace_TaskExtract')
        // fix/normalize Directory Structure
        ->pushTaskByClassName('Marketplace_TaskPluginDirectory')
        // verify staged Plugin
        ->pushTaskByClassName('Marketplace_TaskPluginVerify')
        // deaktivate
        ->pushTaskByClassName('Marketplace_TaskPluginDeactivate')
        // deploy
        ->pushTaskByClassName('Marketplace_TaskPluginDeploy')
        // install
        ->pushTaskByClassName('Marketplace_TaskPluginInstall')
        // activate
        ->pushTaskByClassName('Marketplace_TaskPluginActivate');
        
        $processor->pushTaskSet($taskSet);
        // execute until tmp-uploadfile copy to tmppath
        $processor->step();
        $processor->step();
        return $taskSet;
    }
    
    
    public static function Remove($pluginName, Marketplace_Processor $processor)
    {
        $config = array('pluginname' => $pluginName,
        'workspace' => '/tmp/backup');
        $taskSet = new Marketplace_TaskSet($pluginName, $config, $processor);
        $taskSet
        // create tmpPath
        ->pushTaskByClassName('Marketplace_TaskPrepareFilesystem')
        // create Backup
        ->pushTaskByClassName('Marketplace_TaskPluginBackup')
        // remove
        ->pushTaskByClassName('Marketplace_TaskPluginRemove');
        $processor->pushTaskSet($taskSet);
        return $taskSet;
    }
    
    
}