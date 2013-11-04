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
class Marketplace_TaskExtract extends Marketplace_TaskAbstract
{
    /**
     * Name of the Task
     * @LOCA:PluginMarketplace_Taskextract
     * 
     * @var string
     */
    protected $name = 'extract';
    protected $workspace = null;
    protected $filename = null;

    public function execute()
    {
        /*
         * Unzip the file
         */
        $archive = Piwik_Unzip::factory('PclZip', $this->filename);
        
        if (0 == ($archive_files = $archive->extract($this->workspace))) {
            throw new RuntimeException('zipfile is incompatible');
        }
        
        if (0 == count($archive_files)) {
            throw new RuntimeException('empty zipfile');
        }
        
        foreach ($archive_files as $archive_file) {
            if ($archive_file['status'] !== 'ok') {
                throw new RuntimeException('zipfile incompatible');
            }
        }
        return $this;
    }

    public function setUp()
    {
        // create Extractdir
        $this->workspace = $this->container->getConfig('tmpPath') . 'extracted' . DIRECTORY_SEPARATOR;
        $this->filename = $this->container->getConfig('filename');
        $this->md5signature = $this->container->getConfig('md5signature');
        $this->isZip()
            ->cleanupTmpDir();
        return $this;
    }

    public function tearDown()
    {
        $this->container->setConfig('stage', $this->workspace);
        return $this;
    }

    protected function cleanupTmpDir()
    {
        if (file_exists($this->workspace)) {
            Piwik::unlinkRecursive($this->workspace, true);
        }
        return $this;
    }

    protected function checkfileExists()
    {
        if ($this->filename === null || !file_exists($this->filename) ||
                 !is_readable($this->filename)) {
            throw new RuntimeException('zipfile does not exists');
        }
        return $this;
    }

    protected function checkFilesize($minSize = 200)
    {
        $this->checkfileExists();
        if (filesize($this->filename) < $minSize) {
            throw new RuntimeException('zip file to small');
        }
        return $this;
    }

    protected function checkMDSignature()
    {
        $this->checkFilesize();
        if ($this->md5signature && function_exists('md5_file') &&
                 $this->md5signature != md5_file($this->filename)) {
            throw new RuntimeException('md5 Signature check failed');
        }
        return $this;
    }

    protected function isZip()
    {
        $this->checkMDSignature();
        
        if (!function_exists('finfo_open')) {
            return $this;
        }
        
        $arrayZips = array("application/zip", "application/x-zip", "application/x-zip-compressed");
        $finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
        $filetype = finfo_file($finfo, $this->filename);
        if (!in_array($filetype, $arrayZips)) {
            throw new RuntimeException('not a Zipfile');
        }
        return $this;
    }
}
