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
 * Holds and execute a set of TaskSets
 *
 * @package PluginMarketplace
 */
class Marketplace_Processor
{
    /**
     * List of TaskSets
     *
     * @var array
     */
    protected $taskSets = array();

    /**
     * Add a taskset to the list
     *
     * @param Marketplace_TaskSet $taskSet            
     * @return Marketplace_Processor
     */
    public function pushTaskSet(Marketplace_TaskSet $taskSet)
    {
        array_push($this->taskSets, $taskSet);
        return $this;
    }

    /**
     * Return the current status of all tasksets
     *
     * @return array array('items' => array( status of each taskset),
     *         'finished' => all done?)
     */
    public function getStatus()
    {
        $status = array('items' => array());
        
        foreach ($this->taskSets as $taskSet) {
            $status['items'][] = $taskSet->getStatus();
        }
        $finished = true;
        foreach ($status['items'] as $s) {
            // finished, if all TaskSets reached last step or an error occurred
            $taskSetfinished = ($s['step'] == $s['steps']) &&
                     $s['status'] == Marketplace_TaskSet::STATUS_DONE;
            $taskSetfinished |= ($s['status'] == Marketplace_TaskSet::STATUS_ERROR);
            $finished &= $taskSetfinished;
        }
        $extend = array('sets' => count($status['items']), 'finished' => $finished);
        return array_merge($extend, $status);
    }

    /**
     * execute one step (task) of an available and active taskset.
     *
     * if there was no available single-step taskset, loop until end of precess
     * 
     * @return boolean true, if a step was executable, false if finished
     */
    public function step()
    {
        foreach ($this->taskSets as $taskSet) {
            if (!$taskSet->isFinished() && $taskSet->step()) {
                return true;
            }
            $taskSet->log('processor marked for loop');
        }
        
        // no single step more found, so loop() the rest
        $this->loop();
        return false;
    }

    /**
     * run all steps of all tasksteps until error or finished
     */
    public function loop()
    {
        foreach ($this->taskSets as $taskSet) {
            $taskSet->loop();
        }
        return $this;
    }
}